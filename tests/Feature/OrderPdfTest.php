<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Mobile\BuildsMobileSchema;
use Tests\TestCase;

/**
 * PDF vận đơn/tem (orders.pdf.label / orders.pdf.bill) — tính năng mới,
 * độc lập với luồng in trình duyệt (window.print) sẵn có.
 *
 * Kiểm: trả đúng file PDF, tôn trọng OrderAccess (row-level), gate route.
 */
class OrderPdfTest extends TestCase
{
    use BuildsMobileSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildMobileSchema();

        // Bảng phụ template PDF cần thêm ngoài schema mobile.
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_order');
            $table->string('tenhang')->nullable();
            $table->unsignedInteger('soluong')->default(0);
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('countries');
        Schema::dropIfExists('invoices');
        $this->dropMobileSchema();

        parent::tearDown();
    }

    protected function makeOrder(array $attributes = []): Order
    {
        return Order::query()->create(array_merge([
            'uuid' => 'pdf-test-'.uniqid(),
            'id_bill' => 'BEE'.random_int(100000000, 999999999),
            'bill_status' => 'moi_tao',
            'sender' => ['company' => 'Công ty Gửi', 'fullname' => 'Người Gửi', 'phone' => '0909', 'address' => 'HCM'],
            'receiver' => ['fullname' => 'Người Nhận', 'phone' => '123', 'address' => 'Tokyo', 'postcode' => '100'],
            'service' => ['tensanpham' => 'Quần áo'],
        ], $attributes));
    }

    public function test_admin_can_download_label_and_bill_pdf(): void
    {
        $admin = $this->makeUser(['admin']);
        $order = $this->makeOrder();

        $label = $this->actingAs($admin)->get(route('orders.pdf.label', ['uuid' => $order->uuid]));
        $label->assertOk();
        $label->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $label->getContent());

        $bill = $this->actingAs($admin)->get(route('orders.pdf.bill', ['uuid' => $order->uuid]));
        $bill->assertOk();
        $this->assertStringStartsWith('%PDF', $bill->getContent());

        // Kèm CVCK: PDF vẫn hợp lệ và dài hơn bản thường (thêm trang công văn).
        $billCvck = $this->actingAs($admin)->get(route('orders.pdf.bill', ['uuid' => $order->uuid, 'cvck' => 1]));
        $billCvck->assertOk();
        $this->assertGreaterThan(strlen($bill->getContent()), strlen($billCvck->getContent()));
    }

    public function test_sale_cannot_download_pdf_of_other_sales_order(): void
    {
        $sale = $this->makeUser(['sale']);
        $otherOrder = $this->makeOrder(['id_sale' => $sale->id + 999]);

        $this->actingAs($sale)
            ->get(route('orders.pdf.label', ['uuid' => $otherOrder->uuid]))
            ->assertForbidden();
    }

    public function test_sale_can_download_pdf_of_own_order(): void
    {
        $sale = $this->makeUser(['sale']);
        $order = $this->makeOrder(['id_sale' => $sale->id]);

        $this->actingAs($sale)
            ->get(route('orders.pdf.label', ['uuid' => $order->uuid]))
            ->assertOk();
    }

    public function test_unknown_uuid_returns_404(): void
    {
        $admin = $this->makeUser(['admin']);

        $this->actingAs($admin)
            ->get(route('orders.pdf.label', ['uuid' => 'khong-ton-tai']))
            ->assertNotFound();
    }

    // ------------------------------------------------------------------
    // In hàng loạt (bulk PDF)
    // ------------------------------------------------------------------

    public function test_bulk_label_merges_selected_orders_into_one_pdf(): void
    {
        $admin = $this->makeUser(['admin']);
        $a = $this->makeOrder();
        $b = $this->makeOrder();

        $response = $this->actingAs($admin)
            ->get(route('orders.pdf.bulk-label', ['ids' => $a->id.','.$b->id]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        // Cùng heuristic đếm: bulk 2 đơn (mỗi đơn 1 kiện) = 2× trang của 1 đơn.
        $single = $this->actingAs($admin)->get(route('orders.pdf.label', ['uuid' => $a->uuid]));
        $this->assertSame(
            2 * $this->pdfPageCount($single->getContent()),
            $this->pdfPageCount($response->getContent()),
        );
    }

    /** Đếm trang PDF qua object /Type /Page (trừ /Pages tree). */
    protected function pdfPageCount(string $pdf): int
    {
        return substr_count($pdf, '/Type /Page') - substr_count($pdf, '/Type /Pages');
    }

    public function test_bulk_bill_works_and_rejects_empty_ids(): void
    {
        $admin = $this->makeUser(['admin']);
        $order = $this->makeOrder();

        $this->actingAs($admin)
            ->get(route('orders.pdf.bulk-bill', ['ids' => (string) $order->id]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('orders.pdf.bulk-bill'))
            ->assertStatus(422);

        $this->actingAs($admin)
            ->get(route('orders.pdf.bulk-bill', ['ids' => 'abc,,-1']))
            ->assertStatus(422);
    }

    public function test_bulk_label_skips_orders_outside_user_scope(): void
    {
        $sale = $this->makeUser(['sale']);
        $mine = $this->makeOrder(['id_sale' => $sale->id]);
        $others = $this->makeOrder(['id_sale' => $sale->id + 999]);

        // Chọn cả 2: đơn ngoài phạm vi bị bỏ qua lặng lẽ, PDF vẫn ra với đơn mình.
        $this->actingAs($sale)
            ->get(route('orders.pdf.bulk-label', ['ids' => $mine->id.','.$others->id]))
            ->assertOk();

        // Chỉ chọn đơn người khác → không còn đơn hợp lệ → 404.
        $this->actingAs($sale)
            ->get(route('orders.pdf.bulk-label', ['ids' => (string) $others->id]))
            ->assertNotFound();
    }

    public function test_bulk_rejects_over_limit(): void
    {
        $admin = $this->makeUser(['admin']);
        $ids = implode(',', range(1, \App\Services\Pdf\OrderPdfRenderer::BULK_MAX_ORDERS + 1));

        $this->actingAs($admin)
            ->get(route('orders.pdf.bulk-label', ['ids' => $ids]))
            ->assertStatus(422);
    }
}
