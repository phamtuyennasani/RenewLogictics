<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Mobile\BuildsMobileSchema;
use Tests\TestCase;

/**
 * Trang tra cước công khai /tra-cuoc (A4 — quote calculator, không đăng nhập).
 *
 * Nguyên tắc bất di bất dịch: response CHỈ được chứa cước bán —
 * cước vốn (cost_price) và cước gốc (base_price) tuyệt đối không lộ.
 */
class PublicQuotePageTest extends TestCase
{
    use BuildsMobileSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildMobileSchema();

        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
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

        Schema::create('setting', function (Blueprint $table): void {
            $table->id();
            $table->string('namevi')->nullable();
            $table->json('options')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        DB::table('setting')->insert(['options' => json_encode(['dim' => 5000])]);
    }

    protected function tearDown(): void
    {
        foreach (['setting', 'service_price_details', 'service_price_list_countries', 'service_price_lists', 'countries'] as $table) {
            Schema::dropIfExists($table);
        }
        $this->dropMobileSchema();

        parent::tearDown();
    }

    /** Dựng dịch vụ + quốc gia + bảng giá: DON_GIA 450k/kg (0–5kg), CO_DINH 2tr (5–10kg). */
    protected function seedPriceList(): array
    {
        $service = News::query()->create(['namevi' => 'Chuyển phát đi Nhật', 'type' => News::TYPE_MAIN_SERVICE]);
        $countryId = DB::table('countries')->insertGetId(['name' => 'JAPAN']);

        $listId = DB::table('service_price_lists')->insertGetId([
            'name' => 'Bảng giá Nhật', 'service_id' => $service->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('service_price_list_countries')->insert([
            'service_price_list_id' => $listId, 'country_id' => $countryId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('service_price_details')->insert([
            [
                'service_price_list_id' => $listId, 'quycach' => 'DON_GIA',
                'weight_from' => 0, 'weight_to' => 5,
                'sale_price' => 450_000, 'cost_price' => 330_000, 'base_price' => 280_000,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'service_price_list_id' => $listId, 'quycach' => 'CO_DINH',
                'weight_from' => 5.01, 'weight_to' => 10,
                'sale_price' => 2_000_000, 'cost_price' => 1_500_000, 'base_price' => 1_200_000,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        return [$service->id, $countryId];
    }

    public function test_quote_page_renders_without_login(): void
    {
        $this->seedPriceList();

        $response = $this->get('/tra-cuoc');

        $response->assertOk();
        $response->assertSee('Ước tính cước vận chuyển');
        $response->assertSee('Chuyển phát đi Nhật');
    }

    public function test_quote_computes_don_gia_price(): void
    {
        [$serviceId, $countryId] = $this->seedPriceList();

        // 2.3kg → làm tròn lên mốc 0.5 → cân tính cước 2.5kg × 450k = 1.125.000.
        $response = $this->get("/tra-cuoc?service_id={$serviceId}&country_id={$countryId}&g_weight=2.3");

        $response->assertOk();
        $response->assertSee('1.125.000');
        $response->assertSee('2.5 kg');
    }

    public function test_quote_computes_co_dinh_price(): void
    {
        [$serviceId, $countryId] = $this->seedPriceList();

        // 7kg rơi vào dòng CO_DINH → 2.000.000 nguyên giá.
        $response = $this->get("/tra-cuoc?service_id={$serviceId}&country_id={$countryId}&g_weight=7");

        $response->assertOk();
        $response->assertSee('2.000.000');
        $response->assertSee('Giá cố định theo khoảng cân');
    }

    public function test_quote_never_leaks_cost_or_base_price(): void
    {
        [$serviceId, $countryId] = $this->seedPriceList();

        $response = $this->get("/tra-cuoc?service_id={$serviceId}&country_id={$countryId}&g_weight=2.3");

        $response->assertOk();
        // Cước vốn 330k/825k và cước gốc 280k/700k không được xuất hiện dưới mọi định dạng.
        foreach (['330.000', '330,000', '330000', '280.000', '280,000', '280000', '825.000', '700.000', 'cost_price', 'base_price'] as $forbidden) {
            $response->assertDontSee($forbidden);
        }
    }

    public function test_quote_out_of_range_weight_shows_friendly_message(): void
    {
        [$serviceId, $countryId] = $this->seedPriceList();

        // 50kg vượt mọi khoảng cân → thông báo liên hệ, không lỗi 500, không giá 0đ.
        $response = $this->get("/tra-cuoc?service_id={$serviceId}&country_id={$countryId}&g_weight=50");

        $response->assertOk();
        $response->assertSee('Chưa có bảng giá cho tuyến này');
        $response->assertDontSee('0 đ');
    }

    public function test_quote_feature_flag_disables_page(): void
    {
        config(['features.items.quote.available' => false]);

        $this->get('/tra-cuoc')->assertNotFound();
    }
}
