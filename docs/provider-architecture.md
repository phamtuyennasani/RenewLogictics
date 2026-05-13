# Provider Architecture Guide

Tài liệu này mô tả cách tổ chức tích hợp provider bên thứ ba trong project để đội khác có thể thêm cổng thanh toán hoặc đơn vị hóa đơn điện tử mới mà không phải sửa lan man trong code hiện có.

## Mục tiêu

Tách riêng 2 domain:

- `payment`: cổng thanh toán
- `einvoice`: hóa đơn điện tử

Mỗi domain có:

- manager riêng để resolve provider
- config riêng để đăng ký driver
- implementation riêng theo từng provider

Mục tiêu của cách tổ chức này là khi thêm `VnPay`, `Momo`, `MeInvoice`, `Viettel`, ... thì phần lớn code nghiệp vụ phía trên không cần đổi.

## Cấu trúc hiện tại

```text
app/Services/
  EInvoices/
    EInvoiceProviderManager.php
  Payments/
    PaymentProviderManager.php
    Sepay.php
    SepayEInvoice.php
  Providers/
    ProviderHub.php
    Sepay/
      SepayEInvoiceService.php
      SepayPaymentService.php

config/
  sepay.php
  einvoice_providers.php
  payment_providers.php

docs/
  provider-architecture.md
  sepay-einvoice-integration.md
  sepay-payment-integration.md
```

## Ý nghĩa từng lớp

### 1. Provider implementation

Đây là nơi chứa logic thật của từng provider.

Ví dụ hiện tại:

- `App\Services\Providers\Sepay\SepayPaymentService`
- `App\Services\Providers\Sepay\SepayEInvoiceService`

Các class này chịu trách nhiệm:

- đọc config riêng của provider
- build payload
- gọi API ngoài
- verify webhook/IPN
- parse response
- trả về dữ liệu chuẩn mà tầng nghiệp vụ có thể dùng

### 2. Manager theo domain

Đây là lớp trung gian để code nghiệp vụ không phải tự biết class cụ thể của provider.

Hiện tại có:

- `App\Services\Payments\PaymentProviderManager`
- `App\Services\EInvoices\EInvoiceProviderManager`

Manager chỉ làm một việc:

- nhận tên provider hoặc lấy provider mặc định từ config
- resolve ra class implementation tương ứng

Ví dụ:

```php
$payment = app(\App\Services\Payments\PaymentProviderManager::class)->driver();
$einvoice = app(\App\Services\EInvoices\EInvoiceProviderManager::class)->driver();
```

Hoặc chỉ định rõ provider:

```php
$payment = app(\App\Services\Payments\PaymentProviderManager::class)->driver('sepay');
$einvoice = app(\App\Services\EInvoices\EInvoiceProviderManager::class)->driver('sepay');
```

### 3. Alias tương thích ngược

Hiện vẫn giữ:

- `App\Services\Payments\Sepay`
- `App\Services\Payments\SepayEInvoice`

Hai class này chỉ để không làm gãy code cũ. Code mới không nên dùng chúng làm entry point chính.

### 4. ProviderHub

`App\Services\Providers\ProviderHub` hiện chỉ là lớp tương thích tạm, gom cả `payment` và `einvoice`.

Code mới nên ưu tiên:

- `PaymentProviderManager` cho payment
- `EInvoiceProviderManager` cho einvoice

Không nên tiếp tục thiết kế mới dựa trên `ProviderHub`.

## Config hiện tại

### Payment provider config

File: `config/payment_providers.php`

Ví dụ:

```php
return [
    'default' => env('PAYMENT_PROVIDER_DEFAULT', 'sepay'),
    'drivers' => [
        'sepay' => \App\Services\Providers\Sepay\SepayPaymentService::class,
    ],
];
```

### EInvoice provider config

File: `config/einvoice_providers.php`

Ví dụ:

```php
return [
    'default' => env('EINVOICE_PROVIDER_DEFAULT', 'sepay'),
    'drivers' => [
        'sepay' => \App\Services\Providers\Sepay\SepayEInvoiceService::class,
    ],
];
```

### Biến môi trường mặc định

Trong `.env`:

```env
PAYMENT_PROVIDER_DEFAULT=sepay
EINVOICE_PROVIDER_DEFAULT=sepay
```

### Config riêng của từng provider

Credential và config kỹ thuật của từng provider không còn đặt trong `config/services.php`.

Hiện tại SePay đã được tách ra riêng:

- `config/sepay.php`

Quy ước về sau:

- `config/sepay.php`
- `config/vnpay.php`
- `config/momo.php`
- `config/meinvoice.php`

Nhờ vậy khi thêm provider mới sẽ không phải nhét tất cả vào một file `services.php`.

## Cách code nghiệp vụ nên dùng

### Với payment

Trong service nghiệp vụ hoặc controller:

```php
use App\Services\Payments\PaymentProviderManager;

class CheckoutService
{
    public function __construct(
        protected PaymentProviderManager $payments,
    ) {
    }

    public function prepare(array $order): array
    {
        $provider = $this->payments->driver();

        return $provider->makePaymentData(
            amount: $order['amount'],
            paymentCode: $order['payment_code'],
        );
    }
}
```

### Với eInvoice

```php
use App\Services\EInvoices\EInvoiceProviderManager;

class InvoiceService
{
    public function __construct(
        protected EInvoiceProviderManager $einvoices,
    ) {
    }

    public function createDraft(array $payload): array
    {
        $provider = $this->einvoices->driver();

        return $provider->createInvoice($payload);
    }
}
```

### Khi cần chỉ định provider cụ thể

```php
$payment = $this->payments->driver('sepay');
$einvoice = $this->einvoices->driver('sepay');
```

Điều này hữu ích khi:

- một số khách hàng dùng provider khác
- một số môi trường test dùng provider khác
- cần migrate dần từ provider A sang provider B

## Quy ước thêm provider mới

### Thêm cổng thanh toán mới

Ví dụ thêm `VnPay`:

1. Tạo implementation:

```text
app/Services/Providers/Vnpay/VnpayPaymentService.php
```

2. Đăng ký trong `config/payment_providers.php`:

```php
'drivers' => [
    'sepay' => \App\Services\Providers\Sepay\SepayPaymentService::class,
    'vnpay' => \App\Services\Providers\Vnpay\VnpayPaymentService::class,
],
```

3. Nếu muốn dùng mặc định:

```env
PAYMENT_PROVIDER_DEFAULT=vnpay
```

### Thêm đơn vị hóa đơn điện tử mới

Ví dụ thêm `MeInvoice`:

1. Tạo implementation:

```text
app/Services/Providers/Meinvoice/MeinvoiceEInvoiceService.php
```

2. Đăng ký trong `config/einvoice_providers.php`

3. Nếu muốn dùng mặc định:

```env
EINVOICE_PROVIDER_DEFAULT=meinvoice
```

## Quy tắc dành cho team dev

- Không thêm logic provider mới vào class `Sepay` hiện tại.
- Không thêm provider hóa đơn mới vào `SepayEInvoiceService`.
- Mỗi provider phải có thư mục riêng dưới `app/Services/Providers/<ProviderName>/`.
- Mỗi domain phải đi qua manager tương ứng.
- Tầng nghiệp vụ `Order`, `Checkout`, `Invoice` không nên hard-code trực tiếp provider cụ thể nếu không thật sự cần.
- Nếu cần đổi provider mặc định, ưu tiên đổi qua config/env trước khi sửa code nghiệp vụ.

## Trạng thái hiện tại của SePay

### Payment

- implementation chuẩn: `App\Services\Providers\Sepay\SepayPaymentService`
- manager truy cập: `App\Services\Payments\PaymentProviderManager`

### EInvoice

- implementation chuẩn: `App\Services\Providers\Sepay\SepayEInvoiceService`
- manager truy cập: `App\Services\EInvoices\EInvoiceProviderManager`

## Hướng mở rộng sau này

Khi hệ thống lớn hơn, có thể bổ sung thêm:

- interface riêng cho payment provider
- interface riêng cho eInvoice provider
- DTO chuẩn hóa response giữa các provider
- factory chọn provider theo tenant hoặc khách hàng
- cache token dùng chung cho provider cần OAuth/token

Hiện tại chưa cần làm các bước đó. Cấu trúc manager + config registry là đủ để mở rộng mà ít đổi code nhất.
