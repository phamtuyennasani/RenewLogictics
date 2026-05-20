# Ke Hoach Thiet Ke Giao Dien Danh Sach Order Moi

Ngay lap: 2026-05-20

## 1. Nguon tham chieu

Giao dien cu: `src/Views/templates/manager/index.blade.php`

- 698 dong.
- 1 bang DataTables server-side.
- Alpine component `DataTableManager()`.
- 8 modal include: bo loc, xac nhan thanh toan, xuat hang, huy don, xoa don, departed, xem payment, add cong no.
- Cac thead rieng theo role: admin, manager, ketoan, cs, sale, ops, ctv.

Giao dien moi hien tai:

- `resources/views/pages/order/⚡index/index.blade.php`: placeholder.
- `resources/views/pages/order/⚡index/index.php`: Livewire component trong.
- `OrderDataTableController`: da co endpoint DataTables moi va partial render cho mot so cot.

## 2. Cac vung UI can giu lai tu he thong cu

### 2.1 Header chinh

Can co:

- Tieu de: `Danh sách đơn hàng`.
- Nut tao order: hien cho admin, cs, sale, ctv.
- Nhom action icon theo selection:
  - Huy don.
  - Xoa don da huy.
  - Nhap departed.
  - Xuat hang.
  - KH thanh toan.
  - Thanh toan NCC.
  - Them vao cong no.
  - Xuat Excel Debit.
  - Xuat Excel Report.
  - Link scan cho admin/ops.

Trong giao dien moi nen gom action vao toolbar ro rang:

- Primary action: `Tạo Order`.
- Bulk actions: chi hien khi co selected rows.
- Export actions: tach thanh menu `Export`.
- Destructive actions: can confirm modal.

### 2.2 Search va page size

Giao dien cu co:

- Page length: 10, 35, 50, 100.
- Search keyword.
- Nut mo bo loc nang cao.

Giao dien moi nen dung:

- `wire:model.live.debounce.400ms="search"`.
- Select page size.
- Button filter co badge so filter dang active.
- Nut reset filter.

### 2.3 Thanh trang thai

Giao dien cu co status tabs:

- `Tất cả`.
- Cac status trong `$statusBill`.
- Moi tab co count.
- Khi doi tab reload table.

Giao dien moi nen dung segmented tabs tren Livewire:

- `status = null` cho Tat ca.
- Moi status map tu `OrderStatusEnum`.
- Count query rieng theo role/filter.
- Tab active ro rang, khong dung icon mui ten lap lai qua nhieu status nhu UI cu.

### 2.4 Bang danh sach

Cot can co theo role cu:

- Common: checkbox/STT, Ma AWB/REF, ngay tao/ngay xuat, trang thai xu ly, nguoi gui, nguoi nhan, dia chi nguoi nhan, dich vu, can nang, thao tac.
- Admin/manager: them ten khach hang, kiem hang, dai ly, thanh toan khach hang, thanh toan dai ly, loi nhuan, ngay giao.
- Ketoan: them thanh toan, dai ly, loi nhuan; khong can kiem hang.
- CS: gan giong admin nhung khong hien loi nhuan.
- OPS: can trang thai xu ly, kiem hang, thong tin hang/dich vu/dai ly/can nang; khong can payment/profit.
- Sale: can tong cuoc/phu phi, net sale/hoa hong sale, thanh toan khach hang; khong can dai ly/thanh toan NCC/profit.
- CTV: cot rut gon, tap trung tracking, nguoi gui/nhan, dich vu, thanh toan, can nang, ngay giao.

De phu hop he thong moi, nen chuan hoa cot thanh cac nhom:

- Identity: checkbox, order code, ref/tracking code.
- Timeline: created, pickup/export/delivery dates.
- Status: bill status, check status neu co.
- Parties: customer, sale, sender, receiver.
- Service: main service, branch, agent.
- Shipment: package count, gross/volume/chargeable weight.
- Finance: sale total, cost total, profit, customer payment, provider payment.
- Actions: view, payment, tracking, print/export per order.

Ghi chu pham vi hien tai: chua lam cong no, nen 2 cot tinh trang thanh toan khach hang va thanh toan dai ly/NCC chi can giu cot trong de on dinh layout, chua can hien badge, filter hay action cap nhat thanh toan.

## 3. Chien luoc Livewire 4

Nen uu tien Livewire component native thay vi tiep tuc phu thuoc DataTables JS cho index moi.

Ly do:

- Dễ dong bo state: search, filters, page, selected rows, status tab.
- Bulk action co the validate role ngay trong component.
- Giam coupling voi DOM id cua modal/filter nhu giao dien cu.
- Pagination/sort/filter ro rang va test duoc.

Component de xuat:

- `resources/views/pages/order/⚡index/index.php`
  - State: `search`, `perPage`, `status`, `filters`, `selected`, `selectPage`, `showFilters`, `confirmingAction`.
  - Computed: `orders`, `statusCounts`, `activeFilterCount`, `visibleColumns`, `canBulk*`.
  - Methods: `updatedSearch`, `resetFilters`, `toggleSelectPage`, `bulkStatus`, `deleteCancelled`, `export`, `openConfirm`.

- `resources/views/pages/order/⚡index/index.blade.php`
  - Toolbar.
  - Status tabs.
  - Filter drawer/panel.
  - Responsive table.
  - Bulk action bar.
  - Confirm modals.

Co the giu `OrderDataTableController` tam thoi neu can tuong thich nhanh, nhung thiet ke UI nen huong ve Livewire-native.

## 4. Layout de xuat

### 4.1 Desktop

- Header 1 dong:
  - Trai: title + count tong.
  - Phai: Create, Export menu, Filter.
- Dong 2:
  - Search input lon.
  - Page size.
  - Bulk action bar chi hien khi selected.
- Dong 3:
  - Status tabs dang scroll ngang neu nhieu.
- Bang:
  - Sticky header.
  - Cot checkbox/order code/action nen co width co dinh.
  - Horizontal scroll cho nhieu cot.
  - Row height gon, phu hop man hinh nghiep vu.

### 4.2 Mobile/tablet

- Khong co gang hien tat ca cot.
- Dung card row hoac table compact voi cot chinh:
  - Order code.
  - Status.
  - Sender/receiver.
  - Service.
  - Chargeable weight.
  - Total/payment state.
  - Actions menu.

## 5. Bo loc nang cao

Tu modal cu `boloc.blade.php`, bo loc can migrate:

- Tu ngay, den ngay.
- Trang thai don.
- Trang thai kiem hang.
- Thanh toan khach hang.
- Thanh toan NCC.
- Tai hang.
- Cong no.
- Chi nhanh.
- Dich vu.
- Sale.
- Dai ly.
- CTV/khach hang.

Trong he thong moi hien query da co:

- `status`
- `fromDate`
- `toDate`
- `saleId`
- `customerId`
- `serviceId`
- `branchId`
- keyword search

Can bo sung sau neu nghiep vu con can:

- payment customer/provider state.
- agent/daily.
- check status.
- debt status.
- package/export status.

## 6. Action va rule UI

Rule cu dang enable/disable action dua tren filter hien tai:

- Huy don chi khi status la moi tao/da xac nhan.
- Xoa don chi khi status la huy.
- Xuat hang chi khi status la da nhan hang.
- KH thanh toan khi payment customer o mot so trang thai.
- NCC thanh toan khi provider chua thanh toan.
- Them cong no khi payment customer da chot cuoc ban.
- Xuat debit can chon CTV.

Trong Livewire moi, khong nen dua vao DOM filter de quyet dinh. Nen tinh theo selected orders:

- Neu selected rows co trang thai khong dong nhat, disable action hoac hien thong bao ly do.
- Bulk action phai goi backend validation lai, UI chi la hint.
- Destructive action dung confirm modal co so luong order.

## 7. Cot uu tien cho MVP

Phase 1 nen lam cac cot da co data trong `OrderDataTableController`:

- Checkbox.
- Order code.
- Status.
- Dates.
- Assignee.
- Sender.
- Receiver.
- Service.
- Packages.
- Sale total.
- Cost total cho admin/manager/ketoan.
- Profit cho admin/manager/ketoan.
- Payment state: tam thoi hien 2 cot trong vi chua trien khai cong no/thanh toan.
- Actions.

Phase 2 bo sung:

- Dai ly.
- Kiem hang.
- Thanh toan khach hang va thanh toan NCC/dai ly.
- Debit/cong no.
- Export Debit.
- Departed modal.

## 8. Tieu chi hoan thanh

- UI index moi thay placeholder.
- Hoat dong trong Livewire 4, khong can reload trang khi filter/search/page.
- Role nao thay dung cot va dung action.
- Bulk select hoat dong qua pagination.
- Status counts dung theo role va filter.
- Export CSV hien co van dung.
- Test duoc query/filter/action o component hoac controller.
- Khong lam mat route DataTables hien co neu chua migrate xong.

## 9. De xuat thu tu trien khai

1. Tao Livewire state/query/pagination cho danh sach order.
2. Build toolbar + search + perPage + status tabs.
3. Build table desktop voi cot MVP.
4. Them role-based column visibility.
5. Them filter panel tu cac filter da co trong controller.
6. Them selected rows + bulk status/delete cancelled.
7. Them export report CSV.
8. Sau khi on dinh moi migrate cac action nang cao: payment, departed, debit, xuat hang.
