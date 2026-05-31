# Ke hoach trien khai tinh nang Pickup

> Ngay phan tich: 31/05/2026
> Trang thai: De xuat trien khai
> Pham vi: Quan ly lay hang tai dia chi khach hang truoc khi don duoc nhap kho

## 1. Ket luan nhanh

He thong da co mot phan khung legacy cho Pickup nhung chua co luong chay thuc te:

- Da co `App\Models\Pickup`, `App\Enums\PickupStatusEnum`, `App\Models\OrderPhotoPickup`.
- Da co menu, dashboard shortcut, route `pickups.index`, route `pickups.create` va gate `pickups.index`.
- Chua co view `resources/views/pickups/index.blade.php` va `resources/views/pickups/create.blade.php`, nen hai route Pickup hien tai se loi khi truy cap.
- Chua co migration Pickup trong repo, action/service Pickup, relation Pickup - Order, lich su Pickup, trang chi tiet hoac test.
- `Pickup::packages()` dang tro toi `Package::class`, nhung repo khong co model `App\Models\Package`. Model hien tai khong phu hop voi kien truc Order moi.
- `OrderPhotoPickup` dang lien ket truc tiep voi `Order`, chua lien ket voi mot phieu Pickup.

Huong de xuat: xay Pickup thanh aggregate rieng dung truoc moc `OrderStatusEnum::DA_NHAN_HANG`, dung pivot `pickup_orders` de gom mot hoac nhieu don trong mot chuyen lay hang. Diem khoi tao chinh nam trong trang chi tiet don: khi don dang `DA_XAC_NHAN`, hien nut `Tao phieu Pickup`. Khi chot Pickup, he thong cap nhat cac don hop le sang `DA_NHAN_HANG`, dien `ngaynhanhang` va ghi `order_history`.

## 2. Luong nghiep vu de xuat

```text
Mo chi tiet don DA_XAC_NHAN
  -> bam "Tao phieu Pickup"
  -> mo form Pickup voi don hien tai da duoc chon san
  -> co the bo sung don DA_XAC_NHAN khac cua cung dia chi lay hang neu can
  -> gan shipper, lich hen, phuong tien, chi nhanh nhan hang
  -> trang thai MOI_TAO_PICKUP

Dieu phoi Pickup
  -> chuyen PICKUP_CHO_NHAN
  -> shipper bat dau lay: PICKUP_DANG_LAY
  -> shipper xac nhan da lay: PICKUP_DA_LAY
  -> ghi chu va tai anh pickup neu co

Chot Pickup / nhap kho
  -> lock phieu Pickup va cac don
  -> validate phieu chua chot, co it nhat mot don, cac don con hop le
  -> chuyen Pickup sang DA_CHOT_PICKUP
  -> chuyen don DA_XAC_NHAN sang DA_NHAN_HANG
  -> dien ngaynhanhang neu chua co
  -> ghi lich su cho Pickup va tung don

Sau do
  -> don DA_NHAN_HANG du dieu kien vao module Tai hang
  -> module Tai hang tiep tuc duyet xuat sang DUYET_XUAT_HANG
```

Khong nen chen trang thai Pickup vao `OrderStatusEnum`. Pickup la mot quy trinh van hanh rieng co the gom nhieu don, con trang thai don chi can phan anh moc da nhap kho.

### 2.1. Diem khoi tao tu chi tiet don

Tai `resources/views/pages/order/⚡show.blade.php`, method `actionButtons()` dang tao cac nut thao tac theo `bill_status`.

Can bo sung nut:

```text
Neu don.bill_status === DA_XAC_NHAN
  va don chua thuoc Pickup dang mo
  va user co quyen tao Pickup
    -> hien nut "Tao phieu Pickup"
```

Khi bam nut, mo modal `Tao Pickup moi` ngay trong trang chi tiet don. Modal doc don hien tai, tu dong do thong tin `sender` va cho phep dieu chinh snapshot Pickup truoc khi luu. Khong can dieu huong sang trang khac.

Neu don da co Pickup, khong hien nut tao lai. Tai vi tri thao tac Pickup chi hien text:

```text
Da tao Pickup: <ma_pickup>
```

Ma Pickup nen la link tro toi `pickups.show` de co the xem chi tiet khi can, nhung giao dien khong can them nut `Xem phieu Pickup`.

Can chot them: trang chi tiet don hien dang cho phep chuyen truc tiep `DA_XAC_NHAN -> DA_NHAN_HANG`. Neu Pickup la bat buoc, phai an nut `Nhan hang` khi don da thuoc luong Pickup va chi cho `ClosePickupAction` cap nhat moc nay.

## 3. Kien truc du lieu

### 3.1. Bang `pickups`

De xuat tao bang moi theo convention Laravel thay vi dua truc tiep vao bang legacy `pickup`:

| Cot | Y nghia |
| --- | --- |
| `id` | Khoa chinh |
| `code` | Ma `PICK-YYMMDD-NNNN` hoac giu format legacy `PICKxxxxxxxx` neu khach hang yeu cau |
| `status` | Gia tri tu `PickupStatusEnum` |
| `created_by` | Nguoi tao |
| `shipper_id` | Shipper duoc phan cong |
| `scheduled_at` | Thoi gian hen lay |
| `started_at` | Thoi gian bat dau lay |
| `picked_up_at` | Thoi gian lay xong |
| `closed_at` | Thoi gian chot nhap kho |
| `closed_by` | Nguoi chot |
| `branch_id` | Chi nhanh nhan hang, nullable |
| `vehicle_id` | Phuong tien, nullable |
| `sender_snapshot` | JSON snapshot dia chi va lien he lay hang |
| `packages_count` | So luong kien du kien |
| `total_weight` | Can nang du kien |
| `labor_cost` | Chi phi cong |
| `note` | Ghi chu |
| `orders_count` | Tong so don |
| `total_chargeable_weight` | Tong can tinh phi |
| `created_at`, `updated_at` | Audit co ban |

### 3.2. Bang `pickup_orders`

| Cot | Y nghia |
| --- | --- |
| `id` | Khoa chinh |
| `pickup_id` | FK toi `pickups` |
| `id_order` | FK toi `orders` |
| `added_by` | Nguoi them don |
| `created_at`, `updated_at` | Audit co ban |

Can dat unique key tren `id_order` cho MVP: mot don chi thuoc mot Pickup. Neu sau nay co lay hang bo sung hoac lay lai, doi sang rang buoc "mot Pickup dang mo" va them lich su lan lay.

### 3.3. Bang `pickup_histories`

Luu audit rieng cho thao tac dieu phoi:

- `pickup_id`, `id_user`, `from_status`, `to_status`.
- `happened_at`, `note`.
- `metadata` JSON de luu thong tin bo sung khi can.

### 3.4. Anh Pickup

MVP co the giu `order_photo_pickup` vi anh dang gan voi don. Can bo sung migration neu bang chua ton tai va them quan he `Order::pickupPhotos()`.

Neu khach hang muon anh chung cho ca chuyen lay hang, bo sung `pickup_photos` rieng thay vi nhan ban anh cho moi don.

### 3.5. Legacy migration

Truoc khi code migration can kiem tra DB production:

- Bang legacy `pickup` co dang du lieu that hay khong.
- Bang `order_photo_pickup` co ton tai hay khong.
- Co can migrate du lieu cu sang `pickups`, `pickup_orders` hay chi tao module moi.

## 4. Action va model can them

De giu logic nghiep vu ngoai Livewire, tao cac action nho tuong tu module Tai hang:

| Action | Trach nhiem |
| --- | --- |
| `CreatePickupAction` | Tao Pickup, sinh code, attach cac don hop le trong transaction |
| `AddOrdersToPickupAction` | Them don, lock va validate cung dia chi lay hang |
| `RemoveOrderFromPickupAction` | Xoa don khi Pickup chua chot |
| `AssignPickupShipperAction` | Gan shipper va chuyen sang `PICKUP_CHO_NHAN` |
| `TransitionPickupStatusAction` | Kiem soat FSM Pickup va luu audit |
| `ClosePickupAction` | Chot Pickup, cap nhat don sang `DA_NHAN_HANG`, dien ngay nhan, ghi history |
| `SyncPickupTotalsAction` | Tinh lai so don, so kien, tong can tinh phi |

Model can cap nhat:

- `Pickup`: doi sang schema moi, cast `PickupStatusEnum`, relation `orders`, `histories`, `shipper`, `creator`.
- `Order`: them relation `pickups` hoac `pickup`, them `pickupPhotos`.
- `PickupStatusEnum`: them `allowedTransitions()` va `canEditOrders()`.
- `OrderPhotoPickup`: bo sung xu ly file neu module co upload/xoa anh.

## 5. UI de xuat

Dung Livewire single-file component giong module `resources/views/pages/packages`.

### 5.1. `pages::pickups.index`

- Filter theo ma Pickup, trang thai, shipper, ngay tao, ngay hen.
- Bang: ma Pickup, ngay hen, dia chi lay, shipper, so don, so kien, tong can, trang thai.
- Nut tao Pickup va vao chi tiet.

### 5.2. Modal tao Pickup trong chi tiet don

- Duoc mo tu nut `Tao phieu Pickup` tai chi tiet don `DA_XAC_NHAN`.
- Dung chinh don dang xem lam don khoi tao.
- Hien thi ma don khoi tao o dau modal de nguoi dung biet dang tao Pickup cho don nao.
- Cho bo sung don `DA_XAC_NHAN` chua thuoc Pickup neu cung dia chi lay hang.
- Khong hien dropdown `Chon khach hang`.
- Tu dong do thong tin nguoi gui tu `order.sender` cua don khoi tao vao form Pickup.
- Cho phep dieu chinh cac field nguoi gui tren form truoc khi tao Pickup.
- Khi luu, ghi thong tin da dieu chinh vao `sender_snapshot` cua Pickup; khong tu dong sua `order.sender` goc.
- Tim don bo sung theo ma don, tracking, so dien thoai, dia chi neu cho phep gom nhieu don.
- Chi cho gom cac don cung dia chi lay hang trong mot phieu.
- Chon shipper, lich hen, chi nhanh, phuong tien, ghi chu.
- Hien summary don, kien va can tinh phi.

Form tao Pickup gom cac field:

| Field | Nguon mac dinh | Bat buoc | Ghi chu |
| --- | --- | --- | --- |
| Ten cong ty | `order.sender.company` | Co | Cho phep sua |
| Ten nguoi gui | `order.sender.fullname` | Co | Cho phep sua |
| So dien thoai | `order.sender.phone` | Co | Cho phep sua |
| Email | `order.sender.email` | Khong | Cho phep sua |
| Quoc gia | Viet Nam | Co | Co the khoa neu chi Pickup noi dia |
| Tinh / Thanh pho | `order.sender.province_id` hoac field tuong ung | Co | Cho phep sua |
| Phuong / Xa | `order.sender.ward_id` hoac field tuong ung | Co | Cho phep sua |
| Dia chi | `order.sender.address` | Co | Cho phep sua |
| Phuong tien | Danh muc `phuongtien` | Co | Nguoi dung chon |
| Ngay hen | Thoi diem hien tai hoac de trong | Co | Datetime picker |
| So luong kien | Tong kien cua don neu co | Khong | Cho phep sua |
| Can nang | Tong can cua don neu co | Khong | Cho phep sua |
| Chi phi cong | `0` | Khong | Cho phep nhap |
| Chi nhanh nhan hang | Dich vu / chi nhanh cua don neu co | Khong | Cho phep chon |
| Ghi chu | Rong | Khong | Cho phep nhap |

### 5.3. `pages::pickups.show`

- Thong tin dieu phoi va danh sach don.
- Them/xoa don khi Pickup chua chot.
- Gan hoac doi shipper.
- Nut chuyen trang thai theo FSM.
- Upload va xem anh pickup.
- Timeline lich su.
- Nut "Chot Pickup / Nhap kho" o buoc `PICKUP_DA_LAY`.

### 5.4. Man hinh shipper

MVP co the dung lai `pickups.show` voi quyen han han che:

- Shipper chi xem Pickup duoc gan cho minh.
- Shipper chi duoc chuyen `PICKUP_CHO_NHAN -> PICKUP_DANG_LAY -> PICKUP_DA_LAY`.
- Shipper duoc tai anh va ghi chu.
- Shipper khong duoc chot nhap kho.

## 6. Phan quyen

Hien tai quyen dang bi tach lam hai co che:

- `PermissionEnum` co `pickups.view`, `pickups.create`, `pickups.update`.
- Route lai dung gate role-based `pickups.index`.
- Sidebar hien Pickup cho `manager`, `ctv`, nhung gate khong cho hai role nay; sidebar khong hien cho `shipper`, trong khi nghiep vu can shipper cap nhat.

Can chot matrix va dong bo route, gate, sidebar:

| Hanh dong | Role de xuat |
| --- | --- |
| Xem danh sach tat ca Pickup | `admin`, `manager`, `cs`, `ops`, `sale` |
| Tao va sua Pickup | `admin`, `cs`, `ops`, `sale` |
| Phan cong shipper | `admin`, `cs`, `ops` |
| Xem Pickup duoc giao | `shipper` |
| Bat dau / xac nhan da lay | `shipper`, `admin`, `ops` |
| Chot nhap kho | `admin`, `ops`, `warehouse` neu role nay duoc chinh thuc hoa |

## 7. Ke hoach trien khai theo batch

### Batch 0 - Xac minh production va chot nghiep vu

- Kiem tra schema va du lieu legacy `pickup`, `order_photo_pickup`.
- Chot format ma Pickup, quy tac gom dia chi, ai duoc chot nhap kho.
- Chot anh gan voi Pickup, voi don, hay ca hai.
- Chot co can retry pickup / pickup mot phan hay khong.

Validation: co migration strategy va acceptance criteria duoc xac nhan.

### Batch 1 - Data foundation

- Migration `pickups`, `pickup_orders`, `pickup_histories`; migration anh neu can.
- Model va relation.
- Hoan thien `PickupStatusEnum`.
- Unit test FSM va model relation.

Validation: migrate/rollback tren DB test; test FSM pass.

### Batch 2 - Core actions

- Them action tao Pickup, attach/remove don, gan shipper, sync totals.
- Validate don `DA_XAC_NHAN`, chua thuoc Pickup, cung dia chi.
- Ho tro tao Pickup voi mot don khoi tao tu trang chi tiet don.
- Tao `sender_snapshot` tu `order.sender`, nhan gia tri da dieu chinh tu form va khong ghi de sender cua don.
- Dung `DB::transaction()` va `lockForUpdate()` giong module Tai hang.

Validation: feature test concurrent attach, sai trang thai, sai dia chi, sync totals.

### Batch 3 - Chot Pickup va dong bo Order

- Them `ClosePickupAction`.
- Cap nhat don sang `DA_NHAN_HANG`, dien `ngaynhanhang`.
- Ghi lich su Pickup va `order_history`.
- Bao ve idempotency: chot lai khong tao lich su trung.

Validation: feature test happy path, Pickup rong, don bi doi trang thai giua chung, chot lai.

### Batch 4 - UI van hanh

- Doi route placeholder thanh Livewire route.
- Tao index, create, show.
- Them nut `Tao phieu Pickup` tai chi tiet don `DA_XAC_NHAN`.
- Neu don da co Pickup, hien text `Da tao Pickup: <ma_pickup>`; ma Pickup co the bam de xem chi tiet.
- Tao modal Pickup trong chi tiet don, dung don dang xem lam don khoi tao.
- Bo dropdown chon khach hang; tu dong do `order.sender` va van cho phep sua snapshot Pickup.
- Them filter, summary, toast va timeline.
- Them anh Pickup theo quyet dinh o Batch 0.

Validation: smoke test route; Livewire test cac thao tac chinh.

### Batch 5 - Phan quyen va man hinh shipper

- Tach gate xem / tao / cap nhat / chot.
- Dong bo `AuthServiceProvider`, sidebar, dashboard va `PermissionEnum`.
- Loc du lieu theo role; shipper chi thay phieu duoc giao.

Validation: feature test tung role va test truy cap truc tiep URL.

### Batch 6 - Hoan thien van hanh

- Them thong bao cho shipper khi duoc gan neu khach hang can.
- Them export Pickup neu ops can doi soat.
- Cap nhat dashboard KPI neu can.
- Viet tai lieu huong dan su dung.

Validation: regression test Order -> Pickup -> Tai hang -> Scan.

## 8. Acceptance criteria MVP

- Tai chi tiet don `DA_XAC_NHAN`, nguoi co quyen thay nut `Tao phieu Pickup`.
- Bam nut mo form tao Pickup voi don hien tai da duoc chon san.
- Form khong co dropdown `Chon khach hang`; thong tin nguoi gui duoc do tu `order.sender`.
- Nguoi dung co the dieu chinh thong tin nguoi gui tren Pickup ma khong lam thay doi sender goc cua don.
- Nguoi co quyen tao duoc phieu Pickup tu mot hoac nhieu don `DA_XAC_NHAN`.
- Neu don da thuoc Pickup, trang chi tiet don hien text `Da tao Pickup: <ma_pickup>` thay vi nut tao trung.
- Khong the them don da thuoc Pickup hoac sai dia chi vao phieu.
- Ops gan duoc shipper; shipper xem va cap nhat dung phieu cua minh.
- FSM Pickup chi cho chuyen trang thai hop le.
- Chot Pickup chuyen cac don sang `DA_NHAN_HANG`, dien ngay nhan va ghi history.
- Don vua chot xuat hien trong danh sach du dieu kien cua module Tai hang.
- Tong so don, so kien va can tinh phi tren Pickup duoc dong bo.
- Route Pickup khong con tro toi view khong ton tai.
- Moi action quan trong co feature test va phan quyen theo role.

## 9. Rui ro va luu y

- Repo khong co migration khoi tao cho cac bang legacy nhu `orders`; migration Pickup can chay an toan tren DB production dang co du lieu.
- Model `Pickup` cu co nhieu field JSON va relation legacy khong con khop kien truc moi. Khong nen sua dan model cu khi chua audit DB production.
- Cap nhat `OrderStatusEnum` anh huong rong toi order detail, tracking, scan, tai hang, bao cao va cong no. Ke hoach nay co chu dich khong them status don moi.
- Viec scan hien tai co the dua don `DA_XAC_NHAN -> DA_NHAN_HANG` truc tiep, bo qua Pickup. Can chot: scan tai kho la luong thay the hop le hay bat buoc phai chot Pickup neu don da nam trong phieu.
- GitNexus FTS index dang thieu va lenh rebuild bi parser idle timeout khi quet Blade. Truoc moi batch van can chay impact analysis theo tung symbol va dua tren source review bo sung.

## 10. Cau hoi can khach hang xac nhan

1. Mot phieu Pickup gom nhieu don theo cung dia chi hay co the gom nhieu dia chi tren cung tuyen shipper?
2. Co truong hop lay duoc mot phan, hen lay lai, hoac tach mot don sang nhieu lan Pickup khong?
3. Ai duoc phep chot nhap kho: shipper, ops, warehouse hay admin?
4. Anh Pickup la anh chung cho chuyen lay hang hay can gan anh theo tung don?
5. Don co duoc scan tai kho de vao `DA_NHAN_HANG` ma khong qua Pickup khong?
6. Co can thong bao cho shipper qua he thong, email hoac kenh ngoai khi duoc giao Pickup khong?
7. Du lieu bang `pickup` cu tren production co can migrate va tra cuu lich su khong?
8. Nut `Nhan hang` truc tiep tren chi tiet don co duoc giu lai hay bat buoc phai chot Pickup de don sang `DA_NHAN_HANG`?
