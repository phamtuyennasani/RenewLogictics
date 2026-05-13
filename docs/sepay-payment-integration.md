# SePay Payment Integration Guide

Tài liệu này mô tả phần tích hợp SePay cho `payment` đã có sẵn trong project, cách dùng đúng với kiến trúc hiện tại, và cách để đội khác nối tiếp vào nghiệp vụ mà không phải đọc lại từ đầu.

## Phạm vi đã có sẵn

Project hiện đã có sẵn phần integration kỹ thuật cho SePay payment:

- service chuẩn: `App\Services\Providers\Sepay\SepayPaymentService`
- manager domain payment: `App\Services\Payments\PaymentProviderManager`
- webhook controller số dư/QR: `App\Http\Controllers\Webhook\SepayWebhookController`
- webhook controller payment gateway IPN: `App\Http\Controllers\Webhook\SepayGatewayIpnController`
- log model webhook QR: `App\Models\SepayWebhookLog`
- log model gateway IPN: `App\Models\SepayGatewayIpnLog`
- route webhook/IPN: `routes/api.php`
- test: `tests/Unit/SepayTest.php`

Phần này mới dừng ở mức provider:

- tạo dữ liệu thanh toán
- verify request từ SePay
- parse payload
- check trạng thái paid
- log webhook/IPN

Phần này chưa nối trực tiếp vào `Order`.

## File chính

- [app/Services/Providers/Sepay/SepayPaymentService.php](/H:/laragon/www/Hethong/hethong-laravel/app/Services/Providers/Sepay/SepayPaymentService.php:1)
- [app/Services/Payments/PaymentProviderManager.php](/H:/laragon/www/Hethong/hethong-laravel/app/Services/Payments/PaymentProviderManager.php:1)
- [app/Http/Controllers/Webhook/SepayWebhookController.php](/H:/laragon/www/Hethong/hethong-laravel/app/Http/Controllers/Webhook/SepayWebhookController.php:1)
- [app/Http/Controllers/Webhook/SepayGatewayIpnController.php](/H:/laragon/www/Hethong/hethong-laravel/app/Http/Controllers/Webhook/SepayGatewayIpnController.php:1)
- [config/sepay.php](/H:/laragon/www/Hethong/hethong-laravel/config/sepay.php:1)
- [config/payment_providers.php](/H:/laragon/www/Hethong/hethong-laravel/config/payment_providers.php:1)

## Biến môi trường

Khai báo trong `.env`:

```env
PAYMENT_PROVIDER_DEFAULT=sepay

SEPAY_BANK=
SEPAY_ACCOUNT_NUMBER=
SEPAY_ACCOUNT_NAME=
SEPAY_QR_BASE_URL=https://qr.sepay.vn/img
SEPAY_AUTH_MODE=hmac
SEPAY_API_KEY=
SEPAY_WEBHOOK_SECRET=
SEPAY_TIMESTAMP_TOLERANCE=300

SEPAY_GATEWAY_ENV=sandbox
SEPAY_GATEWAY_MERCHANT_ID=
SEPAY_GATEWAY_SECRET_KEY=
SEPAY_GATEWAY_IPN_SECRET_KEY=
SEPAY_GATEWAY_API_BASE_URL=
SEPAY_GATEWAY_CHECKOUT_BASE_URL=
SEPAY_GATEWAY_SUCCESS_URL=
SEPAY_GATEWAY_ERROR_URL=
SEPAY_GATEWAY_CANCEL_URL=
```

## Route đã có

```text
POST /api/webhooks/sepay
POST /api/payment-gateways/sepay/ipn
```

Hai route này hiện đang làm:

- xác thực request từ SePay
- parse payload
- chống log trùng
- ghi log request
- trả `{"success": true}`

Hai route này chưa cập nhật `Order`.

## Cách lấy provider đúng chuẩn

Code mới nên đi qua `PaymentProviderManager`:

```php
$paymentProvider = app(\App\Services\Payments\PaymentProviderManager::class)->driver();
```

Hoặc chỉ định rõ:

```php
$paymentProvider = app(\App\Services\Payments\PaymentProviderManager::class)->driver('sepay');
```

Không nên lấy alias cũ `App\Services\Payments\Sepay` làm entry point chính cho code mới.

## Các chức năng đã có trong SePay payment

### 1. Tạo QR URL

```php
$url = $paymentProvider->makeQrUrl(
    amount: 100000,
    description: 'DH12345',
);
```

Kết quả là URL QR của SePay theo tài khoản ngân hàng đã cấu hình.

### 2. Tạo dữ liệu thanh toán QR

```php
$payment = $paymentProvider->makePaymentData(
    amount: 100000,
    paymentCode: 'AVN260513001',
);
```

Response gồm:

- `gateway`
- `payment_code`
- `amount`
- `bank`
- `account_number`
- `account_name`
- `description`
- `qr_url`
- `expires_at`

Đây là method phù hợp nhất để nghiệp vụ `Order` dùng khi cần chuẩn bị dữ liệu thanh toán QR.

### 3. Tạo dữ liệu payment gateway

```php
$gateway = $paymentProvider->makeGatewayPaymentData(
    amount: 100000,
    invoiceNumber: 'AVN260513001',
    description: 'Thanh toan don hang AVN260513001',
);
```

Response gồm:

- `gateway`
- `environment`
- `checkout_url`
- `fields`
- `form_html`

`form_html` có thể render trực tiếp ra nút submit hoặc form thanh toán.

### 4. Verify webhook request SePay QR

```php
$paymentProvider->verifyRequest($request);
```

Provider hiện hỗ trợ 3 mode:

- `none`
- `apikey`
- `hmac`

Mode lấy theo `SEPAY_AUTH_MODE`.

### 5. Parse webhook QR payload

```php
$payload = $paymentProvider->parseWebhookPayload($rawBody);
```

### 6. Kiểm tra đã thanh toán hay chưa

```php
$paid = $paymentProvider->isPaid(
    payload: $payload,
    paymentCode: 'AVN260513001',
    expectedAmount: 100000,
);
```

Điều kiện `paid` hiện tại:

- `transferType === in`
- `code` khớp `paymentCode`
- `transferAmount >= expectedAmount`

### 7. Trích xuất kết quả thanh toán QR

```php
$result = $paymentProvider->extractPaymentResult(
    payload: $payload,
    paymentCode: 'AVN260513001',
    expectedAmount: 100000,
);
```

Response gồm:

- `paid`
- `payment_code`
- `expected_amount`
- `received_amount`
- `transaction_id`
- `bank`
- `account_number`
- `reference_code`
- `transaction_date`
- `raw`

### 8. Verify gateway IPN

```php
$paymentProvider->verifyGatewayIpnRequest($request);
```

Nếu `SEPAY_GATEWAY_IPN_SECRET_KEY` có giá trị, provider sẽ kiểm tra header `X-Secret-Key`.

### 9. Parse gateway IPN payload

```php
$payload = $paymentProvider->parseGatewayIpnPayload($rawBody);
```

### 10. Kiểm tra gateway order đã thanh toán chưa

```php
$paid = $paymentProvider->isGatewayOrderPaid(
    payload: $payload,
    invoiceNumber: 'AVN260513001',
    expectedAmount: 100000,
);
```

Điều kiện `paid` hiện tại:

- `notification_type === ORDER_PAID`
- `order.order_status === CAPTURED`
- `transaction.transaction_status === APPROVED`
- `order.order_invoice_number` khớp
- `transaction.transaction_amount >= expectedAmount`

### 11. Trích xuất kết quả gateway payment

```php
$result = $paymentProvider->extractGatewayPaymentResult(
    payload: $payload,
    invoiceNumber: 'AVN260513001',
    expectedAmount: 100000,
);
```

### 12. Query chi tiết gateway order

```php
$details = $paymentProvider->getGatewayOrderDetails('SEPAY-68B01673A77FF');
```

Method này dùng khi:

- IPN đến chậm
- cần đối soát lại trạng thái
- cần lấy dữ liệu order trực tiếp từ gateway API

## Luồng tích hợp khuyến nghị cho team Order

### Luồng QR

1. Tìm `Order`
2. Xác định số tiền cần thu
3. Chọn `paymentCode`
4. Gọi `makePaymentData()`
5. Lưu metadata thanh toán vào `orders.payment`

### Luồng payment gateway

1. Tìm `Order`
2. Xác định số tiền cần thu
3. Chọn `invoiceNumber`
4. Gọi `makeGatewayPaymentData()`
5. Lưu metadata thanh toán vào `orders.payment`

### Luồng webhook/IPN

1. Parse payload bằng provider
2. Tìm `Order` theo `paymentCode` hoặc `invoiceNumber`
3. Gọi `extractPaymentResult()` hoặc `extractGatewayPaymentResult()`
4. Nếu `paid === true` thì cập nhật trạng thái thanh toán
5. Lưu payload thực tế vào `orders.payment` hoặc bảng log nghiệp vụ riêng

## Mapping nghiệp vụ khuyến nghị

Để đơn giản và thống nhất, nên map như sau:

### QR webhook

- `paymentCode` = `orders.id_bill`
- `reference` = `payload.code`
- `transactionId` = `payload.id`

### Gateway

- `invoiceNumber` = `orders.id_bill`
- `reference` = `payload.order.order_invoice_number`
- `transactionId` = `payload.transaction.id`

Lợi ích:

- không cần mã trung gian
- dễ truy đơn từ webhook/IPN
- thống nhất giữa hai luồng QR và gateway

## Ví dụ service nghiệp vụ tối thiểu

```php
use App\Services\Payments\PaymentProviderManager;

class OrderPaymentService
{
    public function __construct(
        protected PaymentProviderManager $payments,
    ) {
    }

    public function prepareQr(array $order): array
    {
        $provider = $this->payments->driver();

        return $provider->makePaymentData(
            amount: $order['amount'],
            paymentCode: $order['id_bill'],
        );
    }
}
```

## Những gì team khác cần làm tiếp

- nối provider này vào `Order`
- lưu metadata thanh toán vào bảng nghiệp vụ thật
- map `payment_status` theo rule của hệ thống
- cập nhật `Order` khi webhook/IPN báo thanh toán thành công
- bổ sung UI để hiển thị QR hoặc nút thanh toán gateway

## Kiểm thử đã có

Test hiện có ở:

- [tests/Unit/SepayTest.php](/H:/laragon/www/Hethong/hethong-laravel/tests/Unit/SepayTest.php:1)

Các case đã cover:

- build QR URL
- verify HMAC webhook
- check QR paid status
- sign gateway fields
- build gateway checkout form
- check gateway IPN paid status

## Tài liệu SePay tham chiếu

- https://developer.sepay.vn/vi/sepay-webhooks/tao-qr-va-form-thanh-toan
- https://developer.sepay.vn/vi/sepay-webhooks/tich-hop-webhook
- https://developer.sepay.vn/vi/sepay-webhooks/xac-thuc
- https://developer.sepay.vn/vi/cong-thanh-toan/bat-dau
- https://developer.sepay.vn/vi/cong-thanh-toan/API/don-hang/form-thanh-toan
- https://developer.sepay.vn/vi/cong-thanh-toan/API/tong-quan
- https://developer.sepay.vn/vi/cong-thanh-toan/IPN
- https://developer.sepay.vn/vi/cong-thanh-toan/API/don-hang/chi-tiet-don-hang
