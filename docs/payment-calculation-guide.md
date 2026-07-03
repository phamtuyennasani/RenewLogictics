# Huong Dan Tinh Payment Don Hang

Tai lieu nay mo ta cong thuc tinh gia don hang.

> **Nguon code (cap nhat 2026-07-03):** toan bo cong thuc trong tai lieu nay
> duoc implement tai `app/Support/OrderPaymentCalculator.php` — nguon su that
> duy nhat, dung chung cho man tao don (`CreateOrderAction`) va man cap nhat gia
> (`resources/views/pages/order/⚡payment.blade.php`). Moi thay doi cong thuc
> phai sua o calculator + cap nhat test so lieu
> `tests/Unit/OrderPaymentCalculatorTest.php`, KHONG sua le o tung man hinh.
>
> Luu y semantics: `total_phuphi` la tong phu phi SAU VAT
> (`total_phuphi_no_vat` la truoc VAT). O buoc tao don VAT phu phi = 0 nen
> hai gia tri bang nhau.

## 1. Nhom Du Lieu Payment

Man hinh payment gom 4 nhom du lieu chinh:

- `payment_cuocban`: cuoc ban bao khach.
- `payment_cuocvon`: cuoc von nha cung ung, kem bonus sale.
- `payment_cuocgoc`: cuoc goc / cuoc cong ty de doi soat noi bo.
- `payment_loinhuan`: snapshot loi nhuan duoc tinh tu cac nhom tren.

## 2. Cuoc Ban Truoc VAT

Gia tri `cuocban.total_tongcuoc_no_vat` duoc tinh tu:

```text
cuocban.total_tongcuoc_no_vat =
    cuocban.dongiaban
    + cuocban.ppxd_amount
    + cuocban.total_phuphi_no_vat
```

Trong do:

- `cuocban.dongiaban`: don gia ban.
- `cuocban.ppxd_amount`: phu phi xang dau tinh theo `%`.
- `cuocban.total_phuphi_no_vat`: tong phu phi ban truoc VAT.

## 3. Bonus Sale

Bonus sale nam trong nhom `cuocvon`.

```text
cuocvon.bonus_sale_amount =
    cuocban.total_tongcuoc_no_vat
    * (cuocvon.bonus_sale_percent / 100)
```

Vi du:

```text
cuocban.total_tongcuoc_no_vat = 10,000,000
cuocvon.bonus_sale_percent = 5

cuocvon.bonus_sale_amount = 10,000,000 * 5 / 100 = 500,000
```

## 4. Hoa Hong Khach Hang

Hoa hong khach hang nam trong:

```text
cuocban.total_hh_khachhang
```

Gia tri nay la tong cac dong trong bucket `cuocban.hh_khachhang`.

## 5. Payment Loi Nhuan

### 5.1. Gia tri dau vao

`payment_loinhuan` lay cac gia tri sau:

```text
payment_loinhuan.cuocban_no_vat = cuocban.total_tongcuoc_no_vat
payment_loinhuan.cuocban = cuocban.total_tongcuoc

payment_loinhuan.cuocvon_no_vat = cuocvon.total_tongcuoc_no_vat
payment_loinhuan.cuocvon = cuocvon.total_tongcuoc

payment_loinhuan.cuocgoc_no_vat = cuocgoc.total_tongcuoc_no_vat
payment_loinhuan.cuocgoc = cuocgoc.total_tongcuoc
```

### 5.2. Loi nhuan tam tinh

```text
payment_loinhuan.loinhuantamtinh =
    payment_loinhuan.cuocban_no_vat
    - payment_loinhuan.cuocvon_no_vat
    - cuocban.total_hh_khachhang
```

Vi du:

```text
payment_loinhuan.cuocban_no_vat = 10,000,000
payment_loinhuan.cuocvon_no_vat = 7,000,000
cuocban.total_hh_khachhang = 500,000

payment_loinhuan.loinhuantamtinh = 10,000,000 - 7,000,000 - 500,000 = 2,500,000
```

### 5.3. Loi nhuan cuoi

```text
payment_loinhuan.loinhuan =
    payment_loinhuan.loinhuantamtinh
    - cuocvon.bonus_sale_amount
```

Vi du:

```text
payment_loinhuan.loinhuantamtinh = 2,500,000
cuocvon.bonus_sale_amount = 500,000

payment_loinhuan.loinhuan = 2,500,000 - 500,000 = 2,000,000
```

## 6. Ty Suat Loi Nhuan

Tat ca ty suat duoc lam tron den 2 chu so thap phan.

### 6.1. Ty suat tam tinh

```text
payment_loinhuan.tysuattamtinh =
    round(
        (payment_loinhuan.loinhuantamtinh * 100)
        / payment_loinhuan.cuocban_no_vat,
        2
    )
```

Vi du:

```text
payment_loinhuan.loinhuantamtinh = 1,525,000
payment_loinhuan.cuocban_no_vat = 10,000,000

payment_loinhuan.tysuattamtinh = 15.25%
```

### 6.2. Ty suat loi nhuan cuoi

```text
payment_loinhuan.tysuat =
    round(
        (payment_loinhuan.loinhuan * 100)
        / payment_loinhuan.cuocban_no_vat,
        2
    )
```

`payment_loinhuan.tysuatloinhuan` hien duoc giu bang `payment_loinhuan.tysuat` de tuong thich voi giao dien cu.

Vi du:

```text
payment_loinhuan.loinhuan = 1,525,000
payment_loinhuan.cuocban_no_vat = 10,000,000

payment_loinhuan.tysuat = 15.25%
payment_loinhuan.tysuatloinhuan = 15.25%
```

## 7. Luu Y Dinh Dang So

Giao dien payment co the nhap tien theo dang:

```text
1.000.000
1,000,000
1000000
```

Ham parse so trong payment page da xu ly cac dang tren de tranh truong hop `1.000.000` bi tinh thanh `1`.

