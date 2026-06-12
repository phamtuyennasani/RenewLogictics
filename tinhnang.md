# Bổ sung tính năng OPS cho Flutter app

## Context

Tài khoản OPS trên app hiện chỉ có màn hình **scan/nhập kho** (`ops_scan`). Cần
mở rộng để OPS làm trọn nghiệp vụ trên điện thoại:

1. Xem **danh sách order được giao cho mình** (`Order.id_ops` = OPS đang đăng nhập)
   → mở chi tiết → **tạo phiếu pickup** (form đầy đủ).
2. Xem **danh sách pickup của mình** → mở chi tiết → **chọn shipper** (tất cả shipper).
3. Giữ nguyên màn scan hiện có.

Đồng thời backend cần bắn push về app cho OPS khi:
- Giao một order cho OPS (`Order.id_ops` được gán) → `order_assigned`.
- Giao một pickup cho OPS (`Pickup.id_user` được gán/đổi) → `pickup_assigned_ops`.

Tái dùng tối đa hạ tầng có sẵn: action `CreatePickupAction`, FSM
`PickupStatusEnum` + `TransitionPickupStatusAction`, trait `ApiResponse`,
`FcmSender`, và toàn bộ pattern module `shipper_pickup` của Flutter.

---

## Phần A — Backend (Laravel)

### A1. Routes mới — `routes/api.php`

Thêm vào group `role:ops|admin|manager|cs` → `prefix('ops')` (sau các route scan hiện có):

```php
// OPS orders — đơn được giao cho OPS này (id_ops = auth id).
Route::get('/orders', [MobileOpsOrderController::class, 'index'])->name('orders.index');
Route::get('/orders/{order}', [MobileOpsOrderController::class, 'show'])->name('orders.show');
Route::post('/orders/{order}/pickups', [MobileOpsOrderController::class, 'createPickup'])->name('orders.pickups.create');

// OPS pickups — phiếu pickup OPS này sở hữu (id_user = auth id).
Route::get('/pickups', [MobileOpsPickupController::class, 'index'])->name('pickups.index');
Route::get('/pickups/{pickup}', [MobileOpsPickupController::class, 'show'])->name('pickups.show');
Route::post('/pickups/{pickup}/assign-shipper', [MobileOpsPickupController::class, 'assignShipper'])->name('pickups.assign-shipper');

// Danh mục dùng chung cho form tạo pickup.
Route::get('/shippers', [MobileOpsPickupController::class, 'shippers'])->name('shippers.index');
Route::get('/locations/provinces', [MobileLocationController::class, 'provinces'])->name('locations.provinces');
Route::get('/locations/wards', [MobileLocationController::class, 'wards'])->name('locations.wards');
```

### A2. `MobileOpsOrderController` (mới)

`app/Http/Controllers/Api/Mobile/MobileOpsOrderController.php`, dùng trait `ApiResponse`.

- **`index`**: query `Order::where('id_ops', $request->user()->id)`.
  - Filter: `keyword` (id_bill/tracking_code/mathamchieu), `status`, `has_pickup`
    (lọc đơn chưa có pickup để OPS biết đơn nào tạo được), `page`/`per_page`.
  - Eager load `sale`, `withCount('packages')`.
  - Payload tái dùng cấu trúc `orderPayload()` của `MobileOpsScanController`
    (sender/receiver/package_count/weight_kg/status) + cờ `has_pickup` =
    `$order->pickups()->exists()`.
- **`show`**: ép `where('id_ops', auth id)` → 404 nếu không thuộc OPS. Trả chi tiết
  order + danh sách package + cờ `can_create_pickup` (đơn `MOI_TAO`/`DA_XAC_NHAN` và
  chưa có pickup, theo điều kiện trong `CreatePickupAction`).
- **`createPickup`**: validate form đầy đủ rồi gọi
  `CreatePickupAction::execute($order, $data, $request->user()->id)`.
  - Ép `ops_id = $request->user()->id` (OPS chỉ tạo cho chính mình — KHÔNG nhận từ client).
  - Rules: `shipper_id` (nullable, exists + role shipper), `scheduled_at` (nullable date),
    `note`, và block sender: `company`, `fullname`, `phone`, `email?`, `country`,
    `address`, `id_city` (exists province), `id_ward` (exists + thuộc city),
    `pickup_lat?`, `pickup_lng?`, `vehicle_id?`, `branch_id?`.
  - Map vào `$data` đúng key `CreatePickupAction` mong đợi: `sender_snapshot`
    (mảng company/fullname/phone/email/country/address/id_city/id_ward),
    `shipper_id`, `scheduled_at`, `note`, `pickup_lat/lng`, `vehicle_id`, `branch_id`,
    `total_weight`, `packages_count`.
  - Bắt `RuntimeException` từ action (đơn sai trạng thái / đã có pickup) → trả 409.
  - Validate shipper role giống logic web (`whereHas('roles', name='shipper')`).

### A3. `MobileOpsPickupController` (mới)

`app/Http/Controllers/Api/Mobile/MobileOpsPickupController.php`, dùng trait `ApiResponse`.

- **`index`**: query `Pickup::where('id_user', $request->user()->id)`.
  - Tái dùng nguyên `statusesForTab()`, `pickupPayload()`, `transitionsPayload()`,
    `summary()`, `meta()` từ `MobileShipperPickupController` — **trích ra trait
    chung** `Concerns/PicksPickupPayload` để cả 2 controller dùng, tránh copy.
  - Khác biệt với shipper: scope theo `id_user` thay vì `id_shipper`; payload thêm
    `shipper` (tên shipper đã gán) để OPS biết phiếu đã giao ai.
- **`show`**: ép `where('id_user', auth id)` → 404 nếu không thuộc OPS. Trả chi tiết
  + danh sách order + thông tin shipper hiện tại.
- **`assignShipper`**: validate `shipper_id` (required, exists + role shipper).
  - Gán `$pickup->id_shipper = $shipper->id` rồi save → **PickupObserver tự bắn
    push cho shipper** (`updated` + `wasChanged('id_shipper')`), không cần làm thêm.
  - Chỉ cho gán khi pickup chưa final (`!status->isFinal()`).
- **`shippers`**: trả tất cả user role shipper (`id`, `fullname`, `username`) —
  giống `getPickupShippersProperty()` của web.

### A4. `MobileLocationController` (mới)

`app/Http/Controllers/Api/Mobile/MobileLocationController.php` cho form đầy đủ:
- **`provinces`**: `Province::orderBy('name')->get(['id','name'])`.
- **`wards`**: validate `province_id` (required), trả
  `Ward::where('parent_code', $provinceId)->orderBy('name')->get(['id','name'])`
  (tái dùng quan hệ `parent_code` như `pickupWards()` của web).

### A5. Push notification cho OPS

**`order_assigned`** — khi `Order.id_ops` được gán cho một OPS:
- Tạo `app/Observers/OrderObserver.php` với `created` + `updated`:
  - `created`: nếu `filled(id_ops) && id_ops != 0` → dispatch.
  - `updated`: nếu `wasChanged('id_ops')` và giá trị mới hợp lệ (filled, != 0) → dispatch.
- Tạo job `app/Jobs/SendOrderAssignedPush.php` (khuôn theo `SendPickupAssignedPush`):
  truyền tường minh `(int $orderId, int $opsId)`; gửi tới deviceTokens của OPS;
  data payload: `type=order_assigned`, `order_id`, `id_bill`.
- Đăng ký `Order::observe(OrderObserver::class)` trong `AppServiceProvider`.
- Guard `config('services.firebase.push_enabled')` như `PickupObserver`.

**`pickup_assigned_ops`** — khi `Pickup.id_user` được gán/đổi cho OPS:
- Mở rộng `PickupObserver`: thêm xử lý `wasChanged('id_user')` (created + updated)
  → dispatch job mới `SendPickupAssignedOpsPush` (khuôn theo bản shipper) với
  `data.type = pickup_assigned_ops`. Giữ nguyên nhánh `id_shipper` cho shipper.

> Lưu ý phân biệt: pickup tạo từ app OPS có `id_user` = chính OPS đó (người tạo) →
> KHÔNG nên tự bắn push cho người vừa tạo. Trong observer, chỉ bắn `pickup_assigned_ops`
> khi `id_user` đổi sang OPS **khác** người thực hiện thao tác (so với `auth()->id()`),
> hoặc khi gán từ web. → kiểm tra `id_user !== auth()?->id()` trước khi dispatch.

### A6. Cập nhật `docs/MOBILE_API_CONTRACT.md`

Thêm mục cho các endpoint OPS mới (orders, pickups, assign-shipper, locations) và
2 loại push mới `order_assigned`, `pickup_assigned_ops`.

---

## Phần B — Flutter app

### B1. Feature `ops_orders` (mới) — khuôn theo `shipper_pickup`

Thư mục `flutter/lib/features/ops_orders/`:
- `domain/ops_order.dart`: model `OpsOrder` (list item) + `OpsOrderDetail`
  (orders/packages), parse từ payload A2. Khuôn theo `pickup.dart`.
- `domain/ops_order_repository.dart` + `data/ops_order_repository_impl.dart`.
- `data/ops_order_api.dart`: `list()`, `detail(id)`, `createPickup(id, body)` —
  khuôn `pickup_api.dart`, gọi `/ops/orders...`.
- `presentation/`: `ops_order_list_controller.dart` (khuôn `pickup_list_controller.dart`
  với phân trang/search), `ops_order_list_screen.dart`, `ops_order_detail_screen.dart`,
  `create_pickup_screen.dart` (form đầy đủ), `ops_order_providers.dart`.

### B2. Feature `ops_pickups` (mới)

Thư mục `flutter/lib/features/ops_pickups/`:
- Tái dùng tối đa model `Pickup`/`PickupDetail` từ `shipper_pickup/domain/pickup.dart`
  (import lại, không nhân bản model).
- `data/ops_pickup_api.dart`: `list()`, `detail(id)`, `assignShipper(id, shipperId)`,
  `shippers()` → gọi `/ops/pickups...` và `/ops/shippers`.
- `presentation/`: list controller + screen (khuôn shipper), detail screen có nút
  **Chọn shipper** mở bottom sheet chọn từ danh sách `/ops/shippers`
  (khuôn `status_action_sheet.dart`), providers.

### B3. Form tạo pickup — `create_pickup_screen.dart`

- Prefill sender từ order detail (company/fullname/phone/address từ `order.sender`).
- Dropdown **Tỉnh/thành** (`/ops/locations/provinces`) → chọn xong load
  **Phường/xã** (`/ops/locations/wards?province_id=`).
- Chọn **shipper** (optional) từ `/ops/shippers`.
- `scheduled_at` (date/time picker), `note`.
- Submit → `createPickup`; thành công → pop về list order và refresh.

### B4. Routing & điều hướng — `flutter/lib/app/router.dart`

Thêm route con dưới `/ops` (giữ scanner làm trang chính OPS), ví dụ:
- `/ops/orders`, `/ops/orders/:id`, `/ops/orders/:id/create-pickup`
- `/ops/pickups`, `/ops/pickups/:id`

Thêm hằng vào `AppRoutes` + helper location. Guard hiện có đã chặn theo
`isOpsCapable` cho mọi path bắt đầu bằng `/ops` → không cần sửa guard.

Bổ sung điều hướng tới 2 màn mới: thêm nút/menu trên `OpsScannerScreen`
(hoặc bottom nav OPS) để mở "Đơn của tôi" và "Pickup của tôi".

### B5. Điều hướng từ push — `push_notification_service.dart` + nơi xử lý route

- Mở rộng `PushRoute.fromData`: nhận thêm `order_id` và các `type` mới.
- Nơi gắn `onRouteSelected` (tìm chỗ hiện điều hướng `pickup_assigned`): thêm nhánh
  `order_assigned` → `/ops/orders/:id`, `pickup_assigned_ops` → `/ops/pickups/:id`.

---

## Các file chính

**Backend (mới):** `MobileOpsOrderController.php`, `MobileOpsPickupController.php`,
`MobileLocationController.php`, `Concerns/PicksPickupPayload.php` (trait trích chung),
`Observers/OrderObserver.php`, `Jobs/SendOrderAssignedPush.php`,
`Jobs/SendPickupAssignedOpsPush.php`.
**Backend (sửa):** `routes/api.php`, `Observers/PickupObserver.php`,
`Providers/AppServiceProvider.php`, `MobileShipperPickupController.php` (rút trait),
`docs/MOBILE_API_CONTRACT.md`.

**Flutter (mới):** toàn bộ `features/ops_orders/`, `features/ops_pickups/`.
**Flutter (sửa):** `app/router.dart`, `core/notifications/push_notification_service.dart`,
nơi xử lý `onRouteSelected`, `OpsScannerScreen` (thêm điều hướng).

---

## Verification

**Backend:**
- `php artisan route:list --path=mobile/ops` → xác nhận route mới đăng ký đúng.
- Test thủ công bằng token OPS: `GET /api/mobile/ops/orders` chỉ trả đơn `id_ops`=OPS;
  `GET /api/mobile/ops/pickups` chỉ trả pickup `id_user`=OPS; gọi order của OPS khác → 404.
- `POST /ops/orders/{id}/pickups` với đơn hợp lệ → tạo pickup + attach order; đơn đã
  có pickup → 409.
- `POST /ops/pickups/{id}/assign-shipper` → `id_shipper` đổi → kiểm tra push shipper bắn.
- Viết feature test (PHPUnit) cho scope `id_ops`/`id_user` và nhánh observer push:
  giả lập gán `id_ops`/`id_user` → assert job `SendOrderAssignedPush` /
  `SendPickupAssignedOpsPush` được dispatch (dùng `Queue::fake()`); và KHÔNG dispatch
  khi `id_user` == người thao tác.

**Flutter:**
- `cd flutter && flutter analyze` sạch lỗi.
- Chạy app với tài khoản OPS: mở "Đơn của tôi" → chi tiết → tạo pickup (form đầy đủ,
  dropdown tỉnh/phường hoạt động) → kiểm tra pickup xuất hiện ở "Pickup của tôi".
- Mở pickup → chọn shipper → xác nhận gán thành công.
- Push: từ web giao 1 order/pickup cho OPS → app nhận notification → tap điều hướng
  đúng màn (`/ops/orders/:id`, `/ops/pickups/:id`).
- Xác nhận màn scan cũ vẫn hoạt động bình thường.
