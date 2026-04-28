# Cấu trúc Livewire Order Create - Hệ thống mới

## Tổng quan kiến trúc

Hệ thống tạo order mới được tổ chức theo mô hình **Livewire Page Component + Nested Components + Actions**, tách biệt rõ ràng giữa:

- **UI Layer**: Livewire components (stateful presentation)
- **Business Logic Layer**: Actions (reusable, testable)
- **Data Layer**: Eloquent models (persistence only)

## Cấu trúc thư mục

```
app/
├── Livewire/
│   └── Order/
│       ├── CreatePage.php                    # Page component chính
│       └── Components/
│           ├── SaleAssignment.php            # Chọn Sale/CTV
│           ├── ServiceSection.php            # Dịch vụ
│           ├── SenderSection.php             # Người gửi
│           ├── ReceiverSection.php           # Người nhận
│           └── PackagesSection.php           # Kiện hàng
├── Actions/
│   └── Order/
│       ├── CreateOrderAction.php             # Tạo order aggregate
│       ├── GenerateOrderCodeAction.php       # Sinh mã order
│       └── CalculateChargeableWeightAction.php # Tính cân nặng
└── DataTransferObjects/
    └── OrderFormData.php                     # DTO cho form data

resources/views/livewire/order/
├── create-page.blade.php                     # View chính
└── components/
    ├── sale-assignment.blade.php
    ├── service-section.blade.php
    ├── sender-section.blade.php
    ├── receiver-section.blade.php
    └── packages-section.blade.php
```

## Luồng dữ liệu

### 1. Page Component (CreatePage.php)

**Trách nhiệm:**
- Orchestration: điều phối các nested components
- State management: giữ state tổng của form
- Submit handler: validate và gọi CreateOrderAction
- Event coordination: lắng nghe và dispatch events giữa components

**State:**
```php
public ?int $idSale = null;
public ?int $idCtv = null;
public array $service = [];
public array $sender = [];
public array $receiver = [];
public array $packages = [];
public bool $agreedToTerms = false;
public float $dim = 6000;
```

**Events:**
- `saleChanged` → load CTV options
- `ctvChanged` → update DIM, load sender contacts
- `serviceChanged` → update service data
- `dimUpdated` → recalculate package weights

### 2. Nested Components

#### SaleAssignment
- Chọn Sale → load danh sách CTV thuộc Sale đó
- Chọn CTV → dispatch `ctvChanged` event
- Chỉ hiện với role ADMIN/CS

#### ServiceSection
- Load danh sách dịch vụ, dịch vụ đi kèm, tình trạng đơn
- Bind two-way với `$service` của parent
- Dispatch `serviceChanged` khi có thay đổi

#### SenderSection
- Load danh sách tỉnh/thành, phường/xã
- Load danh sách người gửi đã lưu (nếu có CTV)
- Chọn người gửi đã lưu → fill form
- Checkbox "Lưu thông tin cho lần sau"

#### ReceiverSection
- Load danh sách quốc gia
- Load danh sách người nhận đã lưu
- Check VSVX khi nhập postcode
- Tự động fill mã vùng khi chọn quốc gia

#### PackagesSection
- Thêm/xóa kiện hàng
- Tính toán real-time: v_weight, c_weight
- Listen `dimUpdated` event để recalculate
- Hiển thị tổng số kiện, tổng cân nặng

### 3. Actions Layer

#### GenerateOrderCodeAction
```php
execute(): string
```
- Thread-safe với DB transaction lock
- Format: AVN{YYMMDD}{NNN}
- Tự động increment số thứ tự

#### CalculateChargeableWeightAction
```php
execute(float $length, float $width, float $height, float $grossWeight, float $dim): array
```
- Tính v_weight = (L × W × H) / DIM
- Tính c_weight = max(v_weight, g_weight)
- Apply rounding rules:
  - < 21kg: làm tròn 0.5kg
  - ≥ 21kg: làm tròn lên số nguyên

#### CreateOrderAction
```php
execute(OrderFormData $formData): Order
```
- Generate order code
- Calculate package weights
- Create order record
- Create package records
- Save sender/receiver contacts (nếu được yêu cầu)
- Wrapped trong DB transaction

### 4. Data Transfer Object

#### OrderFormData
```php
public function __construct(
    public ?int $idSale,
    public ?int $idCtv,
    public ?int $idCustomer,
    public array $service,
    public array $sender,
    public array $receiver,
    public array $packages,
    public ?string $notes,
    public bool $saveInfoSender,
    public bool $saveInfoReceiver,
    public float $dim,
) {}
```

## Quy tắc nghiệp vụ

### 1. Phân quyền
- **ADMIN, CS**: Chọn được Sale/CTV, tạo đơn cho bất kỳ ai
- **SALE**: Tạo đơn cho chính mình, chọn CTV thuộc mình
- **CTV**: Tạo đơn cho chính mình, không chọn Sale/CTV

### 2. DIM (Dimensional Weight Factor)
- Mặc định: 6000
- Nếu có CTV: lấy từ `user.options['dim']`
- Khi đổi CTV: update DIM và recalculate packages

### 3. Tính cân nặng
- **v_weight** (volumetric): (L × W × H) / DIM
- **c_weight** (chargeable): max(v_weight, g_weight)
- **Rounding**:
  - < 21kg: ceil(c_weight / 0.5) × 0.5
  - ≥ 21kg: ceil(c_weight)

### 4. Lưu contacts
- Chỉ lưu khi checkbox được chọn
- Sender: cần có CTV
- Receiver: luôn có thể lưu
- Generate code: CUS{NNNNNN}

### 5. Trạng thái mặc định
- `bill_status`: 'moi-tao'
- `payment_status`: 'chua-thanh-toan'
- `payment_status_ncc`: 'chua-thanh-toan-ncc'
- `id_create`: auth()->id()

## So sánh với hệ thống cũ

| Khía cạnh | Hệ thống cũ | Hệ thống mới |
|-----------|-------------|--------------|
| **Architecture** | Monolithic controller | Layered (UI/Logic/Data) |
| **State management** | Alpine.js + DOM | Livewire reactive properties |
| **Business logic** | Controller + JS | Actions (reusable, testable) |
| **Validation** | Client + Server riêng rẽ | Livewire unified validation |
| **Code reuse** | Khó tái sử dụng | Actions có thể dùng cho API/Import |
| **Testing** | Khó test controller | Dễ test Actions riêng lẻ |
| **Maintainability** | Logic rải rác | Tách biệt rõ ràng |

## Migration path

Để migrate từ hệ cũ sang hệ mới:

1. **Route**: Đã update `/orders/create` → `CreatePage::class`
2. **Middleware**: Đã thêm `role:admin|cs|sale|ctv`
3. **Gates**: Đã define trong `AuthServiceProvider`
4. **Database**: Không cần thay đổi schema (tương thích ngược)
5. **Old views**: Giữ nguyên để fallback nếu cần

## Testing checklist

- [ ] ADMIN tạo đơn, chọn Sale/CTV
- [ ] CS tạo đơn, chọn Sale/CTV
- [ ] SALE tạo đơn cho chính mình, chọn CTV
- [ ] CTV tạo đơn cho chính mình
- [ ] Tính cân nặng đúng với DIM khác nhau
- [ ] Lưu sender/receiver contacts
- [ ] Load sender/receiver đã lưu
- [ ] Check VSVX postcode
- [ ] Thêm/xóa kiện hàng
- [ ] Validate form đầy đủ
- [ ] Transaction rollback khi lỗi
- [ ] Generate order code không trùng

## Next steps

1. Thêm upload attachments (photos)
2. Thêm real-time VSVX check API
3. Thêm auto-save draft
4. Thêm duplicate order feature
5. Migrate edit order page theo cùng pattern
