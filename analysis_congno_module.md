# 📊 PHÂN TÍCH MODULE CÔNG NỢ — Hệ thống cũ (NINACORE)

> **Ngày phân tích:** 25/04/2026  
> **Phạm vi:** Module Công Nợ Khách Hàng (`CongNoController`) + Công Nợ Đại Lý (`CongNoDaiLyController`)

---

## 1. TỔNG QUAN KIẾN TRÚC

### 1.1 Files liên quan

| Layer | File | Vai trò |
|-------|------|---------|
| **Controller** | `src/Controllers/Web/CongNoController.php` | Quản lý công nợ khách hàng |
| **Controller** | `src/Controllers/Web/CongNoDaiLyController.php` | Quản lý công nợ đại lý (NCC) |
| **Controller** | `src/Controllers/Web/ApiController.php` | Xuất Excel debit, gửi email nhắc nợ |
| **Controller** | `src/Controllers/Web/ApiOrder.php` | Lọc đơn hàng theo công nợ |
| **Model** | `src/Models/CongNoModel.php` | Model công nợ KH — bảng `congno` |
| **Model** | `src/Models/CongNoDetail.php` | Bảng pivot `congno_detail` |
| **Model** | `src/Models/CongNoDaiLy.php` | Model công nợ đại lý — bảng `congno_daily` |
| **Model** | `src/Models/CongNoDaiLyDetail.php` | Bảng pivot `congno_daily_detail` |
| **Model** | `src/Models/OrdersModel.php` | Model đơn hàng, chứa `TraitPayment` |
| **Trait** | `src/Traits/TraitPayment.php` | Accessor tính giá bán/vốn/VAT/hoa hồng trên Order |
| **Command** | `src/Commands/CheckCongNoCommand.php` | Cron cập nhật công nợ quá hạn |
| **Views** | `src/Views/templates/congno/` | Blade templates: index, detail, modal, component |
| **Views** | `src/Views/templates/congnodaily/` | Blade templates công nợ đại lý |
| **Routes** | `src/Routes/web.php` | Route definitions |

### 1.2 Database Tables

```
congno                  — Bảng chính công nợ khách hàng
congno_detail           — Pivot: congno ↔ orders (many-to-many)
congno_daily            — Bảng chính công nợ đại lý
congno_daily_detail     — Pivot: congno_daily ↔ orders
orders                  — Bảng đơn hàng
news                    — Bảng danh mục (trạng thái, phụ phí, tỷ giá, dịch vụ...)
users                   — Bảng người dùng (CTV, Sale, Kế toán, Manager)
```

### 1.3 Relationships

```
CongNoModel
  ├── belongsTo UserModel (id_ctv, id_user, id_ketoan, id_success, id_sale)
  ├── belongsTo NewsModel (status → trạng thái công nợ)
  ├── hasMany CongNoDetail (id_congno)
  └── belongsToMany OrdersModel qua congno_detail (id_congno ↔ id_order)

OrdersModel
  ├── belongsToMany CongNoModel qua congno_detail
  └── uses TraitPayment (tất cả accessor tính tiền)
```

---

## 2. BẢNG `congno` — CẤU TRÚC DỮ LIỆU

| Cột | Kiểu | Mô tả |
|-----|------|-------|
| `id` | INT PK | |
| `sohoadon` | VARCHAR | Mã công nợ, format: `DEB{tungay}{denngay}{random2char}` |
| `sohoadon_thamchieu` | VARCHAR | Mã hóa đơn tham chiếu (nhập tay) |
| `id_ctv` | INT FK → users | CTV/Khách hàng sở hữu công nợ |
| `id_sale` | INT FK → users | Sale phụ trách |
| `id_user` | INT FK → users | Người tạo |
| `id_ketoan` | INT FK → users | Kế toán phụ trách |
| `id_success` | INT FK → users | Người chốt/thanh toán |
| `status` | INT FK → news | Trạng thái (CONG_NO_MOI_TAO, DA_CHOT, QUA_HAN, DA_THANH_TOAN, HUY) |
| `tungay` | DATETIME | Khoảng thời gian đầu lấy đơn |
| `denngay` | DATETIME | Khoảng thời gian cuối lấy đơn |
| `ngaytaohoadon` | DATETIME | Ngày tạo công nợ |
| `ngaychothoadon` | DATETIME | Ngày chốt hóa đơn |
| `hanthanhtoan` | DATETIME | Deadline thanh toán (tính từ ngày chốt + songaythanhtoan) |
| `ngaythanhtoan` | DATETIME | Ngày thực thanh toán |
| `songaythanhtoan` | INT | Số ngày hạn thanh toán |
| `ghichu` | TEXT | Ghi chú |
| `photo` | VARCHAR | Ảnh xác nhận thanh toán |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

---

## 3. VÒNG ĐỜI (STATE MACHINE) CÔNG NỢ

```
 ┌─────────────┐
 │ CONG_NO_MOI │ ← Tạo mới
 │    _TAO     │
 └──────┬──────┘
        │ ChotCongNo()
        ▼
 ┌─────────────┐      hanthanhtoan < now()
 │ CONG_NO_DA  │──────────────────────────┐
 │   _CHOT     │                          │
 └──────┬──────┘                          ▼
        │ ThanhToanCongNo()        ┌──────────────┐
        ▼                         │ CONG_NO_QUA  │
 ┌──────────────┐                 │    _HAN      │
 │ CONG_NO_DA   │                 └──────┬───────┘
 │ _THANH_TOAN  │                        │ có thể khôi phục
 └──────────────┘                        │ về MOI_TAO/DA_CHOT
                                         ▼
                                  ┌──────────────┐
                                  │  CONG_NO_HUY │ ← HuyCongNo()
                                  └──────────────┘
```

### Trạng thái constants (trong `news` table, type = `trang-thai-thanh-cong-no`)

| Constant | Ý nghĩa |
|----------|---------|
| `CONG_NO_MOI_TAO` | Mới tạo, chưa chốt |
| `CONG_NO_DA_CHOT` | Đã chốt, chờ thanh toán |
| `CONG_NO_QUA_HAN` | Quá hạn thanh toán (cron tự chuyển) |
| `CONG_NO_DA_THANH_TOAN` | Đã thanh toán xong |
| `CONG_NO_HUY` | Đã hủy |

---

## 4. CÁC THAO TÁC NGHIỆP VỤ (API)

Controller `api()` dùng `match($method)` để route:

| Method | Chức năng | Quyền yêu cầu |
|--------|-----------|----------------|
| `load-cong-no` | Danh sách công nợ (DataTable server-side) | KETOAN, ADMIN, MANAGER, CTV |
| `create-cong-no` | Tạo công nợ mới | KETOAN, ADMIN, MANAGER |
| `get-cong-no-by-ctv` | Lấy công nợ chưa chốt theo CTV | (không check rõ) |
| `add-order-to-debit` | Thêm đơn hàng vào công nợ | KETOAN, ADMIN, MANAGER |
| `load-detail-cong-no` | Chi tiết DataTable các đơn trong công nợ | (không check) |
| `update-detail-cong-no` | Cập nhật thông tin công nợ (status, ghi chú, ảnh) | KETOAN, ADMIN, MANAGER |
| `chot-cong-no` | Chốt công nợ → tính hạn thanh toán | KETOAN, ADMIN, MANAGER |
| `thanh-toan-cong-no` | Thanh toán hàng loạt → cập nhật orders | KETOAN, ADMIN, MANAGER |
| `cap-nhat-cong-no-qua-han` | Cron: đánh dấu quá hạn | Cron key header |
| `huy-cong-no` | Hủy công nợ hàng loạt | KETOAN, ADMIN, MANAGER |
| `xoa-cong-no` | Xóa vĩnh viễn (hard delete) | KETOAN, ADMIN, MANAGER |
| `edit-price-cong-no` | Sửa giá đơn hàng trong công nợ (modal popup) | (check trong view) |
| `remove-order` | Xóa đơn khỏi công nợ | (không check rõ) |
| `lam-moi-cong-no` | Reset công nợ: xóa details, query lại orders | (không check rõ) |

---

## 5. CÔNG THỨC TÍNH TIỀN

### 5.1 Cấu trúc `orders.payment` (JSON)

```json
{
  "cuocban": {
    "don_gia": 0,          // Đơn giá bán
    "ppxd": 0,             // % PPXD
    "ppxd_calc": 0,        // Tiền PPXD
    "vat": 0,              // % VAT
    "vat_calc": 0,         // Tiền VAT
    "tong_cuoc_ban_dau": 0,
    "tong_cuoc_ban": 0,
    "chi_phi_khac": [],    // [{name, so_tien, vat_calc}]
    "hh_khach_hang": [],   // Hoa hồng khách hàng
    "total_hhkh": 0,
    "total_chi_phi_khac": 0,
    "tong_cuoc_truoc_vat": 0,
    "tong_cuoc": 0,        // ← TỔNG CUỐI CÙNG GIÁ BÁN
    "tong_vat": 0
  },
  "cuocvon": {
    "don_gia": 0,
    "ppxd": 0, "ppxd_calc": 0,
    "vat": 0, "vat_calc": 0,
    "tong_cuoc_von": 0,
    "chi_phi_khac": [],
    "phi_chi_ho": [],
    "net_sale": 0,
    "phantram_bonus_sale": 0,   // % hoa hồng sale
    "bonus_sale": 0,            // Tiền hoa hồng sale
    "tong_cuoc": 0              // ← TỔNG GIÁ VỐN
  },
  "cuocvoncongty": { ... },     // Giá vốn nội bộ (chỉ ADMIN/MANAGER/KETOAN thấy)
  "loi_nhuan_tam_tinh": 0,
  "loi_nhuan": 0,
  "ty_suat": 0
}
```

### 5.2 Accessor trên Order (TraitPayment)

| Accessor | Nguồn | Mô tả |
|----------|-------|-------|
| `totalpricegiaban` | `cuocban.tong_cuoc` | Tổng giá bán (bao gồm VAT) |
| `dongiaban` | `cuocban.don_gia` | Đơn giá bán |
| `ppxdgiaban` | `cuocban.ppxd_calc` | Phụ phí xăng dầu bán |
| `phuphigiaban` | `sum(cuocban.chi_phi_khac[].so_tien)` | Tổng phụ phí bán |
| `vatgiaban` | `cuocban.tong_vat` | Tổng VAT bán |
| `allhoahong` | `cuocvon.bonus_sale` | Hoa hồng sale |
| `tongcuocban` | `cuocban.tong_cuoc` | Giống totalpricegiaban |

### 5.3 Accessor tổng hợp trên CongNoModel (computed từ orders)

| Accessor | Công thức | Hiển thị |
|----------|-----------|----------|
| `tongcuoc` | `orders.sum('totalpricegiaban')` | Tổng cước bao gồm VAT |
| `dongia` | `orders.sum('dongiaban')` | Tổng đơn giá |
| `ppxd` | `orders.sum('ppxdgiaban')` | Tổng PPXD |
| `phuphi` | `orders.sum('phuphigiaban')` | Tổng phụ phí khác |
| `tongvat` | `orders.sum('vatgiaban')` | Tổng VAT |
| `hoahongsale` | `orders.sum('allhoahong')` | Tổng hoa hồng sale |
| `tongcuocchuathanhtoan` | `orders(chưa TT).sum('totalpricegiaban')` | Số tiền còn nợ |
| `tongcuocdathanhtoan` | `tongcuoc - tongcuocchuathanhtoan` | Đã thanh toán |
| `sumCanNangThucTe` | `orders.sum('cannangg')` | Tổng cân nặng tính phí |
| `sumCanNangCBanDau` | `orders.sum('sumCanNang')` | Tổng cân nặng ban đầu |

### 5.4 Cân nặng đơn hàng

```
cannangg = packageorder → max(g_weight, v_weight) per kiện → sum
  g_weight = gross weight (cân thực)
  v_weight = volumetric weight = (L × W × H) / 5000
  c_weight = chargeable weight = max(g_weight, v_weight)
```

---

## 6. PHÂN QUYỀN & TRUY CẬP

### 6.1 Roles liên quan

| Constant | Vai trò |
|----------|---------|
| `ADMIN` | Full quyền |
| `MANAGER` | Quản lý — gần full |
| `KETOAN` | Kế toán — chỉ thấy công nợ mình quản lý |
| `CTV` | Cộng tác viên — chỉ xem, không sửa |
| `SALE` | Sale — xem hoa hồng |

### 6.2 Logic phân quyền

- **Xem danh sách**: KETOAN, ADMIN, MANAGER, CTV
- **CTV**: Chỉ thấy công nợ `id_ctv = mình`, không thể sửa/chốt/thanh toán
- **KETOAN**: Chỉ thao tác trên công nợ `id_ketoan = mình` hoặc `id_ketoan IS NULL/0`
- **Tạo/Chốt/Thanh toán/Hủy/Xóa**: KETOAN, ADMIN, MANAGER
- **Cron quá hạn**: Xác thực bằng header `X-CRON-KEY` = `env('CRON_SECRET')`

### 6.3 Middleware

```php
// Route groups
\NINACORE\Middlewares\CongNo::class      → /quan-ly-cong-no
\NINACORE\Middlewares\CongNoDaiLy::class → /quan-ly-cong-no-dai-ly
```

---

## 7. TÍNH NĂNG BỔ SUNG

### 7.1 Xuất Excel Debit
- `ApiController::xuatCongNoDebit()` — dùng template Excel `upload/excel/template_debit.xlsx`
- Xuất tất cả đơn hàng trong công nợ, bao gồm thông tin chi tiết vận chuyển

### 7.2 Gửi Email Nhắc Nợ
- `ApiController::guiEmailDebit()` — template `component.email.congno`
- Chỉ cho phép khi trạng thái = DA_CHOT hoặc QUA_HAN
- Nội dung: mã khách hàng, mã công nợ, giá trị, đã thanh toán

### 7.3 Dashboard Thống Kê (Index page)

5 box thống kê:
1. **Tổng công nợ**: count + tổng tiền
2. **Đã thanh toán**: count + tổng tiền
3. **Chưa thanh toán**: count + tổng tiền nợ
4. **Quá hạn**: count + tổng tiền
5. **Hoa hồng kinh doanh**: tổng tiền + % (chỉ non-CTV)

### 7.4 Cron Job
```bash
congno:check — Chạy hàng ngày, đánh dấu quá hạn cho:
  - status IN (MOI_TAO, DA_CHOT)  
  - hanthanhtoan < NOW()
  → Chuyển status = QUA_HAN
```

---

## 8. CÔNG NỢ ĐẠI LÝ vs KHÁCH HÀNG

| Tiêu chí | Công nợ KH | Công nợ Đại Lý |
|----------|-----------|---------------|
| Table | `congno` | `congno_daily` |
| Pivot | `congno_detail` | `congno_daily_detail` |
| Controller | `CongNoController` | `CongNoDaiLyController` |
| Giá tính | Giá bán (`cuocban`) | Giá vốn (`cuocvon/cuocvoncongty`) |
| typecongno | `'khach-hang'` | `'ncc'` |
| Trạng thái | Full lifecycle | Giới hạn: MOI_TAO, DA_THANH_TOAN, HUY |
| Hoa hồng box | Có | Không có trong index |

---

## 9. RỦI RO & VẤN ĐỀ CẦN LƯU Ý KHI MIGRATE

### 9.1 Performance Risks
- ⚠️ **N+1 queries nghiêm trọng**: Tất cả accessor trên CongNoModel (`tongcuoc`, `dongia`, `ppxd`, `phuphi`, `tongvat`, `hoahongsale`, `tongcuocchuathanhtoan`...) đều gọi `$this->orders->sum(...)` → Mỗi lần render 1 công nợ phải load tất cả orders + tính toán trên PHP
- ⚠️ `loadCongNo()`: Gọi `$totalOrders = $query->get()` lấy **toàn bộ** rồi mới filter/sum trên PHP → Không scalable
- ⚠️ `loadDetailCongNo()`: Không phân trang

### 9.2 Security Risks
- ⚠️ Một số action thiếu kiểm tra quyền: `loadDetailCongNo`, `RemoveOrderFromCongNo`, `LamMoiCongNo`, `getCongNoByCTV`
- ⚠️ `$data = $request->input('data')` rồi `$congNo->update($data)` → Mass assignment không filter (dù `$guarded = []`)

### 9.3 Logic Risks
- ⚠️ `LamMoiCongNo`: Reset toàn bộ details rồi query lại, nhưng không kiểm tra trạng thái → có thể reset công nợ đã thanh toán
- ⚠️ `AddOrderToDebit`: Check `CongNoDetail::whereIn('id_order', ...)` không filter theo `id_congno` → 1 order chỉ có thể thuộc 1 công nợ trên toàn hệ thống?
- ⚠️ `HuyCongNo`: Comment out `$congNo->orders()->update(['payment_status' => CHUA_THANH_TOAN])` → Hủy công nợ nhưng không revert payment_status orders

### 9.4 Data Integrity
- ⚠️ Trạng thái lưu bằng ID news (dynamic) thay vì enum → Phụ thuộc data seed
- ⚠️ Không có database constraint/foreign key rõ ràng

---

## 10. GỢI Ý MIGRATE SANG LARAVEL

### 10.1 Database

```
Migrations:
  - create_cong_nos_table
  - create_cong_no_details_table (pivot)
  - create_cong_no_dailies_table
  - create_cong_no_daily_details_table (pivot)

Thêm:
  - Foreign key constraints
  - Enum cho status thay vì FK news
  - Index trên (id_ctv, status, created_at, hanthanhtoan)
```

### 10.2 Models

```php
// App\Models\CongNo
class CongNo extends Model {
    protected $table = 'cong_nos';
    protected $casts = ['tungay' => 'datetime', 'denngay' => 'datetime', ...];
    
    // Chuyển accessor thành computed columns hoặc cache
    // HOẶC dùng subquery select để tránh N+1
}
```

### 10.3 Controllers → Tách API riêng

```
App\Http\Controllers\CongNo\
  ├── CongNoController.php        (index, detail — web)
  ├── CongNoApiController.php     (CRUD API)
  └── CongNoExportController.php  (Excel, Email)
```

### 10.4 Business Logic → Services

```
App\Services\CongNoService.php
  - createCongNo()
  - chotCongNo()
  - thanhToanCongNo()
  - huyCongNo()
  - capNhatQuaHan()
  - lamMoiCongNo()
```

### 10.5 Authorization → Form Requests + Policy

```
App\Policies\CongNoPolicy.php
  - view(), create(), update(), chot(), thanhToan(), huy(), delete()

App\Http\Requests\CongNo\
  - CreateCongNoRequest.php
  - UpdateCongNoRequest.php
```

### 10.6 Performance Optimization

1. **Denormalize tổng tiền**: Lưu `tongcuoc`, `tongcuocchuathanhtoan` vào bảng `cong_nos` → Update qua event/observer khi order thay đổi
2. **Eager loading**: Luôn load `orders` khi cần tổng hợp
3. **Database aggregation**: Dùng `DB::raw('SUM(...)')` thay vì PHP collection sum
4. **Phân trang đúng cách**: Server-side pagination cho cả list và detail

---

## 11. ROUTE MAP (Hiện tại → Laravel)

| Hiện tại | Laravel đề xuất |
|----------|----------------|
| `GET /quan-ly-cong-no` | `GET /cong-no` |
| `GET /quan-ly-cong-no/{id}` | `GET /cong-no/{id}` |
| `POST /cong-no/load-cong-no` | `POST /api/cong-no/list` |
| `POST /cong-no/create-cong-no` | `POST /api/cong-no` |
| `POST /cong-no/chot-cong-no` | `POST /api/cong-no/{id}/chot` |
| `POST /cong-no/thanh-toan-cong-no` | `POST /api/cong-no/thanh-toan` |
| `POST /cong-no/huy-cong-no` | `POST /api/cong-no/huy` |
| `POST /cong-no/xoa-cong-no` | `DELETE /api/cong-no` |
| `POST /cong-no/edit-price-cong-no` | `POST /api/cong-no/order/{id}/price` |
| `POST /cong-no/remove-order` | `DELETE /api/cong-no/{id}/order/{orderId}` |
| `POST /cong-no/lam-moi-cong-no` | `POST /api/cong-no/{id}/refresh` |

---

*Tài liệu này phục vụ mục đích phân tích và lên kế hoạch migrate module Công Nợ từ NINACORE sang Laravel.*