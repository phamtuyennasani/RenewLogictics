<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureFeatureEnabled;
use App\Models\Setting;
use App\Services\EInvoices\EInvoiceProviderManager;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Providers\MoMo\MoMoPaymentService;
use App\Services\Providers\Sepay\SepayEInvoiceService;
use App\Services\Providers\Sepay\SepayPaymentService;
use App\Services\Providers\VNPay\VNPayPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class SettingsHeThongPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn ($user = null, ?string $ability = null) => $ability === 'settings.admin' ? true : null);

        config([
            'payment_providers.drivers.sepay' => SepayPaymentService::class,
            'payment_providers.drivers.momo' => MoMoPaymentService::class,
            'payment_providers.drivers.vnpay' => VNPayPaymentService::class,
            'einvoice_providers.drivers.sepay' => SepayEInvoiceService::class,
        ]);

        // Migration của project dùng information_schema (MySQL-only) nên không chạy được
        // bằng RefreshDatabase trên SQLite. Tự tạo bảng `setting` tối thiểu cho test.
        Schema::dropIfExists('setting');
        Schema::create('setting', function ($table) {
            $table->id();
            $table->string('namevi')->nullable();
            $table->json('options')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('setting');

        parent::tearDown();
    }

    public function test_it_renders_a_provider_block_for_every_registered_payment_gateway(): void
    {
        $component = Livewire::test('pages::settings.he-thong');

        foreach (PaymentProviderManager::configSchemas() as $schema) {
            $component->assertSee($schema['name']);
        }
    }

    public function test_toggling_a_provider_flips_its_enabled_flag_in_state(): void
    {
        Livewire::test('pages::settings.he-thong')
            ->assertSet('paymentEnabled.sepay', false)
            ->call('togglePayment', 'sepay')
            ->assertSet('paymentEnabled.sepay', true)
            ->call('togglePayment', 'sepay')
            ->assertSet('paymentEnabled.sepay', false);
    }

    public function test_saving_with_a_non_sensitive_provider_persists_storage_keys(): void
    {
        Livewire::test('pages::settings.he-thong')
            ->set('tab', 'payment')
            ->set('paymentEnabled.sepay', true)
            ->set('paymentConfig.bank_account_name', 'CONG TY TEST')
            ->set('paymentConfig.bank_account_number', '0123456789')
            ->set('paymentConfig.bank_code', 'VCB')
            ->call('save')
            ->assertHasNoErrors();

        $options = data_get(Setting::first(), 'options', []);

        $this->assertTrue((bool) $options['payment_sepay_enabled']);
        $this->assertSame('CONG TY TEST', $options['bank_account_name']);
        $this->assertSame('0123456789', $options['bank_account_number']);
        $this->assertSame('VCB', $options['bank_code']);
        // mirrorKeys: bank_code phải được nhân bản sang bank_name để tương thích ngược.
        $this->assertSame('VCB', $options['bank_name']);
    }

    public function test_save_blocks_writes_to_sensitive_fields_until_admin_unlocks(): void
    {
        Setting::create([
            'namevi' => 'Cấu hình hệ thống',
            'options' => [
                'payment_momo_partner_code' => 'OLD_PARTNER',
                'payment_momo_access_key' => 'OLD_ACCESS',
                'payment_momo_secret_key' => 'OLD_SECRET',
            ],
        ]);

        Livewire::test('pages::settings.he-thong')
            ->set('paymentEnabled.momo', true)
            ->set('paymentConfig.payment_momo_partner_code', 'NEW_PARTNER')
            ->call('save')
            ->assertHasNoErrors();

        $options = data_get(Setting::first(), 'options', []);

        // Vẫn giữ giá trị cũ (gateway còn khóa, không phải Admin xác thực).
        $this->assertSame('OLD_PARTNER', $options['payment_momo_partner_code']);
        $this->assertSame('OLD_ACCESS', $options['payment_momo_access_key']);
        $this->assertSame('OLD_SECRET', $options['payment_momo_secret_key']);
        $this->assertTrue((bool) $options['payment_momo_enabled']);
    }

    public function test_select_fields_default_to_first_option_when_db_value_is_missing(): void
    {
        // DB chưa có payment_momo_environment / payment_vnpay_environment.
        Livewire::test('pages::settings.he-thong')
            ->assertSet('paymentConfig.payment_momo_environment', 'sandbox')
            ->assertSet('paymentConfig.payment_vnpay_environment', 'sandbox')
            ->assertSet('einvoiceConfig.einvoice_sepay_environment', 'sandbox');
    }

    public function test_select_fields_preserve_existing_db_value(): void
    {
        Setting::create([
            'namevi' => 'Cấu hình hệ thống',
            'options' => [
                'payment_momo_environment' => 'production',
                'payment_vnpay_environment' => 'production',
                'einvoice_sepay_environment' => 'production',
            ],
        ]);

        Livewire::test('pages::settings.he-thong')
            ->assertSet('paymentConfig.payment_momo_environment', 'production')
            ->assertSet('paymentConfig.payment_vnpay_environment', 'production')
            ->assertSet('einvoiceConfig.einvoice_sepay_environment', 'production');
    }

    public function test_it_renders_a_provider_block_for_every_registered_einvoice_gateway(): void
    {
        $component = Livewire::test('pages::settings.he-thong')->set('tab', 'invoice');

        foreach (EInvoiceProviderManager::configSchemas() as $schema) {
            $component->assertSee($schema['name']);
        }
    }

    public function test_toggling_an_einvoice_provider_flips_its_enabled_flag_in_state(): void
    {
        Livewire::test('pages::settings.he-thong')
            ->assertSet('einvoiceEnabled.sepay', false)
            ->call('toggleEinvoice', 'sepay')
            ->assertSet('einvoiceEnabled.sepay', true)
            ->call('toggleEinvoice', 'sepay')
            ->assertSet('einvoiceEnabled.sepay', false);
    }

    public function test_save_blocks_writes_to_sensitive_einvoice_fields_until_admin_unlocks(): void
    {
        Setting::create([
            'namevi' => 'Cấu hình hệ thống',
            'options' => [
                'einvoice_sepay_environment' => 'sandbox',
                'einvoice_sepay_client_id' => 'OLD_CLIENT_ID',
                'einvoice_sepay_client_secret' => 'OLD_CLIENT_SECRET',
            ],
        ]);

        Livewire::test('pages::settings.he-thong')
            ->set('einvoiceEnabled.sepay', true)
            ->set('einvoiceConfig.einvoice_sepay_provider_account_id', 'ACC_123')
            ->set('einvoiceConfig.einvoice_sepay_template_code', '01GTKT0/001')
            ->set('einvoiceConfig.einvoice_sepay_invoice_series', 'C26TSE')
            ->set('einvoiceConfig.einvoice_sepay_client_id', 'NEW_CLIENT_ID')
            ->set('einvoiceConfig.einvoice_sepay_client_secret', 'NEW_SECRET')
            ->call('save')
            ->assertHasNoErrors();

        $options = data_get(Setting::first(), 'options', []);

        // Còn khóa => giữ nguyên giá trị cũ.
        $this->assertSame('OLD_CLIENT_ID', $options['einvoice_sepay_client_id']);
        $this->assertSame('OLD_CLIENT_SECRET', $options['einvoice_sepay_client_secret']);
        $this->assertSame('sandbox', $options['einvoice_sepay_environment']);
        $this->assertTrue((bool) $options['einvoice_sepay_enabled']);
        // Field không nhạy cảm vẫn được ghi bình thường.
        $this->assertSame('ACC_123', $options['einvoice_sepay_provider_account_id']);
        $this->assertSame('01GTKT0/001', $options['einvoice_sepay_template_code']);
        $this->assertSame('C26TSE', $options['einvoice_sepay_invoice_series']);
    }

    public function test_invoice_routes_are_guarded_by_feature_middleware(): void
    {
        $invoiceRoutes = collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn ($route, string $name) => str_starts_with($name, 'invoice.'));

        $this->assertNotEmpty($invoiceRoutes);

        $invoiceRoutes->each(function ($route, string $name): void {
            $this->assertContains('feature:invoice', $route->gatherMiddleware(), "Route {$name} must be feature guarded.");
        });
    }
    public function test_disabled_feature_returns_not_found(): void
    {
        config(['features.items.invoice.available' => false]);

        $this->expectException(NotFoundHttpException::class);

        app(EnsureFeatureEnabled::class)->handle(
            Request::create('/hoa-don-thu'),
            fn () => response('ok'),
            'invoice'
        );
    }
}

