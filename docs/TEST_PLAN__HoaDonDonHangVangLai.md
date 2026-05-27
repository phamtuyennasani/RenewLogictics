# KẾ HOẠCH KIỂM THỬ: Hóa đơn cho đơn hàng vãng lai

> **Ngày viết:** 2026-05-27
> **Người viết:** DEV team
> **Phiên bản:** 1.0

---

## 1. Mô tả chức năng

Cho phép tạo hóa đơn thu trực tiếp tại trang thanh toán của đơn hàng **vãng lai** (không qua công nợ). Đơn hàng vãng lai là đơn có `id_customer = 0 | null`.

### Đặc điểm chính

| Đặc điểm | Mô tả |
|---|---|
| Số tiền hóa đơn | = `total_tongcuoc` trong `payment_cuocban` của đơn hàng |
| Tạo xong | Auto-approve sang `DA_DUYET` (không qua `CHO_DUYET`) |
| Khóa cước bán | Khi có hóa đơn active → không cho sửa cước bán |
| Mở khóa | Hủy hóa đơn → được sửa cước bán |
| Thanh toán thành công | `DA_THANH_TOAN` → đơn hàng `customer_payment_status = DA_THANH_TOAN` |
| Quyền tạo | `admin`, `manager`, `ketoan`, `sale` |

### Luồng trạng thái hóa đơn

```
Tạo hóa đơn → DA_DUYET (auto-approve)

DA_DUYET
  ├── Thanh toán tiền mặt → DA_GUI_HOA_DON_TT
  │     ├── Duyệt → DA_THANH_TOAN ✓
  │     └── Từ chối → KHONG_CHAP_NHAN
  │           └── Upload lại → DA_GUI_HOA_DON_TT
  │
  └── Thanh toán online → DA_GUI_YEU_CAU_TT
        ├── Webhook / Admin → DA_THANH_TOAN ✓
        └── Hủy (trước thanh toán) → HUY ✓

DA_DUYET → HUY (hủy) ✓
```

---

## 2. Phạm vi kiểm thử

### 2.1. Module liên quan

- **Trang thanh toán đơn hàng:** `/orders/{uuid}/payment`
- **Trang chi tiết đơn hàng:** `/orders/{uuid}`
- **Danh sách hóa đơn thu:** `/hoa-don-thu`

### 2.2. Files đã thay đổi

| File | Thay đổi |
|---|---|
| `database/migrations/2026_05_27_150000_add_order_columns_to_congno_payments_table.php` | Thêm `id_order`, `order_snapshot` |
| `app/Models/CongNoPayment.php` | Thêm quan hệ `order()`, `hasDirectOrder()`, fillable, cast |
| `app/Models/Order.php` | Thêm `congNoPayments()`, `isInvoiceLocked()`, `hasActiveInvoice()`, `getActiveInvoice()` |
| `app/Services/OrderInvoiceService.php` | Tạo mới — encapsulate logic tạo/hủy/sync hóa đơn |
| `app/Http/Controllers/Invoice/InvoiceDataTableController.php` | Cập nhật sync đơn hàng, hiển thị đơn lẻ |
| `resources/views/pages/order/⚡payment.blade.php` | Thêm section hóa đơn thu + Flux modals |
| `resources/views/components/order/⚡payment-invoices.blade.php` | Component lịch sử hóa đơn |
| `resources/views/pages/order/⚡show.blade.php` | Thêm component lịch sử hóa đơn |

---

## 3. Test Cases

### 3.1. Tạo hóa đơn cho đơn vãng lai

**Mục tiêu:** Tạo hóa đơn cho đơn hàng không qua công nợ

**Điều kiện tiên quyết:**
- Đơn hàng vãng lai: `id_customer = 0` hoặc `null`
- Đơn hàng có `payment_cuocban` với `total_tongcuoc > 0`
- Đăng nhập quyền: `admin | manager | ketoan | sale`

**Các bước:**
1. Truy cập `/orders/{uuid}/payment`
2. Cuộn xuống section **"HÓA ĐƠN THU"**
3. Nhấn **"Tạo hóa đơn"**

**Kết quả mong đợi:**
- [ ] Hóa đơn tạo thành công, trạng thái = **"Đã duyệt"** (`DA_DUYET`) — không phải "Chờ duyệt"
- [ ] Số tiền hóa đơn = đúng `total_tongcuoc` từ `payment_cuocban`
- [ ] Mã hóa đơn có dạng `HD-TH-YYYYMMDD-XXXX`
- [ ] Hiển thị: mã hóa đơn, số tiền, trạng thái, ngày tạo
- [ ] Form cước bán bị **ẩn/disabled** — không cho sửa
- [ ] Database: `congno_payments` có record với `id_order`, `id_congno = null`, `order_snapshot` được lưu

**Kiểm tra nghịch:**
- [ ] Tạo hóa đơn khi `total_tongcuoc = 0` → báo lỗi "Tổng cước bán phải lớn hơn 0"
- [ ] Tạo hóa đơn khi đã có hóa đơn active → báo lỗi "Đơn hàng đã có hóa đơn đang xử lý"
- [ ] Tạo hóa đơn với quyền `CTV` → báo lỗi 403

---

### 3.2. Khóa sửa cước bán khi có hóa đơn

**Mục tiêu:** Form cước bán bị khóa sau khi tạo hóa đơn

**Các bước:**
1. Tạo hóa đơn cho đơn vãng lai (test case 3.1)
2. Kiểm tra form cước bán

**Kết quả mong đợi:**
- [ ] Toàn bộ form cước bán bị ẩn hoặc disabled
- [ ] Không thể thay đổi giá trị nào trong cước bán
- [ ] Không hiển thị nút "Lưu giá"

---

### 3.3. Hủy hóa đơn → mở lại quyền sửa cước bán

**Mục tiêu:** Hủy hóa đơn mở lại quyền sửa cước bán

**Các bước:**
1. Hóa đơn ở trạng thái `DA_DUYET`
2. Nhấn **"Hủy hóa đơn"** → nhập lý do → xác nhận

**Kết quả mong đợi:**
- [ ] Hóa đơn chuyển sang trạng thái **"Đã hủy"** (`HUY`)
- [ ] Lý do hủy được lưu và hiển thị trên trang payment + trang chi tiết
- [ ] Form cước bán **hiện lại / enabled** — có thể sửa
- [ ] Có thể tạo hóa đơn mới cho đơn hàng

**Kiểm tra nghịch:**
- [ ] Hủy hóa đơn ở trạng thái `DA_THANH_TOAN` → báo lỗi "Không thể hủy hóa đơn đã thanh toán"

---

### 3.4. Thanh toán tiền mặt — gửi chứng từ

**Mục tiêu:** Luồng thanh toán tiền mặt cho đơn lẻ (bước gửi chứng từ)

**Các bước:**
1. Hóa đơn ở trạng thái `DA_DUYET`
2. Nhấn **"Thanh toán"** → chọn **"Tiền mặt"**
3. Upload ảnh chứng từ thanh toán
4. Xác nhận gửi

**Kết quả mong đợi:**
- [ ] Trạng thái hóa đơn chuyển sang **"Chờ duyệt thanh toán"** (`DA_GUI_HOA_DON_TT`)
- [ ] Ảnh chứng từ được lưu
- [ ] `submitted_at` được ghi nhận

---

### 3.5. Thanh toán tiền mặt — duyệt / từ chối

**Mục tiêu:** Kế toán/Admin duyệt hoặc từ chối chứng từ

**Điều kiện:** Hóa đơn ở trạng thái `DA_GUI_HOA_DON_TT`

**Duyệt thanh toán:**
1. Đăng nhập `admin | ketoan`
2. Truy cập `/hoa-don-thu` → tìm hóa đơn → **"Chi tiết"** → **"Xác nhận thanh toán"**

**Kết quả mong đợi:**
- [ ] Trạng thái → **"Đã thanh toán"** (`DA_THANH_TOAN`)
- [ ] `paid_at` được ghi nhận
- [ ] Đơn hàng: `customer_payment_status = DA_THANH_TOAN`
- [ ] Đơn hàng: `customer_paid_at = now()`

**Từ chối chứng từ:**
1. Đăng nhập `admin | ketoan`
2. Nhấn **"Chi tiết"** → **"Từ chối"**
3. Nhập lý do từ chối → xác nhận

**Kết quả mong đợi:**
- [ ] Trạng thái → **"Không chấp nhận"** (`KHONG_CHAP_NHAN`)
- [ ] Lý do từ chối được lưu
- [ ] Lý do hiển thị trên trang payment + trang chi tiết đơn hàng

**Upload lại chứng từ từ `KHONG_CHAP_NHAN`:**
4. Đăng nhập sale/user đã tạo hóa đơn
5. Upload ảnh chứng từ mới → gửi

**Kết quả mong đợi:**
- [ ] Trạng thái quay về **"Chờ duyệt thanh toán"** (`DA_GUI_HOA_DON_TT`)
- [ ] Lý do từ chối trước đó được xóa

---

### 3.6. Thanh toán online (QR)

**Mục tiêu:** Tạo QR thanh toán cho đơn lẻ

**Các bước:**
1. Hóa đơn ở trạng thái `DA_DUYET`
2. Nhấn **"Thanh toán"** → chọn cổng thanh toán (SePay / MoMo / VNPay)
3. Xác nhận tạo QR

**Kết quả mong đợi:**
- [ ] Trạng thái → **"Đã gửi yêu cầu thanh toán"** (`DA_GUI_YEU_CAU_TT`)
- [ ] QR code hoặc link thanh toán hiển thị
- [ ] `qr_payment_code`, `qr_url` được lưu

**Tạo lại QR sau 15 phút:**
4. Đợi đủ 15 phút (hoặc kiểm tra countdown)
5. Nhấn **"Tạo lại QR"**

**Kết quả mong đợi:**
- [ ] QR mới được tạo, payment code giữ nguyên (reuse code cũ để thuận tiện đối soát)
- [ ] Thời điểm tạo lại được cập nhật

**Kiểm tra nghịch:**
- [ ] Tạo lại QR trước 15 phút → báo lỗi "Vui lòng đợi đến HH:mm DD/MM/YYYY"

---

### 3.7. Admin đánh dấu thanh toán thủ công

**Mục tiêu:** Admin xác nhận thanh toán không qua cổng thanh toán

**Các bước:**
1. Hóa đơn ở trạng thái `DA_GUI_YEU_CAU_TT`
2. Đăng nhập quyền `admin`
3. Nhấn **"Xác nhận thanh toán (Admin)"**

**Kết quả mong đợi:**
- [ ] Trạng thái → **"Đã thanh toán"** (`DA_THANH_TOAN`)
- [ ] Đơn hàng: `customer_payment_status = DA_THANH_TOAN`
- [ ] `paid_at` được ghi nhận

---

### 3.8. Reset kênh thanh toán

**Mục tiêu:** Admin reset hóa đơn về trạng thái chờ thanh toán

**Các bước:**
1. Hóa đơn ở trạng thái `DA_GUI_HOA_DON_TT` hoặc `DA_GUI_YEU_CAU_TT`
2. Đăng nhập `admin`
3. Nhấn **"Reset kênh thanh toán"**

**Kết quả mong đợi:**
- [ ] Hóa đơn quay về **"Đã duyệt"** (`DA_DUYET`)
- [ ] Thông tin thanh toán (QR, photo, provider) bị xóa

---

### 3.9. Danh sách hóa đơn thu — hiển thị đơn lẻ

**Mục tiêu:** Hóa đơn đơn lẻ hiển thị đúng trong danh sách `/hoa-don-thu`

**Các bước:**
1. Tạo hóa đơn đơn lẻ (test case 3.1)
2. Truy cập `/hoa-don-thu`
3. Tìm hóa đơn đã tạo

**Kết quả mong đợi:**
- [ ] Cột **"Mã hóa đơn"** → click link → chuyển đến trang payment đơn hàng
- [ ] Cột **"Mã công nợ"** → hiển thị mã đơn hàng (`id_bill`) hoặc "ĐH-{id}"
- [ ] Cột **"Khách hàng"** → hiển thị tên người gửi hoặc "Vãng lai"
- [ ] Cột **"Sale"** → hiển thị sale phụ trách đơn hàng
- [ ] Badge trạng thái hiển thị đúng màu theo enum
- [ ] Bộ đếm trạng thái (tab counts) cập nhật đúng

**Search:**
- [ ] Tìm theo mã hóa đơn → tìm thấy
- [ ] Tìm theo `id_bill` đơn hàng → tìm thấy

---

### 3.10. Trang chi tiết đơn hàng — lịch sử hóa đơn

**Mục tiêu:** Section lịch sử hóa đơn hiển thị đúng trên trang chi tiết

**Các bước:**
1. Tạo hóa đơn cho đơn hàng (test case 3.1)
2. Truy cập `/orders/{uuid}`
3. Tìm section **"Hóa đơn thu"**

**Kết quả mong đợi:**
- [ ] Hiển thị danh sách tất cả hóa đơn của đơn hàng
- [ ] Mỗi hóa đơn: mã, trạng thái, số tiền, ngày tạo, người tạo
- [ ] `KHONG_CHAP_NHAN`: hiển thị lý do từ chối (màu đỏ)
- [ ] `HUY`: hiển thị lý do hủy (màu xám)
- [ ] Nút **"Thanh toán"** chuyển đến trang payment
- [ ] Chưa có hóa đơn: hiển thị thông báo + nút "Tạo hóa đơn"

---

### 3.11. Snapshot cước bán tại thời điểm tạo hóa đơn

**Mục tiêu:** `order_snapshot` được lưu đúng tại thời điểm tạo

**Các bước:**
1. Tạo hóa đơn cho đơn hàng có cước bán
2. Kiểm tra database: bảng `congno_payments`, cột `order_snapshot`

**Kết quả mong đợi:**
- [ ] `order_snapshot` = JSON của `payment_cuocban` tại thời điểm tạo
- [ ] Thay đổi cước bán sau khi hủy hóa đơn không ảnh hưởng snapshot

---

## 4. Bảng quyền người dùng

| Hành động | Admin | Manager | Kế toán | Sale | CTV |
|---|:---:|:---:|:---:|:---:|:---:|
| Tạo hóa đơn | ✅ | ✅ | ✅ | ✅ | ❌ |
| Hủy hóa đơn | ✅ | ✅ | ✅ | ✅* | ❌ |
| Gửi thanh toán tiền mặt | ✅ | ✅ | ✅ | ✅ | ❌ |
| Xác nhận thanh toán | ✅ | ❌ | ✅ | ❌ | ❌ |
| Từ chối chứng từ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Tạo QR online | ✅ | ✅ | ✅ | ✅ | ❌ |
| Reset kênh thanh toán | ✅ | ❌ | ❌ | ❌ | ❌ |
| Đánh dấu thanh toán thủ công | ✅ | ❌ | ❌ | ❌ | ❌ |

*Sale chỉ hủy được hóa đơn do mình tạo

---

## 5. Checkpoints tổng hợp

- [ ] **TC-3.1:** Tạo hóa đơn → auto-approve sang `DA_DUYET`
- [ ] **TC-3.1:** Số tiền hóa đơn = đúng `total_tongcuoc`
- [ ] **TC-3.1:** `id_order` được lưu, `id_congno = null`
- [ ] **TC-3.1:** `order_snapshot` được lưu đúng
- [ ] **TC-3.2:** Sau khi tạo hóa đơn → form cước bán bị ẩn/disabled
- [ ] **TC-3.3:** Hủy hóa đơn → form cước bán hiện lại
- [ ] **TC-3.3:** Hủy hóa đơn đã thanh toán → bị chặn
- [ ] **TC-3.4:** Gửi chứng từ → `DA_GUI_HOA_DON_TT`
- [ ] **TC-3.5:** Duyệt tiền mặt → `DA_THANH_TOAN` → đơn hàng được sync
- [ ] **TC-3.5:** Từ chối chứng từ → `KHONG_CHAP_NHAN` → hiển thị lý do
- [ ] **TC-3.5:** Upload lại từ `KHONG_CHAP_NHAN` → quay về `DA_GUI_HOA_DON_TT`
- [ ] **TC-3.6:** Tạo QR → `DA_GUI_YEU_CAU_TT` → QR hiển thị
- [ ] **TC-3.6:** Tạo lại QR trước 15 phút → bị chặn
- [ ] **TC-3.6:** Tạo lại QR sau 15 phút → thành công
- [ ] **TC-3.7:** Admin đánh dấu thanh toán → `DA_THANH_TOAN` → đơn hàng sync
- [ ] **TC-3.8:** Reset kênh thanh toán → quay về `DA_DUYET`
- [ ] **TC-3.9:** Danh sách hóa đơn hiển thị đơn lẻ đúng
- [ ] **TC-3.9:** Link mã hóa đơn → chuyển đến trang payment đơn hàng
- [ ] **TC-3.9:** Search theo mã hóa đơn + `id_bill` → tìm thấy
- [ ] **TC-3.10:** Trang chi tiết hiển thị lịch sử hóa đơn
- [ ] **TC-3.10:** `KHONG_CHAP_NHAN` hiển thị lý do từ chối (đỏ)
- [ ] **TC-3.10:** `HUY` hiển thị lý do hủy (xám)
- [ ] **TC-3.11:** `order_snapshot` lưu đúng JSON tại thời điểm tạo

---

## 6. Các lỗi đã sửa trong quá trình phát triển

| # | Lỗi | Fix |
|---|---|---|
| 1 | PHP Fatal: duplicate `congNos()` method trong `Order.php` | Xóa method trùng lặp, giữ đúng method `belongsToMany(CongNo::class)` |
| 2 | Route name confusion | Dùng `orders.payment` (prefix `orders.` trong route group) |
