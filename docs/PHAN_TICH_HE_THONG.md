# PHÂN TÍCH HỆ THỐNG QUẢN LÝ VẬN CHUYỂN QUỐC TẾ

## 1. Tổng quan hệ thống

Hệ thống quản lý vận chuyển quốc tế được xây dựng trên nền tảng **Laravel 13** kết hợp **Livewire 4** (real-time UI), phục vụ cho doanh nghiệp logistics chuyên vận chuyển hàng hóa quốc tế. Hệ thống giúp quản lý toàn bộ quy trình từ tiếp nhận đơn hàng, lấy hàng, đóng gói, vận chuyển, theo dõi tracking đến thanh toán và công nợ.

### Công nghệ sử dụng

| Thành phần | Công nghệ |
|---|---|
| Backend Framework | Laravel 13 (PHP 8.3+) |
| Frontend Realtime | Livewire 4 + Flux UI |
| CSS Framework | Tailwind CSS |
| Database | MySQL |
| Phân quyền | Spatie Laravel Permission |
| Xuất Excel | Maatwebsite Excel |
| Xuất PDF | DomPDF |
| Tracking API | TrackingMore |
| Thanh toán | SePay (QR Banking) |
| Hóa đơn điện tử | SePay E-Invoice |
| DataTables | Yajra DataTables |

---

## 2. Các module chức năng chính

### 2.1. Quản lý đơn hàng (Orders)

**Mục đích:** Quản lý toàn bộ vòng đời đơn hàng vận chuyển quốc tế từ lúc tạo đến khi giao thành công.

**Chức năng:**
- Tạo đơn hàng mới với đầy đủ thông tin: người gửi, người nhận, kiện hàng, dịch vụ
- Tự động sinh mã đơn hàng (format: BEE + ngày + số thứ tự)
- Tính toán cân nặng tính phí (chargeable weight) tự động theo công thức DIM
- Quản lý trạng thái đơn hàng theo quy trình (FSM):
  - **Mới tạo** → **Đã xác nhận** → **Đã nhận hàng** → **Duyệt xuất hàng** → **Đang phát hàng** → **Đã giao**
- Các trạng thái đặc biệt: Hủy, Hoàn hàng, Cảnh báo, Hải quan thông quan, Cập bến
- Khóa đơn hàng (lock) để ngăn chỉnh sửa
- Xuất dữ liệu đơn hàng
- Lưu lịch sử thay đổi trạng thái
- Đính kèm hình ảnh (ảnh pickup, ảnh thực tế, ảnh xuất kho)

**Thông tin đơn hàng bao gồm:**
- Thông tin người gửi (tên, địa chỉ, tỉnh/thành, phường/xã)
- Thông tin người nhận (tên, địa chỉ, quốc gia, bang/thành phố, mã bưu chính)
- Thông tin kiện hàng (kích thước, cân nặng thực, cân nặng thể tích)
- Dịch vụ vận chuyển (dịch vụ chính, chi tiết, hình thức gửi, loại bưu gửi)
- Thông tin tài chính (cước vốn, cước gốc, cước bán, lợi nhuận)
- Tracking code, mã tham chiếu

---

### 2.2. Quản lý Pickup (Lấy hàng)

**Mục đích:** Quản lý quy trình lấy hàng tại địa chỉ khách hàng.

**Chức năng:**
- Tạo phiếu pickup với mã tự động (format: PICK + 8 ký tự)
- Phân công shipper/tài xế lấy hàng
- Theo dõi trạng thái pickup:
  - **Mới tạo** → **Chờ nhận** → **Đang lấy hàng** → **Đã lấy hàng** → **Đã chốt pickup**
- Ghi nhận thông tin: phương tiện, chi nhánh nhận hàng, địa chỉ lấy hàng
- Tổng hợp cân nặng, số lượng kiện

---

### 2.3. Quản lý Packages / Tải hàng (Lô hàng)

**Mục đích:** Quản lý việc đóng gói và xuất kho hàng hóa theo lô.

**Chức năng:**
- Tạo và quản lý lô hàng
- Quét barcode để thêm đơn vào lô
- Theo dõi trạng thái lô hàng
- Ghi nhận cân nặng thực tế khi xuất kho

---

### 2.4. Scan (Quét mã)

**Mục đích:** Hỗ trợ quét barcode/QR code để xử lý nhanh đơn hàng tại kho.

**Chức năng:**
- Quét mã đơn hàng
- Cập nhật trạng thái nhanh
- Xác nhận nhận hàng/xuất hàng

---

### 2.5. Quản lý Công nợ CTV (Cộng tác viên)

**Mục đích:** Quản lý công nợ giữa công ty và các cộng tác viên/khách hàng.

**Chức năng:**
- Tạo bảng công nợ theo kỳ (từ ngày - đến ngày)
- Tự động sinh mã hóa đơn (format: DEB + ngày + mã ngẫu nhiên)
- Tổng hợp các đơn hàng trong kỳ
- Tính toán: tổng cước, cước vốn, cước bán, hoa hồng
- Quản lý trạng thái thanh toán
- Phân công kế toán phụ trách, sale phụ trách
- Xem chi tiết từng đơn trong bảng công nợ

---

### 2.6. Quản lý Công nợ Đại lý / NCC

**Mục đích:** Quản lý công nợ với các đại lý vận chuyển và nhà cung cấp dịch vụ.

**Chức năng:**
- Tạo và quản lý bảng công nợ đại lý
- Chốt công nợ, xác nhận thanh toán
- Theo dõi trạng thái thanh toán

---

### 2.7. Thống kê & Báo cáo

**Mục đích:** Cung cấp báo cáo tổng hợp về hoạt động kinh doanh.

**Chức năng:**
- Thống kê doanh thu, lợi nhuận
- Báo cáo theo thời gian, theo nhân viên, theo khách hàng
- Xuất báo cáo Excel

---

### 2.8. Quản lý Khách hàng

**Mục đích:** Quản lý thông tin khách hàng và người nhận hàng.

**Chức năng:**
- Quản lý danh sách khách hàng (người gửi)
- Quản lý danh sách người nhận
- Lưu trữ thông tin liên hệ, địa chỉ
- Liên kết khách hàng với CTV/Sale phụ trách
- Hỗ trợ địa chỉ quốc tế (quốc gia, bang, thành phố, mã bưu chính)

---

### 2.9. Quản lý Địa chỉ (Sender / Receiver)

**Mục đích:** Lưu trữ sổ địa chỉ để tái sử dụng khi tạo đơn.

**Chức năng:**
- Quản lý địa chỉ người gửi (trong nước: tỉnh/thành, phường/xã)
- Quản lý địa chỉ người nhận (quốc tế: quốc gia, bang, thành phố, mã bưu chính)
- Chọn nhanh địa chỉ đã lưu khi tạo đơn

---

### 2.10. Quản lý CTV (Cộng tác viên / Khách hàng)

**Mục đích:** Quản lý hệ thống cộng tác viên - những đối tác gửi hàng thường xuyên.

**Chức năng:**
- Thêm, sửa, xóa thông tin CTV
- Cấu hình DIM riêng cho từng CTV
- Gán CTV cho Sale phụ trách
- CTV có thể tự đăng nhập và tạo đơn

---

### 2.11. Quản lý Nhân sự

**Mục đích:** Quản lý nhân viên nội bộ của công ty.

**Chức năng:**
- Quản lý thông tin nhân viên theo phòng ban/vai trò
- Phân quyền truy cập hệ thống
- Các vai trò: Admin, Quản lý, Kế toán, CS, Sale, Ops, Shipper

---

### 2.12. Quản lý Dữ liệu danh mục

**Mục đích:** Quản lý các danh mục dữ liệu tham chiếu dùng trong toàn hệ thống.

**Các nhóm danh mục:**

| Nhóm | Danh mục |
|---|---|
| Dịch vụ | Dịch vụ chính, Dịch vụ chi tiết, Dịch vụ đi kèm, Chi nhánh nhận hàng, Tình trạng đơn |
| Đơn vị | Loại kiện, Hàng hóa |
| Phân loại | Loại bưu gửi, Lý do gửi hàng, Hình thức gửi, Delivery term, Phương tiện |
| Đối tác | Đại lý, Hãng bay, Đối tác chung chuyển |
| Phụ phí | Phụ phí đơn hàng, Loại chi phí HHKH, Loại chi hộ |
| Địa điểm | Quốc gia, Bang/Tỉnh, Thành phố |

---

### 2.13. Tracking đơn hàng (Công khai)

**Mục đích:** Cho phép khách hàng tra cứu trạng thái đơn hàng mà không cần đăng nhập.

**Chức năng:**
- Tra cứu bằng mã đơn hàng (ID Bill)
- Hiển thị lịch sử trạng thái đơn hàng
- Tích hợp TrackingMore API để theo dõi tracking quốc tế
- Giới hạn tần suất truy cập (rate limit: 10 lần/phút)

---

### 2.14. Thanh toán & Hóa đơn

**Mục đích:** Quản lý thanh toán và xuất hóa đơn điện tử.

**Chức năng:**
- Tích hợp cổng thanh toán SePay (QR Banking)
- Tạo mã thanh toán, xác nhận thanh toán qua webhook/IPN
- Xuất hóa đơn điện tử tự động
- Kiến trúc mở rộng: dễ dàng thêm cổng thanh toán mới (VnPay, Momo...)

---

### 2.15. Chính sách

**Mục đích:** Quản lý nội dung chính sách công ty hiển thị cho khách hàng.

**Chức năng:**
- Tạo và chỉnh sửa các trang chính sách
- Hiển thị theo slug URL

---

### 2.16. Cấu hình hệ thống

**Mục đích:** Quản lý các thiết lập chung của hệ thống.

**Chức năng:**
- Cấu hình thông báo
- Quản lý logo, favicon
- Quản lý banner
- Cấu hình mạng xã hội
- Thông tin công ty

---

## 3. Hệ thống phân quyền

Hệ thống phân quyền chi tiết theo vai trò (Role-Based Access Control):

| Vai trò | Mô tả | Quyền chính |
|---|---|---|
| **Admin** | Quản trị viên | Toàn quyền hệ thống |
| **Manager** | Quản lý | Tạo/xóa đơn, quản lý công nợ, xem nhân sự |
| **Kế toán** | Kế toán | Quản lý công nợ, cập nhật trạng thái thanh toán |
| **CS** | Chăm sóc khách hàng | Tạo đơn, xóa đơn mới tạo |
| **Sale** | Kinh doanh | Tạo đơn, tạo/xem công nợ, xóa đơn chưa xác nhận |
| **Ops** | Vận hành | Xem đơn, cập nhật trạng thái nhận hàng |
| **CTV** | Cộng tác viên/Khách hàng | Tạo đơn, xem công nợ của mình |
| **Shipper** | Tài xế | Xem và cập nhật pickup |

---

## 4. Quy trình nghiệp vụ chính

### 4.1. Quy trình xử lý đơn hàng

```
Khách hàng/CTV tạo đơn
        ↓
   [Mới tạo]
        ↓
Sale/CS xác nhận đơn
        ↓
  [Đã xác nhận]
        ↓
Ops xác nhận nhận hàng tại kho
        ↓
  [Đã nhận hàng]
        ↓
Duyệt xuất hàng (đóng lô)
        ↓
 [Duyệt xuất hàng]
        ↓
Hàng đang vận chuyển
        ↓
 [Đang phát hàng]
        ↓
Giao hàng thành công
        ↓
   [Đã giao]
```

### 4.2. Quy trình Pickup

```
Tạo phiếu pickup
        ↓
  [Mới tạo]
        ↓
Phân công shipper
        ↓
  [Chờ nhận]
        ↓
Shipper đang lấy hàng
        ↓
 [Đang lấy hàng]
        ↓
Shipper đã lấy xong
        ↓
 [Đã lấy hàng]
        ↓
Chốt pickup, nhập kho
        ↓
[Đã chốt pickup]
```

### 4.3. Quy trình Công nợ

```
Tạo bảng công nợ (chọn kỳ)
        ↓
Hệ thống tổng hợp đơn hàng trong kỳ
        ↓
Kế toán kiểm tra, chốt công nợ
        ↓
Gửi thông báo cho CTV/Khách hàng
        ↓
CTV/Khách hàng thanh toán
        ↓
Kế toán xác nhận đã thanh toán
```

---

## 5. Tích hợp bên thứ ba

### 5.1. TrackingMore
- Theo dõi tracking quốc tế real-time
- Hỗ trợ nhiều hãng vận chuyển (couriers)
- Tra cứu Air Waybill
- Webhook cập nhật trạng thái tự động

### 5.2. SePay (Thanh toán)
- Tạo QR code thanh toán ngân hàng
- Xác nhận thanh toán tự động qua webhook/IPN
- Đối soát giao dịch

### 5.3. SePay E-Invoice (Hóa đơn điện tử)
- Xuất hóa đơn điện tử tự động
- Quản lý hóa đơn theo quy định pháp luật

---

## 6. Điểm nổi bật của hệ thống

1. **Giao diện real-time:** Sử dụng Livewire 4, mọi thao tác được cập nhật tức thì không cần reload trang
2. **Phân quyền chi tiết:** 8 vai trò với quyền hạn rõ ràng, đảm bảo bảo mật dữ liệu
3. **Tự động hóa:** Tự động tính cước, sinh mã, cập nhật trạng thái
4. **Tracking quốc tế:** Tích hợp TrackingMore theo dõi hàng hóa toàn cầu
5. **Thanh toán tự động:** QR Banking tự động xác nhận qua webhook
6. **Hóa đơn điện tử:** Xuất hóa đơn tự động theo quy định
7. **Kiến trúc mở rộng:** Dễ dàng thêm cổng thanh toán, đơn vị hóa đơn mới
8. **Quản lý công nợ:** Tự động tổng hợp, theo dõi thanh toán
9. **Hỗ trợ đa quốc gia:** Quản lý địa chỉ quốc tế (quốc gia, bang, thành phố, mã bưu chính)
10. **Tra cứu công khai:** Khách hàng tra cứu đơn hàng không cần đăng nhập

---

## 7. Đối tượng sử dụng

| Đối tượng | Cách sử dụng |
|---|---|
| **Ban lãnh đạo** | Xem thống kê, báo cáo doanh thu, quản lý nhân sự |
| **Nhân viên Sale** | Tạo đơn, quản lý khách hàng, theo dõi công nợ |
| **Nhân viên CS** | Hỗ trợ tạo đơn, xử lý vấn đề đơn hàng |
| **Kế toán** | Quản lý công nợ, xác nhận thanh toán, xuất hóa đơn |
| **Nhân viên Ops** | Nhận hàng, đóng gói, xuất kho, quét barcode |
| **Shipper** | Nhận và thực hiện pickup |
| **CTV/Khách hàng** | Tự tạo đơn, theo dõi đơn hàng, xem công nợ |

---

## 8. Lợi ích mang lại

- **Tiết kiệm thời gian:** Tự động hóa quy trình, giảm thao tác thủ công
- **Giảm sai sót:** Tính toán tự động, kiểm soát quy trình chặt chẽ
- **Minh bạch:** Lịch sử thay đổi, tracking real-time, công nợ rõ ràng
- **Mở rộng dễ dàng:** Kiến trúc module, thêm tính năng không ảnh hưởng hệ thống cũ
- **Bảo mật:** Phân quyền chi tiết, mỗi người chỉ thấy dữ liệu thuộc phạm vi mình
- **Chuyên nghiệp:** Hóa đơn điện tử, tracking quốc tế, thanh toán QR