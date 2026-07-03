<?php

namespace Tests\Feature;

use App\Actions\Order\CreateOrderAction;
use App\DataTransferObjects\CreateOrderResult;
use App\DataTransferObjects\OrderFormData;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Semantics tạo đơn:
 * - Lõi (order + tracking history) all-or-nothing trong transaction.
 * - Packages/invoices/photos/contacts fail mềm → đơn vẫn tạo, tên bước
 *   fail trả về qua CreateOrderResult::$warnings (không im lặng).
 */
class CreateOrderActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildSchema();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();

        parent::tearDown();
    }

    public function test_it_creates_order_with_packages_history_and_no_warnings(): void
    {
        $result = app(CreateOrderAction::class)->execute($this->formData());

        $this->assertInstanceOf(CreateOrderResult::class, $result);
        $this->assertFalse($result->hasWarnings());

        $order = $result->order;
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertSame(OrderStatusEnum::MOI_TAO, $order->bill_status);
        $this->assertNotEmpty($order->id_bill);

        // 1 dòng khai báo x number_of_package=2 → 2 record kiện, mỗi kiện code riêng.
        $this->assertSame(2, $order->packages()->count());
        $this->assertSame($order->id_bill.'-01', $order->packages()->orderBy('id')->first()->code);

        // Tracking history khởi tạo là phần lõi bắt buộc.
        $this->assertSame(1, DB::table('order_history')->where('id_order', $order->id)->count());

        // Invoice items được lưu.
        $this->assertSame(1, DB::table('invoices')->where('id_order', $order->id)->count());
    }

    public function test_soft_step_failure_keeps_order_and_reports_warning(): void
    {
        // Phá bảng order_package → createPackages throw, các bước khác vẫn chạy.
        Schema::drop('order_package');

        $result = app(CreateOrderAction::class)->execute($this->formData());

        $this->assertTrue($result->hasWarnings());
        $this->assertContains('kiện hàng', $result->warnings);

        // Đơn + history vẫn tồn tại (lõi không bị ảnh hưởng bởi bước mềm).
        $this->assertDatabaseHas('orders', ['id' => $result->order->id]);
        $this->assertSame(1, DB::table('order_history')->where('id_order', $result->order->id)->count());

        // Bước sau bước fail vẫn chạy bình thường.
        $this->assertSame(1, DB::table('invoices')->where('id_order', $result->order->id)->count());
    }

    public function test_core_failure_rolls_back_order_completely(): void
    {
        // Phá bảng order_history → RecordTrackingHistoryAction (phần lõi) throw.
        Schema::drop('order_history');

        try {
            app(CreateOrderAction::class)->execute($this->formData());
            $this->fail('Kỳ vọng exception từ phần lõi tạo đơn.');
        } catch (\Throwable) {
            // expected
        }

        // Lõi all-or-nothing: không được để lại đơn "nửa vời".
        $this->assertSame(0, Order::query()->count());
    }

    public function test_save_contact_generates_member_code_inside_transaction(): void
    {
        $result = app(CreateOrderAction::class)->execute($this->formData(saveInfoSender: true));

        $this->assertFalse($result->hasWarnings());
        $this->assertDatabaseHas('member', ['type' => 'sender', 'code' => 'CUS000001']);
    }

    public function test_order_codes_are_sequential_per_day(): void
    {
        $first = app(CreateOrderAction::class)->execute($this->formData());
        $second = app(CreateOrderAction::class)->execute($this->formData());

        $this->assertNotSame($first->order->id_bill, $second->order->id_bill);
        $this->assertSame(
            (int) substr($first->order->id_bill, -3) + 1,
            (int) substr($second->order->id_bill, -3),
        );
    }

    public function test_customs_fees_are_stored_in_all_three_payment_groups(): void
    {
        $formData = new OrderFormData(
            idSale: 1,
            idCustomer: 2,
            service: ['id_dichvu' => 1, 'tensanpham' => 'Test product'],
            sender: [
                'id' => null,
                'type' => 'khach',
                'company' => 'Sender Co',
                'fullname' => 'Sender Name',
                'phone' => '0900000001',
                'address' => 'Sender address',
            ],
            receiver: [
                'id' => null,
                'company' => 'Receiver Co',
                'fullname' => 'Receiver Name',
                'phone' => '0900000002',
                'country_id' => 1,
                'address' => 'Receiver address',
                'postcode' => '10000',
            ],
            packages: [
                ['number_of_package' => 1, 'length' => 10, 'width' => 10, 'height' => 10, 'g_weight' => 1.5],
            ],
            notes: null,
            saveInfoSender: false,
            saveInfoReceiver: false,
            dim: 5000,
            // Giá dạng chuỗi VN "150.000" phải parse thành 150000, không thành 150.
            phuphihaiquan: [
                ['id_loaiphuphi' => 1, 'soluong' => 2, 'price' => '150.000'],
                ['id_loaiphuphi' => 2, 'soluong' => 1, 'price' => 80000],
            ],
            invoiceItems: [],
            orderPhotos: [],
        );

        $order = app(CreateOrderAction::class)->execute($formData)->order;

        // 2×150k + 1×80k = 380k, VAT=0 khi tạo đơn → total_phuphi = trước VAT.
        foreach (['payment_cuocban', 'payment_cuocvon', 'payment_cuocgoc'] as $groupKey) {
            $group = $order->{$groupKey};
            $this->assertEquals(380_000, $group['total_phuphi_no_vat'], $groupKey);
            $this->assertEquals(380_000, $group['total_phuphi'], $groupKey);
            $this->assertEquals(0, $group['total_vat_phuphi'], $groupKey);
            $this->assertEquals(380_000, $group['total_tongcuoc_no_vat'], $groupKey);
            $this->assertEquals(380_000, $group['total_tongcuoc'], $groupKey);
            $this->assertEquals(300_000, $group['phuphi'][0]['total'], $groupKey);
        }

        // Lợi nhuận: cước bán/vốn = 0 (không bảng giá), phụ phí cân nhau → 0.
        $this->assertEquals(0, $order->payment_loinhuan['loinhuan']);
    }

    protected function formData(bool $saveInfoSender = false): OrderFormData
    {
        return new OrderFormData(
            idSale: 1,
            idCustomer: 2,
            service: ['id_dichvu' => 1, 'tensanpham' => 'Test product'],
            sender: [
                'id' => null,
                'type' => 'khach',
                'company' => 'Sender Co',
                'fullname' => 'Sender Name',
                'phone' => '0900000001',
                'address' => 'Sender address',
            ],
            receiver: [
                'id' => null,
                'company' => 'Receiver Co',
                'fullname' => 'Receiver Name',
                'phone' => '0900000002',
                'country_id' => 1,
                'address' => 'Receiver address',
                'postcode' => '10000',
            ],
            packages: [
                ['number_of_package' => 2, 'length' => 10, 'width' => 10, 'height' => 10, 'g_weight' => 1.5],
            ],
            notes: 'test note',
            saveInfoSender: $saveInfoSender,
            saveInfoReceiver: false,
            dim: 5000,
            phuphihaiquan: [],
            invoiceItems: [
                ['tenhang' => 'Item A', 'soluong' => 1, 'price' => 100, 'total' => 100],
            ],
            orderPhotos: [],
        );
    }

    protected function buildSchema(): void
    {
        $this->dropSchema();

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('id_bill')->unique();
            $table->unsignedBigInteger('id_sale')->default(0);
            $table->unsignedBigInteger('id_customer')->default(0);
            $table->unsignedBigInteger('id_manager')->default(0);
            $table->unsignedBigInteger('id_ketoan')->default(0);
            $table->unsignedBigInteger('id_ops')->default(0);
            $table->unsignedBigInteger('id_cs')->default(0);
            $table->unsignedBigInteger('id_create')->nullable();
            $table->string('bill_status');
            $table->float('dim')->nullable();
            $table->text('ghichu')->nullable();
            $table->json('sender')->nullable();
            $table->json('receiver')->nullable();
            $table->json('service')->nullable();
            $table->json('payment_cuocban')->nullable();
            $table->json('payment_cuocvon')->nullable();
            $table->json('payment_cuocgoc')->nullable();
            $table->json('payment_loinhuan')->nullable();
            $table->timestamps();
        });

        Schema::create('order_sequences', function (Blueprint $table): void {
            $table->id();
            $table->date('sequence_date')->unique();
            $table->unsignedInteger('current_number')->default(0);
            $table->timestamps();
        });

        Schema::create('order_package', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_order');
            $table->string('code')->nullable();
            $table->decimal('length', 12, 2)->default(0);
            $table->decimal('width', 12, 2)->default(0);
            $table->decimal('height', 12, 2)->default(0);
            $table->decimal('g_weight', 12, 3)->default(0);
            $table->decimal('v_weight', 12, 3)->default(0);
            $table->decimal('c_weight', 12, 3)->default(0);
            $table->unsignedInteger('number_of_package')->default(1);
            $table->decimal('row_g_weight', 12, 3)->default(0);
            $table->decimal('row_v_weight', 12, 3)->default(0);
            $table->decimal('row_c_weight', 12, 3)->default(0);
            $table->string('package_type')->nullable();
            $table->timestamps();
        });

        Schema::create('order_history', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_order');
            $table->unsignedInteger('id_user')->nullable();
            $table->string('action')->nullable();
            $table->timestamp('thoigian')->nullable();
            $table->string('diadiem')->nullable();
            $table->string('trangthai')->nullable();
            $table->text('ghichu')->nullable();
            $table->boolean('main')->default(false);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_order');
            $table->string('tenhang')->nullable();
            $table->unsignedInteger('soluong')->default(0);
            $table->string('xuatxu')->nullable();
            $table->string('loaihang')->nullable();
            $table->string('hscode')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('member', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->string('uuid')->nullable();
            $table->string('fullname')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('id_province')->nullable();
            $table->unsignedBigInteger('id_ward')->nullable();
            $table->unsignedBigInteger('id_ctv')->nullable();
            $table->unsignedBigInteger('id_sale')->nullable();
            $table->unsignedBigInteger('id_khachhang')->nullable();
            $table->string('type')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ResolveServicePriceAction tra bảng giá; không có bảng giá → cước 0 (hợp lệ).
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        // RecordTrackingHistoryAction tra chi nhánh nhận hàng qua news.
        Schema::create('news', function (Blueprint $table): void {
            $table->id();
            $table->string('namevi')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_price_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('service_id');
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('service_price_list_countries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_price_list_id');
            $table->unsignedBigInteger('country_id');
            $table->timestamps();
        });

        Schema::create('service_price_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_price_list_id');
            $table->string('quycach', 20)->default('DON_GIA');
            $table->decimal('weight_from', 12, 2);
            $table->decimal('weight_to', 12, 2);
            $table->decimal('sale_price', 14, 2)->default(0);
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('base_price', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    protected function dropSchema(): void
    {
        foreach ([
            'service_price_details', 'service_price_list_countries', 'service_price_lists',
            'news', 'countries', 'member', 'invoices', 'order_history', 'order_package',
            'order_sequences', 'orders',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
