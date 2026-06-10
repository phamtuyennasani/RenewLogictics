# Cách tính giá cước từ bảng giá dịch vụ

Tài liệu này mô tả cách hệ thống tự lấy giá từ menu `Phụ phí / Bảng giá dịch vụ` khi tạo đơn hàng.

## Dữ liệu dùng để dò bảng giá

Khi tạo đơn, hệ thống lấy các dữ liệu sau:

- Dịch vụ chính: `service.id_dichvu`.
- Quốc gia nhận: `receiver.country_id`.
- Danh sách kiện: chiều dài, chiều rộng, chiều cao, cân nặng thực và số kiện.
- DIM đang áp dụng cho khách hoặc hệ thống.

Hệ thống tìm bảng giá mới cập nhật gần nhất có cùng dịch vụ và có áp dụng cho quốc gia nhận.

## Cân nặng tính cước

Mỗi dòng kiện được tính lại ở server:

```text
cân thể tích = dài x rộng x cao / DIM
cân tính cước 1 kiện = max(cân thể tích, cân nặng thực)
```

Quy tắc làm tròn:

- Dưới 21 kg: làm tròn lên mốc 0.5 kg.
- Từ 21 kg trở lên: làm tròn lên kg nguyên.

Tổng cân tính cước của đơn:

```text
tổng cân tính cước = tổng(cân tính cước 1 kiện x số kiện)
```

## Chọn dòng giá

Sau khi có tổng cân tính cước, hệ thống tìm chi tiết bảng giá có:

```text
Cân nặng từ <= tổng cân tính cước <= Cân nặng đến
```

Nếu không có bảng giá hoặc không có dòng cân phù hợp, hệ thống chặn tạo đơn và báo lỗi.

## Quy cách tính giá

Bảng giá có 2 quy cách:

```text
CO_DINH
DON_GIA
```

Với `CO_DINH`, hệ thống lấy nguyên giá trên dòng giá:

```text
cước bán = Cước bán
cước vốn = Cước vốn
cước gốc = Cước gốc
```

Với `DON_GIA`, hệ thống nhân đơn giá với tổng cân tính cước:

```text
cước bán = Cước bán x tổng cân tính cước
cước vốn = Cước vốn x tổng cân tính cước
cước gốc = Cước gốc x tổng cân tính cước
```

Các kết quả được làm tròn về số nguyên.

## Lưu vào đơn hàng

Khi tạo đơn, hệ thống lưu:

- `payment_cuocban.dongiaban`: cước bán.
- `payment_cuocvon.dongiavon`: cước vốn.
- `payment_cuocgoc.dongiagoc`: cước gốc.

Mỗi nhóm payment cũng lưu metadata để đối soát:

- `service_price_list_id`
- `service_price_list_name`
- `service_price_detail_id`
- `service_price_quycach`
- `service_price_weight`
- `service_price_weight_from`
- `service_price_weight_to`
- `service_price_unit`
- `service_price_sale_unit`
- `service_price_cost_unit`
- `service_price_base_unit`
- `service_price_sale_amount`
- `service_price_cost_amount`
- `service_price_base_amount`

Trong đó:

- `service_price_unit`: đơn giá/giá cố định của chính nhóm payment đang lưu. Ví dụ trong `payment_cuocban` là đơn giá bán, trong `payment_cuocvon` là đơn giá vốn.
- `service_price_sale_unit`, `service_price_cost_unit`, `service_price_base_unit`: snapshot đủ 3 đơn giá từ dòng bảng giá tại thời điểm tạo đơn.
- `service_price_sale_amount`, `service_price_cost_amount`, `service_price_base_amount`: số tiền cước chính đã tính ra từ dòng bảng giá, chưa cộng phụ phí.

Các snapshot này giúp đơn hàng giữ được giá đã áp dụng dù bảng giá sau này bị chỉnh sửa. Khi chỉnh cân nặng, hệ thống có thể dùng lại snapshot nếu cân mới vẫn nằm trong khoảng `service_price_weight_from` đến `service_price_weight_to`; nếu vượt khoảng này thì cần dò lại dòng giá mới.

## Tổng cước và phụ phí khi tạo đơn

Khi tạo đơn mới, nếu người dùng chọn phụ phí ở khối `Phụ phí hải quan`, hệ thống sẽ cộng các phụ phí đó vào cả cước bán, cước vốn và cước gốc.

Mỗi dòng phụ phí được tính lại ở server:

```text
tiền phụ phí dòng = số lượng x đơn giá phụ phí
```

Tổng phụ phí:

```text
tổng phụ phí = tổng(tiền phụ phí dòng)
```

VAT và phụ phí xăng dầu mặc định là 0 ở bước tạo đơn ban đầu.

```text
tổng trước VAT = cước chính + phụ phí xăng dầu + tổng phụ phí
tổng sau VAT = cước chính + phụ phí xăng dầu + VAT + tổng phụ phí
```

Ở bước tạo đơn ban đầu:

```text
phụ phí xăng dầu = 0
VAT = 0
tổng trước VAT = cước chính + tổng phụ phí
tổng sau VAT = cước chính + tổng phụ phí
```

Trong đó:

```text
cước bán trước VAT = cước bán từ bảng giá + tổng phụ phí
cước vốn trước VAT = cước vốn từ bảng giá + tổng phụ phí
cước gốc trước VAT = cước gốc từ bảng giá + tổng phụ phí
```

## Lợi nhuận

Hệ thống dùng cùng công thức với màn hình cập nhật cước đơn hàng:

```text
lợi nhuận tạm tính = cước bán trước VAT - cước vốn trước VAT - hoa hồng khách hàng
tỷ suất tạm tính = lợi nhuận tạm tính / cước bán trước VAT x 100
lợi nhuận = lợi nhuận tạm tính - bonus sale
tỷ suất lợi nhuận = lợi nhuận / cước bán trước VAT x 100
```

Khi tạo đơn mới, hoa hồng khách hàng và bonus sale mặc định là 0.

Các chỉ số này được tính và lưu ngay vào `payment_loinhuan` khi tạo đơn:

- `payment_loinhuan.loinhuantamtinh`: lợi nhuận tạm tính.
- `payment_loinhuan.tysuattamtinh`: tỷ suất tạm tính.
- `payment_loinhuan.loinhuan`: lợi nhuận sau bonus sale.
- `payment_loinhuan.tysuatloinhuan`: tỷ suất lợi nhuận sau bonus sale.
- `payment_loinhuan.tysuat`: alias cùng giá trị với `tysuatloinhuan` để tương thích các màn hình cũ.
