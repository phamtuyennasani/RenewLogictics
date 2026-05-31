# Hướng dẫn Quản lý Công nợ

> Công nợ là phần quan trọng nhất về dòng tiền. Tài liệu này giúp kế toán và quản lý theo dõi tiền khách nợ và tiền nợ đại lý một cách rõ ràng.

---

## 1. Hai loại công nợ trong hệ thống

Hệ thống tách công nợ thành hai sổ riêng biệt:

| Loại công nợ | Ý nghĩa | Dòng tiền |
|--------------|---------|-----------|
| **Công nợ khách hàng** | Tiền khách hàng còn nợ doanh nghiệp | Tiền **thu vào** |
| **Công nợ đại lý** | Tiền doanh nghiệp còn nợ đại lý | Tiền **chi ra** |

Việc tách riêng giúp kế toán không nhầm giữa khoản phải thu và khoản phải trả.

---

## 2. Ai quản lý công nợ?

| Vai trò | Quyền với công nợ |
|--------|-------------------|
| Admin | Toàn quyền |
| Quản lý | Theo dõi, kiểm soát |
| Kế toán | Chốt cước, tạo và xử lý hóa đơn, thu/chi |
| CTV | Xem công nợ liên quan đến mình (công nợ khách hàng) |

Công nợ đại lý thường chỉ dành cho Admin, Quản lý, Kế toán.

---

## 3. Vào màn hình công nợ

### Công nợ khách hàng

`Tác vụ` → `Công nợ khách hàng`

### Công nợ đại lý

`Tác vụ` → `Công nợ đại lý`

Mỗi màn hình hiển thị danh sách công nợ, có thể tìm kiếm, lọc và mở chi tiết.

---

## 4. Vòng đời của một công nợ

Một công nợ đi qua các giai đoạn chính:

1. **Chưa chốt cước:** đơn đã có nhưng số tiền chưa được khóa lại.
2. **Đã chốt cước:** số tiền đã được xác nhận, sẵn sàng thu/chi.
3. **Thanh toán một phần:** đã thu/chi được một phần.
4. **Đã thanh toán:** đã thu/chi đủ.
5. **Quá hạn:** đến hạn nhưng chưa thanh toán đủ.

Điểm quan trọng:

> Chỉ sau khi **chốt cước**, hệ thống mới cho phép tạo hóa đơn thu hoặc hóa đơn chi.

---

## 5. Chốt cước là gì và vì sao quan trọng?

**Chốt cước** là bước khóa số tiền công nợ lại để bắt đầu xử lý thanh toán.

Trước khi chốt cước:

- Có thể chỉnh sửa cước bán của đơn.
- Có thể gỡ đơn ra khỏi công nợ.
- Chưa tạo được hóa đơn.

Sau khi chốt cước:

- Số tiền được khóa lại.
- Không còn sửa cước bán hoặc gỡ đơn tùy tiện.
- Mở phần **Hóa đơn thanh toán**.

### Khi nào nên chốt cước?

- Khi đã thống nhất số tiền với khách hoặc đại lý.
- Khi đơn hàng đã đủ thông tin chi phí.
- Khi sẵn sàng thu tiền hoặc chi tiền.

---

## 6. Công nợ khách hàng (Thu tiền)

### 6.1 Mở chi tiết công nợ khách hàng

1. Vào `Tác vụ` → `Công nợ khách hàng`.
2. Tìm khách hàng cần xử lý.
3. Bấm vào dòng để mở chi tiết.

### 6.2 Nội dung màn hình chi tiết

Màn hình chi tiết thường hiển thị:

- Thông tin khách hàng.
- Danh sách đơn hàng thuộc công nợ.
- Tổng cước bán.
- Số đã thanh toán.
- Số còn lại.
- Trạng thái công nợ.
- Khu vực hóa đơn thanh toán (hiện sau khi chốt cước).

### 6.3 Quy trình xử lý

1. Kiểm tra các đơn trong công nợ.
2. Chỉnh sửa cước bán nếu cần (chỉ khi chưa chốt cước).
3. Bấm **Chốt cước** khi số tiền đã chính xác.
4. Sau khi chốt, tạo hóa đơn thu để bắt đầu thu tiền.

Chi tiết về hóa đơn thu xem tại tài liệu:

`06-huong-dan-hoa-don.md`

---

## 7. Công nợ đại lý (Chi tiền)

### 7.1 Mở chi tiết công nợ đại lý

1. Vào `Tác vụ` → `Công nợ đại lý`.
2. Tìm đại lý cần xử lý.
3. Bấm vào dòng để mở chi tiết.

### 7.2 Quy trình xử lý

1. Kiểm tra các đơn thuộc công nợ đại lý.
2. Chốt cước khi số tiền đã thống nhất.
3. Sau khi chốt, tạo hóa đơn chi.
4. Khi đã chi tiền cho đại lý, đánh dấu hóa đơn chi là đã thanh toán.

Hóa đơn chi đơn giản hơn hóa đơn thu, chỉ gồm:

- Mới tạo.
- Đã thanh toán.
- Hủy.

---

## 8. Nguyên tắc số tiền hóa đơn không vượt công nợ

Hệ thống có một quy tắc bảo vệ quan trọng:

> Tổng số tiền các hóa đơn chưa hủy không được vượt quá tổng công nợ.

### Ví dụ dễ hiểu

Giả sử công nợ là 15 triệu:

- Tạo hóa đơn 1: 10 triệu → còn có thể tạo thêm tối đa 5 triệu.
- Nếu hóa đơn 1 được thanh toán → đã thu 10 triệu, còn lại 5 triệu.
- Nếu hủy hóa đơn 1 → quay lại được tạo tối đa 15 triệu.

Khi tạo hóa đơn, hệ thống hiển thị **số tiền tối đa có thể tạo** để tránh nhập vượt.

---

## 9. Đồng bộ trạng thái thanh toán với đơn hàng

Khi hóa đơn được thanh toán:

- Công nợ khách hàng: hệ thống tự cập nhật trạng thái thanh toán của khách trên đơn.
- Công nợ đại lý: hệ thống tự cập nhật trạng thái thanh toán với đại lý trên đơn.

Nhờ vậy, trạng thái tiền nong giữa công nợ và đơn hàng luôn khớp nhau.

---

## 10. Tìm kiếm và lọc công nợ

Trong danh sách công nợ, bạn có thể:

- Tìm theo tên khách hàng hoặc đại lý.
- Lọc theo trạng thái.
- Lọc theo khoảng thời gian nếu có.
- Xuất danh sách công nợ ra file để đối soát.

---

## 11. Xử lý các tình huống thường gặp

| Tình huống | Cách xử lý |
|-----------|------------|
| Chưa thấy phần hóa đơn | Công nợ chưa chốt cước, cần chốt cước trước |
| Không sửa được cước bán | Công nợ đã chốt cước, cần kiểm tra lại quy trình |
| Không gỡ được đơn khỏi công nợ | Công nợ đã chốt cước, đơn đã bị khóa |
| Tạo hóa đơn báo vượt số tiền | Tổng hóa đơn vượt công nợ, giảm số tiền hoặc hủy hóa đơn cũ |
| Thu đủ tiền nhưng trạng thái chưa đổi | Kiểm tra lại hóa đơn đã ở trạng thái đã thanh toán chưa |

---

## 12. Checklist xử lý công nợ

### Công nợ khách hàng

- [ ] Đã kiểm tra danh sách đơn trong công nợ.
- [ ] Đã xác nhận cước bán chính xác.
- [ ] Đã chốt cước.
- [ ] Đã tạo hóa đơn thu.
- [ ] Đã thu tiền và xác nhận thanh toán.
- [ ] Trạng thái công nợ cập nhật đúng.

### Công nợ đại lý

- [ ] Đã kiểm tra danh sách đơn trong công nợ.
- [ ] Đã xác nhận số tiền với đại lý.
- [ ] Đã chốt cước.
- [ ] Đã tạo hóa đơn chi.
- [ ] Đã chi tiền và đánh dấu đã thanh toán.
- [ ] Trạng thái công nợ cập nhật đúng.

---

## 13. Khuyến nghị vận hành

- Chỉ chốt cước khi số tiền đã chắc chắn.
- Đối soát công nợ định kỳ với khách và đại lý.
- Không tạo hóa đơn khống vượt số tiền thực tế.
- Lưu chứng từ thanh toán đầy đủ.
- Khi có sai sót, ưu tiên hủy hóa đơn sai và tạo lại đúng, thay vì sửa lệch số liệu.