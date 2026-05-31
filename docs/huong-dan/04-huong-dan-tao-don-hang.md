# Hướng dẫn Tạo Đơn hàng

> Tài liệu này dành cho nhân viên kinh doanh, CS, CTV và quản lý cần tạo đơn vận chuyển trên hệ thống.

---

## 1. Mục đích của màn hình tạo đơn

Màn hình **Tạo đơn nhanh** giúp nhập đầy đủ thông tin một lô hàng trước khi xử lý vận chuyển.

Một đơn hàng thường gồm:

- Sale phụ trách.
- Thông tin người gửi.
- Thông tin người nhận.
- Dịch vụ vận chuyển.
- Danh sách kiện hàng.
- Phụ phí nếu có.
- Khai báo hàng hóa / invoice.
- Ghi chú và ảnh đính kèm.
- Xác nhận quy định tạo đơn.

Sau khi tạo thành công, hệ thống tự sinh mã đơn hàng để theo dõi.

---

## 2. Ai có thể tạo đơn?

Tùy theo phân quyền:

| Vai trò | Khả năng tạo đơn |
|--------|------------------|
| Admin | Tạo đơn cho mọi sale / CTV |
| CS | Tạo đơn hỗ trợ sale / CTV |
| Sale | Tạo đơn cho khách thuộc mình |
| CTV | Tạo đơn của chính mình |

Nếu bạn không thấy menu **Tạo đơn nhanh**, hãy liên hệ quản trị viên để kiểm tra quyền.

---

## 3. Vào màn hình tạo đơn

Từ thanh menu bên trái:

`Tác vụ` → `Tạo đơn nhanh`

Hoặc vào:

`Tác vụ` → `Đơn hàng` → bấm nút tạo mới nếu màn hình có nút này.

---

## 4. Quy trình tạo đơn tổng quát

Thứ tự nhập nên làm theo các bước sau:

1. Chọn sale phụ trách nếu hệ thống yêu cầu.
2. Nhập người gửi.
3. Nhập người nhận.
4. Chọn dịch vụ.
5. Nhập kiện hàng.
6. Nhập phụ phí nếu có.
7. Nhập khai báo hàng hóa.
8. Thêm ghi chú và hình ảnh.
9. Đọc và đồng ý quy định tạo đơn.
10. Bấm **Tạo đơn**.

---

## 5. Bước 1: Chọn Sale phụ trách

Một số tài khoản như Admin hoặc CS sẽ thấy ô **Sale phụ trách**.

### Cách làm

1. Bấm vào ô **Sale phụ trách**.
2. Tìm theo tên hoặc mã nhân viên.
3. Chọn đúng nhân viên kinh doanh phụ trách đơn này.

### Lưu ý

- Chọn đúng sale giúp tính doanh số, hoa hồng và phân quyền xem đơn chính xác.
- Nếu bạn là Sale hoặc CTV, hệ thống có thể tự gán người phụ trách, bạn không cần chọn.

---

## 6. Bước 2: Nhập thông tin người gửi

Phần **Người gửi** là thông tin khách gửi hàng tại Việt Nam.

Thông tin thường cần nhập:

- Tên người gửi.
- Số điện thoại.
- Địa chỉ.
- Tỉnh/thành.
- Ghi chú lấy hàng nếu có.

### Nếu người gửi đã từng lưu

1. Tìm trong danh sách người gửi đã lưu.
2. Chọn đúng người gửi.
3. Hệ thống tự điền lại thông tin.

### Nếu là người gửi mới

1. Nhập đầy đủ thông tin.
2. Nếu muốn dùng lại lần sau, chọn lưu thông tin người gửi nếu màn hình có tùy chọn này.
3. Kiểm tra lại số điện thoại và địa chỉ trước khi qua bước tiếp theo.

---

## 7. Bước 3: Nhập thông tin người nhận

Phần **Người nhận** là thông tin người nhận hàng ở nước ngoài.

Thông tin thường cần nhập:

- Tên người nhận.
- Số điện thoại.
- Email nếu có.
- Quốc gia.
- Bang / tỉnh.
- Thành phố.
- Địa chỉ chi tiết.
- Postcode / ZIP code.

### Lưu ý quan trọng

- Postcode sai có thể làm sai vùng giao hàng hoặc phát sinh phụ phí.
- Địa chỉ nên nhập bằng tiếng Anh hoặc theo đúng chuẩn quốc gia nhận.
- Với Mỹ, Úc, Canada, cần kiểm tra kỹ bang/tỉnh và postcode.
- Nếu người nhận đã lưu, nên chọn từ danh sách để tránh nhập sai.

---

## 8. Bước 4: Chọn dịch vụ vận chuyển

Phần **Dịch vụ** xác định tuyến và cách xử lý hàng.

Thông tin thường gặp:

- Dịch vụ chính.
- Dịch vụ chi tiết.
- Dịch vụ đi kèm nếu có.
- Chi nhánh nhận hàng.
- Tình trạng đơn ban đầu.

### Cách chọn

1. Chọn dịch vụ chính trước.
2. Chọn dịch vụ chi tiết phù hợp.
3. Chọn các tùy chọn đi kèm nếu cần.
4. Kiểm tra lại chi nhánh nhận hàng.

### Ví dụ

- Khách cần gửi nhanh đi Mỹ: chọn dịch vụ bay nhanh Mỹ.
- Khách ưu tiên chi phí thấp: chọn tuyến tiết kiệm nếu có.
- Hàng cần xử lý đặc biệt: chọn dịch vụ đi kèm tương ứng.

---

## 9. Bước 5: Nhập kiện hàng

Phần **Kiện hàng** dùng để nhập số lượng, cân nặng và kích thước từng kiện.

Thông tin thường nhập cho mỗi kiện:

- Số kiện.
- Cân nặng thực tế.
- Dài.
- Rộng.
- Cao.
- Loại kiện / hàng hóa nếu có.

### Hệ thống tự tính gì?

Hệ thống có thể tự tính:

- Cân nặng quy đổi theo kích thước.
- Cân nặng tính cước.
- Tổng số kiện.
- Tổng cân nặng.

### Cách hiểu cân nặng tính cước

Trong vận chuyển quốc tế, phí thường tính theo số lớn hơn giữa:

- Cân nặng thực tế.
- Cân nặng quy đổi từ kích thước.

Ví dụ: kiện nhẹ nhưng rất cồng kềnh có thể bị tính theo cân nặng quy đổi.

### Lưu ý

- Đo kích thước bằng cm nếu hệ thống quy định như vậy.
- Nhập cân nặng chính xác để tránh lệch cước.
- Mỗi kiện nên nhập riêng nếu kích thước khác nhau.
- Kiểm tra lại tổng cân nặng trước khi tạo đơn.

---

## 10. Bước 6: Nhập phụ phí nếu có

Phần **Phụ phí** dùng cho các khoản phát sinh ngoài cước chính.

Ví dụ:

- Phụ phí hải quan.
- Phụ phí vùng xa.
- Phụ phí đóng gỗ.
- Phụ phí hàng quá khổ.
- Phụ phí kiểm hóa.
- Phí chi hộ.

### Cách làm

1. Chọn loại phụ phí.
2. Nhập số tiền hoặc thông tin cần thiết.
3. Ghi chú lý do phát sinh nếu cần.
4. Kiểm tra tổng tiền sau khi thêm phụ phí.

### Lưu ý

Chỉ nhập phụ phí đã thống nhất với khách hoặc đúng chính sách công ty.

---

## 11. Bước 7: Nhập khai báo hàng hóa / invoice

Phần **Invoice** dùng để khai báo nội dung hàng hóa.

Thông tin thường cần:

- Tên hàng.
- Số lượng.
- Giá trị khai báo.
- Loại hàng.
- Ghi chú nếu có.

### Vì sao cần khai báo đúng?

- Phục vụ thông quan.
- Phục vụ đối soát hàng hóa.
- Giảm rủi ro khi hàng bị kiểm tra.
- Giúp bộ phận vận hành xử lý đúng loại hàng.

### Lưu ý

Không khai báo chung chung như `hàng hóa`, `đồ dùng`, `stuff`. Nên ghi rõ hơn, ví dụ:

- `áo thun cotton`
- `sách giấy`
- `mỹ phẩm chăm sóc da`
- `phụ kiện điện thoại`

---

## 12. Bước 8: Ghi chú và ảnh đính kèm

Phần **Ghi chú đơn hàng** dùng để ghi các yêu cầu đặc biệt.

Ví dụ ghi chú:

- Khách yêu cầu lấy hàng sau 18h.
- Hàng dễ vỡ, cần đóng gói kỹ.
- Giao cho người nhận gọi trước.
- Khách đã báo giá riêng.

### Ảnh đính kèm kiện hàng

Hệ thống cho phép đính kèm tối đa 5 ảnh.

Định dạng hỗ trợ:

- PNG.
- JPG.
- JPEG.
- WEBP.

Dung lượng tối đa mỗi ảnh: 20MB.

Nên đính kèm:

- Ảnh kiện hàng trước khi gửi.
- Ảnh tem nhãn.
- Ảnh hàng hóa đặc biệt.
- Ảnh chứng từ nếu cần.

---

## 13. Bước 9: Đọc và đồng ý quy định tạo đơn

Trước khi bấm tạo đơn, hệ thống yêu cầu tick vào:

**Tôi đã đọc và đồng ý với Quy định tạo đơn**

Bạn có thể bấm vào chữ **Quy định tạo đơn** để xem nội dung.

Nút **Tạo đơn** chỉ có thể bấm sau khi đã đồng ý quy định.

---

## 14. Bước 10: Bấm Tạo đơn

Sau khi kiểm tra đầy đủ:

1. Bấm **Tạo đơn**.
2. Chờ hệ thống xử lý.
3. Nếu thiếu thông tin bắt buộc, hệ thống sẽ báo và kéo bạn đến vị trí cần bổ sung.
4. Nếu thành công, hệ thống tạo mã đơn hàng mới.

---

## 15. Sau khi tạo đơn xong

Bạn có thể vào:

`Tác vụ` → `Đơn hàng`

Tại đây có thể:

- Tìm đơn theo mã.
- Xem chi tiết đơn.
- Theo dõi trạng thái.
- Mở màn hình thanh toán.
- Mở màn hình tracking.
- Xuất danh sách đơn.
- Cập nhật trạng thái hàng loạt nếu có quyền.

---

## 16. Tra cứu tracking cho khách

Hệ thống có đường dẫn theo dõi công khai dạng:

`/theo-doi/{mã vận đơn}`

Khách có thể mở link để xem hành trình mà không cần tài khoản đăng nhập.

---

## 17. Các lỗi thường gặp khi tạo đơn

| Lỗi | Nguyên nhân thường gặp | Cách xử lý |
|-----|------------------------|------------|
| Không bấm được nút Tạo đơn | Chưa tick đồng ý quy định | Tick vào ô đồng ý quy định tạo đơn |
| Hệ thống báo thiếu trường | Chưa nhập ô bắt buộc | Bổ sung thông tin được báo |
| Không thấy dịch vụ cần chọn | Chưa nhập danh mục dịch vụ hoặc thiếu quyền | Liên hệ Admin / CS quản trị dữ liệu |
| Không chọn được người gửi | Chưa chọn sale/CTV hoặc chưa lưu địa chỉ | Chọn đúng sale/CTV, kiểm tra danh sách địa chỉ |
| Cân nặng tính cước cao hơn cân thực tế | Kiện hàng cồng kềnh | Kiểm tra lại kích thước dài/rộng/cao |
| Ảnh không tải lên được | Sai định dạng hoặc quá dung lượng | Dùng ảnh PNG/JPG/JPEG/WEBP, dưới 20MB/ảnh |

---

## 18. Checklist trước khi bấm Tạo đơn

- [ ] Đã chọn đúng sale phụ trách.
- [ ] Đã nhập đúng người gửi.
- [ ] Đã nhập đúng người nhận.
- [ ] Đã kiểm tra quốc gia, bang/tỉnh, thành phố, postcode.
- [ ] Đã chọn đúng dịch vụ.
- [ ] Đã nhập đủ kiện hàng.
- [ ] Đã kiểm tra cân nặng và kích thước.
- [ ] Đã nhập phụ phí nếu có.
- [ ] Đã khai báo hàng hóa.
- [ ] Đã thêm ghi chú nếu có.
- [ ] Đã đính kèm ảnh cần thiết.
- [ ] Đã đọc và đồng ý quy định tạo đơn.

---

## 19. Khuyến nghị vận hành

- Luôn tìm khách hàng hoặc địa chỉ đã lưu trước khi nhập mới.
- Không tạo nhiều bản địa chỉ giống nhau cho cùng một khách.
- Kiểm tra postcode trước khi báo giá hoặc tạo đơn quốc tế.
- Chụp ảnh kiện hàng trước khi bàn giao cho kho.
- Nếu phát hiện sai thông tin sau khi tạo đơn, báo người có quyền chỉnh sửa trước khi hàng đi.