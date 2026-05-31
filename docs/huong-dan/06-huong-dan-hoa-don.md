# Hướng dẫn Hóa đơn và Thanh toán

> Hóa đơn giúp kế toán kiểm soát từng lần thu tiền từ khách hàng và từng lần chi tiền cho đại lý.

---

## 1. Hóa đơn trong hệ thống dùng để làm gì?

Sau khi công nợ được chốt cước, hệ thống cho phép tạo hóa đơn thanh toán.

Hóa đơn giúp:

- Ghi nhận số tiền cần thu hoặc cần chi.
- Theo dõi trạng thái duyệt, gửi thanh toán, đã thanh toán hoặc hủy.
- Lưu chứng từ thanh toán.
- Tạo mã QR để khách chuyển khoản.
- Tự động đối soát thanh toán online khi tiền về.

---

## 2. Hai loại hóa đơn

| Loại hóa đơn | Nguồn phát sinh | Ý nghĩa |
|-------------|-----------------|--------|
| **Hóa đơn thu** | Công nợ khách hàng | Khách hàng trả tiền cho doanh nghiệp |
| **Hóa đơn chi** | Công nợ đại lý | Doanh nghiệp trả tiền cho đại lý |

---

## 3. Điều kiện để tạo hóa đơn

Bạn chỉ tạo được hóa đơn khi công nợ đã được **chốt cước**.

Nếu công nợ chưa chốt, hệ thống sẽ chưa hiển thị khu vực tạo hóa đơn hoặc sẽ báo cần chốt cước trước.

---

## 4. Hóa đơn thu từ khách hàng

### 4.1 Vào màn hình hóa đơn thu

Có hai cách:

### Cách 1: Từ công nợ khách hàng

1. Vào `Tác vụ` → `Công nợ khách hàng`.
2. Mở chi tiết công nợ.
3. Sau khi công nợ đã chốt cước, tìm khu vực **Hóa đơn thanh toán**.
4. Tạo hóa đơn thu tại đây.

### Cách 2: Từ danh sách hóa đơn thu

1. Vào `Tác vụ` → `Hóa đơn thu`.
2. Xem toàn bộ hóa đơn thu.
3. Lọc theo trạng thái, ngày, sale hoặc khách hàng nếu cần.
4. Mở về công nợ gốc để xử lý chi tiết.

---

## 5. Trạng thái hóa đơn thu

Hóa đơn thu có các trạng thái chính:

| Trạng thái | Ý nghĩa |
|-----------|--------|
| Mới tạo | Hóa đơn vừa được tạo, chờ duyệt |
| Đã duyệt | Kế toán / quản lý đã duyệt, sẵn sàng gửi yêu cầu thanh toán |
| Đã gửi hóa đơn thanh toán | Đã gửi yêu cầu thanh toán tiền mặt hoặc chứng từ |
| Đã gửi yêu cầu thanh toán | Đã tạo mã QR / yêu cầu chuyển khoản online |
| Đã thanh toán | Đã nhận đủ tiền |
| Hủy | Hóa đơn không còn hiệu lực |

---

## 6. Quy trình hóa đơn thu bằng tiền mặt

Dùng khi khách thanh toán tiền mặt hoặc gửi chứng từ thanh toán thủ công.

### Các bước

1. Mở chi tiết công nợ khách hàng.
2. Tạo hóa đơn thu với số tiền cần thu.
3. Admin / Quản lý / Kế toán bấm **Duyệt**.
4. Bấm **Thanh toán**.
5. Chọn phương thức **Tiền mặt**.
6. Tải ảnh chứng từ hoặc ảnh hóa đơn nếu hệ thống yêu cầu.
7. Bấm gửi hóa đơn thanh toán.
8. Kế toán kiểm tra tiền/chứng từ.
9. Bấm **Xác nhận đã nhận**.
10. Hóa đơn chuyển sang trạng thái **Đã thanh toán**.

### Lưu ý

- Nên đính kèm ảnh chứng từ rõ ràng.
- Chỉ xác nhận đã nhận khi tiền thực tế đã về hoặc chứng từ hợp lệ.
- Sau khi đã thanh toán, hóa đơn không nên hủy.

---

## 7. Quy trình hóa đơn thu bằng QR / chuyển khoản

Dùng khi khách thanh toán qua ngân hàng.

### Các bước

1. Mở chi tiết công nợ khách hàng.
2. Tạo hóa đơn thu với số tiền cần thu.
3. Admin / Quản lý / Kế toán bấm **Duyệt**.
4. Bấm **Thanh toán**.
5. Chọn phương thức **Online / QR**.
6. Bấm **Tạo mã QR thanh toán**.
7. Gửi mã QR hoặc thông tin chuyển khoản cho khách.
8. Khách chuyển khoản đúng số tiền và đúng nội dung.
9. Khi ngân hàng báo tiền về, hệ thống tự đối soát qua SePay.
10. Hóa đơn tự chuyển sang **Đã thanh toán** nếu thông tin khớp.

### Lưu ý quan trọng

- Không sửa nội dung chuyển khoản nếu hệ thống đã tạo mã.
- Khách cần chuyển đúng số tiền trên hóa đơn.
- Nếu mã QR cũ cần tạo lại, hệ thống có giới hạn thời gian tạo lại để tránh thao tác sai.
- Nếu khách chuyển sai nội dung hoặc sai số tiền, kế toán cần kiểm tra thủ công.

---

## 8. Tạo lại mã QR

Trong một số trường hợp, cần tạo lại QR:

- QR hết hạn.
- QR cũ bị lỗi.
- Khách cần nhận lại mã thanh toán.

Hệ thống có thể giới hạn thời gian tạo lại, ví dụ không cho tạo lại liên tục trong thời gian ngắn.

Nếu không thấy nút tạo lại QR, có thể do:

- Hóa đơn chưa ở đúng trạng thái.
- Bạn không có quyền.
- Chưa hết thời gian chờ tạo lại.
- Hóa đơn đã thanh toán hoặc đã hủy.

---

## 9. Hủy hóa đơn thu

Có thể hủy hóa đơn khi:

- Tạo sai số tiền.
- Tạo nhầm khách.
- Khách đổi phương án thanh toán.
- Hóa đơn chưa thanh toán.

Không nên hủy hóa đơn đã thanh toán. Nếu phát sinh sai sót sau thanh toán, cần báo quản lý hoặc kế toán trưởng để xử lý theo quy trình nội bộ.

---

## 10. Hóa đơn chi cho đại lý

Hóa đơn chi dùng để ghi nhận số tiền doanh nghiệp trả cho đại lý.

### Vào màn hình

1. Vào `Tác vụ` → `Công nợ đại lý`.
2. Mở chi tiết công nợ đại lý.
3. Chốt cước nếu chưa chốt.
4. Tạo hóa đơn chi.

---

## 11. Trạng thái hóa đơn chi

Hóa đơn chi đơn giản hơn hóa đơn thu:

| Trạng thái | Ý nghĩa |
|-----------|--------|
| Mới tạo | Vừa tạo hóa đơn chi |
| Đã thanh toán | Doanh nghiệp đã chi tiền cho đại lý |
| Hủy | Hóa đơn chi không còn hiệu lực |

---

## 12. Quy trình hóa đơn chi

1. Mở chi tiết công nợ đại lý.
2. Tạo hóa đơn chi với số tiền cần chi.
3. Kiểm tra số tiền và ghi chú.
4. Sau khi đã chi tiền cho đại lý, Admin / Quản lý / Kế toán bấm **Đánh dấu đã chi**.
5. Hóa đơn chuyển sang **Đã thanh toán**.
6. Công nợ đại lý được cập nhật số đã thanh toán.

### Lưu ý

- Chỉ đánh dấu đã chi khi tiền đã thực sự chuyển cho đại lý.
- Nếu tạo nhầm, hãy hủy hóa đơn khi chưa thanh toán.
- Nên ghi chú rõ kỳ thanh toán, mã tải hàng hoặc thông tin đối soát.

---

## 13. Quy tắc số tiền tối đa

Khi tạo hóa đơn, hệ thống tính số tiền còn có thể tạo.

Ví dụ công nợ 20 triệu:

- Đã thanh toán: 8 triệu.
- Hóa đơn đang chờ xử lý: 5 triệu.
- Số tiền còn có thể tạo hóa đơn mới: 7 triệu.

Nếu nhập vượt 7 triệu, hệ thống sẽ không cho lưu.

---

## 14. Danh sách hóa đơn thu

Màn hình `Hóa đơn thu` giúp kế toán theo dõi tập trung toàn bộ hóa đơn thu.

Có thể dùng để:

- Xem hóa đơn mới tạo.
- Duyệt hóa đơn.
- Lọc hóa đơn đã thanh toán.
- Tìm hóa đơn theo khách hàng.
- Tìm hóa đơn theo sale.
- Kiểm tra hóa đơn theo khoảng ngày.
- Mở lại công nợ gốc để xem chi tiết.

---

## 15. Phân quyền thao tác hóa đơn

| Vai trò | Thao tác thường có |
|--------|--------------------|
| Admin | Duyệt, hủy, xác nhận, đánh dấu thanh toán |
| Quản lý | Kiểm soát và duyệt theo quyền được cấp |
| Kế toán | Duyệt, tạo yêu cầu thanh toán, xác nhận thu/chi |
| Sale / CTV | Theo dõi liên quan đến khách của mình nếu được phân quyền |

Nếu không thấy nút thao tác, nguyên nhân thường là:

- Bạn không có quyền.
- Hóa đơn đang ở trạng thái không cho thao tác.
- Công nợ chưa chốt.
- Hóa đơn đã thanh toán hoặc đã hủy.

---

## 16. Các tình huống thường gặp

| Tình huống | Cách xử lý |
|-----------|------------|
| Không tạo được hóa đơn | Kiểm tra công nợ đã chốt cước chưa |
| Không thấy nút Duyệt | Kiểm tra quyền Admin / Quản lý / Kế toán |
| Không tạo được QR | Kiểm tra hóa đơn đã duyệt chưa |
| QR không tự thanh toán | Kiểm tra khách chuyển đúng số tiền và nội dung chưa |
| Tạo hóa đơn vượt số tiền | Giảm số tiền hoặc hủy hóa đơn đang chờ |
| Lỡ tạo sai hóa đơn | Hủy hóa đơn sai rồi tạo hóa đơn mới |
| Đã thu tiền nhưng hệ thống chưa cập nhật | Kế toán kiểm tra giao dịch và xác nhận thủ công nếu cần |

---

## 17. Checklist xử lý hóa đơn thu

- [ ] Công nợ đã chốt cước.
- [ ] Số tiền hóa đơn không vượt số còn lại.
- [ ] Hóa đơn đã được duyệt.
- [ ] Đã chọn đúng phương thức: tiền mặt hoặc QR.
- [ ] Nếu tiền mặt: đã có chứng từ.
- [ ] Nếu QR: khách chuyển đúng số tiền và nội dung.
- [ ] Hóa đơn đã chuyển sang Đã thanh toán.
- [ ] Công nợ cập nhật đúng số đã thu.

---

## 18. Checklist xử lý hóa đơn chi

- [ ] Công nợ đại lý đã chốt cước.
- [ ] Số tiền hóa đơn đúng với số cần chi.
- [ ] Đã kiểm tra thông tin đại lý.
- [ ] Đã chuyển tiền thực tế.
- [ ] Đã bấm Đánh dấu đã chi.
- [ ] Công nợ cập nhật đúng số đã thanh toán.

---

## 19. Khuyến nghị kế toán

- Xử lý hóa đơn theo ngày để tránh tồn đọng.
- Đối chiếu giao dịch ngân hàng với danh sách hóa đơn mỗi ngày.
- Không xác nhận thanh toán nếu chưa có tiền hoặc chứng từ hợp lệ.
- Hủy hóa đơn sai càng sớm càng tốt trước khi phát sinh thanh toán.
- Ghi chú rõ ràng khi tạo hóa đơn, đặc biệt với hóa đơn chi cho đại lý.