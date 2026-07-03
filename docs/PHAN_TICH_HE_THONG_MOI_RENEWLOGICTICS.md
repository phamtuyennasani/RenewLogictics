# Phan Tich He Thong Moi RenewLogictics

Ngay phan tich: 2026-05-20

> **Cap nhat 2026-07-03:** Cac muc P0/P1 trong tai lieu nay da duoc xu ly:
> - P0 `idCtv` (muc 6): da fix truoc do (dung `idCustomer`).
> - P1 transaction tao don: phan loi (order + tracking history) da boc `DB::transaction`;
>   packages/invoices/photos/contacts fail mem co chu dich (bo sung sau duoc),
>   buoc fail duoc tra ve qua `CreateOrderResult::$warnings` de UI canh bao.
> - P1 `generateMemberCode()`: da boc transaction quanh save contact + unique index `member.code`.
> - P2 ExampleTest: da doi thanh assertRedirect.
> - Ngoai ra: fix 17 route group middleware `can:` vo hieu (xem `tests/Feature/RoutePermissionTest.php`).

## 1. Tong quan

RenewLogictics la ung dung Laravel 13, PHP 8.3, Livewire 4, Flux UI, Tailwind/Vite. He thong moi tap trung vao nghiep vu quan ly van chuyen quoc te, dac biet la module don hang:

- Tao don moi bang Livewire page `resources/views/pages/order/⚡create/create.php`.
- Quan ly danh sach don bang DataTables server-side qua `OrderDataTableController`.
- Theo doi hanh trinh/tracking, thanh toan, cong no, package, pickup, scan, khach hang, CTV, nhan su va du lieu danh muc.
- Tich hop provider ben ngoai theo lop service: SePay payment, SePay eInvoice, TrackingMore.

Ung dung dung `spatie/laravel-permission` cho role/permission. Cac role chinh gom admin, manager, ketoan, cs, sale, ops, ctv, shipper.

## 2. Kien truc chinh

### 2.1 Routing va bao ve truy cap

Route web duoc tach theo nhom `auth`, moi module gan middleware `can:*`.

Module orders co 9 route:

- `orders.index`: Livewire danh sach don.
- `orders.datatable`: API JSON cho DataTables.
- `orders.bulk-status`: cap nhat trang thai hang loat.
- `orders.delete-cancelled`: xoa don da huy, gioi han admin/manager.
- `orders.export`: export CSV toi da 3000 don.
- `orders.create`: Livewire tao don.
- `orders.payment`: man hinh thanh toan.
- `orders.tracking`: man hinh tracking.
- `orders.show`: chi tiet don.

API hien co chu yeu phuc vu webhook SePay:

- `POST /api/webhooks/sepay`
- `POST /api/payment-gateways/sepay/ipn`

### 2.2 Domain model

Model trung tam la `Order`. Don hang luu nhieu du lieu nghiep vu dang JSON:

- `service`
- `sender`
- `receiver`
- `payment_cuocvon`
- `payment_cuocgoc`
- `payment_cuocban`
- `payment_loinhuan`

Quan he chinh:

- `Order -> packages`: cac kien hang.
- `Order -> invoices`: khai bao hang hoa/invoice.
- `Order -> photos`: anh don hang.
- `Order -> histories`: lich su sua don va hanh trinh.
- `Order -> sale/customer/creator/manager/ketoan/ops/cs`: nguoi phu trach.
- `Order -> News` qua JSON path trong `service`, vi du dich vu, chi nhanh nhan hang, loai buu gui.

Huong tiep can nay giup migrate nhanh tu he thong cu vi payload phuc tap duoc giu trong JSON, nhung doi lai can kiem soat schema bang validation/action de tranh du lieu lech chuan.

## 3. Luong tao don moi

Luong tao don moi dang duoc tach tu UI sang action layer:

1. Livewire page khoi tao danh sach sale, khach hang, nguoi gui/nhan, danh muc service, DIM.
2. UI chia thanh nested components:
   - sender
   - receiver
   - service
   - packages
   - phu phi
   - invoice
   - ghi chu/anh
3. Khi submit, page:
   - Chuan hoa role assignment theo sale/ctv/admin.
   - Validate cac truong bat buoc.
   - Chuan hoa service, phu phi.
   - Bat buoc dong y chinh sach tao don.
   - Goi `CreateOrderAction` voi `OrderFormData`.
4. `CreateOrderAction`:
   - Tao ma don bang `GenerateOrderCodeAction`.
   - Tao `orders`.
   - Ghi tracking history trang thai `moi_tao`.
   - Tao packages, invoices, photos.
   - Luu contact sender/receiver neu duoc chon.

`GenerateOrderCodeAction` dung transaction va `lockForUpdate()` tren bang `order_sequences`, format mac dinh `{ORDER_CODE_PREFIX}{YYMMDD}{NNN}`. Day la thiet ke tot cho tranh trung ma don khi nhieu user tao dong thoi.

## 4. Thanh toan, hoa don dien tu, tracking

Provider layer da tach kha ro:

- `ProviderHub`: gateway chung cho payment/einvoice.
- `PaymentProviderManager`: resolve payment driver.
- `EInvoiceProviderManager`: resolve eInvoice driver.
- `SepayPaymentService`: tao QR, parse webhook, verify request, verify signature, kiem tra paid, build gateway checkout form.
- `SepayEInvoiceService`: access token, tao/xuat/xoa/tra cuu hoa don.
- `TrackingMore`: facade domain gom courier, tracking, air waybill.
- `TrackingMoreClient`: HTTP client va xu ly loi API.

Danh sach test hien co tap trung tot vao SePay, eInvoice provider va TrackingMore binding.

## 5. Diem manh

- Da co tach lop giua UI Livewire va action domain cho tao don.
- Ma don co sequence rieng va lock transaction.
- Role-based filtering trong danh sach don: sale chi thay don cua minh, ctv chi thay don cua minh, cs thay don chua gan hoac gan cho minh.
- Lich su don hang duoc ghi qua action rieng, co diff before/after cho cac man hinh sua.
- Provider architecture giup thay the SePay hoac bo sung provider moi ma khong can sua UI.
- Co tai lieu san trong `docs/` cho order create, provider, payment, SePay.
- Co test cho provider/service tich hop quan trong.

## 6. Rủi ro va van de can uu tien

### P0 - Loi runtime khi luu contact sender/receiver

`CreateOrderAction` dang doc `$formData->idCtv`, nhung `OrderFormData` khong co property `idCtv`. Cac vi tri bi anh huong:

- `CreateOrderAction::saveContacts()`
- `CreateOrderAction::saveSenderContact()`
- `CreateOrderAction::saveReceiverContact()`

Khi user tick `saveInfoSender` hoac `saveInfoReceiver`, nguy co phat sinh loi `Undefined property: OrderFormData::$idCtv`. Nen quyet dinh mapping dung:

- Neu `idCustomer` dai dien CTV thi them alias/field `idCtv`.
- Neu `sender.id` moi la CTV/customer dang chon thi tinh ro trong DTO.
- Neu phan biet `customer`, `ctv`, `sender` thi nen dat ten lai de tranh nham nghia.

### P1 - Tao order chua duoc bao boi transaction tong

`CreateOrderAction::execute()` tao order truoc, sau do tung buoc tao packages/invoices/photos/contacts nam trong cac `try/catch` rieng va chi `report($e)`. Neu tao package loi, order van ton tai o trang thai thanh cong mot phan.

Khuyen nghi:

- Boc order + packages + invoices + contact trong `DB::transaction()`.
- Photos la file IO co the xu ly rieng, nhung can co chien luoc rollback/cleanup neu database fail.
- Neu buoc nao bat buoc cho nghiep vu, khong nen nuot exception.

### P1 - `generateMemberCode()` co lock nhung khong nam trong transaction

Ham dung `lockForUpdate()` nhung khong thay transaction bao quanh trong flow hien tai. Tren MySQL, lock row can transaction de dam bao y nghia. Neu nhieu user cung luu contact moi, co nguy co trung code `CUSxxxxxx`.

### P1 - Payment calculation moi chi tinh phu phi hai quan

`calculatePrice()` hien gan phu phi vao cuoc ban/von/goc va tong cuoc, nhung cac thanh phan chinh nhu don gia ban, VAT, PPXD, bonus sale, hoa hong khach hang, loi nhuan thuc te chua duoc tinh trong luong tao don. Tai lieu `payment-calculation-guide.md` co mo ta chi tiet hon, can doi chieu de hoan thien.

### P2 - Test suite co 1 fail do test mau

`php artisan test` ket qua:

- 17 passed
- 1 failed: `tests/Feature/ExampleTest.php`

Ly do: test mong `GET /` tra 200, nhung app tra 302. Day co the la hanh vi dung neu root redirect sang login/dashboard. Nen cap nhat test thanh `assertRedirect()` hoac test route guest/auth dung thuc te.

### P2 - Schema JSON phu thuoc validation UI

Nhieu field quan trong nam trong JSON. Neu co endpoint/import/job tao don khong di qua Livewire validation, du lieu co the khong dong nhat. Nen co validation/cast/DTO chuan o action layer, khong chi nam o component.

## 7. De xuat lo trinh ngan han

1. Sua `OrderFormData` va `CreateOrderAction` de thong nhat `idCustomer/idCtv/sender`.
2. Boc tao don trong transaction va phan loai buoc bat buoc/khong bat buoc.
3. Viet Feature test cho tao don thanh cong, tao packages theo `number_of_package`, upload photo, save contact.
4. Cap nhat test root redirect.
5. Dong bo `calculatePrice()` voi `payment-calculation-guide.md`.
6. Them test concurrency cho `GenerateOrderCodeAction` neu moi truong DB ho tro.
7. Tao schema doc cho JSON `service/sender/receiver/payment_*`.

## 8. Ket luan

He thong moi da co huong kien truc dung: Laravel/Livewire cho UI nghiep vu, action layer cho tao don, provider layer cho tich hop ngoai, enum/permission cho trang thai va quyen. Diem can xu ly som nhat khong phai la framework, ma la tinh nhat quan du lieu va atomicity cua luong tao don. Neu sua cac diem P0/P1, nen tang nay co the mo rong tiep cho payment, tracking va cong no on dinh hon.
