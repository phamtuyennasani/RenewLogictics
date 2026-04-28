# Phân Tích Logic Tạo Order - Hệ Thống Cũ & Đề Xuất Hệ Thống Mới

## 1. TỔNG QUAN LUỒNG NGHIỆP VỤ HỆ THỐNG CŨ

### 1.1 Controller chính: `OrderController@index` + `OrderController@submit`

**Luồng tạo order gồm 2 bước:**
- `index()` → Load trang form tạo order (GET)
- `submit()` → Xử lý submit form (POST)

### 1.2 Data được load khi mở trang tạo order

```
OrderController@index:
├── listSale          → Users có role SALE
├── listCity          → Tỉnh/thành phố (ProvinceModel)
├── listCountry       → Quốc gia (NewsModel type='country')
├── dataDichvu        → Query gộp nhiều type:
│   ├── listDichVu           → type='dich-vu'
│   ├── dichvuChitiet        → type='dich-vu-chi-tiet'
│   ├── chinhanhNhanhang     → type='chi-nhanh-nhan-hang'
│   ├── daily                → type='dai-ly'
│   ├── hangbay              → type='hang-bay'
│   └── doitacchungchuyen    → type='doi-tac-chung-chuyen'
└── Các type khác: dich-vu-di-kem, tinh-trang-don, loai-buu-gui, ly-do-gui-hang, 
    hinh-thuc-gui-hang, delivery-term, hinh-thuc-van-chuyen
```

### 1.3 Form Submit - Cấu trúc dữ liệu gửi lên

Form `create-order-form` submit bằng Alpine.js (`@submit.prevent="submitForm"`), gửi các nhóm data:

```
POST order.submit:
├── data[id_sale]         → ID nhân viên Sale
├── data[id_ctv]          → ID Cộng tác viên (auto nếu user là CTV)
├── data[id_customer]     → ID Khách hàng
├── data[ghichu]          → Ghi chú
│
├── sender[sender_id]     → ID người gửi (nếu chọn từ danh sách)
├── sender[type]          → 'ctv' hoặc 'sender'
├── sender[company]       → Tên công ty
├── sender[fullname]      → Tên người gửi
├── sender[phone]         → SĐT
├── sender[email]         → Email
├── sender[address]       → Địa chỉ
├── sender[province_id]   → Tỉnh/TP
├── sender[ward_id]       → Phường/xã
│
├── dichvu[dichvu]             → Dịch vụ chính
├── dichvu[dichvu_chitiet]     → Dịch vụ chi tiết
├── dichvu[chinhanhnhanhang]   → Chi nhánh nhận hàng
├── dichvu[hangbay]            → Hãng bay
├── dichvu[loaibuugui]         → Loại bưu gửi
├── dichvu[lydoguihang]        → Lý do gửi hàng
├── dichvu[hinhthucguihang]    → Hình thức gửi hàng
├── dichvu[deliveryterm]       → Delivery term
├── dichvu[hinhthucvanchuyen]  → Hình thức vận chuyển
├── dichvu[dichvudikem][]      → Dịch vụ đi kèm (checkbox)
├── dichvu[tinhtrangdon][]     → Tình trạng đơn (checkbox)
├── dichvu[tensanpham]         → Tên sản phẩm
├── dichvu[hangdevo]           → Hàng dễ vỡ (checkbox)
│
├── receiver[company]     → Tên công ty nhận
├── receiver[tenlienhe]   → Tên người nhận
├── receiver[phone]       → SĐT
├── receiver[email]       → Email
├── receiver[address]     → Địa chỉ
├── receiver[country_id]  → Quốc gia
├── receiver[mavung]      → Mã vùng
├── receiver[state]       → Tỉnh/Bang
├── receiver[city]        → Thành phố
├── receiver[postcode]    → Mã bưu điện
├── receiver[vsvx]        → Vùng sâu vùng xa (0/1)
│
├── package[0][length]    → Dài (cm)
├── package[0][width]     → Rộng (cm)
├── package[0][height]    → Cao (cm)
├── package[0][g_weight]  → Cân nặng thực (kg)
├── package[N][...]       → N kiện hàng
│
├── ghichu[noidung]       → Ghi chú nội bộ
├── files[]               → Ảnh đính kèm
├── saveinfosender        → Checkbox lưu thông tin người gửi
└── saveinforeceiver      → Checkbox lưu thông tin người nhận
```

### 1.4 Logic xử lý Submit (`OrderController@submit`)

```
1. Thu thập input: data, sender, dichvu, receiver, ghichu, package, files
2. Nếu user là CTV → tự gán data[id_ctv]
3. Load thông tin CTV (UserModel) → lấy DIM
4. Tính cân nặng quy đổi cho từng package:
   - v_weight = L × W × H / DIM
   - c_weight = max(v_weight, g_weight)
   - Nếu < 21kg: làm tròn lên 0.5kg
   - Nếu ≥ 21kg: làm tròn lên 1kg
5. Tạo Order (OrdersModel::create) với:
   - bill_status = MOI_TAO
   - payment_status = CHUA_THANH_TOAN
   - payment_status_ncc = CHUA_THANH_TOAN_NCC
   - id_bill = auto-generate code
   - id_create = current user
6. Tạo packages cho order
7. Upload photos
8. Lưu sender/receiver (nếu checked)
9. Tạo ghi chú nội bộ
10. Tạo 2 history entries:
    - Entry 1: MOI_TAO
    - Entry 2: DA_XAC_NHAN (sau 2 phút)
11. Update bill_status = DA_XAC_NHAN
12. Redirect → trang view order
```

### 1.5 AJAX APIs phụ trợ (ApiOrder)

Trang tạo order cũ dùng nhiều AJAX call qua `ApiOrder`:

| API Method | Mục đích |
|---|---|
| `get-ctv-by-sale` | Load danh sách CTV theo Sale |
| `getInfoCTV` | Load thông tin chi tiết CTV (tên, ĐT, địa chỉ) |
| `get-receiver` | Load danh sách người nhận theo CTV/khách hàng |
| `get-ward-by-province` | Load phường/xã theo tỉnh |
| `check-vsvx` | Kiểm tra vùng sâu vùng xa theo postcode |

### 1.6 View Templates cũ (Alpine.js + Blade partials)

```
src/Views/templates/order/
├── create.blade.php           → Layout chính, Alpine x-data="createOrder()"
├── sender.blade.php           → Form người gửi (Alpine x-model)
├── receiver.blade.php         → Form người nhận
├── dichvu.blade.php           → Chọn dịch vụ, dịch vụ đi kèm
├── hinhthucguihang.blade.php  → Radio buttons: loại bưu gửi, lý do, hình thức
├── yeucauguihang.blade.php    → Checkbox tình trạng đơn
├── package.blade.php          → Bảng kiện hàng (thêm/xóa row)
└── noidungdinhkem.blade.php   → Ghi chú + hàng dễ vỡ + upload ảnh
```

---

## 2. VẤN ĐỀ CỦA HỆ THỐNG CŨ

| # | Vấn đề | Mức độ |
|---|--------|--------|
| 1 | **God Controller** – `submit()` ~100 dòng xử lý mọi thứ: validation, tính toán, tạo record, upload, lưu sender/receiver, tạo history | Cao |
| 2 | **God API** – `ApiOrder` 1497 dòng, 30+ method trong 1 class, routing bằng `match()` thủ công | Cao |
| 3 | **Alpine.js monolith** – Toàn bộ state nằm trong 1 object `createOrder()` rất lớn | Cao |
| 4 | **Không validation server-side** – Không có FormRequest, không validate trước khi create | Cao |
| 5 | **AJAX scattered** – Mỗi tương tác (chọn sale→load CTV, chọn CTV→load sender) là 1 fetch() riêng | Trung bình |
| 6 | **Duplicate logic** – Tính cân nặng, clean array, update user role assignment lặp nhiều nơi | Trung bình |
| 7 | **Không có transaction** – Tạo order + packages + photos + history không wrap trong DB transaction | Cao |
| 8 | **Hard-coded constants** – MOI_TAO, DA_XAC_NHAN... là constants toàn cục, khó trace | Thấp |

---

## 3. ĐỀ XUẤT KIẾN TRÚC MỚI: LIVEWIRE PAGE COMPONENT + NESTING COMPONENTS

### 3.1 Tổng quan kiến trúc

```
┌─────────────────────────────────────────────────────────────┐
│  Livewire Full-Page Component: CreateOrder                  │
│  (Route: /order/create)                                     │
│  Vai trò: Orchestrator - giữ state chính, điều phối submit │
│                                                             │
│  ┌────────────────────┐  ┌────────────────────────────┐     │
│  │ Nested Component:  │  │ Nested Component:           │     │
│  │ SaleSelector       │  │ SenderSection               │     │
│  │ - Chọn Sale        │  │ - Chọn/nhập người gửi      │     │
│  │ - Chọn CTV         │  │ - Autocomplete từ DB        │     │
│  │ - Chọn Khách hàng  │  │ - Province → Ward cascade   │     │
│  │ - Emit: selected   │  │ - Emit: sender-updated      │     │
│  └────────────────────┘  └────────────────────────────────┘  │
│                                                             │
│  ┌────────────────────┐  ┌────────────────────────────┐     │
│  │ Nested Component:  │  │ Nested Component:           │     │
│  │ ServiceSection     │  │ ReceiverSection              │     │
│  │ - Dịch vụ chính    │  │ - Thông tin người nhận      │     │
│  │ - Chi nhánh        │  │ - Quốc gia, postcode        │     │
│  │ - Hãng bay         │  │ - Check VSVX                │     │
│  │ - Loại bưu gửi    │  │ - Emit: receiver-updated     │     │
│  │ - Delivery term    │  │                              │     │
│  │ - Dịch vụ đi kèm  │  └────────────────────────────┘     │
│  │ - Emit: updated    │                                      │
│  └────────────────────┘                                      │
│                                                             │
│  ┌────────────────────────────────────────────────────┐     │
│  │ Nested Component: PackageSection                    │     │
│  │ - Bảng kiện hàng (thêm/xóa row)                   │     │
│  │ - Tính v_weight, c_weight realtime                 │     │
│  │ - Emit: packages-updated                           │     │
│  └────────────────────────────────────────────────────┘     │
│                                                             │
│  ┌────────────────────────────────────────────────────┐     │
│  │ Nested Component: AttachmentSection                 │     │
│  │ - Upload ảnh (Livewire file upload)                │     │
│  │ - Ghi chú đơn hàng                                │     │
│  │ - Checkbox: hàng dễ vỡ, lưu sender/receiver       │     │
│  │ - Emit: attachments-updated                        │     │
│  └────────────────────────────────────────────────────┘     │
│                                                             │
│  [═══════════ NÚT TẠO ĐƠN HÀNG ═══════════]               │
│  → Parent gọi Action: CreateOrderAction                     │
└─────────────────────────────────────────────────────────────┘
```

### 3.2 File Structure

```
hethong-laravel/
├── app/
│   ├── Livewire/
│   │   └── Order/
│   │       ├── CreateOrder.php              ← Full-page component (orchestrator)
│   │       └── Components/
│   │           ├── SaleSelector.php          ← Nested: chọn Sale/CTV/KH
│   │           ├── SenderSection.php         ← Nested: thông tin người gửi
│   │           ├── ReceiverSection.php        ← Nested: thông tin người nhận
│   │           ├── ServiceSection.php         ← Nested: dịch vụ
│   │           ├── PackageSection.php         ← Nested: kiện hàng
│   │           └── AttachmentSection.php      ← Nested: ảnh + ghi chú
│   │
│   ├── Actions/
│   │   └── Order/
│   │       ├── CreateOrderAction.php         ← Business logic tạo order
│   │       ├── GenerateOrderCodeAction.php   ← Sinh mã đơn hàng
│   │       ├── CalculatePackageWeightAction.php ← Tính cân nặng
│   │       ├── SaveSenderAction.php          ← Lưu info người gửi
│   │       └── SaveReceiverAction.php        ← Lưu info người nhận
│   │
│   ├── DTOs/
│   │   └── Order/
│   │       ├── CreateOrderDTO.php            ← Data Transfer Object
│   │       ├── SenderDTO.php
│   │       ├── ReceiverDTO.php
│   │       └── PackageDTO.php
│   │
│   └── Enums/
│       └── Order/
│           ├── BillStatus.php                ← MOI_TAO, DA_XAC_NHAN...
│           └── PaymentStatus.php             ← CHUA_THANH_TOAN...
│
├── resources/views/
│   ├── pages/order/
│   │   └── ⚡create.blade.php                ← Full-page layout
│   └── livewire/order/
│       ├── create-order.blade.php             ← View chính
│       └── components/
│           ├── sale-selector.blade.php
│           ├── sender-section.blade.php
│           ├── receiver-section.blade.php
│           ├── service-section.blade.php
│           ├── package-section.blade.php
│           └── attachment-section.blade.php
```

### 3.3 Chi tiết từng Component

#### 3.3.1 `CreateOrder` (Full-Page Component - Orchestrator)

```php
class CreateOrder extends Component
{
    // === State tổng hợp từ nested components ===
    public ?int $saleId = null;
    public ?int $ctvId = null;
    public ?int $customerId = null;
    
    // === Listeners từ nested components ===
    #[On('sale-selected')]
    public function onSaleSelected($saleId, $ctvId, $customerId) { ... }
    
    #[On('sender-updated')]
    public function onSenderUpdated($senderData) { ... }
    
    #[On('receiver-updated')]
    public function onReceiverUpdated($receiverData) { ... }
    
    #[On('service-updated')]
    public function onServiceUpdated($serviceData) { ... }
    
    #[On('packages-updated')]
    public function onPackagesUpdated($packages) { ... }
    
    // === Submit chính ===
    public function submit()
    {
        // 1. Validate tổng hợp
        // 2. Gọi CreateOrderAction::execute($dto)
        // 3. Redirect to view order
    }
}
```

#### 3.3.2 `SaleSelector` (Nested)

```php
class SaleSelector extends Component
{
    public ?int $saleId = null;
    public ?int $ctvId = null;
    public ?int $customerId = null;
    public Collection $listSale;
    public Collection $listCtv;
    public Collection $listCustomer;
    
    public function updatedSaleId($value)
    {
        // Load CTV theo Sale → thay thế AJAX 'get-ctv-by-sale'
        $this->listCtv = User::where('id_sale', $value)->get();
        $this->dispatch('sale-selected', ...);
    }
    
    public function updatedCtvId($value)
    {
        // Load khách hàng theo CTV → thay thế AJAX 'get-khachhang-by-ctv'
        $this->listCustomer = Member::where('id_ctv', $value)->get();
        $this->dispatch('sale-selected', ...);
    }
}
```

#### 3.3.3 `SenderSection` (Nested)

```php
class SenderSection extends Component
{
    public string $company = '';
    public string $fullname = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public ?int $provinceId = null;
    public ?int $wardId = null;
    public bool $saveSender = false;
    
    public Collection $provinces;
    public Collection $wards;
    
    public function updatedProvinceId($value)
    {
        // Cascade load wards → thay thế AJAX 'get-ward-by-province'
        $this->wards = Ward::where('id_province', $value)->get();
    }
    
    // Khi chọn sender từ danh sách → fill form
    public function selectSender($senderId) { ... }
    
    public function updated($property)
    {
        $this->dispatch('sender-updated', $this->toArray());
    }
}
```

#### 3.3.4 `ReceiverSection` (Nested)

```php
class ReceiverSection extends Component
{
    public string $company = '';
    public string $tenlienhe = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public ?int $countryId = null;
    public string $mavung = '';
    public string $state = '';
    public string $city = '';
    public string $postcode = '';
    public bool $isVsvx = false;
    public bool $saveReceiver = false;
    
    public function updatedPostcode($value)
    {
        // Check VSVX → thay thế AJAX 'check-vsvx'
        $this->isVsvx = Vsvx::where('postcode', $value)->exists();
    }
    
    // Khi chọn receiver từ danh sách
    public function selectReceiver($receiverId) { ... }
}
```

#### 3.3.5 `ServiceSection` (Nested)

```php
class ServiceSection extends Component
{
    public ?int $dichvuId = null;
    public ?int $dichvuChitietId = null;
    public ?int $chinhanhId = null;
    public ?int $hangbayId = null;
    public ?int $loaiBuuGuiId = null;
    public ?int $lyDoGuiHangId = null;
    public ?int $hinhThucGuiHangId = null;
    public ?int $deliveryTermId = null;
    public ?int $hinhThucVanChuyenId = null;
    public array $dichVuDiKem = [];
    public array $tinhTrangDon = [];
    
    // Data loaded once
    public Collection $listDichVu;
    public Collection $listDichVuChiTiet;
    public Collection $listChiNhanh;
    // ... etc
}
```

#### 3.3.6 `PackageSection` (Nested)

```php
class PackageSection extends Component
{
    public array $packages = [];
    public float $dim = 5000; // Default DIM
    
    // Nhận DIM từ CTV khi parent dispatch
    #[On('dim-updated')]
    public function updateDim($dim) { $this->dim = $dim; }
    
    public function addPackage()
    {
        $this->packages[] = [
            'length' => 0, 'width' => 0, 'height' => 0,
            'g_weight' => 0, 'v_weight' => 0, 'c_weight' => 0,
        ];
    }
    
    public function removePackage($index)
    {
        unset($this->packages[$index]);
        $this->packages = array_values($this->packages);
    }
    
    public function updatedPackages()
    {
        // Tính lại v_weight, c_weight cho mỗi package
        foreach ($this->packages as &$pkg) {
            $pkg = CalculatePackageWeightAction::execute($pkg, $this->dim);
        }
        $this->dispatch('packages-updated', $this->packages);
    }
}
```

#### 3.3.7 `AttachmentSection` (Nested)

```php
class AttachmentSection extends Component
{
    public array $photos = [];     // Livewire TemporaryUploadedFile
    public string $ghichu = '';
    public bool $hangDeVo = false;
    
    // Sử dụng WithFileUploads trait
    use WithFileUploads;
}
```

### 3.4 Action Class: `CreateOrderAction`

```php
class CreateOrderAction
{
    public static function execute(CreateOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            // 1. Tạo Order
            $order = Order::create([
                'id_bill'            => GenerateOrderCodeAction::execute(),
                'id_sale'            => $dto->saleId ?: 0,
                'id_ctv'             => $dto->ctvId ?: 0,
                'id_customer'        => $dto->customerId ?: 0,
                'info_sender'        => $dto->sender,
                'info_receiver'      => $dto->receiver,
                'dichvu'             => $dto->service,
                'bill_status'        => BillStatus::DA_XAC_NHAN,
                'payment_status'     => PaymentStatus::CHUA_THANH_TOAN,
                'payment_status_ncc' => PaymentStatus::CHUA_THANH_TOAN_NCC,
                'dim'                => $dto->dim,
                'id_create'          => auth()->id(),
                'info_ctv'           => $dto->infoCTV,
            ]);
            
            // 2. Tạo Packages
            foreach ($dto->packages as $pkg) {
                $calculated = CalculatePackageWeightAction::execute($pkg, $dto->dim);
                $order->packages()->create($calculated);
            }
            
            // 3. Upload Photos
            foreach ($dto->photos as $photo) {
                // Upload & create photo record
            }
            
            // 4. Lưu sender/receiver nếu cần
            if ($dto->saveSender) {
                SaveSenderAction::execute($order);
            }
            if ($dto->saveReceiver) {
                SaveReceiverAction::execute($order);
            }
            
            // 5. Tạo ghi chú
            if (!empty($dto->ghichu)) {
                $order->notes()->create([...]);
            }
            
            // 6. Tạo history
            $order->history()->create([
                'thoigian' => now(),
                'trangthai' => $order->status->display_name,
                'main' => 1,
            ]);
            
            return $order;
        });
    }
}
```

### 3.5 Communication Flow giữa các Components

```
User chọn Sale
    └→ SaleSelector::updatedSaleId()
        ├→ Load CTV (server-side, không cần AJAX)
        └→ dispatch('sale-selected', saleId, ctvId)
            └→ CreateOrder::onSaleSelected()
                └→ dispatch('dim-updated', dim) → PackageSection

User chọn CTV  
    └→ SaleSelector::updatedCtvId()
        ├→ Load danh sách khách hàng
        ├→ Load DIM từ CTV
        └→ dispatch → CreateOrder → dispatch('dim-updated') → PackageSection
        └→ dispatch → SenderSection (fill sender info)

User nhập postcode
    └→ ReceiverSection::updatedPostcode()
        └→ Check VSVX (server-side query)
        └→ Hiển thị warning nếu VSVX

User nhấn "Tạo đơn hàng"
    └→ CreateOrder::submit()
        ├→ Collect data từ tất cả nested components
        ├→ Validate tổng hợp
        ├→ CreateOrderAction::execute(DTO)
        └→ redirect('/order/{id}')
```

---

## 4. LỢI ÍCH CỦA KIẾN TRÚC MỚI

| Tiêu chí | Hệ thống cũ | Hệ thống mới (Livewire) |
|---|---|---|
| **Tương tác cascade** | AJAX fetch() thủ công | Livewire `updated*()` hooks, tự động |
| **State management** | 1 Alpine object khổng lồ | Mỗi component giữ state riêng |
| **Validation** | Không có server-side | FormRequest / `$rules` per component |
| **File upload** | FormData thủ công | `WithFileUploads` trait |
| **Business logic** | Nằm trong Controller | Tách ra Action classes |
| **Testability** | Gần như không test được | Unit test Action, Feature test Component |
| **Code reuse** | Copy-paste giữa create/edit | Nested component dùng chung |
| **DB Safety** | Không transaction | `DB::transaction()` |
| **Maintainability** | 1 file 1497 dòng | Mỗi file < 150 dòng |

---

## 5. THỨ TỰ TRIỂN KHAI ĐỀ XUẤT

1. **Phase 1**: Tạo Enums (BillStatus, PaymentStatus) + DTOs
2. **Phase 2**: Tạo Action classes (CreateOrderAction, CalculatePackageWeight...)
3. **Phase 3**: Tạo nested components (SaleSelector → SenderSection → ReceiverSection → ServiceSection → PackageSection → AttachmentSection)
4. **Phase 4**: Tạo CreateOrder full-page component (orchestrator)
5. **Phase 5**: Tạo views cho từng component
6. **Phase 6**: Testing & QA

---

## 6. GHI CHÚ KỸ THUẬT

### 6.1 Livewire Nesting Best Practices
- Nested components nên **emit events lên parent**, không trực tiếp thay đổi state của nhau
- Sử dụng `#[On('event-name')]` để lắng nghe
- Tránh truyền quá nhiều data qua props, ưu tiên ID rồi query trong `mount()`
- Dùng `wire:model.live` cho các field cần realtime (postcode check, weight calc)
- Dùng `wire:model.blur` cho các field thông thường (giảm request)

### 6.2 Performance
- Dùng `#[Computed]` cho derived data (tổng cân nặng, tổng kiện...)
- Dùng `#[Lazy]` loading cho ServiceSection (ít khi thay đổi)
- Cache danh sách tỉnh/thành, quốc gia (ít thay đổi)

### 6.3 Tương thích với hệ thống hiện tại
- Giữ nguyên cấu trúc DB (orders, order_packages, order_photos...)
- Giữ nguyên mã đơn hàng (GenerateOrderCodeAction đã có)
- Constants cũ (MOI_TAO, DA_XAC_NHAN...) map sang Enums