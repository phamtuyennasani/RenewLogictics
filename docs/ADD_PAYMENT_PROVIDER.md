# Hướng dẫn thêm cổng thanh toán mới

## Tổng quan

Hệ thống quản lý cổng thanh toán qua `PaymentProviderManager` theo cơ chế **schema-driven**: mỗi cổng tự khai báo các trường cấu hình của mình bằng `configSchema()`, trang **Cấu hình hệ thống** đọc khai báo đó và render form động.

Hệ quả: thêm một cổng mới **không phải sửa bất kỳ file blade nào** — cả trang cấu hình lẫn trang checkout đều tự nhận cổng mới.

### Phần backend bắt buộc (3 bước cốt lõi)

| Bước | File | Việc cần làm |
|------|------|--------------|
| 1 | `app/Services/Providers/{Provider}/{Provider}PaymentService.php` | Tạo service implement `PaymentProvider` + khai báo `configSchema()` |
| 2 | `config/payment_providers.php` | Đăng ký driver |
| 3 | `app/Services/Payments/PaymentProviderManager.php` | Thêm key vào `PROVIDERS` + `providerLabels()` |

Sau 3 bước này, trang Cấu hình tự render toggle + form cấu hình, trang thanh toán tự hiển thị cổng. Các bước còn lại (config file, webhook controller, route) chỉ cần khi cổng thực sự dùng đến chúng.

---

## Bước 1 — Tạo service class + khai báo schema

Tạo file `app/Services/Providers/{Provider}/{Provider}PaymentService.php`, implement `PaymentProvider`:

```php
<?php

namespace App\Services\Providers\{Provider};

use App\Services\Payments\Contracts\PaymentProvider;
use App\Services\Payments\Data\PaymentIntentData;
use App\Services\Payments\Data\PaymentRequestData;
use App\Services\Payments\Data\PaymentWebhookData;
use Illuminate\Http\Request;

class {Provider}PaymentService implements PaymentProvider
{
    public function key(): string
    {
        return '{provider_key}';
    }

    public static function configSchema(): array
    {
        // Xem chi tiết cấu trúc field ở Bước 1.1
        return [];
    }

    public function createPayment(PaymentRequestData $data): PaymentIntentData
    {
        // Gọi API provider để tạo thanh toán, trả về PaymentIntentData
    }

    public function parseWebhook(Request $request): PaymentWebhookData
    {
        // Parse webhook từ provider, trả về PaymentWebhookData
    }
}
```

### Bước 1.1 — Cấu trúc một field trong `configSchema()`

Mỗi field là một mảng phẳng (plain array). Đây là "hợp đồng" giữa cổng và trang Cấu hình:

| Khóa | Kiểu | Bắt buộc | Ý nghĩa |
|------|------|---------|---------|
| `key` | string | ✓ | Khóa lưu trong cột `setting.options` (JSON). **Giữ nguyên khóa đang dùng nếu có dữ liệu cũ — sẽ không phải migrate**. |
| `label` | string | ✓ | Nhãn hiển thị trong form. |
| `type` | string | ✓ | `text`, `password`, hoặc `select`. |
| `required` | bool | | Đánh dấu bắt buộc + tự gắn badge "Bắt buộc". Mặc định `false`. |
| `sensitive` | bool | | `true` ⇒ ẩn sau lớp xác thực lại Admin (re-auth) như API key. Mặc định `false`. |
| `placeholder` | string | | Gợi ý nhập. |
| `options` | `array<value, label>` | | Bắt buộc khi `type=select`. |
| `mirrorKeys` | `string[]` | | Các khóa phụ cần ghi cùng giá trị (tương thích ngược, ví dụ `bank_code` → `bank_name`). |

### Bước 1.2 — Ví dụ schema thật từ các cổng đang chạy

**SePay** (field công khai, không nhạy cảm — hiển thị form trực tiếp):

```php
public static function configSchema(): array
{
    return [
        ['key' => 'bank_account_name', 'label' => 'Tên tài khoản',
         'type' => 'text', 'required' => true, 'sensitive' => false,
         'placeholder' => 'VD: CONG TY TNHH ABC'],

        ['key' => 'bank_account_number', 'label' => 'Số tài khoản',
         'type' => 'text', 'required' => true, 'sensitive' => false,
         'placeholder' => 'VD: 1234567890'],

        ['key' => 'bank_code', 'label' => 'Mã ngân hàng',
         'type' => 'text', 'required' => true, 'sensitive' => false,
         'placeholder' => 'VD: VCB, ACB, BIDV',
         'mirrorKeys' => ['bank_name']],
    ];
}
```

**MoMo** (API key — `sensitive: true` ⇒ giấu, cần Admin re-auth):

```php
public static function configSchema(): array
{
    return [
        ['key' => 'payment_momo_partner_code', 'label' => 'Partner Code',
         'type' => 'text',     'required' => true, 'sensitive' => true,
         'placeholder' => 'VD: MOMOBKUN...'],

        ['key' => 'payment_momo_access_key',   'label' => 'Access Key',
         'type' => 'text',     'required' => true, 'sensitive' => true,
         'placeholder' => 'Access key từ MoMo'],

        ['key' => 'payment_momo_secret_key',   'label' => 'Secret Key',
         'type' => 'password', 'required' => true, 'sensitive' => true,
         'placeholder' => 'Secret key từ MoMo'],
    ];
}
```

### Bước 1.3 — Cơ chế `sensitive` hoạt động thế nào

- `sensitive: false` → field hiện form ngay khi bật cổng (kiểu SePay).
- `sensitive: true` → giá trị bị che (`••••••••`); người dùng phải bấm **Xem / chỉnh sửa** và nhập lại mật khẩu Admin mới xem/sửa được. Khi cổng còn khóa, `save()` **không ghi đè** giá trị cũ → tránh xóa nhầm API key.
- Chỉ cần **một** field `sensitive: true`, cả cụm cấu hình của cổng đó sẽ được đưa vào danh sách gateway nhạy cảm tự động — không khai báo thêm ở đâu khác.
- Tiêu đề modal xác thực Admin đọc trực tiếp từ `providerLabels()[<key>]['name']`, nên cổng mới có `sensitive: true` sẽ tự hiện đúng tên (ví dụ "Xác thực Admin - VNPAY") mà không phải sửa blade.

### Bước 1.4 — Cấu trúc DTO trả về

`createPayment()` trả về `PaymentIntentData`:

```php
new PaymentIntentData(
    provider: 'provider_key',        // key của cổng
    channel: 'qr',                   // qr | redirect | wallet...
    reference: $data->reference,     // mã tham chiếu hệ thống
    amount: $data->amount,           // int (VNĐ)
    paymentUrl: $paymentUrl ?? null, // URL thanh toán (null nếu là QR)
    qrUrl: $qrUrl ?? null,           // URL QR (null nếu là redirect)
    providerIntentId: $providerTxnId,// ID giao dịch tại provider
    expiresAt: $expiresAt ?? null,   // Carbon|null
    raw: $rawData,                   // array dữ liệu thô
);
```

`parseWebhook()` trả về `PaymentWebhookData`:

```php
new PaymentWebhookData(
    provider: 'provider_key',
    reference: $reference ?? null,
    amount: (int) $amount,
    status: $isPaid ? 'paid' : 'ignored',
    providerTransactionId: $providerTxnId ?? null,
    paidAt: $isPaid ? Carbon::now() : null,
    raw: $payload,
    message: $message ?? null,
);
```

---

## Bước 2 — Đăng ký driver

Mở `config/payment_providers.php`, thêm vào mảng `drivers`:

```php
'drivers' => [
    // ... các driver hiện có
    '{provider_key}' => \App\Services\Providers\{Provider}\{Provider}PaymentService::class,
],
```

---

## Bước 3 — Thêm vào PaymentProviderManager

Mở `app/Services/Payments/PaymentProviderManager.php`.

**Thêm key vào `PROVIDERS`:**

```php
protected const PROVIDERS = ['sepay', 'momo', 'vnpay', '{provider_key}'];
```

**Thêm meta hiển thị vào `providerLabels()`** (`icon` là tên icon Heroicon mà Flux dùng):

```php
'{provider_key}' => [
    'name'        => '{Tên hiển thị}',
    'description' => '{Mô tả ngắn}',
    'color'       => '{tailwind-color}',  // primary, pink, sky, emerald...
    'icon'        => '{heroicon-name}',   // vd: credit-card, qr-code, device-phone-mobile
],
```

> `configSchemas()` trong manager tự gom `providerLabels()` + `configSchema()` của từng cổng. Trang Cấu hình chỉ gọi `PaymentProviderManager::configSchemas()` rồi `@foreach` — bạn **không** phải sửa gì trong manager ngoài 2 chỗ trên.

✅ **Đến đây cổng mới đã hoạt động trên giao diện.** Bật trong trang Cấu hình → cổng xuất hiện ở checkout. Các bước dưới chỉ làm khi cần.

---

## Bước 4 (tùy chọn) — File config & biến môi trường

Khi cổng có thêm tham số ngoài form (URL endpoint, môi trường sandbox/production…) hoặc bạn muốn fallback từ `.env` khi DB chưa có giá trị:

```php
// config/{provider}.php
return [
    'environment' => env('{PROVIDER}_ENV', 'sandbox'),
    'redirect_url' => env('{PROVIDER}_REDIRECT_URL'),
    'ipn_url'      => env('{PROVIDER}_IPN_URL'),
];
```

```ini
# .env
{PROVIDER}_ENV=sandbox
{PROVIDER}_REDIRECT_URL=
{PROVIDER}_IPN_URL=
```

Trong service, ưu tiên load từ DB trước, fallback config file (xem cách `MoMoPaymentService::loadFromSettings()` đang làm).

---

## Bước 5 (tùy chọn) — Webhook

Tạo `app/Http/Controllers/Webhook/{Provider}WebhookController.php`:

```php
<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentProviderManager;
use Illuminate\Http\Request;

class {Provider}WebhookController extends Controller
{
    public function __construct(private PaymentProviderManager $manager) {}

    public function __invoke(Request $request)
    {
        $provider = $this->manager->driver('{provider_key}');
        $data = $provider->parseWebhook($request);

        if ($data->status === 'paid') {
            // TODO: cập nhật đơn hàng / hóa đơn
        }

        return response()->json(['status' => 'ok']);
    }
}
```

Đăng ký route trong `routes/api.php`:

```php
use App\Http\Controllers\Webhook\{Provider}WebhookController;

Route::post('/webhooks/{provider_key}', {Provider}WebhookController::class)
    ->name('api.webhooks.{provider_key}');
```

---

## Kiểm thử

Bộ test mẫu có sẵn ở `tests/Feature/PaymentProviderManagerTest.php` và `tests/Feature/SettingsHeThongPageTest.php`. Khi thêm cổng mới, nên thêm assertion trong test `config_schemas_preserve_existing_storage_keys` để khóa đặc tả các `key` của cổng mới — tránh ai đó sửa `key` rồi vô tình mất dữ liệu cũ.

Chạy:

```bash
php artisan test tests/Feature/PaymentProviderManagerTest.php
php artisan test tests/Feature/SettingsHeThongPageTest.php
```

### Verify thủ công

1. Vào **Cấu hình hệ thống** → tab Thanh toán → cổng mới phải xuất hiện với toggle riêng.
2. Bật cổng, nhập field, **Lưu** → reload trang xem có giữ giá trị không.
3. Nếu cổng có field `sensitive`, kiểm tra: đóng khóa lại → giá trị bị che `••••••••`; bấm **Xem / chỉnh sửa** → modal hỏi mật khẩu Admin.
4. Vào trang thanh toán đơn hàng → cổng mới xuất hiện trong danh sách.
5. Test webhook (nếu có) bằng Postman hoặc tool của provider.

---

## Tóm tắt thay đổi

| Bắt buộc? | File | Thay đổi |
|-----------|------|----------|
| ✓ | `app/Services/Providers/{Provider}/{Provider}PaymentService.php` | Tạo mới (implement `PaymentProvider` + `configSchema()`) |
| ✓ | `config/payment_providers.php` | Thêm driver |
| ✓ | `app/Services/Payments/PaymentProviderManager.php` | Thêm key vào `PROVIDERS` + meta vào `providerLabels()` |
| tùy chọn | `config/{provider}.php` + `.env` | Khi cần fallback ngoài DB |
| tùy chọn | `app/Http/Controllers/Webhook/{Provider}WebhookController.php` | Khi cổng có webhook |
| tùy chọn | `routes/api.php` | Route webhook |

**Không cần sửa:**

- Trang **Cấu hình hệ thống** ([resources/views/pages/settings/⚡he-thong.blade.php](../resources/views/pages/settings/⚡he-thong.blade.php)) — render động qua `@foreach(PaymentProviderManager::configSchemas())`.
- Trang thanh toán/order/công nợ — render động qua `@foreach(PaymentProviderManager::providerLabels())`.

Đó chính là ý nghĩa của cơ chế schema-driven: **giao diện phản chiếu cấu hình, không cấu hình phản chiếu giao diện**.
