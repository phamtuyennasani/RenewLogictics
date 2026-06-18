# PHÂN QUYỀN CHI TIẾT THEO TỪNG ROLE

Cập nhật: 2026-06-18

---

## 1. 👑 ADMIN (Quản trị viên)

**Toàn quyền hệ thống** - Được phép làm TẤT CẢ

### ✅ Làm được:
- **Dashboard**: Xem tất cả thống kê
- **Đơn hàng**: Tạo, xem, sửa, xóa (tất cả trạng thái)
- **Tải hàng (Packages)**: Tạo, xem, sửa, **XÓA TẢI**, quét barcode
- **Pickup**: Tạo, xem, sửa, xóa tất cả pickup
- **Quét kiện hàng (Scan)**: Có quyền scan
- **Công nợ khách hàng**: Xem, tạo, xóa (tất cả trạng thái)
- **Công nợ đại lý**: Xem, tạo, xóa (tất cả trạng thái)
- **Hóa đơn thu**: Xem, tạo, quản lý, **XÓA** (có điều kiện)
- **Khách hàng**: Xem, tạo, sửa, **XÓA**
- **Địa chỉ gửi/nhận**: Xem, tạo, sửa, **XÓA**
- **CTV**: Xem, tạo, sửa, xóa tất cả CTV
- **Nhân sự**: Xem, tạo, sửa, xóa nhân viên
- **Dữ liệu**: Xem, tạo, sửa, xóa
- **Chính sách**: Xem, tạo, sửa (chỉ admin)
- **Cấu hình**: Xem, sửa (logo, favicon, banner, hệ thống, social, company)
- **Phụ phí**: Xem, tạo, sửa, xóa
- **Bảng giá dịch vụ**: Xem, tạo, sửa, xóa
- **Gán Sale cho đơn**: Có quyền

### ❌ Không làm được:
- **KHÔNG CÓ** - Admin có toàn quyền

---

## 2. 🔶 MANAGER (Quản lý)

**Quyền quản lý cao** - Gần như toàn quyền, trừ một số chức năng admin-only

### ✅ Làm được:
- **Dashboard**: Xem tất cả thống kê
- **Đơn hàng**: Xem tất cả đơn
- **Đơn hàng**: Xóa đơn ở các trạng thái Mới tạo, Đã xác nhận, Đã nhận hàng, Duyệt xuất hàng
- **Tải hàng (Packages)**: Tạo, xem, sửa, **XÓA TẢI**
- **Pickup**: Tạo, xem, sửa, xóa tất cả pickup
- **Công nợ khách hàng**: Xem, tạo, xóa
- **Công nợ đại lý**: Xem, tạo, xóa
- **Hóa đơn thu**: Xem, tạo, quản lý
- **Địa chỉ gửi/nhận**: Xem, tạo, sửa (KHÔNG xóa)
- **CTV**: Xem, tạo, sửa tất cả CTV
- **Nhân sự**: Xem, tạo, sửa nhân viên
- **Dữ liệu**: Xem, tạo, sửa
- **Cấu hình**: Xem, sửa (không có settings.admin)
- **Phụ phí**: Xem, tạo, sửa
- **Bảng giá dịch vụ**: Xem, tạo, sửa

### ❌ Không làm được:
- **Đơn hàng**: KHÔNG tạo đơn
- **Quét kiện hàng (Scan)**: KHÔNG có quyền
- **Quét barcode tải**: KHÔNG có quyền (packages.scan)
- **Địa chỉ gửi/nhận**: KHÔNG xóa được (chỉ admin)
- **Chính sách**: KHÔNG thấy menu (chỉ admin)
- **Settings Admin**: KHÔNG sửa được (logo, favicon, banner, company...)
- **Bảng giá**: KHÔNG xóa được (chỉ admin)
- **Xóa dữ liệu**: KHÔNG xóa được (chỉ admin)

---

## 3. 💜 KETOAN (Kế toán)

**Chuyên trách công nợ và tài chính**

### ✅ Làm được:
- **Dashboard**: Xem thống kê
- **Đơn hàng**: Xem tất cả đơn
- **Công nợ khách hàng**: Xem, tạo, xóa (mọi trạng thái TRỪ "Đã thanh toán")
- **Công nợ khách hàng**: Cập nhật trạng thái (Mới tạo → Đang xử lý → Đã thanh toán)
- **Công nợ đại lý**: Xem, tạo, xóa (mọi trạng thái TRỪ "Đã thanh toán" và "Đã hủy")
- **Hóa đơn thu**: Xem, tạo, quản lý
- **Cấu hình**: Xem, sửa
- **Phụ phí**: Xem, tạo, sửa
- **Bảng giá dịch vụ**: Xem, tạo, sửa
- **Thông báo**: Tạo thông báo

### ❌ Không làm được:
- **Đơn hàng**: KHÔNG tạo, KHÔNG sửa, KHÔNG xóa
- **Tải hàng**: KHÔNG thấy menu, KHÔNG có quyền
- **Pickup**: KHÔNG thấy menu
- **Quét kiện**: KHÔNG có quyền
- **Khách hàng**: KHÔNG thấy menu
- **Địa chỉ gửi/nhận**: KHÔNG thấy menu
- **CTV**: KHÔNG thấy menu
- **Nhân sự**: KHÔNG thấy menu
- **Dữ liệu**: KHÔNG thấy menu
- **Chính sách**: KHÔNG thấy menu

---

## 4. 🔵 CS (Chăm sóc khách hàng)

**Quản lý đơn hàng và tải hàng** - Tương tự Manager nhưng hạn chế hơn

### ✅ Làm được:
- **Dashboard**: Xem thống kê
- **Đơn hàng**: Tạo, xem, sửa đơn
- **Đơn hàng**: Xóa đơn **CHỈ Ở TRẠNG THÁI "MỚI TẠO"**
- **Đơn hàng**: Cập nhật trạng thái sang "Đã nhận hàng", "Duyệt xuất hàng", "Đang phát hàng"
- **Tải hàng (Packages)**: Tạo, xem, sửa
- **Tải hàng**: Thêm đơn vào tải, xóa đơn khỏi tải, cập nhật lịch sử, duyệt tải
- **Pickup**: Tạo pickup, xem tất cả pickup
- **Pickup**: Chọn OPS khi tạo/sửa pickup
- **Pickup**: Sửa pickup ở trạng thái "Mới tạo"
- **Khách hàng**: Xem, tạo, sửa
- **Địa chỉ gửi/nhận**: Xem, tạo, sửa
- **CTV**: Xem, tạo, sửa tất cả CTV
- **Dữ liệu**: Xem, tạo, sửa
- **Cấu hình**: Xem, sửa
- **Thông báo**: Tạo thông báo
- **Gán Sale cho đơn**: Có quyền

### ❌ Không làm được:
- **Đơn hàng**: KHÔNG xóa đơn ở trạng thái khác "Mới tạo"
- **Tải hàng**: **KHÔNG XÓA TẢI** (chỉ admin/manager)
- **Pickup**: **KHÔNG CHỌN SHIPPER** (chỉ admin/manager/ops)
- **Pickup**: KHÔNG sửa pickup ở trạng thái khác "Mới tạo"
- **Quét kiện hàng (Scan)**: KHÔNG có quyền
- **Quét barcode tải**: KHÔNG có quyền
- **Khách hàng**: **KHÔNG XÓA** (chỉ admin)
- **Địa chỉ gửi/nhận**: **KHÔNG XÓA** (chỉ admin)
- **Công nợ**: KHÔNG thấy menu
- **Hóa đơn thu**: KHÔNG thấy menu
- **Nhân sự**: KHÔNG thấy menu
- **Chính sách**: KHÔNG thấy menu
- **Phụ phí**: KHÔNG thấy menu
- **Xóa dữ liệu**: KHÔNG xóa được

---

## 5. 💙 SALE (Kinh doanh)

**Tạo đơn và quản lý công nợ của khách hàng mình**

### ✅ Làm được:
- **Dashboard**: Xem thống kê
- **Đơn hàng**: Tạo, xem đơn
- **Pickup**: Xem pickup của đơn mình, tạo pickup cho đơn mình
- **Công nợ khách hàng**: Xem, tạo
- **Hóa đơn thu**: Xem
- **Khách hàng**: Xem, tạo, sửa
- **Địa chỉ gửi/nhận**: Xem, tạo, sửa
- **CTV**: Xem CTV thuộc mình, tạo CTV mới

### ❌ Không làm được:
- **Đơn hàng**: KHÔNG sửa đơn sau khi đã xác nhận
- **Đơn hàng**: KHÔNG xóa đơn (mọi trạng thái)
- **Tải hàng**: KHÔNG thấy menu
- **Quét kiện**: KHÔNG có quyền
- **Khách hàng**: KHÔNG xóa (chỉ admin)
- **Địa chỉ gửi/nhận**: KHÔNG xóa (chỉ admin)
- **Công nợ đại lý**: KHÔNG thấy menu
- **Cập nhật trạng thái công nợ**: KHÔNG được (chỉ kế toán)
- **CTV**: KHÔNG xem CTV của sale khác
- **Nhân sự**: KHÔNG thấy menu
- **Dữ liệu**: KHÔNG thấy menu
- **Cấu hình**: KHÔNG thấy menu
- **Chính sách**: KHÔNG thấy menu
- **Phụ phí**: KHÔNG thấy menu

---

## 6. 🟢 OPS (Vận hành)

**Xem đơn và cập nhật trạng thái nhận hàng**

### ✅ Làm được:
- **Dashboard**: Xem thống kê hạn chế
  - Số lượng đơn hàng theo thời gian
  - Cân nặng hàng hóa (gross weight, charged weight)
  - Biểu đồ số lượng đơn theo trạng thái
  - Biểu đồ timeline đơn hàng theo ngày
  - **KHÔNG thấy**: Doanh thu, cước bán, cước vốn, lợi nhuận, công nợ, hóa đơn thu
  - **KHÔNG thấy**: Bộ lọc đại lý (agency filter)
  - **KHÔNG thấy**: So sánh doanh số giữa các sale
  - **KHÔNG thấy**: Bảng xếp hạng khách hàng/CTV
- **Đơn hàng**: Xem đơn
- **Đơn hàng**: Cập nhật trạng thái từ **"Đã xác nhận" → "Đã nhận hàng"**
- **Pickup**: Xem tất cả pickup, tạo pickup
- **Quét kiện hàng (Scan)**: Có quyền quét

### ❌ Không làm được:
- **Đơn hàng**: KHÔNG tạo đơn
- **Đơn hàng**: KHÔNG sửa thông tin đơn
- **Đơn hàng**: KHÔNG xóa đơn
- **Tải hàng**: **KHÔNG THẤY MENU, KHÔNG CÓ QUYỀN GÌ**
- **Công nợ**: KHÔNG thấy menu
- **Hóa đơn thu**: KHÔNG thấy menu
- **Khách hàng**: KHÔNG thấy menu
- **Địa chỉ**: KHÔNG thấy menu
- **CTV**: KHÔNG thấy menu
- **Nhân sự**: KHÔNG thấy menu
- **Dữ liệu**: KHÔNG thấy menu
- **Cấu hình**: KHÔNG thấy menu
- **Chính sách**: KHÔNG thấy menu
- **Phụ phí**: KHÔNG thấy menu

---

## 7. 🟡 CTV (Cộng tác viên / Khách hàng)

**Tạo đơn và xem công nợ của mình**

### ✅ Làm được:
- **Dashboard**: Xem thống kê của mình
- **Đơn hàng**: Tạo đơn, xem đơn của mình
- **Pickup**: Xem pickup của đơn mình, tạo pickup cho đơn mình
- **Công nợ**: Xem công nợ của chính mình
- **Địa chỉ gửi/nhận**: Xem, tạo, sửa địa chỉ của mình

### ❌ Không làm được:
- **Đơn hàng**: KHÔNG xem đơn của người khác
- **Đơn hàng**: KHÔNG sửa đơn sau khi tạo
- **Đơn hàng**: KHÔNG xóa đơn
- **Tải hàng**: KHÔNG thấy menu
- **Quét kiện**: KHÔNG có quyền
- **Địa chỉ gửi/nhận**: KHÔNG xóa được (chỉ admin)
- **Công nợ**: KHÔNG tạo công nợ
- **Công nợ**: KHÔNG xem công nợ của CTV khác
- **Công nợ đại lý**: KHÔNG thấy menu
- **Khách hàng**: KHÔNG thấy menu
- **Nhân sự**: KHÔNG thấy menu
- **CTV**: KHÔNG thấy menu
- **Dữ liệu**: KHÔNG thấy menu
- **Cấu hình**: KHÔNG thấy menu

---

## 8. ⚫ SHIPPER (Tài xế)

**Xem pickup read-only**

### ✅ Làm được:
- **Pickup**: Xem pickup (read-only)

### ❌ Không làm được:
- **Dashboard**: KHÔNG thấy menu
- **Đơn hàng**: KHÔNG thấy menu
- **Tải hàng**: KHÔNG thấy menu
- **Quét kiện**: KHÔNG có quyền
- **Công nợ**: KHÔNG thấy menu
- **Khách hàng**: KHÔNG thấy menu
- **Tất cả các menu khác**: KHÔNG có quyền

---

## 📊 BẢNG SO SÁNH NHANH

| Chức năng | Admin | Manager | Ketoan | CS | Sale | OPS | CTV | Shipper |
|-----------|-------|---------|--------|----|----|-----|-----|---------|
| **Tạo đơn** | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ✅ | ❌ |
| **Xóa đơn (nhiều trạng thái)** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Xóa đơn (mới tạo)** | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Xem tải hàng** | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Tạo tải hàng** | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Sửa tải hàng** | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Xóa tải hàng** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Quét kiện hàng** | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **Quét barcode tải** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Xóa khách hàng** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Xóa địa chỉ** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Công nợ khách hàng** | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ | Xem | ❌ |
| **Công nợ đại lý** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Nhân sự** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Chính sách** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Settings Admin** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

## 🔑 LƯU Ý QUAN TRỌNG

### Về Tải hàng (Packages):
- **CS**: Được thao tác đầy đủ (tạo, xem, sửa, thêm/xóa đơn, duyệt) nhưng **KHÔNG được xóa tải**
- **OPS**: **HOÀN TOÀN KHÔNG** tham gia quản lý tải hàng, không thấy menu

### Về Xóa đơn hàng:
- **Admin**: Xóa được đơn ở tất cả trạng thái
- **Manager**: Xóa được đơn ở trạng thái Mới tạo, Đã xác nhận, Đã nhận hàng, Duyệt xuất hàng
- **CS**: Chỉ xóa được đơn ở trạng thái "Mới tạo"
- **Sale, Ketoan, OPS, CTV, Shipper**: KHÔNG xóa được đơn

### Về Khách hàng và Địa chỉ:
- **Admin**: Xóa được khách hàng, địa chỉ gửi, địa chỉ nhận
- **CS**: Xem/tạo/sửa, **KHÔNG xóa được**
- **Sale**: Xem/tạo/sửa, **KHÔNG xóa được**
- **Manager**: Xem/tạo/sửa địa chỉ, **KHÔNG xóa được**
- **CTV**: Xem/tạo/sửa địa chỉ của mình, **KHÔNG xóa được**

### Về Công nợ:
- **Kế toán**: Có quyền cập nhật trạng thái công nợ; xóa được công nợ chưa có kế toán phụ trách hoặc do mình phụ trách (mọi trạng thái trừ "Đã thanh toán")
- **Xóa công nợ**: Chỉ Admin, Manager, Kế toán (Sale KHÔNG xóa được)
- **Sale**: Chỉ tạo và xem công nợ
- **CTV**: Chỉ xem công nợ của chính mình

### Về Xóa hóa đơn thu:
- **Chỉ Admin** mới được xóa hóa đơn thu (xóa cứng, kèm file đính kèm và lịch sử trạng thái, không khôi phục được).
- **Bắt buộc nhập lại mật khẩu admin** để xác nhận trước khi xóa.
- **Chỉ xóa được từng hóa đơn một** (không hỗ trợ xóa hàng loạt).
- **Hóa đơn CHƯA thanh toán**: xóa tự do.
- **Hóa đơn ĐÃ thanh toán**:
  - Nếu tạo từ **công nợ**: chỉ xóa được khi công nợ liên quan đã bị xóa.
  - Nếu tạo từ **đơn khách lẻ**: chỉ xóa được khi đơn hàng liên quan đã bị xóa.
- Các role khác: KHÔNG xóa được hóa đơn (chỉ có thể **hủy** hóa đơn theo quyền hiện có).

### Về Pickup:
- **Admin/Manager**: Tạo, sửa mọi trạng thái, chọn OPS, chọn Shipper
- **OPS**: Tạo, sửa pickup của mình (chưa có người hoặc của chính mình), chọn Shipper khi ở trạng thái "Mới tạo" hoặc "Đã xác nhận"
- **CS**: Tạo, sửa pickup ở trạng thái "Mới tạo", chọn OPS, KHÔNG chọn Shipper
- **Sale**: Tạo, sửa pickup của order mình khi còn "Mới tạo", KHÔNG chọn OPS, KHÔNG chọn Shipper
- **CTV**: Tạo, sửa pickup của order mình khi còn "Mới tạo", KHÔNG chọn OPS, KHÔNG chọn Shipper
- **Admin**: Quét kiện hàng + Quét barcode tải
- **OPS**: Chỉ quét kiện hàng (KHÔNG quét barcode tải)
- **CS**: KHÔNG có quyền quét

---

**Tài liệu này được tạo tự động từ source code hệ thống**
**Cập nhật lần cuối: 2026-06-18 (làm rõ Manager quản lý CTV, CS cập nhật trạng thái Duyệt/Đang phát hàng; bổ sung quyền Admin xóa hóa đơn thu có điều kiện; đồng bộ quyền chọn shipper của CS và quyền xóa công nợ)**
