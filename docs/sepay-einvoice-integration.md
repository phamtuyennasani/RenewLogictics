# SePay eInvoice Integration Guide

Tài liệu này mô tả phần tích hợp SePay cho `einvoice` đã được chuẩn bị sẵn trong project để đội khác có thể tiếp tục code mà không phải nghiên cứu lại API từ đầu.

## Phạm vi đã có sẵn

Project hiện đã có wrapper cho các endpoint eInvoice chính của SePay:

- service chuẩn: `App\Services\Providers\Sepay\SepayEInvoiceService`
- manager domain einvoice: `App\Services\EInvoices\EInvoiceProviderManager`
- config provider: `config/einvoice_providers.php`
- config credential SePay: `config/sepay.php`
- test: `tests/Feature/SepayEInvoiceTest.php`

Phần này đang ở mức provider:

- lấy access token
- gọi các API eInvoice của SePay
- build payload buyer/item/invoice
- parse lỗi và ném exception có nghĩa

Phần này chưa nối vào `Order`, `Invoice`, hoặc database nghiệp vụ riêng của hệ thống.

## File chính

- [app/Services/Providers/Sepay/SepayEInvoiceService.php](/H:/laragon/www/Hethong/hethong-laravel/app/Services/Providers/Sepay/SepayEInvoiceService.php:1)
- [app/Services/EInvoices/EInvoiceProviderManager.php](/H:/laragon/www/Hethong/hethong-laravel/app/Services/EInvoices/EInvoiceProviderManager.php:1)
- [config/einvoice_providers.php](/H:/laragon/www/Hethong/hethong-laravel/config/einvoice_providers.php:1)
- [config/sepay.php](/H:/laragon/www/Hethong/hethong-laravel/config/sepay.php:1)
- [tests/Feature/SepayEInvoiceTest.php](/H:/laragon/www/Hethong/hethong-laravel/tests/Feature/SepayEInvoiceTest.php:1)

## Biến môi trường

Khai báo trong `.env`:

```env
EINVOICE_PROVIDER_DEFAULT=sepay

SEPAY_EINVOICE_ENV=sandbox
SEPAY_EINVOICE_CLIENT_ID=
SEPAY_EINVOICE_CLIENT_SECRET=
SEPAY_EINVOICE_BASE_URL=
```

Ghi chú:

- `SEPAY_EINVOICE_ENV=sandbox` dùng base URL mặc định `https://einvoice-api-sandbox.sepay.vn`
- `SEPAY_EINVOICE_ENV=production` dùng base URL mặc định `https://einvoice-api.sepay.vn`
- nếu SePay cấp base URL riêng thì có thể set `SEPAY_EINVOICE_BASE_URL`

## Cách lấy provider đúng chuẩn

Code mới nên đi qua `EInvoiceProviderManager`:

```php
$einvoice = app(\App\Services\EInvoices\EInvoiceProviderManager::class)->driver();
```

Hoặc chỉ định rõ:

```php
$einvoice = app(\App\Services\EInvoices\EInvoiceProviderManager::class)->driver('sepay');
```

Không nên lấy alias cũ `App\Services\Payments\SepayEInvoice` làm entry point chính cho code mới.

## Các endpoint đã được bọc sẵn

Service hiện đã bọc:

- `POST /v1/token`
- `GET /v1/provider-accounts`
- `GET /v1/provider-accounts/{id}`
- `POST /v1/invoices/create`
- `GET /v1/invoices/create/check/{tracking_code}`
- `POST /v1/invoices/delete/{reference_code}`
- `POST /v1/invoices/issue`
- `GET /v1/invoices/issue/check/{tracking_code}`
- `GET /v1/invoices`
- `GET /v1/invoices/{reference_code}`
- `GET /v1/invoices/{tracking_code}/download`
- `GET /v1/usage`

## Cách dùng chi tiết

### 1. Lấy access token

Service sẽ tự lấy token khi cần. Nếu muốn gọi tường minh:

```php
$tokenData = $einvoice->createToken();
$accessToken = $tokenData['access_token'];
```

Hoặc:

```php
$accessToken = $einvoice->getAccessToken();
```

Trong cùng một request PHP, token được giữ trong object và tái sử dụng.

## 2. Lấy danh sách tài khoản xuất hóa đơn

```php
$accounts = $einvoice->listProviderAccounts(page: 1, perPage: 20);
```

Mục tiêu của bước này là lấy:

- danh sách account có thể xuất hóa đơn
- `provider_account_id`

## 3. Lấy chi tiết tài khoản

```php
$account = $einvoice->getProviderAccount('provider-account-uuid');
```

Từ response này, team nghiệp vụ thường cần lấy:

- `templates[].template_code`
- `templates[].invoice_series`
- `tax_authority_approved_date`

`issued_date` khi tạo hóa đơn nên từ `tax_authority_approved_date` trở đi.

## 4. Build buyer payload

```php
$buyer = $einvoice->makeBuyerPayload('Cong ty ABC', [
    'type' => 'company',
    'tax_code' => '0101234567',
    'address' => '123 Duong A, Quan B, Ha Noi',
    'email' => 'buyer@example.com',
    'phone' => '0900000000',
    'buyer_code' => 'KH-001',
]);
```

## 5. Build item payload

```php
$item = $einvoice->makeInvoiceItem(1, 1, 'San pham A', [
    'item_code' => 'SP001',
    'unit' => 'cai',
    'quantity' => 2,
    'unit_price' => 100000,
    'tax_rate' => 10,
]);
```

Giải thích nhanh:

- `line_number`: số dòng
- `line_type`: loại dòng
- `item_name`: tên hàng hóa/dịch vụ

Line type thường gặp:

- `1`: hàng hóa / dịch vụ
- `2`: khuyến mại
- `3`: chiết khấu
- `4`: ghi chú

## 6. Build invoice payload

```php
$payload = $einvoice->makeInvoicePayload(
    providerAccountId: 'provider-account-uuid',
    templateCode: '1',
    invoiceSeries: 'C26TSE',
    issuedDate: '2026-05-14 10:00:00',
    buyer: $buyer,
    items: [$item],
    overrides: [
        'currency' => 'VND',
        'payment_method' => 'CK',
        'is_draft' => true,
        'notes' => 'Hoa don cho don hang ORD-0001',
    ],
);
```

Payload này phù hợp để truyền thẳng vào `createInvoice()`.

## 7. Tạo hóa đơn

```php
$created = $einvoice->createInvoice($payload);
```

Response thường có:

- `tracking_code`
- `tracking_url`
- `message`

Ghi chú:

- `is_draft=true`: tạo nháp, chưa phát hành
- `is_draft=false`: tạo và phát hành luôn

## 8. Kiểm tra trạng thái tạo hóa đơn

```php
$result = $einvoice->checkCreateInvoiceStatus($created['tracking_code']);
```

Khi SePay xử lý xong, response thường có:

- `reference_code`
- `status`
- `message`
- `invoice`

Có helper kiểm tra nhanh:

```php
if ($einvoice->isSuccessfulStatus($result)) {
    $referenceCode = $result['reference_code'] ?? null;
}
```

## 9. Phát hành hóa đơn từ nháp

```php
$issueRequest = $einvoice->issueInvoice($referenceCode);
```

Sau đó polling:

```php
$issueResult = $einvoice->checkIssueInvoiceStatus($issueRequest['tracking_code']);
```

## 10. Lấy danh sách hóa đơn

```php
$invoices = $einvoice->listInvoices([
    'page' => 1,
    'per_page' => 20,
    'source' => 'api',
]);
```

## 11. Lấy chi tiết hóa đơn

```php
$invoice = $einvoice->getInvoice($referenceCode);
```

Thông tin chi tiết thường có:

- `invoice_number`
- `issued_date`
- `status`
- `buyer`
- `items`
- `pdf_url`
- `xml_url`

## 12. Tải file PDF/XML

```php
$download = $einvoice->downloadInvoice($created['tracking_code'], 'pdf');
$binary = $einvoice->decodeDownloadedInvoice($download);

file_put_contents(
    storage_path('app/private/invoices/' . $download['file_name']),
    $binary,
);
```

Ghi chú:

- API trả `content` dạng base64
- `decodeDownloadedInvoice()` đổi base64 sang binary

## 13. Xóa hóa đơn nháp

```php
$einvoice->deleteDraftInvoice($referenceCode);
```

## 14. Kiểm tra hạn ngạch

```php
$usage = $einvoice->getUsage();
$quotaRemaining = (int) ($usage['quota_remaining'] ?? 0);
```

## Luồng tích hợp khuyến nghị cho team khác

Đội làm nghiệp vụ nên tạo thêm service riêng, ví dụ `OrderInvoiceService`, rồi gọi provider này phía dưới.

Luồng khuyến nghị:

1. Xác định đơn hàng nào đủ điều kiện xuất hóa đơn
2. Chọn `provider_account_id`
3. Lấy `template_code` và `invoice_series`
4. Build `buyer`
5. Build `items`
6. Gọi `createInvoice()`
7. Poll `checkCreateInvoiceStatus()`
8. Nếu tạo nháp thì gọi `issueInvoice()`
9. Poll `checkIssueInvoiceStatus()`
10. Lưu `reference_code`, `invoice_number`, `status`, `pdf_url`, `xml_url` vào bảng nghiệp vụ riêng

## Ví dụ service nghiệp vụ tối thiểu

```php
use App\Services\EInvoices\EInvoiceProviderManager;

class OrderInvoiceService
{
    public function __construct(
        protected EInvoiceProviderManager $einvoices,
    ) {
    }

    public function createDraftForOrder(array $order): array
    {
        $provider = $this->einvoices->driver();

        $buyer = $provider->makeBuyerPayload($order['buyer_name'], [
            'tax_code' => $order['buyer_tax_code'] ?? null,
            'address' => $order['buyer_address'] ?? null,
            'email' => $order['buyer_email'] ?? null,
        ]);

        $items = [];

        foreach ($order['items'] as $index => $orderItem) {
            $items[] = $provider->makeInvoiceItem($index + 1, 1, $orderItem['name'], [
                'item_code' => $orderItem['code'] ?? null,
                'unit' => $orderItem['unit'] ?? 'cai',
                'quantity' => $orderItem['quantity'],
                'unit_price' => $orderItem['unit_price'],
                'tax_rate' => $orderItem['tax_rate'] ?? 10,
            ]);
        }

        $payload = $provider->makeInvoicePayload(
            providerAccountId: $order['provider_account_id'],
            templateCode: $order['template_code'],
            invoiceSeries: $order['invoice_series'],
            issuedDate: now()->format('Y-m-d H:i:s'),
            buyer: $buyer,
            items: $items,
            overrides: [
                'payment_method' => 'CK',
                'is_draft' => true,
                'notes' => 'Hoa don cho don hang ' . $order['code'],
            ],
        );

        return $provider->createInvoice($payload);
    }
}
```

## Những gì team khác cần làm tiếp

- nối provider vào service nghiệp vụ thật
- lưu trạng thái hóa đơn vào bảng riêng của hệ thống
- xây flow polling hoặc queue để theo dõi `create` và `issue`
- quyết định chỗ lưu file PDF/XML hoặc URL
- map trạng thái hóa đơn của SePay vào trạng thái nội bộ

## Lưu ý triển khai

- Chỉ gọi eInvoice API từ server-side vì có `client_secret`.
- Token hiện chỉ cache trong object của request hiện tại.
- Nếu xử lý batch lớn, nên bổ sung lớp cache token riêng.
- `create` và `issue` là luồng bất đồng bộ, nên phải polling endpoint `/check/...`.
- Provider hiện sẽ ném `RuntimeException` khi SePay trả lỗi nghiệp vụ hoặc HTTP lỗi.

## Kiểm thử đã có

Test hiện có ở:

- [tests/Feature/SepayEInvoiceTest.php](/H:/laragon/www/Hethong/hethong-laravel/tests/Feature/SepayEInvoiceTest.php:1)

Các case đã cover:

- lấy token và tái sử dụng token
- tạo hóa đơn với Bearer token
- ném exception có mã lỗi khi SePay trả lỗi nghiệp vụ

## Tài liệu SePay tham chiếu

- https://developer.sepay.vn/vi/einvoice-api/v1/tong-quan
- https://developer.sepay.vn/vi/einvoice-api/v1/tao-token
- https://developer.sepay.vn/vi/einvoice-api/v1/xuat-hoa-don-dien-tu
- https://developer.sepay.vn/vi/einvoice-api/v1/theo-doi-trang-thai-xuat-hoa-don
- https://developer.sepay.vn/vi/einvoice-api/v1/phat-hanh-hoa-don-dien-tu
- https://developer.sepay.vn/vi/einvoice-api/v1/theo-doi-trang-thai-phat-hanh-hoa-don
- https://developer.sepay.vn/vi/einvoice-api/v1/xoa-hoa-don-nhap
- https://developer.sepay.vn/vi/einvoice-api/v1/lay-thong-tin-hoa-don
- https://developer.sepay.vn/vi/einvoice-api/v1/tai-hoa-don
- https://developer.sepay.vn/vi/einvoice-api/v1/han-ngach
