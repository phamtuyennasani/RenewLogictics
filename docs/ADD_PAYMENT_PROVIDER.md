# Hướng dẫn thêm cổng thanh toán mới

## Tổng quan

Hệ thống hỗ trợ nhiều cổng thanh toán qua `PaymentProviderManager`. Giao diện cần thay đổi đã được refactor để tự động nhận provider mới — chỉ cần thêm code backend, **không cần sửa blade**.

## Các bước thực hiện

### Bước 1 — Tạo service class

Tạo file tại `app/Services/Providers/{Provider}/{Provider}PaymentService.php`.

Class phải implement `PaymentProvider`:

```php
<?php

namespace App\Services\Providers\{Provider};

use App\Services\Payments\Contracts\PaymentProvider;
use App\Services\Payments\Data\PaymentIntentData;
use App\Services\Payments\Data\PaymentRequestData;
use App\Services\Payments\Data\PaymentWebhookData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class {Provider}PaymentService implements PaymentProvider
{
    public function key(): string
    {
        return '{provider_key}';
    }

    public function createPayment(PaymentRequestData $data): PaymentIntentData
    {
        // Gọi API provider để tạo thanh toán
        // Trả về PaymentIntentData
    }

    public function parseWebhook(Request $request): PaymentWebhookData
    {
        // Parse request từ provider webhook
        // Trả về PaymentWebhookData
    }
}
```

#### PaymentIntentData

```php
new PaymentIntentData(
    provider: 'provider_key',          // string — key đã định nghĩa ở Bước 3
    channel: 'qr',                      // string — loại kênh (qr, gateway, wallet...)
    reference: $data->reference,        // string — mã tham chiếu hệ thống
    amount: $data->amount,             // int — số tiền (VNĐ)
    paymentUrl: $paymentUrl ?? null,    // string|null — URL thanh toán (null nếu là QR)
    qrUrl: $qrUrl ?? null,             // string|null — URL QR code (null nếu là gateway redirect)
    providerIntentId: $providerTxnId,   // string|null — ID giao dịch tại provider
    expiresAt: Carbon::parse($expiresAt) ?? null,
    raw: $rawData,                     // array — dữ liệu thô từ API
);
```

#### PaymentWebhookData

```php
new PaymentWebhookData(
    provider: 'provider_key',
    reference: $reference ?? null,
    amount: (int) $amount,
    status: $isPaid ? 'paid' : 'pending',
    providerTransactionId: $providerTxnId ?? null,
    paidAt: $isPaid ? Carbon::now() : null,
    raw: $request->all(),
    message: $message ?? null,
);
```

---

### Bước 2 — Tạo config file

Tạo file tại `config/{provider}.php`:

```php
<?php

return [
    'api_url'    => env('{PROVIDER}_API_URL', ''),
    'api_key'    => env('{PROVIDER}_API_KEY', ''),
    'api_secret' => env('{PROVIDER}_API_SECRET', ''),
    'merchant_id' => env('{PROVIDER}_MERCHANT_ID', ''),
    'callback_url' => env('APP_URL').'/api/webhooks/{provider}',
];
```

Thêm vào `.env`:

```
{PROVIDER}_API_URL=
{PROVIDER}_API_KEY=
{PROVIDER}_API_SECRET=
{PROVIDER}_MERCHANT_ID=
```

---

### Bước 3 — Đăng ký driver

Mở `config/payment_providers.php`, thêm vào mảng `drivers`:

```php
'drivers' => [
    // ... các driver hiện có
    '{provider_key}' => \App\Services\Providers\{Provider}\{Provider}PaymentService::class,
],
```

---

### Bước 4 — Thêm vào PaymentProviderManager

Mở `app/Services/Payments/PaymentProviderManager.php`:

**Thêm vào `PROVIDERS`:**

```php
protected const PROVIDERS = ['sepay', 'momo', 'vnpay', '{provider_key}'];
```

**Thêm vào `providerLabels()`:**

```php
'{provider_key}' => [
    'name'        => '{Tên hiển thị}',
    'description' => '{Mô tả ngắn}',
    'color'       => '{tailwind-color}',  // primary, pink, sky, emerald...
],
```

---

### Bước 5 — Tạo webhook controller

Tạo file tại `app/Http/Controllers/Webhook/{Provider}WebhookController.php`:

```php
<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentProviderManager;
use Illuminate\Http\Request;

class {Provider}WebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $provider = PaymentProviderManager::driver('{provider_key}');
        $data = $provider->parseWebhook($request);

        if ($data->isPaid()) {
            // TODO: Xử lý thanh toán thành công
            // Ví dụ: cập nhật trạng thái đơn hàng / hóa đơn
        }

        return response()->json(['status' => 'ok']);
    }
}
```

---

### Bước 6 — Thêm webhook route

Mở `routes/api.php`, thêm route:

```php
use App\Http\Controllers\Webhook\{Provider}WebhookController;

Route::post('/webhooks/{provider_key}', {Provider}WebhookController::class)
    ->name('api.webhooks.{provider_key}');
```

---

### Bước 7 — Thêm cấu hình bật/tắt trong database

Chạy migration hoặc seed để thêm setting:

```php
// Trong database seed hoặc migration
DB::table('settings')->updateOrInsert(
    ['id' => 1],
    [
        'options' => DB::raw("JSON_MERGE_PATCH(options, '{\"payment_{provider_key}_enabled\": false}')"),
    ]
);
```

Hoặc thêm vào `database/seeders/SettingSeeder.php` nếu có seeder.

---

## Kiểm tra

1. Bật provider trong trang **Cấu hình hệ thống**
2. Vào trang thanh toán hóa đơn / đơn hàng — provider mới phải xuất hiện
3. Tắt provider — provider phải ẩn đi
4. Test webhook bằng công cụ như Postman hoặc webhook test của provider

## Tóm tắt thay đổi

| File | Thay đổi |
|------|----------|
| `app/Services/Providers/{Provider}/...PaymentService.php` | Tạo mới |
| `config/{provider}.php` | Tạo mới |
| `.env` | Thêm biến môi trường |
| `config/payment_providers.php` | Thêm driver |
| `app/Services/Payments/PaymentProviderManager.php` | Thêm vào `PROVIDERS` + `providerLabels()` |
| `app/Http/Controllers/Webhook/{Provider}WebhookController.php` | Tạo mới |
| `routes/api.php` | Thêm webhook route |
| Database `settings` table | Thêm `payment_{provider_key}_enabled` |

**Không cần sửa**: blade template của invoice, order payment, và công nợ — đã dùng `@foreach(PaymentProviderManager::providerLabels())` dynamic rendering.
