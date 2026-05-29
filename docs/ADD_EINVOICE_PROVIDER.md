# Hướng dẫn thêm cổng hóa đơn điện tử mới

## Tổng quan

Hệ thống quản lý cổng hóa đơn điện tử qua `EInvoiceProviderManager` theo cơ chế **schema-driven** giống hệt phần thanh toán: mỗi cổng tự khai báo các trường cấu hình của mình bằng `configSchema()`, trang **Cấu hình hệ thống** (tab Hóa đơn) đọc khai báo đó và render form động.

Hệ quả: thêm một cổng hóa đơn mới **không phải sửa bất kỳ file blade nào** — tab Hóa đơn tự nhận cổng mới.

> Phần này song song với [ADD_PAYMENT_PROVIDER.md](ADD_PAYMENT_PROVIDER.md). Khác biệt chính: interface là `EInvoiceProvider` (create/issue/status/download thay vì createPayment/parseWebhook), và field cấu hình hóa đơn **thường là `sensitive: true`** (client secret, token).

### Phần backend bắt buộc (3 bước cốt lõi)

| Bước | File | Việc cần làm |
|------|------|--------------|
| 1 | `app/Services/Providers/{Provider}/{Provider}EInvoiceService.php` | Tạo service implement `EInvoiceProvider` + khai báo `configSchema()` |
| 2 | `config/einvoice_providers.php` | Đăng ký driver |
| 3 | `app/Services/EInvoices/EInvoiceProviderManager.php` | Thêm key vào `PROVIDERS` + `providerLabels()` |

Sau 3 bước này, tab Hóa đơn tự render toggle + form cấu hình. Các bước còn lại (config file, controller phát hành) chỉ cần khi cổng thực sự dùng đến chúng.

---

## Bước 1 — Tạo service class + khai báo schema

Tạo file `app/Services/Providers/{Provider}/{Provider}EInvoiceService.php`, implement `EInvoiceProvider`:

```php
<?php

namespace App\Services\Providers\{Provider};

use App\Services\EInvoices\Contracts\EInvoiceProvider;
use App\Services\EInvoices\Data\EInvoiceRequestData;
use App\Services\EInvoices\Data\EInvoiceResultData;
use App\Services\EInvoices\Data\EInvoiceStatusData;

class {Provider}EInvoiceService implements EInvoiceProvider
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

    public function create(EInvoiceRequestData $data): EInvoiceResultData
    {
        // Gọi API provider để tạo hóa đơn (draft hoặc issued).
    }

    public function issue(string $referenceCode): EInvoiceResultData
    {
        // Phát hành hóa đơn đã ở trạng thái draft.
    }

    public function status(string $trackingOrReferenceCode): EInvoiceStatusData
    {
        // Tra trạng thái hóa đơn.
    }

    public function download(string $trackingCode, string $type = 'pdf'): string
    {
        // Trả về nội dung file PDF/XML đã decode.
    }
}
```

### Bước 1.1 — Cấu trúc một field trong `configSchema()`

Cùng cấu trúc với phần payment (xem [ADD_PAYMENT_PROVIDER.md, Bước 1.1](ADD_PAYMENT_PROVIDER.md)):

| Khóa | Kiểu | Bắt buộc | Ý nghĩa |
|------|------|---------|---------|
| `key` | string | ✓ | Khóa lưu trong `setting.options`. Quy ước: prefix `einvoice_{provider}_*`. |
| `label` | string | ✓ | Nhãn hiển thị. |
| `type` | string | ✓ | `text`, `password`, hoặc `select`. |
| `required` | bool | | Đánh dấu bắt buộc. |
| `sensitive` | bool | | `true` ⇒ ẩn sau lớp xác thực Admin (re-auth). |
| `placeholder` | string | | Gợi ý nhập. |
| `options` | `array<value, label>` | | Bắt buộc khi `type=select`. |
| `mirrorKeys` | `string[]` | | Các khóa phụ cần ghi cùng giá trị. |

> 💡 **Quy ước với hóa đơn:** client_id / client_secret / token đều nên `sensitive: true`. Field `environment` (sandbox/production) thường cũng đặt `sensitive: true` để đi cùng cụm khi mở/khóa.

### Bước 1.2 — Ví dụ schema thật từ SePay eInvoice

Schema đang chạy trong [SepayEInvoiceService](../app/Services/Providers/Sepay/SepayEInvoiceService.php) — cả 3 field đều `sensitive: true`:

```php
public static function configSchema(): array
{
    return [
        ['key' => 'einvoice_sepay_environment', 'label' => 'Môi trường',
         'type' => 'select', 'required' => true, 'sensitive' => true,
         'options' => ['sandbox' => 'Sandbox', 'production' => 'Production']],

        ['key' => 'einvoice_sepay_client_id', 'label' => 'Client ID',
         'type' => 'text', 'required' => true, 'sensitive' => true,
         'placeholder' => 'Client ID từ SePay'],

        ['key' => 'einvoice_sepay_client_secret', 'label' => 'Client Secret',
         'type' => 'password', 'required' => true, 'sensitive' => true,
         'placeholder' => 'Client Secret từ SePay'],
    ];
}
```

### Bước 1.3 — Cơ chế `sensitive` & token mở khóa

- Cơ chế `sensitive` giống payment: giá trị bị che `••••••••`, phải bấm **Xem / chỉnh sửa** + nhập mật khẩu Admin mới xem/sửa được; khi còn khóa `save()` **không ghi đè** giá trị cũ.
- **Khác biệt quan trọng:** token mở khóa của cổng hóa đơn có prefix `einvoice:` (ví dụ `einvoice:sepay`) để **không va chạm** với cổng payment trùng key (`sepay` payment vs `sepay` einvoice). Logic này nằm sẵn trong [⚡he-thong.blade.php](../resources/views/pages/settings/⚡he-thong.blade.php) — bạn không phải tự xử lý, chỉ cần biết khi debug.
- Chỉ cần **một** field `sensitive: true`, cả cụm cấu hình của cổng đó vào danh sách gateway nhạy cảm tự động.
- Tiêu đề modal xác thực Admin tự đọc tên từ `EInvoiceProviderManager::providerLabels()[<key>]['name']` (ví dụ "Xác thực Admin - SePay eInvoice"), không phải sửa blade.

### Bước 1.4 — Cấu trúc DTO trả về

`create()` / `issue()` trả về `EInvoiceResultData`. `status()` trả về `EInvoiceStatusData`. Xem các DTO trong [app/Services/EInvoices/Data/](../app/Services/EInvoices/Data/) để biết constructor signature.

`download()` trả về **string** — nội dung file PDF/XML đã decode (binary).

---

## Bước 2 — Đăng ký driver

Mở `config/einvoice_providers.php`, thêm vào mảng `drivers`:

```php
'drivers' => [
    // ... các driver hiện có
    '{provider_key}' => \App\Services\Providers\{Provider}\{Provider}EInvoiceService::class,
],
```

---

## Bước 3 — Thêm vào EInvoiceProviderManager

Mở `app/Services/EInvoices/EInvoiceProviderManager.php`.

**Thêm key vào `PROVIDERS`:**

```php
protected const PROVIDERS = ['sepay', '{provider_key}'];
```

**Thêm meta hiển thị vào `providerLabels()`** (`icon` là tên Heroicon):

```php
'{provider_key}' => [
    'name'        => '{Tên hiển thị}',
    'description' => '{Mô tả ngắn}',
    'color'       => '{tailwind-color}',  // primary, sky, emerald...
    'icon'        => '{heroicon-name}',   // vd: receipt-percent, document-text
],
```

> `configSchemas()` trong manager tự gom `providerLabels()` + `configSchema()` của từng cổng. Tab Hóa đơn chỉ gọi `EInvoiceProviderManager::configSchemas()` rồi `@foreach`.

✅ **Đến đây cổng mới đã hoạt động trên tab Hóa đơn.** Bật trong Cấu hình → tab Hóa đơn → cổng xuất hiện với toggle riêng.

---

## Bước 4 (tùy chọn) — File config & biến môi trường

Khi cổng có thêm tham số ngoài form (base URL endpoint, môi trường…) hoặc muốn fallback từ `.env` khi DB chưa có giá trị:

```php
// config/{provider}.php
return [
    'einvoice' => [
        'environment' => env('{PROVIDER}_EINVOICE_ENV', 'sandbox'),
        'client_id'   => env('{PROVIDER}_EINVOICE_CLIENT_ID'),
        'client_secret' => env('{PROVIDER}_EINVOICE_CLIENT_SECRET'),
        'base_url'    => env('{PROVIDER}_EINVOICE_BASE_URL'),
    ],
];
```

Trong service, ưu tiên load từ DB (`Setting.options`) trước, fallback config file — xem cách [`SepayEInvoiceService::loadFromSettings()`](../app/Services/Providers/Sepay/SepayEInvoiceService.php) đang làm (đọc `einvoice_sepay_*`, fallback legacy `sepay_einvoice_*`, rồi `config('sepay.einvoice.*')`).

---

## Kiểm thử

Bộ test mẫu: [tests/Feature/EInvoiceProviderManagerTest.php](../tests/Feature/EInvoiceProviderManagerTest.php) (resolve driver) và [tests/Feature/SettingsHeThongPageTest.php](../tests/Feature/SettingsHeThongPageTest.php) (render + lock/unlock tab Hóa đơn).

Khi thêm cổng mới, nhớ đăng ký driver trong `setUp()` của `SettingsHeThongPageTest` (giống dòng `einvoice_providers.drivers.sepay`).

```bash
php artisan test tests/Feature/SettingsHeThongPageTest.php
php artisan test tests/Feature/EInvoiceProviderManagerTest.php
```

### Verify thủ công

1. Vào **Cấu hình hệ thống** → tab Hóa đơn → cổng mới phải xuất hiện với toggle riêng.
2. Bật cổng → bấm **Xem / chỉnh sửa** → nhập mật khẩu Admin → form field hiện ra.
3. Nhập field, **Lưu** → reload trang xem có giữ giá trị không.
4. Khóa lại → giá trị bị che `••••••••`; modal re-auth hiển thị đúng tên cổng.

---

## Tóm tắt thay đổi

| Bắt buộc? | File | Thay đổi |
|-----------|------|----------|
| ✓ | `app/Services/Providers/{Provider}/{Provider}EInvoiceService.php` | Tạo mới (implement `EInvoiceProvider` + `configSchema()`) |
| ✓ | `config/einvoice_providers.php` | Thêm driver |
| ✓ | `app/Services/EInvoices/EInvoiceProviderManager.php` | Thêm key vào `PROVIDERS` + meta vào `providerLabels()` |
| tùy chọn | `config/{provider}.php` + `.env` | Khi cần fallback ngoài DB |

**Không cần sửa:**

- Trang **Cấu hình hệ thống** ([resources/views/pages/settings/⚡he-thong.blade.php](../resources/views/pages/settings/⚡he-thong.blade.php)) — tab Hóa đơn render động qua `@foreach(EInvoiceProviderManager::configSchemas())`, dùng chung modal re-auth với payment.

Đó chính là ý nghĩa của cơ chế schema-driven: **giao diện phản chiếu cấu hình, không cấu hình phản chiếu giao diện**.
