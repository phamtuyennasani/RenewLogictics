# Hướng dẫn Cài đặt Hệ thống

> Tài liệu này hướng dẫn hai màn hình cài đặt quan trọng nhất: **Cấu hình hệ thống** và **Thông tin công ty**. Đây là các bước nên làm trước khi đưa hệ thống vào sử dụng thật.

---

## 1. Ai được vào phần Cài đặt?

Phần lớn cài đặt quan trọng chỉ dành cho **Admin**.

| Màn hình | Ai được dùng |
|----------|--------------|
| Cấu hình hệ thống | Admin |
| Thông tin công ty | Admin |

Nếu bạn không thấy menu này, nghĩa là tài khoản của bạn không phải Admin. Hãy nhờ người quản trị hệ thống thực hiện.

Vào menu:

`Cấu hình` → `Cấu hình chung`

Tại đây sẽ thấy các thẻ cài đặt. Hai mục cần làm trước là:

- **Cấu hình hệ thống**
- **Thông tin công ty**

---

## 2. Thông tin công ty

Đây là thông tin nhận diện doanh nghiệp, dùng để hiển thị trên hệ thống và làm dữ liệu mặc định khi tạo đơn.

### 2.1 Vào màn hình

`Cấu hình` → `Cấu hình chung` → `Thông tin công ty`

### 2.2 Các thông tin cần nhập

| Trường | Ý nghĩa | Bắt buộc |
|--------|---------|----------|
| Tên công ty | Tên đầy đủ của doanh nghiệp | Có |
| Tên viết tắt | Tên ngắn gọn, dùng hiển thị nhanh | Không |
| Địa chỉ | Địa chỉ trụ sở | Không |
| Số điện thoại | Số liên hệ chính | Không |
| Email | Email liên hệ | Không |
| Mã số thuế | Mã số thuế doanh nghiệp | Không |
| Website | Trang web công ty | Không |
| Người đại diện | Người đại diện pháp lý | Không |
| Tỉnh / thành phố | Khu vực trụ sở | Không |
| Phường / xã | Thuộc tỉnh/thành đã chọn | Không |
| DIM mặc định | Hệ số quy đổi cân nặng | Có |

### 2.3 Cách nhập tỉnh/thành và phường/xã

1. Chọn tỉnh/thành phố trước.
2. Sau khi chọn tỉnh/thành, danh sách phường/xã sẽ tự cập nhật.
3. Chọn phường/xã thuộc đúng tỉnh/thành đã chọn.

Nếu chọn phường/xã không thuộc tỉnh/thành, hệ thống sẽ báo lỗi và không cho lưu.

### 2.4 DIM mặc định là gì?

DIM là **hệ số quy đổi cân nặng** dùng để tính cân nặng quy đổi từ kích thước kiện hàng.

- Giá trị mặc định thường là **6000**.
- DIM ảnh hưởng trực tiếp đến cách tính cước theo thể tích.
- Chỉ thay đổi khi bạn hiểu rõ chính sách tính cước của doanh nghiệp.

Lưu ý: nếu nhập sai DIM, toàn bộ cách tính cân nặng quy đổi của các đơn mới có thể bị lệch. Nên thống nhất với bộ phận kế toán/giá cước trước khi đổi.

### 2.5 Lưu thông tin

1. Kiểm tra lại các trường đã nhập.
2. Bấm nút **Lưu**.
3. Hệ thống báo lưu thành công.

### 2.6 Lưu ý

- Email phải đúng định dạng email.
- Website phải đúng định dạng đường dẫn (ví dụ bắt đầu bằng `https://`).
- Tên công ty là bắt buộc, không được để trống.

---

## 3. Cấu hình hệ thống

Màn hình này quản lý các kết nối kỹ thuật của hệ thống. Người dùng bình thường không cần đụng tới, nhưng Admin cần hiểu để bật/tắt đúng tính năng.

### 3.1 Vào màn hình

`Cấu hình` → `Cấu hình chung` → `Cấu hình hệ thống`

Màn hình chia thành 3 thẻ (tab):

- **Thanh toán**
- **Hóa đơn**
- **Email**

Mọi thay đổi chỉ có hiệu lực **sau khi bấm Lưu cấu hình**.

---

### 3.2 Tab Thanh toán

Dùng để bật/tắt và cấu hình các cổng thanh toán mà khách có thể dùng.

#### Cách bật một cổng thanh toán

1. Tìm cổng thanh toán cần dùng.
2. Gạt công tắc sang trạng thái bật (màu xanh).
3. Khi bật, phần cấu hình của cổng sẽ hiện ra.
4. Nhập đầy đủ thông tin được yêu cầu.
5. Bấm **Lưu cấu hình**.

#### Hai loại cổng

- **Cổng thông tin đơn giản:** nhập trực tiếp các thông tin như tên ngân hàng, số tài khoản. Nhập xong và lưu là dùng được.
- **Cổng có khóa bảo mật (API key):** thông tin này nhạy cảm, được che bằng dấu chấm và phải xác thực Admin mới xem/sửa được.

#### Cách xem/sửa thông tin API key bảo mật

1. Bấm nút **Xem / chỉnh sửa** ở cổng có khóa.
2. Hệ thống yêu cầu nhập **mật khẩu Admin**.
3. Nhập đúng mật khẩu và bấm **Mở khóa**.
4. Lúc này mới nhập hoặc chỉnh được khóa kết nối.
5. Sau khi nhập xong, bấm **Lưu cấu hình**.
6. Nên bấm **Khóa lại** sau khi chỉnh xong để bảo vệ thông tin.

#### Lưu ý quan trọng

- Chỉ tài khoản Admin và nhập đúng mật khẩu mới mở khóa được.
- Khi chưa mở khóa, thao tác lưu sẽ **không ghi đè** các khóa cũ. Điều này an toàn, tránh xóa nhầm dữ liệu đang chạy.
- Không chia sẻ API key cho người không có trách nhiệm.

---

### 3.3 Tab Hóa đơn

Dùng để bật/tắt và cấu hình các nhà cung cấp **hóa đơn điện tử**.

Cách thao tác giống tab Thanh toán:

1. Gạt công tắc bật nhà cung cấp cần dùng.
2. Nhập thông tin cấu hình.
3. Nếu có khóa bảo mật, xác thực Admin để xem/sửa.
4. Bấm **Lưu cấu hình**.

Lưu ý: chỉ bật cổng hóa đơn điện tử khi doanh nghiệp đã ký hợp đồng và có thông tin kết nối từ nhà cung cấp.

---

### 3.4 Tab Email

Dùng để bật gửi email tự động và cấu hình máy chủ gửi email (SMTP).

#### Bật email đơn hàng

- Gạt công tắc **Email đơn hàng** để hệ thống tự gửi email thông báo sau khi tạo đơn.

#### Cấu hình SMTP

| Trường | Ý nghĩa | Ví dụ |
|--------|---------|-------|
| SMTP Host | Máy chủ gửi email | smtp.gmail.com |
| SMTP Port | Cổng kết nối | 587 |
| Username | Tài khoản gửi email | noreply@congty.com |
| Password | Mật khẩu / mật khẩu ứng dụng | (bảo mật) |
| From Email | Email người gửi hiển thị | noreply@congty.com |
| From Name | Tên người gửi hiển thị | Tên công ty |

#### Lưu ý

- Thông tin SMTP thường do bộ phận kỹ thuật hoặc nhà cung cấp email cấp.
- Nếu dùng Gmail, có thể cần tạo **mật khẩu ứng dụng** riêng thay vì mật khẩu đăng nhập thường.
- Sau khi nhập xong, bấm **Lưu cấu hình** rồi thử tạo một đơn để kiểm tra email có gửi không.

---

## 4. Trình tự cài đặt khuyến nghị cho khách hàng mới

1. Vào **Thông tin công ty**, nhập đầy đủ tên, liên hệ, mã số thuế, DIM mặc định và lưu.
2. Vào **Cấu hình hệ thống** → tab **Thanh toán**, bật cổng thanh toán đang dùng và nhập thông tin.
3. Sang tab **Hóa đơn** nếu có dùng hóa đơn điện tử.
4. Sang tab **Email**, bật email đơn hàng và nhập SMTP nếu muốn gửi email tự động.
5. Lưu lại toàn bộ và kiểm tra bằng một đơn hàng mẫu.

---

## 5. Các tình huống thường gặp

| Tình huống | Cách xử lý |
|-----------|------------|
| Không thấy menu Cài đặt | Tài khoản không phải Admin, nhờ Admin thực hiện |
| Không lưu được thông tin công ty | Kiểm tra tên công ty, email, website đúng định dạng chưa |
| Không chọn được phường/xã | Chọn tỉnh/thành trước, rồi mới chọn phường/xã |
| Không xem được API key | Cần nhập đúng mật khẩu Admin để mở khóa |
| Lưu xong API key vẫn trống | Phải mở khóa trước khi nhập, nếu chưa mở khóa hệ thống giữ giá trị cũ |
| Bật cổng nhưng chưa hoạt động | Kiểm tra đã nhập đủ thông tin bắt buộc và đã bấm Lưu chưa |
| Email không gửi được | Kiểm tra lại SMTP Host, Port, Username, Password |
| Cước tính sai trên đơn mới | Kiểm tra lại DIM mặc định trong Thông tin công ty |

---

## 6. Khuyến nghị an toàn

- Chỉ Admin mới thao tác cấu hình hệ thống.
- Luôn khóa lại phần API key sau khi chỉnh sửa.
- Không thay đổi DIM nếu chưa thống nhất với bộ phận giá cước.
- Ghi lại thời điểm và nội dung mỗi lần thay đổi cấu hình quan trọng.
- Thử nghiệm bằng đơn mẫu sau mỗi lần thay đổi cấu hình thanh toán hoặc email.