# Plan giao dien danh sach order

## Phan tich he thong cu
- File cu `src/Views/templates/manager/index.blade.php` co 4 vung chinh: header tao order/action bulk, search + page size, tab trang thai co count, table server-side DataTables.
- Bulk action cu gom: huy don, xoa don, nhap departed, xuat hang, KH thanh toan, NCC thanh toan, them cong no, export bao cao.
- Modal bo loc cu gom: tu ngay, den ngay, trang thai bill, trang thai kiem hang, thanh toan KH/NCC, ma tai hang, cong no, chi nhanh, dich vu, sale, dai ly, CTV.
- Cot table thay doi theo role, nhung nhom cot cot loi can giu la: ma don, khach hang, ngay, trang thai, nguoi gui/nhan, dich vu, dai ly/nhan su, thanh toan, tai chinh, can nang, ngay giao, thao tac.

## Plan thiet ke
1. Header: breadcrumb ngan, title, nut tao order, mo bo loc, xuat CSV.
2. Action bar: hien so don da chon, cap nhat trang thai nhanh, huy/xoa don huy, scaffold cac action cong no va thanh toan.
3. Filter modal: dung Flux modal, chi giu state can truyen API: `status`, `fromDate`, `toDate`, `saleId`, `customerId`, `serviceId`, `branchId`.
4. Status tabs: render tu `OrderStatusEnum`, count lay tu payload `statusCounts` cua API datatable.
5. Datatable: khong do data trong Livewire; DataTables goi `orders.datatable`, Livewire chi cap option/filter ban dau.
6. API action: bulk update status goi `orders.bulk-status`, xoa don huy goi `orders.delete-cancelled`, export goi `orders.export`.

## Viec can bo sung sau khi API san sang
- Them endpoint cho KH thanh toan, NCC thanh toan, them cong no, departed/tracking; cac nut nay da duoc scaffold trong action bar va dang disabled neu chua co API.
- Mo rong controller filter cho `kiemhang_status`, `payment_khachhang`, `payment_ncc`, `id_taihang`, `id_congno`, `id_daily` neu API yeu cau day du nhu he thong cu.
