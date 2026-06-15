# Hướng Dẫn & Giới Thiệu Hệ Thống Quản Lý Vận Chuyển Quốc Tế

> Tài liệu tổng hợp dành cho khách hàng, chủ doanh nghiệp, đội kinh doanh và người dùng mới. Viết bằng ngôn ngữ dễ hiểu, không yêu cầu kiến thức kỹ thuật. Đây là tài liệu **duy nhất, đầy đủ**: từ giới thiệu giá trị hệ thống, mô tả từng chức năng, đến hướng dẫn cài đặt, nhập liệu và thao tác chi tiết.

---

## Mục lục

**Phần I — Giới thiệu tổng quan**
1. [Hệ thống này là gì?](#1-hệ-thống-này-là-gì)
2. [Hệ thống giải quyết những vấn đề gì?](#2-hệ-thống-giải-quyết-những-vấn-đề-gì)
3. [Bản đồ tổng thể các phân hệ](#3-bản-đồ-tổng-thể-các-phân-hệ)
4. [Các vai trò sử dụng hệ thống](#4-các-vai-trò-sử-dụng-hệ-thống)

**Phần II — Chuẩn bị trước khi vận hành**
5. [Cài đặt hệ thống ban đầu](#5-cài-đặt-hệ-thống-ban-đầu)
6. [Nhập liệu danh mục nền](#6-nhập-liệu-danh-mục-nền)

**Phần III — Vận hành hằng ngày**
7. [Bảng điều khiển (Dashboard)](#7-bảng-điều-khiển-dashboard)
8. [Tạo đơn hàng — hướng dẫn từng bước](#8-tạo-đơn-hàng--hướng-dẫn-từng-bước)
9. [Quản lý & theo dõi đơn hàng](#9-quản-lý--theo-dõi-đơn-hàng)
10. [Nhận hàng (Pickup) & vận hành kho](#10-nhận-hàng-pickup--vận-hành-kho)
11. [Quản lý công nợ](#11-quản-lý-công-nợ)
12. [Hóa đơn & thanh toán](#12-hóa-đơn--thanh-toán)
13. [Khách hàng, nhân sự & cộng tác viên](#13-khách-hàng-nhân-sự--cộng-tác-viên)

**Phần IV — Công cụ mở rộng**
14. [Ứng dụng di động cho Shipper & OPS](#14-ứng-dụng-di-động-cho-shipper--ops)
15. [Tra cứu vận đơn công khai](#15-tra-cứu-vận-đơn-công-khai)

**Phần V — Hỗ trợ vận hành & bán hàng**
16. [Hướng dẫn nhập liệu — những điều dễ sai](#16-hướng-dẫn-nhập-liệu--những-điều-dễ-sai)
17. [Lợi ích nổi bật & điểm bán hàng](#17-lợi-ích-nổi-bật--điểm-bán-hàng)
18. [Gợi ý kịch bản demo cho đội kinh doanh](#18-gợi-ý-kịch-bản-demo-cho-đội-kinh-doanh)

---

# Phần I — Giới thiệu tổng quan

## 1. Hệ thống này là gì?

Đây là **phần mềm quản lý vận chuyển — giao nhận quốc tế tất-cả-trong-một**. Hệ thống thay thế cách làm rời rạc bằng Excel, sổ tay, tin nhắn Zalo và nhiều file công nợ khác nhau, gom toàn bộ hoạt động kinh doanh về một nơi duy nhất.

Từ lúc nhận một kiện hàng cho đến khi giao xong và thu đủ tiền, mọi bước đều được ghi nhận trên cùng một hệ thống:

- Tạo đơn hàng và tự động sinh mã vận đơn không trùng.
- Lên lịch lấy hàng tại nhà khách, giao việc cho shipper.
- Quét mã nhập kho, gom kiện thành lô, đóng tải gửi đi.
- Theo dõi hành trình kiện hàng từ đầu đến cuối.
- Tính cước tự động theo cân nặng thực tế và cân nặng quy đổi.
- Quản lý công nợ khách hàng và công nợ đại lý rạch ròi từng đồng.
- Xuất hóa đơn, thu tiền mặt hoặc quét mã QR ngân hàng.
- Phân quyền chặt chẽ cho từng vị trí trong công ty.

Hệ thống có **hai phần làm việc cùng nhau**:

- **Phần web** (chạy trên trình duyệt máy tính hoặc điện thoại): dành cho văn phòng — kinh doanh, kế toán, chăm sóc khách hàng, quản lý.
- **Ứng dụng di động** (cài trên điện thoại): dành cho nhân viên hiện trường — shipper đi lấy hàng và nhân viên kho (OPS) quét mã nhập kho.

Không cần cài đặt phần mềm phức tạp lên máy tính, chỉ cần trình duyệt web là dùng được ngay.

---

## 2. Hệ thống giải quyết những vấn đề gì?

Nếu doanh nghiệp của bạn đang gặp những tình huống dưới đây, hệ thống được thiết kế đúng để giải quyết:

| Vấn đề thường gặp | Cách hệ thống giải quyết |
|---|---|
| Báo giá chậm, hay tính nhầm cước | Nhập kích thước và cân nặng là **cước tự nhảy ra ngay**, kèm lợi nhuận dự tính |
| Mã đơn trùng, khó tra cứu | **Tự sinh mã vận đơn duy nhất**, không bao giờ trùng |
| Không biết khách nào còn nợ bao nhiêu | Sổ **công nợ khách hàng** cộng dồn tự động theo từng đơn |
| Lẫn lộn tiền thu của khách và tiền nợ đại lý | Tách riêng **hai sổ công nợ** rõ ràng |
| Thu tiền thủ công, khó đối soát | **Quét mã QR ngân hàng**, tiền về là hóa đơn tự đánh dấu đã thanh toán |
| Khách gọi điện hỏi đơn tới đâu liên tục | **Trang tra cứu công khai**, khách tự xem hành trình không cần đăng nhập |
| Nhân viên kho ghi tay, dễ sót kiện | **Quét mã bằng camera điện thoại**, quét hàng loạt, ghi nhận tức thì |
| Không kiểm soát được nhân viên xem gì, sửa gì | **Phân quyền 8 vai trò** + ghi vết lịch sử mọi thay đổi |
| Quản lý không nắm được bức tranh tổng thể | **Dashboard** tổng hợp doanh thu, lợi nhuận, công nợ trên một màn hình |

---

## 3. Bản đồ tổng thể các phân hệ

Hệ thống được chia thành các phân hệ chính, đi theo đúng dòng chảy của một đơn hàng thực tế:

| Phân hệ | Công dụng ngắn gọn |
|---|---|
| **Bảng điều khiển (Dashboard)** | Nhìn toàn cảnh số liệu kinh doanh trong nháy mắt |
| **Đơn hàng** | Tạo, theo dõi, chỉnh sửa, xuất danh sách đơn vận chuyển |
| **Nhận hàng (Pickup)** | Lên lịch lấy hàng tại địa chỉ khách, giao việc cho shipper |
| **Quét kiện & Vận hành kho** | Quét mã nhập kho, gom kiện thành lô, đóng tải |
| **Công nợ khách hàng** | Theo dõi tiền khách hàng còn nợ mình |
| **Công nợ đại lý** | Theo dõi tiền mình còn nợ đại lý / đối tác |
| **Hóa đơn & Thanh toán** | Xuất hóa đơn thu/chi, thu tiền mặt hoặc QR ngân hàng |
| **Khách hàng & Cộng tác viên** | Quản lý hồ sơ khách, CTV, danh bạ địa chỉ gửi/nhận |
| **Nhân sự** | Quản lý nhân viên theo vị trí và phân quyền |
| **Danh mục dữ liệu** | Dịch vụ, bảng giá, quốc gia, đại lý, phụ phí... |
| **Cấu hình** | Logo, banner, thông tin công ty, chính sách |

> Bạn không cần dùng hết tất cả phân hệ ngay từ đầu. Có thể bắt đầu từ tạo đơn và mở rộng dần theo nhu cầu.

---

## 4. Các vai trò sử dụng hệ thống

Hệ thống có sẵn **8 vai trò**, mỗi vai trò chỉ nhìn thấy và thao tác đúng phần việc của mình:

| Vai trò | Tên gọi | Làm gì trên hệ thống |
|---|---|---|
| **Admin** | Quản trị viên | Toàn quyền: cấu hình hệ thống, quản lý nhân sự, mọi dữ liệu |
| **Manager** | Quản lý | Nhìn toàn cảnh doanh thu, lợi nhuận, công nợ; điều hành vận hành |
| **Kế toán** | Kế toán | Duyệt và thu hóa đơn, đối soát công nợ, xác nhận thanh toán |
| **CS** | Chăm sóc khách hàng | Tạo đơn hộ khách, hỗ trợ tra cứu, quản lý danh mục |
| **Sale** | Kinh doanh | Tạo đơn, chăm sóc khách của mình, theo dõi đơn và hoa hồng |
| **OPS** | Vận hành (kho) | Quét kiện nhập kho, tạo phiếu lấy hàng, gán shipper, đóng tải |
| **CTV** | Khách hàng / Cộng tác viên | Tạo đơn cho khách của mình, xem đơn và công nợ của mình |
| **Shipper** | Tài xế giao nhận | Nhận phiếu lấy hàng trên app, đi lấy hàng, cập nhật tiến độ |

**Nguyên tắc bảo mật quan trọng:** Sale chỉ thấy đơn của mình, CTV chỉ thấy đơn của mình, các thông tin nhạy cảm như cước vốn và lợi nhuận chỉ quản lý cấp cao mới xem được. Điều này giúp bảo vệ dữ liệu kinh doanh khi công ty có nhiều nhân viên.

---

# Phần II — Chuẩn bị trước khi vận hành

## 5. Cài đặt hệ thống ban đầu

Trước khi đưa hệ thống vào dùng thật, **Admin** cần cấu hình hai màn hình quan trọng nhất: **Thông tin công ty** và **Cấu hình hệ thống**. Nếu tài khoản không phải Admin sẽ không thấy menu này — hãy nhờ người quản trị thực hiện.

Vào menu: `Cấu hình` → `Cấu hình chung`.

### 5.1 Thông tin công ty

Đây là thông tin nhận diện doanh nghiệp, hiển thị trên hệ thống và làm dữ liệu mặc định khi tạo đơn.

| Trường | Ý nghĩa | Bắt buộc |
|---|---|---|
| Tên công ty | Tên đầy đủ của doanh nghiệp | Có |
| Tên viết tắt | Tên ngắn gọn, hiển thị nhanh | Không |
| Địa chỉ | Địa chỉ trụ sở | Không |
| Số điện thoại | Số liên hệ chính | Không |
| Email | Email liên hệ (đúng định dạng email) | Không |
| Mã số thuế | Mã số thuế doanh nghiệp | Không |
| Website | Trang web (ví dụ bắt đầu bằng `https://`) | Không |
| Người đại diện | Người đại diện pháp lý | Không |
| Tỉnh / thành phố | Khu vực trụ sở | Không |
| Phường / xã | Thuộc tỉnh/thành đã chọn | Không |
| **DIM mặc định** | **Hệ số quy đổi cân nặng (thường là 6000)** | Có |

**Cách nhập tỉnh/thành và phường/xã:** chọn tỉnh/thành trước → danh sách phường/xã tự cập nhật → chọn phường/xã đúng tỉnh đã chọn. Nếu chọn lệch, hệ thống báo lỗi và không cho lưu.

**DIM mặc định là gì?** Là hệ số quy đổi cân nặng từ kích thước kiện hàng (mặc định 6000). DIM ảnh hưởng trực tiếp đến cách tính cước theo thể tích. **Nếu nhập sai DIM, toàn bộ cước của đơn mới có thể bị lệch** — nên thống nhất với bộ phận giá cước/kế toán trước khi đổi.

### 5.2 Cấu hình hệ thống

Màn hình này quản lý các kết nối kỹ thuật, chia thành 3 thẻ: **Thanh toán**, **Hóa đơn**, **Email**. Mọi thay đổi chỉ có hiệu lực **sau khi bấm Lưu cấu hình**.

**Tab Thanh toán** — bật/tắt và cấu hình các cổng thanh toán:
1. Gạt công tắc cổng cần dùng sang trạng thái bật (màu xanh).
2. Nhập thông tin được yêu cầu (tên ngân hàng, số tài khoản... hoặc khóa kết nối API).
3. Với cổng có **khóa bảo mật (API key)**: bấm **Xem / chỉnh sửa** → nhập **mật khẩu Admin** → **Mở khóa** → nhập khóa → **Lưu cấu hình** → nên bấm **Khóa lại** sau khi xong.
4. Khi chưa mở khóa, thao tác lưu **không ghi đè** khóa cũ (an toàn, tránh xóa nhầm).

**Tab Hóa đơn** — bật/tắt nhà cung cấp **hóa đơn điện tử**. Thao tác giống tab Thanh toán. Chỉ bật khi doanh nghiệp đã ký hợp đồng và có thông tin kết nối.

**Tab Email** — bật gửi email tự động và cấu hình máy chủ gửi (SMTP):

| Trường | Ví dụ |
|---|---|
| SMTP Host | smtp.gmail.com |
| SMTP Port | 587 |
| Username | noreply@congty.com |
| Password | (mật khẩu / mật khẩu ứng dụng) |
| From Email | noreply@congty.com |
| From Name | Tên công ty |

> Nếu dùng Gmail, có thể cần tạo **mật khẩu ứng dụng** riêng thay vì mật khẩu đăng nhập thường. Thông tin SMTP thường do bộ phận kỹ thuật hoặc nhà cung cấp email cấp.

### 5.3 Trình tự cài đặt khuyến nghị

1. Nhập **Thông tin công ty** đầy đủ (tên, liên hệ, mã số thuế, DIM) và lưu.
2. **Cấu hình hệ thống → Thanh toán:** bật cổng đang dùng, nhập thông tin.
3. **Tab Hóa đơn** nếu có dùng hóa đơn điện tử.
4. **Tab Email:** bật email đơn hàng và nhập SMTP nếu muốn gửi email tự động.
5. Lưu toàn bộ và kiểm tra bằng một đơn hàng mẫu.

### 5.4 Khuyến nghị an toàn

- Chỉ Admin thao tác cấu hình hệ thống.
- Luôn khóa lại phần API key sau khi chỉnh sửa; không chia sẻ API key cho người không có trách nhiệm.
- Không đổi DIM nếu chưa thống nhất với bộ phận giá cước.
- Ghi lại thời điểm và nội dung mỗi lần thay đổi cấu hình quan trọng.

---

## 6. Nhập liệu danh mục nền

**Danh mục** là dữ liệu nền dùng lặp lại nhiều lần. Nhập đúng danh mục từ đầu giúp tạo đơn nhanh hơn, tính cước chính xác hơn và giảm lỗi vận hành. Khi đã có sẵn danh mục, nhân viên chỉ cần chọn từ danh sách thay vì gõ tay.

Phần này thường do **Admin, CS/quản trị vận hành, Kế toán** phụ trách. Sale và CTV chỉ dùng dữ liệu đã có.

### 6.1 Nguyên tắc nhập liệu chuẩn

- **Đặt tên rõ ràng, thống nhất:** dùng `Mỹ - Bay nhanh`, `Hàng dễ vỡ`, `Phụ phí hải quan` — tránh `dv1`, `test`, `hàng abc`.
- **Không tạo trùng:** đã có `Mỹ` thì đừng tạo thêm `USA`, `United States`.
- **Sửa thay vì xóa** nếu danh mục đã từng dùng trong đơn (xóa sẽ làm khó tra cứu đơn cũ).
- **Kiểm tra lại** trong màn tạo đơn sau khi thêm danh mục mới.

### 6.2 Các nhóm danh mục chính

| Vào menu | Danh mục bên trong | Dùng để |
|---|---|---|
| `Dữ liệu` → `Dịch vụ` | Dịch vụ chính, dịch vụ chi tiết, dịch vụ đi kèm, chi nhánh nhận hàng, tình trạng đơn | Chọn khi tạo đơn |
| `Dữ liệu` → `Đơn vị` | Loại kiện, loại hàng hóa (thùng carton, pallet, hàng dễ vỡ, mỹ phẩm...) | Phân loại kiện & hàng |
| `Dữ liệu` → `Phân loại` | Loại bưu gửi, lý do gửi, hình thức gửi, delivery term, phương tiện | Khai báo & vận chuyển quốc tế |
| `Dữ liệu` → `Quốc gia` | Quốc gia, tỉnh/bang, thành phố | Chọn địa điểm gửi/nhận |
| `Dữ liệu` → `Đại lý` | Đại lý, hãng bay, đối tác trung chuyển | Tuyến mới, đối tác, đóng tải, công nợ đại lý |
| `Dữ liệu` → `Phụ phí` | Phụ phí đơn hàng, loại chi phí HHKH, loại phí chi hộ | Phí phát sinh, ảnh hưởng công nợ & lợi nhuận |

**Bảng giá dịch vụ** quyết định cách tính cước. Mỗi dòng gồm: dịch vụ + quốc gia áp dụng, khoảng cân nặng (từ — đến), **quy cách tính** (Cố định: một mức giá cho cả khoảng cân / Đơn giá: nhân giá với số kg), và **giá bán / giá vốn / giá gốc**.

### 6.3 Checklist trước khi chạy thật

- [ ] Dịch vụ chính, dịch vụ chi tiết, chi nhánh nhận hàng.
- [ ] Tình trạng đơn, loại kiện, loại hàng hóa.
- [ ] Quốc gia, bang/tỉnh, thành phố.
- [ ] Đại lý, hãng bay, đối tác trung chuyển.
- [ ] Phụ phí thường dùng và **bảng giá dịch vụ**.
- [ ] Tài khoản nhân sự và phân quyền đúng.
- [ ] Thông tin công ty và chính sách tạo đơn.
- [ ] Tạo một đơn mẫu để kiểm tra toàn bộ dữ liệu.

---

# Phần III — Vận hành hằng ngày

## 7. Bảng điều khiển (Dashboard)

Đây là trang đầu tiên bạn thấy sau khi đăng nhập. Dashboard cho bạn **bức tranh tổng quan toàn bộ hoạt động kinh doanh** trên một màn hình.

**Hiển thị:** tổng số đơn, đơn đã giao, đơn đang xử lý, đơn hủy/hoàn; doanh thu (cước bán), chi phí (cước vốn), lợi nhuận; tổng công nợ phải thu/phải chi; biểu đồ sản lượng theo tháng và theo trạng thái; top khách hàng, top dịch vụ.

**Cách dùng:** đăng nhập → Dashboard mở sẵn → chọn bộ lọc **từ ngày — đến ngày** (có thể lọc thêm theo sale, khách hàng, dịch vụ, chi nhánh) → các con số và biểu đồ tự cập nhật.

> Mỗi vai trò thấy số liệu phù hợp với quyền của mình: quản lý thấy toàn bộ, sale chủ yếu thấy số liệu đơn của mình.

---

## 8. Tạo đơn hàng — hướng dẫn từng bước

Tạo đơn là nghiệp vụ trung tâm. Vào menu: `Tác vụ` → `Tạo đơn nhanh`.

**Ai tạo được đơn:** Admin (cho mọi sale/CTV), CS (hỗ trợ sale/CTV), Sale (cho khách của mình), CTV (cho chính mình).

Quy trình tạo đơn theo thứ tự:

**Bước 1 — Chọn Sale phụ trách.** Admin/CS thấy ô này; chọn đúng sale để tính doanh số, hoa hồng và phân quyền xem đơn. Sale/CTV thường được tự gán.

**Bước 2 — Người gửi** (khách gửi hàng tại Việt Nam): tên, số điện thoại, địa chỉ, tỉnh/thành, ghi chú lấy hàng. Nếu đã lưu trước, chọn từ danh sách để tự điền lại. Người gửi mới: tích **lưu thông tin** nếu muốn dùng lại lần sau.

**Bước 3 — Người nhận** (ở nước ngoài): tên, số điện thoại, email, quốc gia, bang/tỉnh, thành phố, địa chỉ chi tiết, **postcode/ZIP**.
- Postcode sai có thể làm sai vùng giao hoặc phát sinh phụ phí.
- Địa chỉ nên nhập bằng tiếng Anh hoặc đúng chuẩn nước nhận.
- Với Mỹ, Úc, Canada cần kiểm tra kỹ bang/tỉnh và postcode.

**Bước 4 — Dịch vụ vận chuyển:** chọn dịch vụ chính → dịch vụ chi tiết → tùy chọn đi kèm → kiểm tra chi nhánh nhận hàng. Ví dụ: gửi nhanh đi Mỹ chọn dịch vụ bay nhanh Mỹ; ưu tiên chi phí thấp chọn tuyến tiết kiệm.

**Bước 5 — Kiện hàng:** mỗi kiện nhập số kiện, cân nặng thực tế, dài/rộng/cao (cm), loại kiện. Hệ thống **tự tính cân nặng quy đổi, cân nặng tính cước, tổng số kiện, tổng cân nặng**.
- Cước thường tính theo **số lớn hơn** giữa cân nặng thực tế và cân nặng quy đổi (kiện nhẹ nhưng cồng kềnh có thể bị tính theo quy đổi).
- Mỗi kiện kích thước khác nhau nên nhập riêng; kiểm tra lại tổng cân nặng trước khi tạo đơn.

**Bước 6 — Phụ phí** (nếu có): chọn loại phụ phí (hải quan, vùng xa, đóng gỗ, quá khổ, kiểm hóa, chi hộ...) → nhập số tiền → ghi chú lý do. Chỉ nhập phụ phí đã thống nhất với khách hoặc đúng chính sách.

**Bước 7 — Khai báo hàng hóa / invoice:** tên hàng, số lượng, giá trị khai báo, loại hàng, mã HS. Phục vụ thông quan và đối soát. **Ghi rõ ràng**, ví dụ `áo thun cotton`, `mỹ phẩm chăm sóc da` — không ghi chung chung `hàng hóa`, `stuff`.

**Bước 8 — Ghi chú & ảnh:** ghi chú yêu cầu đặc biệt (lấy hàng sau 18h, hàng dễ vỡ...). Đính kèm tối đa **5 ảnh**, định dạng PNG/JPG/JPEG/WEBP, tối đa 20MB mỗi ảnh (ảnh kiện hàng, tem nhãn, chứng từ).

**Bước 9 — Đồng ý quy định:** tick **"Tôi đã đọc và đồng ý với Quy định tạo đơn"**. Nút Tạo đơn chỉ bấm được sau khi đã tick.

**Bước 10 — Bấm Tạo đơn.** Nếu thiếu thông tin bắt buộc, hệ thống báo và kéo đến vị trí cần bổ sung. Thành công → sinh **mã vận đơn** tự động theo định dạng `BEE + ngày + số thứ tự` (ví dụ `BEE250615001`), đảm bảo không trùng.

### Cách tính cước tự động (3 lớp giá)

Sau khi nhập kiện và chọn dịch vụ + quốc gia nhận, hệ thống tra **bảng giá** và tính:
- **Cước bán:** giá báo cho khách.
- **Cước vốn:** chi phí thực tế (chỉ quản lý xem được).
- **Cước gốc:** giá cơ sở dùng để đối soát.
- **Lợi nhuận dự tính** = cước bán − cước vốn, tính ngay tức thì.

Nếu không có bảng giá khớp dịch vụ + quốc gia + khoảng cân, hệ thống báo lỗi không tính được cước.

---

## 9. Quản lý & theo dõi đơn hàng

Vào `Tác vụ` → `Đơn hàng` để xem toàn bộ đơn dưới dạng bảng (mã đơn, trạng thái, người gửi/nhận, cước, trạng thái thanh toán).

**Có thể làm:**
- **Lọc & tìm kiếm** theo mã đơn, trạng thái, ngày, sale, khách hàng, dịch vụ, chi nhánh, đại lý, hãng bay...
- **Xem chi tiết** một đơn: thông tin đầy đủ, cước chi tiết, lịch sử, ghi chú, ảnh.
- **Cập nhật trạng thái** đơn, hoặc **đổi trạng thái hàng loạt** cho nhiều đơn cùng lúc.
- **Xuất Excel** danh sách đơn để báo cáo.
- Mở màn hình thanh toán hoặc tracking của đơn.

### Các trạng thái của đơn hàng

Đơn hàng đi qua quy trình:

> **Mới tạo → Đã xác nhận → Đã nhận hàng (nhập kho) → Duyệt xuất → Đang phát → Đã giao**

Các trạng thái đặc biệt: **Hủy, Hoàn hàng, Thông quan, Cảnh báo** (khi có vấn đề cần xử lý).

### Lịch sử đơn tự động

Mỗi lần đổi trạng thái, hệ thống tự ghi lại **ai làm, khi nào, từ trạng thái nào sang trạng thái nào, ở đâu**. Đây là phần lịch sử minh bạch, dùng đối soát khi có tranh chấp.

### Chốt cước (khóa giá)

Sau khi thống nhất giá với khách, có thể **chốt cước bán** để khóa giá, ngăn chỉnh sửa về sau. Hệ thống ghi nhận ai chốt và thời điểm. **Đã chốt thì không sửa được** — chỉ chốt khi đã chắc chắn về giá.

---

## 10. Nhận hàng (Pickup) & vận hành kho

Phân hệ này lo phần **hiện trường**: lấy hàng từ khách và xử lý hàng tại kho.

### 10.1 Phiếu lấy hàng (Pickup)

Khi cần đến tận nơi lấy hàng cho khách:
- Tạo **phiếu lấy hàng** từ một đơn, điền địa chỉ người gửi (hoặc lấy sẵn từ đơn) và thời gian hẹn lấy.
- **Gán shipper** phụ trách → shipper nhận thông báo ngay trên điện thoại.
- Theo dõi tiến độ qua các trạng thái.

**Các trạng thái phiếu lấy hàng:**

> **Mới tạo → Đã xác nhận → Đang lấy → Đã lấy**

Nếu không lấy được (khách vắng nhà, sai địa chỉ, khách xin hủy...), shipper **hủy phiếu kèm lý do**, và phiếu có thể giao lại cho shipper khác.

### 10.2 Quét kiện nhập kho

Khi hàng về tới kho, nhân viên OPS dùng app điện thoại **quét mã kiện** để xác nhận nhập kho. Hệ thống tự tìm đơn theo mã hóa đơn, mã tracking, mã tham chiếu hoặc mã kiện, và chỉ cho nhập kho nếu đơn đúng trạng thái. Mọi lần quét đều ghi nhận thời điểm.

### 10.3 Gom kiện & đóng tải (lô hàng)

Để gửi hàng đi theo chuyến/lô:
- Tạo **lô hàng**, thêm các đơn/kiện vào lô.
- Theo dõi tổng số kiện, trọng lượng của cả lô.
- **Duyệt xuất** lô để đóng tải gửi đi.

> Sau khi **duyệt xuất**, lô hàng bị khóa — không thêm/bớt đơn được nữa. Kiểm tra kỹ số kiện và trọng lượng trước khi duyệt.

### 10.4 Ảnh từng giai đoạn

Hệ thống lưu ảnh theo từng bước để minh bạch và đối chứng: ảnh khi tạo đơn, ảnh khi lấy hàng (pickup), ảnh thực tế tại kho, ảnh khi xuất kho.

---

## 11. Quản lý công nợ

Công nợ là phần quan trọng nhất về dòng tiền. Hệ thống tách thành **hai sổ riêng biệt**, tránh nhầm khoản phải thu với phải trả:

| Loại công nợ | Ý nghĩa | Dòng tiền | Vào menu |
|---|---|---|---|
| **Công nợ khách hàng** | Tiền **khách nợ mình** | Thu vào | `Tác vụ` → `Công nợ khách hàng` |
| **Công nợ đại lý** | Tiền **mình nợ đại lý** | Chi ra | `Tác vụ` → `Công nợ đại lý` |

**Ai quản lý:** Admin (toàn quyền), Quản lý (kiểm soát), Kế toán (chốt cước, tạo hóa đơn, thu/chi). CTV chỉ xem công nợ liên quan đến mình. Công nợ đại lý thường chỉ Admin/Quản lý/Kế toán.

### 11.1 Vòng đời một công nợ

> **Chưa chốt cước → Đã chốt cước → Thanh toán một phần → Đã thanh toán** (hoặc **Quá hạn**)

Mỗi đơn sau khi chốt cước **cộng dồn tự động** vào công nợ tương ứng. Kế toán nhìn một màn hình là biết ai nợ bao nhiêu, đã thu/chi bao nhiêu, còn lại bao nhiêu.

### 11.2 Chốt cước — bước then chốt

**Chốt cước** là khóa số tiền công nợ để bắt đầu thu/chi.
- **Trước khi chốt:** có thể sửa cước bán, gỡ đơn khỏi công nợ; chưa tạo được hóa đơn.
- **Sau khi chốt:** số tiền bị khóa, không sửa cước hay gỡ đơn tùy tiện; mở phần hóa đơn thanh toán.
- **Chỉ sau khi chốt cước mới tạo được hóa đơn thu/chi.**

Nên chốt khi đã thống nhất số tiền với khách/đại lý và đơn đã đủ thông tin chi phí.

### 11.3 Quy trình xử lý

**Công nợ khách hàng (thu):** mở chi tiết → kiểm tra các đơn → sửa cước nếu cần (chỉ khi chưa chốt) → **Chốt cước** → tạo **hóa đơn thu** để bắt đầu thu tiền.

**Công nợ đại lý (chi):** mở chi tiết → kiểm tra các đơn → **Chốt cước** → tạo **hóa đơn chi** → khi đã chi tiền cho đại lý, đánh dấu đã thanh toán.

### 11.4 Quy tắc bảo vệ số tiền

> Tổng số tiền các hóa đơn chưa hủy **không được vượt quá** tổng công nợ.

Ví dụ công nợ 15 triệu: tạo hóa đơn 10 triệu → còn tạo được tối đa 5 triệu. Hủy hóa đơn 10 triệu → quay lại tạo được tối đa 15 triệu. Khi tạo hóa đơn, hệ thống hiển thị **số tiền tối đa có thể tạo**.

Khi hóa đơn được thanh toán, hệ thống **tự cập nhật trạng thái thanh toán trên đơn hàng**, giữ công nợ và đơn luôn khớp nhau.

---

## 12. Hóa đơn & thanh toán

Sau khi công nợ chốt cước, tạo hóa đơn để ghi nhận từng lần thu/chi, lưu chứng từ, tạo QR và tự đối soát khi tiền về.

| Loại hóa đơn | Nguồn | Ý nghĩa |
|---|---|---|
| **Hóa đơn thu** | Công nợ khách hàng | Khách trả tiền cho doanh nghiệp |
| **Hóa đơn chi** | Công nợ đại lý | Doanh nghiệp trả tiền cho đại lý |

> Điều kiện tạo hóa đơn: công nợ phải **đã chốt cước**. Nếu chưa, hệ thống chưa hiển thị khu vực tạo hóa đơn.

### 12.1 Hóa đơn thu — thu tiền mặt

1. Mở chi tiết công nợ khách hàng → tạo hóa đơn thu với số tiền cần thu.
2. Admin/Quản lý/Kế toán bấm **Duyệt**.
3. Bấm **Thanh toán** → chọn **Tiền mặt**.
4. **Tải ảnh chứng từ** (phiếu thu, biên nhận) → gửi hóa đơn thanh toán.
5. Kế toán kiểm tra tiền/chứng từ → bấm **Xác nhận đã nhận**.
6. Hóa đơn chuyển sang **Đã thanh toán**.

> Chỉ xác nhận khi tiền thực tế đã về hoặc chứng từ hợp lệ. Sau khi đã thanh toán không nên hủy.

### 12.2 Hóa đơn thu — QR / chuyển khoản (tự đối soát)

1. Mở chi tiết công nợ → tạo hóa đơn thu → Admin/Quản lý/Kế toán **Duyệt**.
2. Bấm **Thanh toán** → chọn **Online / QR** → **Tạo mã QR thanh toán**.
3. Gửi mã QR / thông tin chuyển khoản cho khách.
4. Khách chuyển **đúng số tiền và đúng nội dung**.
5. Khi ngân hàng báo tiền về, **hệ thống tự đối soát và chuyển hóa đơn sang "Đã thanh toán"** nếu thông tin khớp — kế toán không cần thao tác tay.

> Không sửa nội dung chuyển khoản sau khi đã tạo mã. Nếu khách chuyển sai số tiền/nội dung, kế toán kiểm tra thủ công. Có thể **tạo lại mã QR** khi hết hạn (hệ thống giới hạn thời gian tạo lại để tránh thao tác sai).

### 12.3 Trạng thái hóa đơn thu

> Mới tạo → Đã duyệt → Đã gửi yêu cầu thanh toán → **Đã thanh toán** (hoặc **Hủy**)

### 12.4 Hóa đơn chi cho đại lý

1. Mở chi tiết công nợ đại lý → chốt cước nếu chưa → tạo hóa đơn chi với số tiền cần chi.
2. Sau khi đã chuyển tiền thực tế cho đại lý → Admin/Quản lý/Kế toán bấm **Đánh dấu đã chi**.
3. Hóa đơn chuyển sang **Đã thanh toán**, công nợ đại lý cập nhật số đã chi.

Hóa đơn chi đơn giản hơn, chỉ có 3 trạng thái: **Mới tạo → Đã thanh toán** (hoặc **Hủy**). Chỉ đánh dấu đã chi khi tiền đã thực sự chuyển; nếu tạo nhầm thì hủy khi chưa thanh toán.

### 12.5 Hóa đơn điện tử

Hệ thống hỗ trợ phát hành **hóa đơn điện tử** qua nhà cung cấp tích hợp (e-invoice): tạo, xuất, tra cứu hóa đơn theo quy định.

### 12.6 Phân quyền & khuyến nghị kế toán

Chủ yếu **Admin, Quản lý, Kế toán** thao tác hóa đơn (duyệt, hủy, xác nhận thu/chi). Sale/CTV chỉ theo dõi đơn của mình nếu được phân quyền.
- Xử lý hóa đơn theo ngày, đối chiếu giao dịch ngân hàng với danh sách hóa đơn mỗi ngày.
- Không xác nhận thanh toán nếu chưa có tiền/chứng từ hợp lệ.
- Hủy hóa đơn sai càng sớm càng tốt trước khi phát sinh thanh toán.

---

## 13. Khách hàng, nhân sự & cộng tác viên

### 13.1 Quản lý nhân sự

Vào menu `Nhân sự`. Tạo tài khoản nhân viên với: họ tên, tên đăng nhập, mật khẩu, email, điện thoại; rồi **gán vai trò** (Sale, OPS, Kế toán, CS, Shipper, Quản lý...).

**Cách dùng:** `Nhân sự` → chọn loại → **Thêm mới** → điền thông tin → chọn vai trò → **Lưu**. Nhân viên đăng nhập được ngay.

> Chọn đúng vai trò để mỗi người chỉ thấy đúng màn hình cần làm: Sale tạo/theo dõi đơn của mình, Kế toán quản lý công nợ & hóa đơn, OPS quét kiện & tải hàng, CS hỗ trợ tạo đơn & dữ liệu.

### 13.2 Khách hàng & cộng tác viên (CTV)

Vào `Khách hàng` → `Khách hàng`. Tạo hồ sơ khách/CTV với thông tin liên hệ, tài khoản đăng nhập riêng và **sale phụ trách**. CTV có thể tự đăng nhập **xem đơn và công nợ của mình** — hỗ trợ mô hình bán hàng qua cộng tác viên.

Thông tin nên nhập đầy đủ: tên, số điện thoại, email, nhân viên sale phụ trách, ghi chú đặc biệt.

### 13.3 Danh bạ địa chỉ gửi/nhận

- **Địa chỉ gửi** (`Khách hàng` → `Địa chỉ gửi`): tên người gửi, điện thoại, địa chỉ, tỉnh/thành, ghi chú lấy hàng.
- **Địa chỉ nhận** (`Khách hàng` → `Địa chỉ nhận`): tên người nhận, điện thoại, email, quốc gia, bang/tỉnh, thành phố, địa chỉ chi tiết, postcode/ZIP.

Khi tạo đơn, chỉ cần chọn từ danh bạ thay vì gõ lại — tạo đơn lặp lại cho khách quen rất nhanh.

> Mẹo: khi tạo đơn, tích **"Lưu thông tin"** người gửi/nhận để tự động đưa vào danh bạ cho lần sau. Tránh tạo nhiều bản địa chỉ giống nhau cho cùng một khách.

---

# Phần IV — Công cụ mở rộng

## 14. Ứng dụng di động cho Shipper & OPS

Ngoài bản web, hệ thống có **ứng dụng điện thoại** giúp nhân viên hiện trường làm việc nhanh hơn, không phải ngồi máy tính.

### 14.1 Đăng nhập

Đăng nhập bằng tên đăng nhập / mật khẩu công ty cấp. Hỗ trợ **mở khóa bằng Face ID / vân tay** cho lần sau. App tự chuyển sang giao diện Shipper hoặc OPS phù hợp với tài khoản.

### 14.2 Dành cho Shipper (tài xế lấy hàng)

- **Danh sách phiếu lấy hàng** chia theo tab: Mới tạo, Đã xác nhận, Đang lấy, Đã lấy. Tìm theo tên khách, SĐT, mã phiếu.
- **Xem chi tiết phiếu**, cập nhật tiến độ: Xác nhận → Bắt đầu lấy → Đã lấy xong; hoặc **Hủy kèm lý do** (bắt buộc nhập lý do).
- **Gọi điện cho khách** và **xem chỉ đường** (mở Google Maps / Apple Maps) ngay trong app.

### 14.3 Dành cho OPS (nhân viên kho)

- **Quét mã đơn bằng camera** để nhập kho; nếu sai sẽ báo lý do rõ ràng. Có thể nhập tay nếu cần.
- **Quét hàng loạt:** quét liên tục nhiều đơn, cuối cùng tổng kết số thành công / lỗi.
- **Lịch sử quét** trong phiên làm việc.
- **Xem danh sách đơn được giao**, lọc đơn đã / chưa tạo phiếu lấy.
- **Tạo phiếu lấy hàng và chọn shipper** ngay trên điện thoại; xem & giao lại phiếu cho shipper khác.

### 14.4 Thông báo đẩy (Push notification)

Khi được giao phiếu lấy hàng hoặc đơn hàng, shipper/OPS **nhận thông báo ngay trên điện thoại** kể cả khi không mở app. Bấm vào thông báo để mở thẳng phiếu/đơn liên quan.

---

## 15. Tra cứu vận đơn công khai

Khách hàng cuối có thể **tự tra cứu hành trình kiện hàng** mà **không cần đăng nhập**:

1. Truy cập trang tra cứu (đường link công khai dạng `/theo-doi/{mã vận đơn}`).
2. Nhập **mã vận đơn** hoặc mã tracking.
3. Xem trạng thái hiện tại, thông tin người nhận và toàn bộ lịch sử hành trình (thời gian, địa điểm, từng bước).

Tính năng này **giảm tải đáng kể cho bộ phận chăm sóc khách hàng** vì khách tự kiểm tra được đơn của mình.

---

# Phần V — Hỗ trợ vận hành & bán hàng

## 16. Hướng dẫn nhập liệu — những điều dễ sai

Phần này tổng hợp các lỗi nhập liệu phổ biến mà người dùng mới hay mắc. Đọc trước sẽ tránh được nhiều thắc mắc:

### Khi tạo đơn hàng

- **Mã vận đơn tự sinh, đừng sửa tay.** Hệ thống đảm bảo không trùng (`BEE + ngày + số thứ tự`), sửa tay dễ gây đụng mã.
- **Quy tắc làm tròn cân nặng tính cước:** dưới 21kg làm tròn lên mốc **0.5kg**; từ 21kg trở lên làm tròn lên **1kg**. Đừng tự tính tay rồi thắc mắc sai số.
- **Hệ số DIM mặc định là 6000.** Nếu hãng/đối tác dùng hệ số khác (ví dụ 5000), phải đổi đúng, nếu không **cước sẽ sai toàn bộ**.
- **Phải có bảng giá khớp dịch vụ + quốc gia + khoảng cân nặng.** Thiếu là báo lỗi không tính được cước. Khi gửi đi nước mới, kiểm tra bảng giá trước.
- **Postcode/ZIP của người nhận** sai có thể làm sai vùng giao hoặc phát sinh phụ phí — kiểm tra kỹ với Mỹ, Úc, Canada.
- **Khai báo hàng hóa ghi rõ ràng**, không ghi chung chung `hàng hóa`, `stuff`.
- **Phải tích "Lưu thông tin" TRƯỚC khi lưu đơn** nếu muốn người gửi/nhận vào danh bạ.
- **Phải tick đồng ý quy định tạo đơn** mới bấm được nút Tạo đơn.
- **Ảnh đính kèm đơn:** PNG/JPG/JPEG/WEBP, tối đa 5 ảnh, mỗi ảnh ≤ 20MB.

### Khi dựng bảng giá

- **Chọn đúng quy cách "Cố định" hay "Đơn giá".** Chọn sai khiến cước lệch rất lớn (một bên tính cố định cả khoảng cân, một bên nhân theo từng kg).

### Khi xử lý công nợ & hóa đơn

- **Chốt cước là khóa cứng.** Sau khi chốt không sửa được số tiền, không gỡ được đơn. Đừng chốt vội khi giá chưa chắc chắn.
- **Chỉ tạo được hóa đơn sau khi chốt cước.**
- **Số tiền hóa đơn không vượt công nợ còn lại** — hệ thống sẽ chặn nếu nhập quá.
- **QR chuyển khoản:** khách phải chuyển đúng số tiền và đúng nội dung thì mới tự đối soát; sai thì kế toán xử lý thủ công.

### Khi quản lý nhân sự

- **Mật khẩu nhân viên** cần đủ mạnh (tối thiểu 8 ký tự, có chữ hoa, số, ký tự đặc biệt) để không bị từ chối.

### Trên ứng dụng di động

- **OPS chỉ thấy phiếu lấy hàng của chính mình** — đừng tưởng "mất phiếu" khi không thấy phiếu của người khác.
- **Shipper hủy phiếu phải nhập lý do**, không để trống.
- **Đơn chỉ nhập kho được khi đúng trạng thái** (thường là "Đã xác nhận"); nếu bị từ chối, đọc lý do hiển thị.

### Về dữ liệu & lô hàng

- **Lô hàng sau khi "Duyệt xuất" bị khóa** — không thêm/bớt đơn được. Kiểm tra kỹ trước khi duyệt.
- **Cập nhật thay vì xóa** danh mục đã dùng, để không làm khó tra cứu đơn cũ.

---

## 17. Lợi ích nổi bật & điểm bán hàng

Những giá trị cốt lõi nên nhấn mạnh khi giới thiệu hệ thống:

- **Tự động hóa báo giá & lợi nhuận:** nhập kích thước là ra ngay cước bán/vốn/gốc và lợi nhuận. Giảm sai sót, báo giá nhanh.
- **Ba lớp giá + lợi nhuận realtime:** quản lý nhìn được biên lợi nhuận từng đơn — rất giá trị với chủ doanh nghiệp.
- **Tra cứu công khai không cần đăng nhập:** khách tự theo dõi đơn, giảm tải chăm sóc khách hàng.
- **App di động cho Shipper + OPS:** quét mã nhập kho, quét hàng loạt, gọi điện, chỉ đường, đăng nhập vân tay, thông báo đẩy — số hóa toàn bộ khâu hiện trường.
- **Quy trình khép kín:** từ tạo đơn → lấy hàng → nhập kho → gom lô → duyệt xuất → giao → thu tiền → công nợ. Một hệ thống lo trọn.
- **Phân quyền 8 vai trò sẵn có:** triển khai nhanh, không cần cấu hình phức tạp.
- **Ghi vết tự động mọi thay đổi:** ai làm gì, khi nào — minh bạch, hữu ích khi có tranh chấp.
- **Hỗ trợ mô hình cộng tác viên:** CTV tự xem đơn và công nợ của mình.
- **Thanh toán hiện đại:** tạo QR ngân hàng, tiền về tự đối soát, kết nối tracking quốc tế tự động.
- **Danh bạ người gửi/nhận:** tạo đơn lặp lại cho khách quen chỉ trong vài giây.

---

## 18. Gợi ý kịch bản demo cho đội kinh doanh

Demo theo đúng dòng chảy một đơn hàng để khách dễ hình dung (khoảng 15–20 phút):

1. **Mở đầu — Dashboard (1 phút):** cho khách thấy tổng đơn, doanh thu, lợi nhuận, công nợ trên một màn hình. Thông điệp: "kiểm soát cả doanh nghiệp trên một màn hình".

2. **Tạo đơn hàng (4–5 phút) — điểm nhấn mạnh nhất:**
   - Chọn người gửi/nhận từ danh bạ (nhanh).
   - Nhập kích thước kiện → cho khách thấy **cân nặng quy đổi và cước tự nhảy ra ngay**.
   - Mở phần cước bán/vốn/gốc → khoe **lợi nhuận tính tự động**.
   - Thao tác chốt cước.

3. **Vận hành kho + App di động (4–5 phút) — phần gây ấn tượng nhất:**
   - Tạo phiếu lấy hàng, gán shipper → cho thấy **thông báo đẩy về điện thoại shipper**.
   - Mở app: shipper xem phiếu, gọi điện, chỉ đường.
   - OPS **quét mã nhập kho bằng camera** (kể cả quét hàng loạt).
   - Gom lô → duyệt xuất.

4. **Tra cứu công khai (2 phút):** mở link tracking không cần đăng nhập, nhập mã đơn → khách thấy hành trình. Nhấn mạnh "giảm tải CS, khách tự tra".

5. **Thanh toán & công nợ (2–3 phút):** tạo hóa đơn, sinh **mã QR**, xác nhận thanh toán; xem công nợ khách hàng và đại lý.

6. **Phân quyền & lịch sử (1–2 phút):** khoe ghi vết "ai làm gì lúc nào" và phân quyền 8 vai trò.

7. **Chốt (1 phút):** tóm 3 giá trị cốt lõi — *Tự động hóa báo giá & lợi nhuận — Số hóa hiện trường bằng app — Khép kín từ đơn đến thu tiền.*

> **Mẹo cho sale:** chuẩn bị sẵn dữ liệu mẫu đẹp (một khách quen có sẵn danh bạ, một bảng giá đã dựng, một tài khoản shipper demo đã cài app) để mọi thao tác chạy mượt, tránh bị lỗi "không có bảng giá" giữa buổi demo.

---

> *Tài liệu này được tổng hợp từ phân tích thực tế mã nguồn hệ thống (phần web Laravel và ứng dụng di động). Khi hệ thống bổ sung tính năng mới, hãy cập nhật lại các phần tương ứng để tài liệu luôn chính xác.*
