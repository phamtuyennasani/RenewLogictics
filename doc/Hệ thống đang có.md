# HỆ THỐNG QUẢN LÝ VẬN TẢI & GIAO HÀNG — TÀI LIỆU PHÂN TÍCH LOGIC

> **Phiên bản:** NINA Framework 2.0 (PHP 8.2)
> **Ngày phân tích:** 2026-04-11
> **Tác giả:** Claude Code Agent
> **Mục đích:** Tài liệu hướng dẫn rewrite Laravel

---

## MỤC LỤC

1. [Tổng quan hệ thống](#1-tổng-quan-hệ-thống)
2. [Database Schema](#2-database-schema)
3. [Phân quyền & Xác thực](#3-phân-quyền--xác-thực)
4. [Module Đơn hàng](#4-module-đơn-hàng)
5. [Module Lấy hàng (Pickup)](#5-module-lấy-hàng-pickup)
6. [Module Lô hàng (Package)](#6-module-lô-hàng-package)
7. [Module Công nợ](#7-module-công-nợ)
8. [Module Tracking](#8-module-tracking)
9. [Module Báo cáo](#9-module-báo-cáo)
10. [Tính toán tài chính](#10-tính-toán-tài-chính)
11. [Tích hợp bên ngoài](#11-tích-hợp-bên-ngoài)
12. [Bảo mật & Rủi ro](#12-bảo-mật--rủi-ro)

---

## 1. TỔNG QUAN HỆ THỐNG

### 1.1 Kiến trúc

```
NINA Framework (Custom PHP)
├── NINACORE\DatabaseCore\Eloquent   ← ORM tự viết (giống Eloquent)
├── Pecee\SimpleRouter               ← Router HTTP
├── PhpSpreadsheet                    ← Export Excel
├── Intervention Image               ← Xử lý ảnh
├── Custom Auth (2 guards)          ← auth:admin, auth:member
└── Custom Permission (NINAPermission)
```

### 1.2 Các guard đăng nhập

| Guard | Model | Table | Mục đích |
|-------|-------|-------|----------|
| `admin` | `UserModel` | `table_user` | Nhân viên nội bộ |
| `member` | `MemberModel` | `table_member` | Khách hàng công khai |

### 1.3 Cấu trúc routing

```
/login, /login-submit         → NotLogin middleware
/api/{method?}                → Login middleware (POST/GET)
/api-order/{method?}          → Login middleware
/cong-no-dai-ly/{method?}     → Login + CongNoDaiLy middleware
/cong-no/{method?}            → Login + CongNo middleware
/tai-hang/{method?}           → Login middleware
/tracking/{idbill}            → TrackingAPI middleware (công khai)
/                              → ManagerController → dashboard
```

### 1.4 Danh sách controllers

| Controller | Dòng | Mục đích |
|------------|------|----------|
| `UserController` | ~150 | Đăng nhập/đăng xuất |
| `ManagerController` | ~100 | Dashboard tổng quan |
| `OrderController` | ~800 | Tạo đơn, form, thanh toán |
| `ApiController` | 1,537 | API vạn năng (20 methods) |
| `ApiOrder` | 1,497 | API đơn hàng (32+ methods) |
| `CongNoController` | ~600 | Công nợ khách hàng |
| `CongNoDaiLyController` | ~550 | Công nợ đại lý (NCC) |
| `PackagesController` | ~500 | Quản lý lô hàng |
| `PickupController` | ~700 | Quản lý lấy hàng |
| `ScanController` | ~400 | Quét barcode |
| `KiemHangController` | ~200 | Kiểm hàng |
| `PrintController` | ~150 | In bill/label |
| `TrackingController` | ~300 | Trang tracking công khai |
| `ThongKeController` | ~500 | Báo cáo thống kê |
| `AccountController` | ~100 | Hồ sơ người dùng |
| `DuLieuController` | ~500 | Quản lý dữ liệu tham chiếu |
| `NhanSuController` | ~400 | Quản lý nhân sự |
| `CTVController` | ~300 | Quản lý cộng tác viên |
| `KhachHangController` | ~300 | Quản lý khách hàng |
| `PhotoController` | ~200 | Quản lý album ảnh |
| `SettingController` | ~100 | Cài đặt |
| `StaticController` | ~100 | Trang tĩnh |
| `PlaceController` | ~200 | Địa lý (tỉnh, phường) |

---

## 2. DATABASE SCHEMA

### 2.1 Qui tắc đặt tên

- **Prefix:** Tất cả table có prefix `table_` (config: `config/database.php`)
- **Model:** Property `$table` trong model bỏ prefix (ví dụ: `$table = 'orders'` → table thực: `table_orders`)
- **Engine:** InnoDB, charset `utf8mb4_unicode_ci`
- **Timestamps:** Mặc định có `created_at`, `updated_at`

### 2.2 Danh sách bảng

#### Bảng cốt lõi

| Table | Model | Mô tả |
|-------|-------|-------|
| `table_orders` | `OrdersModel` | Đơn hàng chính |
| `table_user` | `UserModel`, `CongTacVienModel` | Nhân viên + CTV (dùng chung!) |
| `table_member` | `MemberModel` | Tài khoản khách hàng |
| `table_news` | `NewsModel` | Bảng lookup đa năng |
| `table_setting` | `SettingModel` | Cài đặt hệ thống |

#### Bảng đơn hàng con

| Table | Model | Mô tả |
|-------|-------|-------|
| `table_order_package` | `OrderPackageModel` | Package ban đầu |
| `table_order_package_thucte` | `OrderPackageThucTeModel` | Package thực tế |
| `table_order_package_daily` | `OrderPackageDaiLyModel` | Package đại lý |
| `table_order_notes` | `OrderNoteModel` | Ghi chú đơn |
| `table_order_photo` | `OrderPhotoModel` | Ảnh ban đầu |
| `table_order_photo_thucte` | `OrderPhotoThucTeModel` | Ảnh thực tế |
| `table_order_photo_xuatkho` | `OrderPhotoXuatKhoModel` | Ảnh xuất kho |
| `table_order_photo_pickup` | `OrderPhotoPickupModel` | Ảnh pickup |
| `table_order_history` | `OrderHistoryModel` | Lịch sử trạng thái |
| `table_order_action` | `OrderActionModel` | Log hành động |

#### Bảng công nợ

| Table | Model | Mô tả |
|-------|-------|-------|
| `table_congno` | `CongNoModel` | Công nợ CTV |
| `table_congno_detail` | `CongNoDetail` | Pivot: công nợ ↔ đơn |
| `table_congno_daily` | `CongNoDaiLy` | Công nợ đại lý (NCC) |
| `table_congno_daily_detail` | `CongNoDaiLyDetail` | Pivot: công nợ NCC ↔ đơn |

#### Bảng logistics

| Table | Model | Mô tả |
|-------|-------|-------|
| `table_packages` | `PackagesModel` | Lô hàng |
| `table_packages_detail` | `PackagesDetailModel` | Pivot: lô ↔ đơn |
| `table_pickup` | `PickupModel` | Yêu cầu lấy hàng |
| `table_vsvx` | `VSVXModel` | Mã VSVX quốc tế |

#### Bảng tham chiếu

| Table | Model | Mô tả |
|-------|-------|-------|
| `table_province` | `ProvinceModel` | Tỉnh/thành |
| `table_wards` | `WardModel` | Phường/xã |
| `table_city` | `CityModel` | Quốc gia/thành phố |

#### Bảng khác

| Table | Model | Mô tả |
|-------|-------|-------|
| `table_extensions` | *(implicit)* | Footer (liên hệ, hotline) |
| `table_static` | `StaticModel` | Trang tĩnh |
| `table_photo` | `PhotoModel` | Album ảnh |
| `table_kiemhang` | `KiemHangModel` | Phiếu kiểm hàng |
| `table_kiemhang_photo` | `KiemHangPhoto` | Ảnh kiểm hàng |
| `table_package_invoices` | `PackageInvoice` | Invoice đơn |
| `table_package_invoices_daily` | `PackageInvoiceDaiLy` | Invoice đại lý |
| `table_package_files` | `PackageFile` | File đính kèm |
| `table_member_log` | `MemberLogModel` | Log khách hàng |
| `table_user_log` | `UserLogModel` | Log nhân viên |
| `table_user_limit` | `UserLimitModel` | Giới hạn nhân viên |

### 2.3 Chi tiết table_orders (QUAN TRỌNG NHẤT)

```sql
table_orders
├── id                          INT PK AUTO_INCREMENT
├── id_bill                     VARCHAR(20)      -- Mã đơn: AVN240511001
├── id_sale                     INT FK → table_user.id
├── id_ctv                      INT FK → table_user.id
├── id_ketoan                   INT FK → table_user.id
├── id_manager                  INT FK → table_user.id
├── id_cs                       INT FK → table_user.id
├── id_ops                      INT FK → table_user.id
├── id_customer                 STRING (UUID)    -- FK → table_member.uuid
├── bill_status                 INT FK → table_news.id
├── pickup_status               INT FK → table_news.id
├── payment_status              INT FK → table_news.id
├── payment_status_ncc          INT FK → table_news.id
├── dichvu                      JSON             -- Dịch vụ
├── info_receiver               JSON             -- Thông tin người nhận
├── info_sender                 JSON             -- Thông tin người gửi
├── info_pickup                 JSON             -- Thông tin pickup
├── info_ctv                    JSON             -- Thông tin CTV
├── last_update                 JSON
├── last_update_info            JSON
├── last_update_dichvu          JSON
├── last_update_pickup          JSON
├── payment                     JSON             -- Thông tin thanh toán
├── cannangg                    DECIMAL          -- Cân nặng gốc
├── cannangv                    DECIMAL          -- Cân nặng volumetric
├── cannangc                    DECIMAL          -- Cân nặng tính phí
├── cannangcdaily               DECIMAL          -- Cân nặng đại lý
├── re_weight                   DECIMAL          -- Cân nặng thực tế
├── totalpricegiaban             DECIMAL          -- Giá bán tổng
├── totalpricegiavon             DECIMAL          -- Giá vốn tổng
├── dongiaban                   DECIMAL          -- Đơn giá bán
├── dongiavon                   DECIMAL          -- Đơn giá vốn
├── tongcuocban                  DECIMAL          -- Tổng cước bán
├── ppxdgiaban                  DECIMAL          -- PPX giá bán
├── ppxdgiavon                  DECIMAL          -- PPX giá vốn
├── phuphigiaban                DECIMAL          -- Phụ phí bán
├── vatgiaban                   DECIMAL          -- VAT bán
├── allhoahong                  DECIMAL          -- Hoa hồng
├── sumCanNang                  DECIMAL          -- Tổng cân
├── created_at                  TIMESTAMP
└── updated_at                  TIMESTAMP
```

### 2.4 Chi tiết table_user

```sql
table_user
├── id                          INT PK AUTO_INCREMENT
├── email                       VARCHAR          -- Tài khoản đăng nhập
├── password                    VARCHAR          -- bcrypt hash
├── fullname                    VARCHAR
├── code                        VARCHAR          -- Mã nhân viên: CTV001, SALE001...
├── role                        VARCHAR          -- admin, manager, ketoan, cs, sale, ops, ctv, shipper
├── id_permission               INT              -- FK → bảng permission cũ
├── id_province                 INT FK → table_province.id
├── id_ward                     INT FK → table_wards.id
├── address                     VARCHAR
├── phone                       VARCHAR
├── photo                       VARCHAR          -- Avatar filename
├── id_sale                     INT FK → table_user.id (CTV dưới quyền sale)
├── status                      VARCHAR          -- hienthi / an
├── lastlogin                   TIMESTAMP
├── user_token                  VARCHAR
├── login_session               TEXT
├── secret_key                  VARCHAR          -- Mã bí mật
├── confirm_code                VARCHAR
├── remember_token              VARCHAR
├── options                     JSON
├── created_at                  TIMESTAMP
└── updated_at                  TIMESTAMP
```

### 2.5 Chi tiết table_news (BẢNG POLYMORPHIC)

Bảng này dùng cho **rất nhiều loại dữ liệu**, phân biệt qua cột `type` và `id_list/id_cat/id_item/id_sub`:

| Loại dữ liệu | Type | Ví dụ giá trị |
|-------------|------|--------------|
| Trạng thái đơn | `bill_status` | Đã xác nhận, Đang giao, Đã giao, Hủy |
| Trạng thái pickup | `pickup_status` | Chưa nhận, Đang lấy, Đã lấy |
| Trạng thái thanh toán KH | `payment_status` | Chưa thanh toán, Đã thanh toán |
| Trạng thái thanh toán NCC | `payment_status_ncc` | Chưa thanh toán NCC, Đã thanh toán NCC |
| Chi nhánh nhận hàng | `chinhanhnhanhang` | CN HCM, CN HN |
| Dịch vụ | `dichvu` | Hàng không, Hàng hải |
| Dịch vụ chi tiết | `dichvuchitiet` | Economy, Express |
| Loại bưu gửi | `loaibuugui` | Hàng nhẹ, Hàng nặng |
| Hình thức gửi | `hinhthucguihang` | Gửi hộ, Gửi hộ có khai giá |
| Lý do gửi | `lydoguihang` | Hàng thử, Bán hàng, Quà tặng |
| Hãng bay | `hangbay` | Vietnam Airlines, VietJet |
| Đối tác vận chuyển | `doitacvanchuyen` | DHL, FedEx |
| Đại lý (NCC) | `daily` | Đại lý A, Đại lý B |
| Quốc gia | `quocgia` | Mỹ, Canada, Úc |
| Delivery term | `deliveryterm` | DAP, DDP, FOB |
| Phụ phí mua | `phuphimua` | Phí xăng dầu, Phí lưu kho |
| Phụ phí bán | `phuphiban` | Phí bảo hiểm, Phí xử lý |
| Tỷ giá | `tygia` | USD/VND, EUR/VND |
| Thông báo | `thongbao` | Nội bộ, Khách hàng |
| VSVX | `vsvx` | Mã VSVX quốc tế |

### 2.6 JSON columns trong orders

```php
// dichvu: { id_dichvu, id_daily, loaibuugui, hinhthucguihang, lydoguihang,
//            deliveryterm, dichvudikem, tinhtrangdon, id_hangbay, id_doitacchungchuyen }
// info_receiver: { name, phone, address, country_id, province_id, ward_id }
// info_sender: { name, phone, address, province_id, ward_id }
// info_pickup: { phuongtien, chinhanhnhanhang, ngaylayhang, giaylayhang }
// info_ctv: { ... }
// payment: { loinhuan, ty_suat, tong_cuoc, tong_vat, tong_hhkd, con_lai }
// last_update_*: { user_id, timestamp, note }
```

---

## 3. PHÂN QUYỀN & XÁC THỰC

### 3.1 Role Hierarchy

```
ADMIN > MANAGER > KETOAN > OPS > CS > SALE > CTV > SHIPPER
```

**Role constants:**

| Constant | Giá trị | Mô tả |
|----------|---------|-------|
| `ADMIN` | `admin` | Toàn quyền, bypass tất cả kiểm tra |
| `MANAGER` | `manager` | Quản lý |
| `KETOAN` | `ketoan` | Kế toán |
| `CS` | `cs` | Chăm sóc khách hàng |
| `SALE` | `sale` | Kinh doanh |
| `OPS` | `ops` | Vận hành |
| `CTV` | `ctv` | Cộng tác viên |
| `SHIPPER` | `shipper` | Shipper/lái xe |

### 3.2 Permission System (NINAPermission)

**Format quyền:** `.{module}.{type}.{act}`
- Ví dụ: `product.san-pham.man`, `orders.shipping.view`

**Pivot tables:**
```
user_has_roles          -- User → Role (many-to-many)
role_has_permissions    -- Role → Permission (many-to-many)
user_has_permissions    -- User → Permission (many-to-many) direct grant
```

**Middleware kiểm tra:**

| Middleware | Role được phép |
|-----------|---------------|
| `LoginAdmin` | Tất cả user đã đăng nhập |
| `PermissionAdmin` | Admin bypass, others check permission theo URL |
| `Pickup` | ops, sale, admin, manager, ctv, shipper, cs |
| `CongNo` | ketoan, admin, manager, ctv |
| `CongNoDaiLy` | ketoan, admin, manager |
| `CreateOrder` | admin, cs, sale, ctv |
| `KiemHang` | ops, admin, manager, cs (mobile only) |

### 3.3 Login Flow

```php
// UserController::loginsubmit()
$credentials = ['email' => $email, 'password' => $password];
Auth::guard('admin')->attempt($credentials, $remember);

// Session: set('admin', $user)
// Remember token: {random_60_chars}|{user_id}|{username} → hash → DB remember_token
// Redirect: shipper → /tai-hang, others → /manager
```

### 3.4 User Model Permissions

```php
// UserModel kế thừa HasPermission trait
trait HasPermission {
    hasRole($role): bool
    hasAnyRole([...]): bool
    ableTo($permission): bool
    grantRole($role): void
    allowTo($permission): void
}

// CongTacVienModel cũng kế thừa HasPermission
// nhưng dùng chung table 'user' với UserModel
```

---

## 4. MODULE ĐƠN HÀNG

### 4.1 Quy trình tạo đơn (OrderController::submit)

```
1. Generate order code: AVN{YYMMDD}{NNNN}
   → Lấy ngày hiện tại (YYMMDD)
   → Lấy sequence tiếp theo trong ngày (reset 00:00)
   → Format: AVN240511001

2. Tính volumetric weight cho từng package:
   v_weight = (D × R × C) / dim_factor
   dim_factor = 5000 (mặc định)

3. Tính chargeable weight:
   c_weight = max(v_weight, g_weight)

4. Rounding rules:
   - < 21kg: làm tròn lên 0.5kg (ví dụ: 3.1kg → 3.5kg, 3.6kg → 4.0kg)
   - ≥ 21kg: ceil (3.1kg → 4kg, 21.1kg → 22kg)

5. Tạo records:
   - orders (main)
   - order_package (ban dau) — từng kiện ban đầu
   - order_package_thucte — bản sao chờ cân thực tế
   - order_package_daily — bản sao cho đại lý

6. Set status: MOI_TAO → DA_XAC_NHAN

7. Tạo order_history đầu tiên
```

### 4.2 Order Code Generation

```php
// OrdersModel::generateOrderCode()
$prefix = 'AVN';
$date = date('ymd');  // YYMMDD
$seq = $this->getDailySequence($date);  // 001, 002...
return $prefix . $date . str_pad($seq, 3, '0', STR_PAD_LEFT);
// Ví dụ: AVN240511001
```

### 4.3 Trạng thái đơn hàng (FSM)

```
MOI_TAO
    ↓ (submit đơn)
DA_XAC_NHAN
    ↓ (xác nhận lấy hàng)
DA_NHAN_HANG
    ↓ (duyệt xuất hàng)
DUYET_XUAT_HANG
    ↓ (xuất kho)
DANG_PHAT_HANG
    ↓ (giao thành công)
DA_GIAO

Các trạng thái đặc biệt:
├── HUY                (hủy đơn)
├── RETURN_ORDER       (hoàn hàng)
├── CAUTION            (cảnh báo)
├── CUSTOM_RELEASING    (hải quan thông quan)
└── CAP_BEN            (cấp bến)
```

### 4.4 API Endpoints (ApiOrder)

| Method | Logic |
|--------|-------|
| `loadManager()` | DataTable đơn: 25 columns, filter date/status/service/branch/sale/CTV/payment/pickup/kiemhang/congnos/taihang |
| `lockOrder()` | Toggle khóa đơn |
| `getReceiver()` | Thông tin người nhận |
| `getCtvBySale()` | Lấy CTV theo sale |
| `getKhachHangByCtv()` | Lấy KH theo CTV |
| `getWardByProvince()` | Lấy wards theo tỉnh |
| `updateThongTinNguoiGui()` | Upsert sender info |
| `updateThongTinNguoiGuiNhan()` | Upsert receiver info |
| `fetchDataEditThongTinNguoiGui()` | Lấy sender info |
| `updateThongTinDichVu()` | Update dichvu JSON |
| `checkVSVX()` | Validate VSVX international code |
| `fetchDataPackageBanDau()` | Package ban đầu |
| `fetchDataPackageThucTe()` | Package thực tế |
| `fetchDataPackageDaiLy()` | Package đại lý |
| `updateCanNangOrder()` | Update cân nặng (gọi helper updateCanNang) |
| `khaiInvoice()` | Khai báo hải quan |
| `khaiInvoiceDaiLy()` | Khai báo cho đại lý |
| `fetchInvoicePackage()` | Lấy invoice package |
| `updateCanNangXuatKho()` | Cân nặng xuất kho |
| `updatePayment()` | Lưu cuocban/cuocvon/cuocvoncongty, VAT, phuphi, hoahong |
| `fetchGiaBanSale()` | Lấy giá bán cho sale |
| `capNhatGiaSale()` | Cập nhật giá sale |
| `ChotCuocBan()` | Khóa giá bán |
| `updateStatusPayment()` | Batch update payment_status |
| `updateXuatHang()` | Đẩy đơn → DANG_PHAT_HANG |
| `updateHuyDonHang()` | Hủy đơn → HUY |
| `deleteDonHang()` | Hard delete đơn |
| `updateBillStatus()` | Update trạng thái + history |
| `updateTrackingHistory()` | Thêm tracking event |
| `updateTracking()` | Lưu tracking numbers, gọi TrackingMore |
| `loadPhotoTemplate()` | HTML template ảnh |
| `deletePhoto()` | Xóa ảnh |

### 4.5 Payment Calculation

```php
// Cuocban (cước bán) = giá bán cho khách
// Cuocvon (cước vốn) = giá vốn công ty
// Cuocvoncongty = cước vốn công ty (thực tế)

// Công thức:
// tongcuocban = cuoc * cannangcdaily
// ppxdgiaban = phụ phí xuất dây bán
// vatgiaban = (tongcuocban + ppxdgiaban) * 10%
// phuphigiaban = phụ phí khác
// tongpricegiaban = tongcuocban + ppxdgiaban + vatgiaban + phuphigiaban

// allhoahong = hoa hồng CTV/Sale (%)
// loinhuan = (cuocban - cuocvon) * can_nang - hoahong
```

---

## 5. MODULE LẤY HÀNG (PICKUP)

### 5.1 Pickup Code

```
Format: PICK{random_8_chars}
Ví dụ: PICKABCD1234
```

### 5.2 Pickup Status FSM

```
MOI_TAO_PICKUP
    ↓ (CTV tạo pickup request)
PICKUP_CHO_NHAN
    ↓ (shipper xác nhận nhận)
PICKUP_DANG_LAY_HANG
    ↓ (shipper đã lấy hàng)
PICKUP_DA_LAY_HANG
    ↓ (hoàn tất)
DA_CHOT_PICKUP
```

### 5.3 Pickup Logic

```php
// PickupController::createPickUp()
- Code: PICK + 8 random alphanumeric
- id_ctv: tự gán nếu CTV đang login
- info_khachhang: { province_id, ward_id, chinhanhnhanhang, ... }

// PickupController::xacNhanPickUp()
- Gán shipper
- Status → PICKUP_DANG_LAY_HANG

// PickupController::daNhanPickUp()
- Upload ảnh chứng từ
- Status → PICKUP_DA_LAY_HANG
```

### 5.4 Pickup Filter theo Role

```php
// SALE/OPS/SALE: chỉ thấy đơn của mình
// CTV: chỉ thấy đơn của mình
// ADMIN: thấy tất cả
```

---

## 6. MODULE LÔ HÀNG (PACKAGE)

### 6.1 Package Code

```
Format: PKG{random_alphanumeric}
Generated: QR SVG + Barcode SVG → lưu trong DB
```

### 6.2 Package Pivot

```
orders ↔ packages: nhiều-nhiều qua packages_detail
- id_order (FK)
- id_package (FK)
```

### 6.3 Package Status FSM

```
MOI_TAO_PACKAGE
    ↓
DA_CHOT_TAI_HANG     (ADMIN xác nhận)
    ↓
DANG_VAN_CHUYEN      (đang vận chuyển)
    ↓
DA_NHAN_TAI_HANG     (đã nhận)
    ↓
DA_CHOT_TAI_HANG     (hoàn tất)
```

### 6.4 ScanController Logic

```php
// processBarcode(): Check bill_code
// Return codes:
// 00 = không tìm thấy
// 01 = chưa cân thực tế
// 02 = xác nhận nhận hàng
// 05 = chưa cân xuất kho
// 09 = xác nhận gửi hàng

// Quy trình scan:
// 1. Scan → kiểm tra trạng thái
// 2. Cập nhật cân nặng package
// 3. Tính lại cân nặng đơn hàng
// 4. Cập nhật trạng thái nếu cần
```

---

## 7. MODULE CÔNG NỢ

### 7.1 Công nợ CTV (CongNoController)

```
Luồng: Tạo công nợ → Thêm đơn → Chốt → Gửi email → Thanh toán
```

**Tạo công nợ:**

```php
1. Chọn date range (từ ngày - đến ngày)
2. Chọn CTV
3. Lọc các đơn: payment_status = CHUA_THANH_TOAN, id_ctv = CTV đó
4. Tạo congno record
5. Tạo congno_detail cho từng đơn
6. Generate sohoadon: DEB{tungay}{denngay}{2_random_chars}
```

**Trạng thái công nợ:**

```
MOI_TAO_CONG_NO → CHO_XAC_NHAN → DA_CHOT → DA_GUI_EMAIL → DA_THANH_TOAN
                                   ↓
                              QUA_HAN (auto qua CRON)
```

**CRON: CapNhatCongNoQuaHan()**
- Header: `X-CRON-KEY` để bảo mật
- Check: các congno đã chốt nhưng chưa thanh toán sau deadline
- Update: thêm flag/histories

### 7.2 Công nợ Đại lý/NCC (CongNoDaiLyController)

Cấu trúc **gần giống hệt** CongNoController:
- Thay `id_ctv` → `id_daily` (FK → table_news)
- Thay `payment_status` → `payment_status_ncc`
- Cùng trạng thái FSM

### 7.3 CongNo Invoice Code

```
Format: DEB{YYMMDD_from}{YYMMDD_to}{random_2}
Ví dụ: DEB240501240531AB
```

---

## 8. MODULE TRACKING

### 8.1 Tracking Page (công khai)

```
URL: /tracking/{idbill}
Không cần auth (TrackingAPI middleware)
```

### 8.2 TrackingMore API Integration

```php
// TrackingController::tracking()
1. Tìm order theo id_bill
2. Lấy tracking_number từ dichvu JSON hoặc order
3. Gọi TrackingMore API:
   - detectCourier(tracking_number) → lấy hãng vận chuyển
   - getTrackingHistory(tracking_number) → lịch sử vận chuyển
4. Group lịch sử theo ngày
5. Return view với tracking data
```

### 8.3 API Methods (ApiController)

```php
detectCourier(trackingNumber) → { courier: string }
guiEmailDebit(id_debit, email) → { success: bool }
```

---

## 9. MODULE BÁO CÁO

### 9.1 Report Formats (5 loại)

| Format | Người xem | Columns |
|--------|----------|---------|
| Admin | Admin | ~40 columns đầy đủ |
| Manager | Manager | ~35 columns |
| Ketoan | Kế toán | financial only |
| Sale | Sale | sale metrics |
| OPS | OPS | operational metrics |
| CS | CS | CS metrics |

### 9.2 Report Filters

```php
$filters = [
    'formDate'        => date,       // Từ ngày
    'toDate'          => date,       // Đến ngày
    'id_daily'        => int,        // Đại lý/NCC
    'id_chinhanh'     => int,        // Chi nhánh nhận hàng
    'id_sale'         => int,        // Sale phụ trách
    'bill_status'     => int,        // Trạng thái đơn
    'payment_status'  => int,        // Thanh toán KH
    'payment_status_ncc' => int,    // Thanh toán NCC
    'dichvu'          => int,        // Dịch vụ
];
```

### 9.3 Thống kê (ThongKeController)

```php
// Tính toán metrics:
TongDon           = COUNT(orders)
TongCanNangGoc    = SUM(cannangg)
TongCanNangPhi    = SUM(cannangc)
TongDoanhThu      = SUM(tongpricegiaban)
TongPhuPhi        = SUM(phuphigiaban)
DoanhThuChuaThanhToan = SUM(payment_status = CHUA)
DonCoCuocBan      = COUNT(cuocban > 0)
DonCoPhuPhi       = COUNT(phuphigiaban > 0)
DonChuaThanhToan  = COUNT(payment_status = CHUA)
TongVAT           = SUM(vatgiaban)
TongHHKD          = SUM(allhoahong)
LoiNhuan          = SUM(loinhuan)

// Reports:
- Top Sale: sort by tong_cuocban
- Theo Quốc gia: don, can_nang, doanh_thu, ti_le_giao, ty_suat_loi_nhuan
- Thông báo: phân quyền noibo/khachhang
```

---

## 10. TÍNH TOÁN TÀI CHÍNH

### 10.1 Cấu trúc giá

```
Đơn giá bán (dongiaban)
├── Cước bán (cuocban) = dongiaban * cannangcdaily
├── PPX giá bán (ppxdgiaban)
├── VAT (10%)
└── Phụ phí bán (phuphigiaban)
    └── Tổng: tongpricegiaban = cuocban + ppxd + VAT + phuphi

Đơn giá vốn (dongiavon)
├── Cước vốn (cuocvon) = dongiavon * cannang
├── PPX giá vốn (ppxdgiavon)
└── Phụ phí vốn

Lợi nhuận
loinhuan = (cuocban - cuocvon) * cannang - hoahong
```

### 10.2 Hoa hồng (HHKD)

```php
allhoahong = tongpricegiaban * ty_suat_hoa_hong(%)
Ty_suat được lưu trong payment JSON
```

### 10.3 Cân nặng tính phí

```php
// 3 loại cân nặng trong orders:
cannangg   = tổng cân nặng gốc (sum từ các package ban đầu)
cannangv   = tổng volumetric weight
cannangc   = tổng chargeable weight (max per package)
cannangcdaily = chargeable weight dùng cho đại lý
re_weight  = cân nặng thực tế (sau kiểm tra)
sumCanNang = tổng (computed, có thể trùng với cannangc)
```

---

## 11. TÍCH HỢP BÊN NGOÀI

### 11.1 TrackingMore API

```php
// Detect courier:
POST https://api.trackingmore.com/v4/carriers/detect
{ tracking_number: string }
// Response: { courier: string, courier_name: string }

// Get tracking history:
POST https://api.trackingmore.com/v4/v2/getNextLevelData
{ courier_code: string, tracking_number: string }
// Response: array of tracking events { datetime, status, location, description }
```

### 11.2 Excel Export (PhpSpreadsheet)

```php
// Xuất report theo format người dùng
// 5 format: Admin, Manager, Ketoan, Sale, OPS, CS
// Giới hạn: max 10,000 rows per export
// Timeout: 300s
// Memory: unlimited (-1)

// Xuất hóa đơn:
exportInvoice(id_bill) → Invoice cho khách
exportInvoiceNCC(id_bill) → Invoice cho NCC
```

### 11.3 QR & Barcode

```php
// Endroid QrCode + Picqer Barcode
// Lưu dạng SVG vào DB
// QR: link tracking công khai
// Barcode: package code hoặc bill code
// Format: CODE128, CODE39, EAN13
```

### 11.4 Image Processing

```php
// Intervention Image
// 1. Resize: giới hạn kích thước tối đa
// 2. Watermark: logo công ty góc phải dưới
// 3. Thumbnail: 200x200 cho preview
// 4. Upload: lưu vào upload/{type}/{date}/
```

---

## 12. BẢO MẬT & RỦI RO

### 12.1 Lỗi bảo mật phát hiện

| Mức | Lỗi | Vị trí | Mô tả |
|-----|-----|--------|-------|
| 🔴 CRITICAL | Hardcoded Secret Key | `config/app.php:22` | Key mã hóa lưu trực tiếp trong config |
| 🔴 CRITICAL | IDOR Invoice Export | `ApiController.php:83,195` | Không kiểm tra quyền sở hữu order |
| 🔴 CRITICAL | SQL Injection | `ApiController.php:1497-1535` | Tham số `$table` từ user input không sanitize |
| 🔴 CRITICAL | Missing Auth on Write APIs | `web.php:31` | API write không yêu cầu xác thực + CSRF |
| 🔴 CRITICAL | Unbounded Query DoS | `ApiController.php:623-788` | Report query không limit, treo server |
| 🟠 HIGH | Files > 800 lines | Nhiều file | `ApiController.php` (1,537), `Func.php` (2,562) |
| 🟠 HIGH | Cell Overwrite Bug | `ApiController.php:895` | AJ bị ghi đè, mất dữ liệu trong Excel |
| 🟠 HIGH | Base64 Upload No MIME Check | `TraitSave.php:125` | Upload webshell có thể |
| 🟠 HIGH | HTTP Google Fonts | `index.php:47` | Mixed content |
| 🟡 MEDIUM | `@` Error Suppression | `Func.php` | Suppress null reference errors |
| 🟡 MEDIUM | Deprecated `rand()` | `Func.php:1300+` | Nên dùng `random_int()` |
| 🟡 MEDIUM | Exception Leaks Info | `App.php:192` | Raw exception message → client |
| 🟡 MEDIUM | Memory Limit -1 | `lock.php:29` | Không giới hạn bộ nhớ |
| 🟡 MEDIUM | X-Forwarded-For Spoofing | `Func.php:1160` | IP có thể bị spoof |
| 🟡 MEDIUM | Inconsistent API Responses | `ApiController.php` | `response()->json()` vs `echo json_encode()` |

### 12.2 Security cần cải thiện

```
1. Rate limiting → chưa có brute-force protection
2. Account lockout → không có
3. 2FA → không có
4. CSRF → có nhưng không đầy đủ trên mọi endpoint
5. Input validation → thiếu trên nhiều endpoint
```

### 12.3 Điểm yếu thiết kế

```
1. CongTacVienModel dùng chung bảng user với UserModel
   → CTV có thể đăng nhập vào hệ thống nội bộ (rủi ro)

2. id_customer trong orders → member.uuid (string)
   → Khó join trong SQL, dễ sai

3. News table dùng cho 13+ loại data
   → Không có type enforcement, dễ conflict

4. Các trạng thái lưu trong table_news (FK)
   → Thay đổi trạng thái = thêm row vào news
   → Không có versioning

5. JSON columns nặng
   → Khó query, index không hoạt động
   → Nên tách thành bảng riêng
```

---

## PHỤ LỤC: ĐỊNH DẠNG MÃ

### Mã đơn hàng

```
AVN{YYMMDD}{NNN}
AVN240511001
```

### Mã CTV/Sale/Nhân viên

```
CTV001, CTV002, ... (CTV = cộng tác viên)
SALE001, SALE002, ... (SALE = kinh doanh)
OPS001, OPS002, ... (OPS = vận hành)
KT001, KT002, ... (KETOAN = kế toán)
MAN001, MAN002, ... (MANAGER = quản lý)
CS001, CS002, ... (CS = chăm sóc khách)
SHIP001, SHIP002, ... (SHIPPER = tài xế)
```

### Mã pickup

```
PICK{random_8}
PICKABCD1234
```

### Mã lô hàng

```
PKG{random}
PKGABC123
```

### Mã công nợ

```
DEB{YYMMDD_from}{YYMMDD_to}{random_2}
DEB240501240531AB
```

### Mã VSVX (quốc tế)

```
Mã 10-13 ký tự alphanumeric
Kiểm tra format trong checkVSVX()
```
