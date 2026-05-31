# Hướng dẫn Nhập liệu Danh mục

> Danh mục là phần dữ liệu nền của hệ thống. Nhập đúng danh mục từ đầu giúp tạo đơn nhanh hơn, tính cước chính xác hơn và giảm lỗi khi vận hành.

---

## 1. Danh mục là gì?

Danh mục là các dữ liệu dùng lặp lại nhiều lần trong hệ thống, ví dụ:

- Dịch vụ vận chuyển.
- Chi nhánh nhận hàng.
- Tình trạng đơn.
- Loại kiện, loại hàng hóa.
- Quốc gia, tỉnh/bang, thành phố.
- Đại lý, hãng bay, đối tác trung chuyển.
- Phụ phí đơn hàng.
- Nhân viên kinh doanh, kế toán, CS, OPS, shipper.
- Chính sách tạo đơn và thông tin công ty.

Khi danh mục được nhập sẵn, nhân viên chỉ cần chọn từ danh sách khi tạo đơn, không phải gõ lại thủ công.

---

## 2. Ai nên nhập danh mục?

Thông thường, phần này do các vai trò sau quản lý:

- **Admin:** có quyền quản lý đầy đủ.
- **CS / Quản trị vận hành:** quản lý dịch vụ, địa chỉ, phân loại, quốc gia.
- **Kế toán:** quản lý phụ phí và các dữ liệu liên quan đến tiền.
- **Quản lý:** theo dõi và kiểm tra dữ liệu đã nhập.

CTV hoặc nhân viên kinh doanh thường chỉ sử dụng dữ liệu đã có, không trực tiếp cấu hình danh mục.

---

## 3. Nguyên tắc nhập liệu chuẩn

### 3.1 Nhập tên rõ ràng, dễ hiểu
Nên dùng tên ngắn, thống nhất, đúng nghiệp vụ.

Ví dụ tốt:

- `Bay nhanh Mỹ`
- `Bay tiết kiệm Úc`
- `Hàng thường`
- `Hàng dễ vỡ`
- `Phụ phí hải quan`

Không nên nhập:

- `dv1`
- `test`
- `hàng abc`
- `phí mới`

### 3.2 Không tạo trùng dữ liệu
Trước khi thêm mới, hãy tìm trong danh sách xem dữ liệu đã tồn tại chưa.

Ví dụ: nếu đã có `Mỹ`, không tạo thêm `USA`, `United States`, `Hoa Kỳ` nếu hệ thống chỉ cần một tên thống nhất.

### 3.3 Cập nhật thay vì xóa nếu dữ liệu đã dùng
Nếu một danh mục đã từng được dùng trong đơn hàng, nên chỉnh sửa tên hoặc trạng thái thay vì xóa. Việc xóa dữ liệu đang dùng có thể làm người dùng khó tra cứu đơn cũ.

### 3.4 Kiểm tra lại sau khi thêm
Sau khi thêm danh mục, mở màn hình tạo đơn để kiểm tra dữ liệu mới đã xuất hiện trong danh sách chọn hay chưa.

---

## 4. Các nhóm danh mục chính

## 4.1 Dịch vụ

Vào menu:

`Dữ liệu` → `Dịch vụ`

Các mục thường gặp:

| Mục | Dùng để làm gì |
|-----|----------------|
| Dịch vụ chính | Nhóm dịch vụ lớn, ví dụ bay nhanh, bay tiết kiệm |
| Dịch vụ chi tiết | Dịch vụ cụ thể cho từng tuyến hoặc phương thức |
| Dịch vụ đi kèm | Các dịch vụ bổ sung |
| Chi nhánh nhận hàng | Nơi tiếp nhận hàng trong nước |
| Tình trạng đơn | Các trạng thái theo dõi đơn hàng |

### Cách thêm dịch vụ mới

1. Vào đúng nhóm dịch vụ cần thêm.
2. Bấm **Thêm mới**.
3. Nhập tên dịch vụ.
4. Nhập các thông tin phụ nếu hệ thống yêu cầu.
5. Bấm **Lưu**.
6. Kiểm tra lại trong màn hình tạo đơn.

### Lưu ý
Tên dịch vụ nên phản ánh rõ tuyến, phương thức hoặc mục đích sử dụng. Ví dụ: `Mỹ - Bay nhanh`, `Úc - Sea`, `Canada - Air`.

---

## 4.2 Đơn vị và loại kiện

Vào menu:

`Dữ liệu` → `Đơn vị`

Các mục thường gặp:

| Mục | Dùng để làm gì |
|-----|----------------|
| Loại kiện | Phân loại kiện hàng khi nhập thông tin kiện |
| Hàng hóa (Loại kiện) | Nhóm hàng hóa để khai báo trong đơn |

### Ví dụ dữ liệu nên có

- Thùng carton.
- Bao tải.
- Pallet.
- Hàng thường.
- Hàng dễ vỡ.
- Hàng thực phẩm khô.
- Hàng mỹ phẩm.
- Hàng điện tử.

---

## 4.3 Phân loại

Vào menu:

`Dữ liệu` → `Phân loại`

Các mục thường gặp:

| Mục | Ý nghĩa |
|-----|--------|
| Loại bưu gửi | Loại hình gửi hàng |
| Lý do gửi hàng | Quà biếu, bán hàng, hàng mẫu... |
| Hình thức gửi hàng | Hình thức vận chuyển |
| Delivery term | Điều kiện giao hàng |
| Phương tiện | Máy bay, xe tải, tàu... |

Dữ liệu này thường dùng khi khai báo hàng hóa và xử lý vận chuyển quốc tế.

---

## 4.4 Quốc gia, tỉnh/bang, thành phố

Vào menu:

`Dữ liệu` → `Quốc gia`

Các mục thường gặp:

| Mục | Dùng để làm gì |
|-----|----------------|
| Quốc gia | Quốc gia nhận hàng |
| Tỉnh / bang | Bang, tỉnh hoặc khu vực |
| Thành phố | Thành phố thuộc quốc gia hoặc bang |

### Cách nhập chuẩn

1. Nhập quốc gia trước.
2. Nhập tỉnh/bang nếu cần.
3. Nhập thành phố sau cùng.
4. Kiểm tra lại khi tạo địa chỉ người nhận.

### Lưu ý
Với các nước như Mỹ, Úc, Canada, nên nhập đúng bang/tỉnh để hỗ trợ tính tuyến, vùng giao và kiểm tra postcode.

---

## 4.5 Đại lý và đối tác

Vào menu:

`Dữ liệu` → `Đại lý`

Các mục thường gặp:

| Mục | Dùng để làm gì |
|-----|----------------|
| Đại lý | Đơn vị nhận xử lý hàng ở đầu nước ngoài hoặc đối tác vận chuyển |
| Hãng bay | Hãng vận chuyển bằng đường hàng không |
| Đối tác trung chuyển | Đơn vị hỗ trợ trung chuyển hàng |

### Khi nào cần nhập đại lý?

- Khi doanh nghiệp phát sinh tuyến mới.
- Khi có đối tác mới tại nước ngoài.
- Khi cần theo dõi công nợ riêng cho từng đại lý.
- Khi đóng tải hàng theo đại lý hoặc hãng bay.

---

## 4.6 Phụ phí

Vào menu:

`Dữ liệu` → `Phụ phí`

Các mục thường gặp:

| Mục | Dùng để làm gì |
|-----|----------------|
| Phụ phí đơn hàng | Phí phát sinh thêm trên đơn |
| Loại chi phí HHKH | Nhóm chi hoa hồng khách hàng |
| Loại phí chi hộ | Nhóm phí doanh nghiệp chi hộ khách |

### Ví dụ phụ phí thường dùng

- Phụ phí hải quan.
- Phụ phí vùng xa.
- Phụ phí hàng quá khổ.
- Phụ phí đóng gỗ.
- Phụ phí kiểm hóa.
- Phụ phí giao tận nhà.

### Lưu ý kế toán
Phụ phí ảnh hưởng trực tiếp đến số tiền khách phải trả, công nợ và lợi nhuận. Chỉ người có trách nhiệm nên thêm hoặc sửa dữ liệu phụ phí.

---

## 5. Nhập khách hàng và địa chỉ

## 5.1 Khách hàng / CTV

Vào menu:

`Khách hàng` → `Khách hàng`

Dùng để quản lý danh sách khách hàng hoặc cộng tác viên.

Thông tin nên nhập đầy đủ:

- Tên khách hàng.
- Số điện thoại.
- Email nếu có.
- Nhân viên sale phụ trách.
- Ghi chú đặc biệt nếu có.

## 5.2 Địa chỉ gửi

Vào menu:

`Khách hàng` → `Địa chỉ gửi`

Dùng để lưu địa chỉ người gửi thường dùng.

Thông tin nên nhập:

- Tên người gửi.
- Số điện thoại.
- Địa chỉ.
- Tỉnh/thành.
- Ghi chú lấy hàng nếu có.

## 5.3 Địa chỉ nhận

Vào menu:

`Khách hàng` → `Địa chỉ nhận`

Dùng để lưu người nhận ở nước ngoài.

Thông tin nên nhập:

- Tên người nhận.
- Số điện thoại.
- Email nếu có.
- Quốc gia.
- Bang/tỉnh.
- Thành phố.
- Địa chỉ chi tiết.
- Postcode / ZIP code.

---

## 6. Nhập nhân sự

Vào menu:

`Nhân sự`

Các nhóm nhân sự:

- Kinh doanh.
- Kế toán.
- CS.
- OPS.
- Shipper.
- Quản lý.

### Lưu ý phân quyền
Khi tạo nhân sự, cần chọn đúng vai trò để người đó chỉ thấy đúng màn hình cần làm việc. Ví dụ:

- Sale: tạo và theo dõi đơn của mình.
- Kế toán: quản lý công nợ và hóa đơn.
- OPS: quét kiện, quản lý tải hàng.
- CS: hỗ trợ tạo đơn và dữ liệu vận hành.

---

## 7. Cấu hình công ty và chính sách

Vào menu:

`Cấu hình`

Các mục thường dùng:

| Mục | Ý nghĩa |
|-----|--------|
| Thông báo | Nội dung thông báo trong hệ thống |
| Logo | Logo thương hiệu |
| Favicon | Biểu tượng nhỏ trên trình duyệt |
| Banner đăng nhập | Hình ảnh màn hình đăng nhập |
| Social | Thông tin mạng xã hội |
| Thông tin công ty | Tên công ty, địa chỉ, liên hệ |
| Hệ thống | Một số cấu hình chung |

Vào menu:

`Cấu hình` → `Chính sách`

Dùng để nhập các quy định tạo đơn, điều khoản và nội dung người dùng cần xác nhận khi tạo đơn hàng.

---

## 8. Checklist trước khi đưa hệ thống vào sử dụng

- [ ] Đã nhập danh sách dịch vụ chính.
- [ ] Đã nhập dịch vụ chi tiết.
- [ ] Đã nhập chi nhánh nhận hàng.
- [ ] Đã nhập tình trạng đơn.
- [ ] Đã nhập loại kiện và loại hàng hóa.
- [ ] Đã nhập quốc gia, bang/tỉnh, thành phố.
- [ ] Đã nhập đại lý, hãng bay, đối tác trung chuyển.
- [ ] Đã nhập phụ phí thường dùng.
- [ ] Đã tạo tài khoản nhân sự.
- [ ] Đã phân quyền đúng cho từng người.
- [ ] Đã nhập thông tin công ty.
- [ ] Đã nhập chính sách tạo đơn.
- [ ] Đã thử tạo một đơn hàng mẫu để kiểm tra dữ liệu.

---

## 9. Lỗi thường gặp khi nhập liệu

| Vấn đề | Cách xử lý |
|--------|------------|
| Không thấy dữ liệu trong màn hình tạo đơn | Kiểm tra đã lưu chưa, đúng nhóm danh mục chưa |
| Dữ liệu bị trùng | Giữ một bản chuẩn, đổi tên hoặc ngừng dùng bản còn lại |
| Nhân viên không thấy menu | Kiểm tra lại vai trò và quyền truy cập |
| Không chọn được quốc gia/bang/thành phố | Kiểm tra thứ tự nhập: quốc gia trước, bang/tỉnh sau, thành phố sau |
| Phụ phí tính sai | Kiểm tra lại danh mục phụ phí và quyền người sửa |

---

## 10. Khuyến nghị vận hành

- Chỉ phân quyền sửa danh mục cho một số người phụ trách.
- Thống nhất cách đặt tên trước khi nhập dữ liệu hàng loạt.
- Không dùng dữ liệu thử nghiệm trên hệ thống thật.
- Kiểm tra danh mục định kỳ mỗi tháng.
- Khi thay đổi giá, phí hoặc tuyến vận chuyển, cần thông báo cho bộ phận tạo đơn và kế toán.