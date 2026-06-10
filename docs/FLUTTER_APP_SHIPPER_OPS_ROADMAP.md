# Lộ Trình Phát Triển Flutter App Cho Shipper Và OPS

> Ngày lập: 09/06/2026  
> Phạm vi: App mobile Flutter cho shipper pickup và OPS quét mã đơn/mã kiện  
> Backend hiện có: Laravel + Livewire + Sanctum + Spatie Permission  
> Trạng thái: Đề xuất triển khai  
> Công nghệ chốt: Flutter cross-platform cho Android và iOS

## 1. Kết Luận Nhanh

Dự án hiện tại có thể phát triển app Flutter riêng cho Shipper và OPS mà không cần viết lại hệ thống. Laravel web vẫn là hệ thống quản trị chính, còn app Flutter là lớp thao tác nhanh ngoài hiện trường.

Nên làm một app Flutter duy nhất. Sau khi đăng nhập, app tách giao diện theo role:

- `shipper`: xem danh sách pickup được gán, cập nhật trạng thái lấy hàng, xem địa chỉ/bản đồ, gọi khách, chụp ảnh nếu cần.
- `ops`: quét mã đơn/mã kiện, xác nhận nhập kho, cập nhật đơn sang `DA_NHAN_HANG`, quét nhiều đơn liên tục.
- User có nhiều role: hiển thị màn chọn chế độ làm việc.

Nên bổ sung API mobile riêng thay vì cho Flutter gọi trực tiếp route Livewire/web. API phải tái sử dụng business action hiện có như `TransitionPickupStatusAction`, `RecordTrackingHistoryAction`, `OrderStatusEnum`, `PickupStatusEnum`.

## 2. Mục Tiêu Sản Phẩm

### 2.1. Mục tiêu cho Shipper

- Nhận việc pickup trên điện thoại.
- Xem rõ địa chỉ, liên hệ, số kiện, cân nặng, ghi chú.
- Cập nhật tiến độ pickup theo thực tế.
- Giảm việc điều phối bằng Zalo/điện thoại riêng lẻ.
- Ghi nhận bằng chứng lấy hàng nếu cần: ảnh, tọa độ, thời gian.

### 2.2. Mục tiêu cho OPS

- Quét nhanh mã đơn hoặc mã kiện khi hàng về kho.
- Giảm thao tác tìm đơn thủ công trên web.
- Cập nhật đúng mốc `DA_NHAN_HANG` và ghi tracking history.
- Hỗ trợ quét liên tục cho nhiều kiện/đơn.
- Cảnh báo sớm mã sai, đơn sai trạng thái, đơn đã hủy, đơn bị khóa.

## 3. Phạm Vi MVP

### 3.1. MVP Shipper

- Đăng nhập bằng tài khoản có role `shipper`.
- Danh sách pickup của shipper đang xử lý.
- Lọc nhanh theo trạng thái pickup.
- Chi tiết pickup: mã pickup, trạng thái, khách hàng, địa chỉ, điện thoại, lịch hẹn, số đơn, số kiện, cân nặng, ghi chú.
- Cập nhật trạng thái: `DA_XAC_NHAN`, `PICKUP_DANG_LAY`, `PICKUP_DA_LAY`, `DA_HUY`.
- Gọi điện cho khách.
- Mở bản đồ/chỉ đường nếu có tọa độ.
- Đồng bộ lại danh sách sau mỗi thao tác.

### 3.2. MVP OPS

- Đăng nhập bằng tài khoản có role `ops`.
- Màn quét mã bằng camera.
- Quét theo mã đơn `orders.id_bill`, mã tracking, hoặc mã kiện `order_package.code` nếu cần.
- Hiển thị thông tin đơn sau khi scan: trạng thái, sale, người gửi, người nhận, số kiện, cân nặng.
- Xác nhận nhập kho với đơn hợp lệ.
- Chỉ cho phép đơn `DA_XAC_NHAN` chuyển sang `DA_NHAN_HANG`.
- Ghi `ngaynhanhang` nếu chưa có.
- Ghi order tracking history.
- Lịch sử scan trong phiên làm việc.

### 3.3. Ngoài phạm vi MVP

- Tạo đơn mới trên app.
- Quản lý công nợ/hóa đơn trên app.
- Chốt tải hàng trên app.
- Offline queue phức tạp.
- Push notification nâng cao.
- Chat nội bộ.

## 4. Kiến Trúc Tổng Thể

```text
Flutter App
  -> Mobile API Laravel
      -> Sanctum token auth
      -> Role/permission check
      -> Business Actions/Services hiện có
      -> Database hiện tại

Laravel Web/Livewire
  -> Vẫn là màn quản trị, điều phối, báo cáo, cấu hình
```

Nguyên tắc quan trọng:

- App Flutter không tự quyết định nghiệp vụ quan trọng.
- Backend Laravel là nguồn sự thật về trạng thái, quyền và validation.
- API mobile phải dùng chung enum và action với web để tránh lệch luồng.

## 5. API Backend Cần Bổ Sung

### 5.1. Auth API

```text
POST /api/mobile/login
POST /api/mobile/logout
GET  /api/mobile/me
```

`POST /api/mobile/login`

- Input: `username`, `password`, optional `device_name`.
- Output: token, user info, roles, permissions, default module.
- Đề xuất dùng Laravel Sanctum personal access token.

`GET /api/mobile/me`

- Trả về user đang đăng nhập.
- Trả về role để app điều hướng sang Shipper/OPS.

### 5.2. Shipper Pickup API

```text
GET  /api/mobile/shipper/pickups
GET  /api/mobile/shipper/pickups/{pickup}
POST /api/mobile/shipper/pickups/{pickup}/status
POST /api/mobile/shipper/pickups/{pickup}/photos
POST /api/mobile/shipper/pickups/{pickup}/location
```

`GET /api/mobile/shipper/pickups`

- Chỉ trả pickup có `id_shipper = auth()->id()`.
- Lọc theo `status`, `date`, `keyword`.
- Mặc định hiển thị pickup chưa final.

`POST /api/mobile/shipper/pickups/{pickup}/status`

- Input: `status`, optional `reason`, optional `lat`, `lng`.
- Validate status bằng `PickupStatusEnum`.
- Gọi `TransitionPickupStatusAction::execute()`.
- Nếu hủy pickup, backend đã có logic gỡ `id_shipper` để điều phối gán lại.

`POST /api/mobile/shipper/pickups/{pickup}/photos`

- Dùng khi cần ảnh lấy hàng.
- Nên nén ảnh phía app trước khi upload.

### 5.3. OPS Scan API

```text
POST /api/mobile/ops/scan
POST /api/mobile/ops/orders/{order}/receive
POST /api/mobile/ops/orders/bulk-receive
GET  /api/mobile/ops/recent-scans
```

`POST /api/mobile/ops/scan`

- Input: `code`.
- Backend tìm theo thứ tự: `orders.id_bill`, `orders.tracking_code`, `orders.mathamchieu`, `order_package.code` nếu cần scan mã kiện.
- Output: đơn tìm thấy, trạng thái hiện tại, có thể nhập kho hay không, lý do nếu không thể.

`POST /api/mobile/ops/orders/{order}/receive`

- Chỉ role `ops`, `admin`, `manager`, `cs` nếu muốn đồng bộ với web.
- Chỉ cho đơn `DA_XAC_NHAN` chuyển sang `DA_NHAN_HANG`.
- Không cho nếu `lock_order = true` với OPS.
- Set `ngaynhanhang` nếu chưa có.
- Gọi `RecordTrackingHistoryAction::execute($order, OrderStatusEnum::DA_NHAN_HANG, now())`.

`POST /api/mobile/ops/orders/bulk-receive`

- Input: `order_ids` hoặc `codes`.
- Dùng cho batch scan.
- Trả về danh sách thành công/thất bại từng đơn.

## 6. Màn Hình Flutter Đề Xuất

### 6.1. Màn chung

- Splash/loading.
- Login.
- Chọn module nếu user có nhiều role.
- Profile nhanh.
- Trạng thái kết nối/API error.

### 6.2. Màn hình Shipper

- PickupListScreen.
- PickupDetailScreen.
- PickupStatusActionSheet.
- PickupCancelReasonScreen hoặc bottom sheet.
- CameraPhotoScreen nếu có upload ảnh.
- MapLauncher action.

### 6.3. Màn hình OPS

- OpsScannerScreen.
- ScanResultScreen.
- BatchScanScreen.
- RecentScansScreen.
- ManualCodeInputSheet cho trường hợp camera không đọc được mã.

## 7. Trạng Thái Và Luồng Nghiệp Vụ

### 7.1. Pickup

```text
MOI_TAO_PICKUP
  -> DA_XAC_NHAN
  -> PICKUP_DANG_LAY
  -> PICKUP_DA_LAY

MOI_TAO_PICKUP / DA_XAC_NHAN / PICKUP_DANG_LAY
  -> DA_HUY
```

App chỉ hiển thị nút hợp lệ theo `allowedTransitions()` backend trả về.

### 7.2. OPS nhập kho

```text
Scan mã
  -> Backend tìm đơn/mã kiện
  -> Nếu đơn DA_XAC_NHAN và user đủ quyền
      -> Cho phép xác nhận nhập kho
      -> Cập nhật DA_NHAN_HANG
      -> Ghi ngaynhanhang
      -> Ghi tracking history
  -> Nếu không hợp lệ
      -> Hiển thị lý do và không cập nhật
```

## 8. Bảo Mật Và Phân Quyền

- Dùng Sanctum token, không dùng session cookie cho app.
- Token lưu bằng secure storage trên thiết bị.
- API shipper bắt buộc `id_shipper = auth()->id()`.
- API OPS bắt buộc role hợp lệ.
- Không trả dữ liệu tài chính không cần thiết cho app mobile.
- Rate limit route login và scan.
- Ghi log các thao tác quan trọng: đổi trạng thái pickup, nhập kho, hủy pickup.
- Có endpoint logout/revoke token.

## 9. Offline Và Kết Nối Yếu

### MVP

- Nếu mất mạng, app báo lỗi rõ ràng và cho thử lại.
- Lưu local lịch sử scan trong phiên hiện tại, chưa đồng bộ offline phức tạp.

### Phase sau

- Offline queue cho OPS scan.
- Mỗi record offline gồm: code, thời gian scan, user, device id.
- Khi có mạng, đồng bộ từng record và trả về kết quả thành công/thất bại.
- Cần xử lý conflict nếu đơn đã được người khác cập nhật trước.

## 10. Lộ Trình Triển Khai

### Phase 0: Chốt yêu cầu và API contract

Thời gian đề xuất: 2-3 ngày.

- Xác nhận app dùng một hay hai module trong cùng app.
- Chốt scan theo mã đơn, mã kiện, hay cả hai.
- Chốt có upload ảnh pickup ở MVP hay để phase sau.
- Chốt GPS bắt buộc hay optional.
- Viết API contract chi tiết.

Kết quả: API contract, danh sách màn hình MVP, danh sách rule nghiệp vụ cần giữ đúng với web.

### Phase 1: Backend Mobile API

Thời gian đề xuất: 5-7 ngày.

- Cấu hình Sanctum token login cho mobile.
- Thêm route group `/api/mobile/*`.
- Tạo controller auth mobile.
- Tạo controller shipper pickup mobile.
- Tạo controller OPS scan mobile.
- Tái sử dụng action/service hiện có.
- Thêm test feature cho login, pickup visibility, pickup FSM, OPS scan và OPS nhập kho.

Kết quả: API mobile sẵn sàng cho Flutter và HTTP collection để test.

### Phase 2: Flutter App MVP

Thời gian đề xuất: 10-14 ngày.

- Tạo project Flutter.
- Cấu trúc module auth, shipper, ops, shared API client.
- Login và lưu token.
- Điều hướng theo role.
- Shipper pickup list/detail/status update.
- OPS scanner/manual input/scan result/receive.
- Xử lý loading, empty state, error state.
- Build bản nội bộ cho Android và iOS.

Kết quả: bản MVP dùng nội bộ, shipper và OPS thao tác được các luồng cơ bản trên Android/iOS.

### Phase 3: Kiểm thử hiện trường

Thời gian đề xuất: 5-7 ngày.

- Test với 1-2 shipper thật.
- Test với OPS tại kho.
- Test camera trên nhiều dòng máy Android và iOS.
- Test mạng yếu/rớt mạng.
- Test quét trùng, quét mã sai, quét đơn sai trạng thái.
- Ghi nhận pain points.

Kết quả: danh sách bug và cải tiến ưu tiên, chốt bản MVP có thể đưa vào vận hành.

### Phase 4: Mở rộng sau MVP

- Upload ảnh pickup.
- GPS check-in/check-out.
- Push notification pickup mới.
- Offline queue cho OPS scan.
- Batch scan nâng cao.
- In/tạo tem mã kiện nếu cần.
- Dashboard mobile nhỏ cho trưởng ca OPS.
- Versioning API mobile.

## 11. Checklist Kỹ Thuật Backend

- [ ] Tạo route group `api/mobile`.
- [ ] Thêm MobileAuthController.
- [ ] Thêm MobileShipperPickupController.
- [ ] Thêm MobileOpsScanController.
- [ ] Dùng Sanctum personal access token.
- [ ] Dùng policy/gate/role check rõ ràng.
- [ ] Không lặp lại logic FSM, tái sử dụng enum/action.
- [ ] Ghi tracking history khi OPS nhập kho.
- [ ] Ghi activity/log khi shipper đổi trạng thái pickup.
- [ ] Test API thành công/thất bại.
- [ ] Thêm rate limit cho login và scan.

## 12. Checklist Kỹ Thuật Flutter

- [ ] Tạo project Flutter.
- [ ] Cấu hình flavor/dev/prod API base URL.
- [ ] Tạo API client có interceptor token.
- [ ] Lưu token bằng secure storage.
- [ ] Tạo auth state.
- [ ] Tạo role-based navigation.
- [ ] Tích hợp camera scanner.
- [ ] Tạo màn pickup list/detail.
- [ ] Tạo màn OPS scan/result.
- [ ] Xử lý lỗi API thống nhất.
- [ ] Build bản nội bộ Android/iOS.

## 13. Rủi Ro Và Cách Giảm Thiểu

- Lệch nghiệp vụ giữa web và app: API mobile phải gọi action/service hiện có, không copy logic riêng trong app.
- Scan sai mã hoặc mã trùng: backend phải trả về `type`, `matched_by`, `can_receive`, `reason`.
- Mất mạng trong kho: MVP cho retry rõ ràng, phase sau thêm offline queue.
- Quyền truy cập pickup của shipper: backend bắt buộc filter `id_shipper = auth()->id()`.
- Ảnh upload nặng: app nên nén ảnh trước khi upload, backend giới hạn dung lượng.

## 14. Đề Xuất Công Nghệ Phù Hợp

### 14.1. Lựa chọn khuyến nghị

Nên dùng stack sau cho MVP:

```text
Flutter Cross-platform App
  -> Riverpod state management
  -> Dio API client
  -> Secure Storage token
  -> Mobile Scanner camera scan.
  -> Laravel Sanctum API
  -> Laravel Actions/Services hiện có
```

Lý do phù hợp với dự án:

- Backend hiện tại là Laravel, đã có `laravel/sanctum`, Spatie role/permission và business action riêng.
- App cần thao tác ngoài hiện trường, camera scan, GPS, upload ảnh; Flutter làm nhanh và đóng gói được cả Android/iOS từ một codebase.
- Nhu cầu đã chốt là chạy được cả Android và iOS, nên không nên chọn native Android/Kotlin làm công nghệ chính.
- MVP có thể test Android trước vì dễ cài APK nội bộ, nhưng kiến trúc và package phải hỗ trợ iOS ngay từ đầu.

### 14.2. Bộ công nghệ Flutter app

- Framework: `Flutter` bản stable mới nhất.
- Language: `Dart`.
- State management: `riverpod`.
- Routing: `go_router`.
- HTTP client: `dio`.
- Token storage: `flutter_secure_storage`.
- Local cache nhẹ: `shared_preferences` cho setting UI, không lưu token.
- Local database phase sau: `drift` hoặc `isar` nếu làm offline queue.
- Camera scan: `mobile_scanner`.
- Upload/chụp ảnh: `image_picker`.
- Nén ảnh: `flutter_image_compress`.
- GPS: `geolocator`.
- Mở bản đồ/gọi điện: `url_launcher`.
- Connectivity check: `connectivity_plus`.
- Push notification phase sau: `firebase_messaging`.

Khuyến nghị MVP:

- Dùng `riverpod` thay vì `bloc` nếu team muốn code gọn, dễ đọc, ít boilerplate.
- Dùng `dio` vì cần interceptor token, retry, upload multipart và log request khi debug.
- Dùng `mobile_scanner` vì nhẹ và phù hợp barcode/QR trong kho.

### 14.2.1. Danh sách package cần dùng

#### Core app

| Nhu cầu | Package | Ghi chú |
| --- | --- | --- |
| State management | `flutter_riverpod` | Quản lý state, async loading/error, dependency injection nhẹ. |
| Router | `go_router` | Điều hướng theo role, auth guard, deep link sau này. |
| HTTP client | `dio` | Gọi API, interceptor token, upload file, timeout, retry. |
| Model immutable | `freezed`, `freezed_annotation` | Tạo model/state immutable, union state nếu cần. |
| JSON serialize | `json_serializable`, `json_annotation` | Parse response API rõ ràng, ít lỗi runtime. |
| Code generation | `build_runner` | Chạy generator cho freezed/json/riverpod nếu dùng. |
| Env config | `flutter_dotenv` | Tách API base URL dev/staging/prod. |

#### Auth và bảo mật

| Nhu cầu | Package | Ghi chú |
| --- | --- | --- |
| Lưu token | `flutter_secure_storage` | Lưu Sanctum token bằng secure storage của Android/iOS. |
| Local setting nhẹ | `shared_preferences` | Lưu theme, module gần đây, setting không nhạy cảm. |
| Device info | `device_info_plus` | Gửi `device_name` khi login/token. |
| App info | `package_info_plus` | Hiển thị version/build, debug issue. |

#### Scanner, media, GPS

| Nhu cầu | Package | Ghi chú |
| --- | --- | --- |
| Quét barcode/QR | `mobile_scanner` | Package chính cho OPS scan. |
| Chụp/chọn ảnh | `image_picker` | Chụp ảnh pickup/chứng từ. |
| Nén ảnh | `flutter_image_compress` | Giảm dung lượng upload. |
| GPS | `geolocator` | Lấy tọa độ check-in/check-out pickup. |
| Permission | `permission_handler` | Xin quyền camera, location, photo. |
| Mở map/gọi điện | `url_launcher` | Mở Google/Apple Maps, tel link. |

#### Offline và cache

| Nhu cầu | Package | Ghi chú |
| --- | --- | --- |
| Trạng thái mạng | `connectivity_plus` | Biết offline/online để hiển thị UI và sync queue. |
| Offline DB phase sau | `drift` | Phù hợp queue scan có schema và sync status. |
| Path/file local | `path_provider`, `path` | Lưu file tạm, ảnh nén, DB local. |

#### UI và tiện ích

| Nhu cầu | Package | Ghi chú |
| --- | --- | --- |
| Format ngày/số | `intl` | Hiển thị ngày giờ, cân nặng, số kiện. |
| Toast/snackbar đẹp | `another_flushbar` hoặc custom Snackbar | Báo thành công/lỗi khi scan. |
| Skeleton/loading | `shimmer` | Hiển thị loading danh sách pickup. |
| SVG icon nếu cần | `flutter_svg` | Dùng nếu có asset SVG. |

#### Dev/test/build

| Nhu cầu | Package | Ghi chú |
| --- | --- | --- |
| Lint | `very_good_analysis` hoặc `flutter_lints` | Giữ code sạch. |
| Mock test | `mocktail` | Test repository/usecase. |
| API mock | `http_mock_adapter` | Test Dio client. |
| Launcher icon | `flutter_launcher_icons` | Tạo icon app. |
| Splash | `flutter_native_splash` | Splash native Android/iOS. |

Package cần đưa vào MVP ngay:

```yaml
dependencies:
  flutter_riverpod: ^latest
  go_router: ^latest
  dio: ^latest
  flutter_secure_storage: ^latest
  shared_preferences: ^latest
  mobile_scanner: ^latest
  image_picker: ^latest
  flutter_image_compress: ^latest
  geolocator: ^latest
  permission_handler: ^latest
  url_launcher: ^latest
  connectivity_plus: ^latest
  intl: ^latest
  freezed_annotation: ^latest
  json_annotation: ^latest

dev_dependencies:
  build_runner: ^latest
  freezed: ^latest
  json_serializable: ^latest
  flutter_lints: ^latest
  mocktail: ^latest
```

Khi tạo `pubspec.yaml` thực tế, không nên để `^latest`; cần dùng version mới nhất từ `pub.dev` tại thời điểm khởi tạo project.

### 14.2.2. Design pattern phù hợp

Khuyến nghị dùng **Feature-first Clean Architecture nhẹ + Repository pattern + Riverpod MVVM**.

Lý do:

- App có 2 module nghiệp vụ rõ: `shipper_pickup` và `ops_scan`.
- Backend Laravel đã giữ logic nghiệp vụ chính, app không cần clean architecture quá nặng.
- Cần tách rõ API, model, state và UI để sau này thêm offline queue/push notification không vỡ code.

Các lớp chính:

```text
Presentation
  -> Screen, Widget, Controller/Notifier

Application
  -> UseCase nhẹ nếu thao tác có nhiều bước

Domain
  -> Entity, enum, rule hiển thị, repository contract

Data
  -> DTO, API service, repository implementation, local datasource
```

Pattern cụ thể nên dùng:

- **Repository pattern**: UI không gọi Dio trực tiếp, chỉ gọi repository.
- **Provider/Notifier pattern với Riverpod**: mỗi screen có controller quản lý loading/error/data.
- **DTO + Mapper**: response API parse vào DTO, map sang entity dùng trong UI.
- **Result/Error pattern**: repository trả lời có kiểm soát, không throw lung tung lên UI.
- **Feature-first folder**: gom file theo nghiệp vụ, không gom tất cả screen/model/api vào một chỗ.

Không nên làm quá nặng:

- Không cần DDD đầy đủ.
- Không cần Bloc nếu team chưa quen.
- Không cần local database ngay từ MVP nếu chưa làm offline queue.

### 14.2.3. Cấu trúc thư mục Flutter đề xuất

```text
lib/
  main.dart
  app/
    app.dart
    router.dart
    env.dart
    theme/
  core/
    api/
      dio_client.dart
      api_exception.dart
      auth_interceptor.dart
    storage/
      secure_token_storage.dart
    models/
      paginated_response.dart
      api_error.dart
    utils/
      date_formatters.dart
      validators.dart
  features/
    auth/
      data/
        auth_api.dart
        auth_repository_impl.dart
        dto/
      domain/
        auth_repository.dart
        user_session.dart
      presentation/
        login_screen.dart
        auth_controller.dart
    shipper_pickup/
      data/
        pickup_api.dart
        pickup_repository_impl.dart
        dto/
      domain/
        pickup.dart
        pickup_status.dart
        pickup_repository.dart
      presentation/
        pickup_list_screen.dart
        pickup_detail_screen.dart
        pickup_list_controller.dart
        pickup_detail_controller.dart
        widgets/
    ops_scan/
      data/
        scan_api.dart
        scan_repository_impl.dart
        dto/
      domain/
        scan_result.dart
        scan_repository.dart
      presentation/
        ops_scanner_screen.dart
        scan_result_screen.dart
        scan_controller.dart
        widgets/
    profile/
  shared/
    widgets/
    constants/
```

Luôn giữ nguyên tắc:

- `presentation` không import `dio`.
- `data` không chứa UI widget.
- `domain` không phụ thuộc Flutter widget.
- API endpoint tập trung trong `data/*_api.dart`.

### 14.3. Bộ công nghệ Backend API

- Auth: Laravel Sanctum personal access token.
- Route prefix: `/api/mobile/*`.
- Controller riêng cho mobile:
  - `MobileAuthController`.
  - `MobileShipperPickupController`.
  - `MobileOpsScanController`.
- Validation: FormRequest hoặc inline validator nếu endpoint nhỏ.
- Authorization: Spatie role/permission + policy/gate hiện có.
- Business logic: gọi lại action/service hiện có, không viết logic riêng cho app.
- Response format: JSON thống nhất gồm `data`, `message`, `errors` nếu có.
- Rate limit: login và scan endpoint.

Nguyên tắc backend:

- App chỉ gửi ý định thao tác, backend mới quyết định có được thao tác hay không.
- Mỗi thay đổi trạng thái phải ghi history/log.
- API shipper chỉ trả pickup của chính shipper đang đăng nhập.
- API OPS chỉ cho cập nhật những trạng thái đúng FSM và đúng role.

### 14.4. Scan và barcode

Thư viện khuyến nghị: `mobile_scanner`.

Loại mã nên hỗ trợ:

- QR code nếu tem đơn/tem kiện có QR.
- Code128 nếu barcode đang in trên tem kiện.
- Manual input fallback khi camera không đọc được.

Thứ tự match mã scan trên backend:

1. `orders.id_bill`.
2. `orders.tracking_code`.
3. `orders.mathamchieu`.
4. `order_package.code` nếu quét theo kiện.

### 14.5. Chiến lược offline

MVP nên làm online-first:

- Scan xong gọi API ngay.
- Nếu mất mạng, hiển thị lỗi và cho thử lại.
- Lưu lịch sử scan trong phiên hiện tại trên app.

Phase sau mới làm offline queue:

- Dùng `drift` nếu cần SQL rõ ràng và sync queue có trạng thái.
- Mỗi item queue gồm `code`, `action`, `payload`, `scanned_at`, `device_id`, `sync_status`.
- Khi có mạng, sync từng item và backend trả về kết quả thành công/thất bại.

### 14.6. Build và phân phối

MVP nên hỗ trợ cả Android và iOS, nhưng có thể test Android trước để nhanh vòng lặp nội bộ:

- Build nội bộ Android: APK debug/release.
- Build nội bộ iOS: TestFlight hoặc ad-hoc build nếu có Apple Developer account.
- Môi trường: `dev`, `staging`, `production` bằng Flutter flavor hoặc file config riêng.
- Signing: tạo keystore riêng cho app nội bộ.
- Crash/error phase sau: Firebase Crashlytics hoặc Sentry.

Chưa cần đưa lên Google Play/App Store trong MVP nếu chỉ dùng nội bộ. Có thể cài APK trực tiếp cho Android và dùng TestFlight/ad-hoc cho iOS để test hiện trường.

### 14.7. Công nghệ chưa nên dùng ở MVP

- Không nên làm native Android Kotlin riêng vì yêu cầu đã chốt là chạy cả Android và iOS.
- Không nên dùng React Native nếu team chưa có kinh nghiệm JS mobile; Flutter ổn định hơn cho app nội bộ cần scan/camera.
- Không nên làm PWA thay Flutter vì camera scan, offline, secure token và trải nghiệm hiện trường sẽ kém hơn.
- Không nên đưa offline queue vào ngay MVP nếu quy trình kho chưa được test thực tế.

### 14.8. Nếu không dùng Flutter thì nên chọn gì?

Nếu bắt buộc không dùng Flutter nhưng vẫn cần chạy cả Android và iOS, phương án thay thế hợp lý nhất là **React Native**.

Thứ tự khuyến nghị:

1. **Flutter**: lựa chọn đã chốt, cân bằng tốt nhất giữa hiệu năng, tốc độ phát triển, Android/iOS, camera scan và offline.
2. **React Native**: phù hợp nếu team có kinh nghiệm React/TypeScript và vẫn muốn cross-platform Android/iOS.
3. **Native Android Kotlin + Native iOS Swift**: nhanh và ổn định nhất theo từng nền tảng, nhưng phải duy trì 2 codebase nên chi phí cao hơn.
4. **Capacitor + Vue/React**: phù hợp nếu muốn tận dụng web skill và làm app nội bộ nhanh; không tối ưu cho camera scan liên tục.
5. **PWA**: load có thể nhanh nếu cache tốt, nhưng không khuyến nghị làm app chính cho OPS/shipper vì giới hạn trải nghiệm camera, token, background, cài đặt và độ ổn định trên nhiều máy.

Bảng so sánh ngắn:

| Phương án | Android/iOS | Load/runtime | Camera scan | Offline | Tốc độ phát triển | Khuyến nghị |
| --- | --- | --- | --- | --- | --- | --- |
| Flutter | Có | Rất tốt | Tốt | Tốt | Nhanh | Chọn cho dự án này |
| React Native | Có | Tốt | Tốt | Tốt | Nhanh nếu team biết React | Phương án thay thế |
| Native Kotlin + Swift | Có, nhưng 2 codebase | Tốt nhất | Tốt nhất | Tốt nhất | Chậm hơn | Chỉ chọn nếu đội mobile mạnh |
| Capacitor | Có | Trung bình-tốt | Trung bình | Trung bình | Nhanh | App nội bộ đơn giản |
| PWA | Có, qua browser | Tốt khi cache tốt | Trung bình | Trung bình | Nhanh | Không nên làm app scan chính |

Kết luận thực tế:

- Nếu muốn **một codebase chạy Android/iOS, hiệu năng tốt, dễ phát triển**: chọn **Flutter**.
- Nếu team đã có **React/TypeScript mạnh**: có thể cân nhắc **React Native**.
- Nếu muốn **hiệu năng native tối đa** và chấp nhận chi phí cao: làm **Kotlin + Swift**.
- Không nên chọn **PWA/Capacitor** làm app scan chính nếu OPS phải quét liên tục mỗi ngày.

Tài liệu tham khảo chính thức:

- Flutter packages: https://pub.dev
- Riverpod: https://pub.dev/packages/flutter_riverpod
- Dio: https://pub.dev/packages/dio
- go_router: https://pub.dev/packages/go_router
- mobile_scanner: https://pub.dev/packages/mobile_scanner
- React Native performance: https://reactnative.dev/docs/performance
- Capacitor docs: https://capacitorjs.com/docs
- PWA offline/service workers: https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Offline_and_background_operation

## 15. Định Nghĩa Hoàn Thành MVP

MVP được xem là xong khi:

- Shipper đăng nhập và chỉ thấy pickup của mình.
- Shipper đổi được trạng thái pickup đúng FSM.
- OPS đăng nhập và scan được mã đơn.
- OPS cập nhật được đơn hợp lệ sang `DA_NHAN_HANG`.
- Mỗi lần nhập kho có tracking history.
- App xử lý được lỗi: sai mã, sai quyền, sai trạng thái, mất mạng.
- Backend có test cho các API chính.
- Bản build Android/iOS cài và chạy được trên máy thực tế.

## 16. Đề Xuất Ưu Tiên Ngay

Nên bắt đầu bằng backend API trước, vì app Flutter phụ thuộc API contract. Thứ tự nên làm:

1. Viết API contract chi tiết.
2. Thêm Sanctum mobile login.
3. Thêm API shipper pickup list/detail/status.
4. Thêm API OPS scan/receive.
5. Viết test backend.
6. Làm Flutter MVP.

Sau khi MVP chạy ổn định mới thêm ảnh, GPS, push notification và offline queue.

## 17. Từng Bước Phát Triển Flutter App

### Bước 1: Chốt API contract

- Chốt response format chung: `data`, `message`, `errors`.
- Chốt auth flow: login -> nhận token -> lưu secure storage -> gọi `/api/mobile/me`.
- Chốt status/action shipper pickup backend trả về.
- Chốt scan response: `found`, `type`, `order`, `can_receive`, `reason`.

### Bước 2: Tạo Flutter project và core app

- Tạo project Flutter.
- Thêm flavor/config cho `dev`, `staging`, `production`.
- Cài package core: `flutter_riverpod`, `go_router`, `dio`, `flutter_secure_storage`.
- Tạo `DioClient`, `AuthInterceptor`, `ApiException`.
- Tạo theme, color, text style theo web system nếu có.

### Bước 3: Làm auth module

- Tạo `AuthApi`, `AuthRepository`, `AuthController`.
- Làm `LoginScreen`.
- Lưu token bằng `flutter_secure_storage`.
- Gọi `/api/mobile/me` để lấy user/role.
- Điều hướng theo role: `shipper`, `ops`, hoặc màn chọn module.

### Bước 4: Làm module Shipper Pickup

- Tạo pickup model/entity/status.
- Tạo API list/detail/update status.
- Làm `PickupListScreen` với filter status.
- Làm `PickupDetailScreen`.
- Thêm action bottom sheet đổi trạng thái.
- Thêm gọi điện/mở bản đồ.
- Xử lý loading/empty/error.

### Bước 5: Làm module OPS Scan

- Tích hợp `mobile_scanner`.
- Làm `OpsScannerScreen`.
- Sau khi scan, gọi `/api/mobile/ops/scan`.
- Hiển thị `ScanResultScreen`.
- Nếu `can_receive = true`, hiển thị nút nhập kho.
- Gọi `/api/mobile/ops/orders/{order}/receive`.
- Lưu lịch sử scan trong memory/local setting nhẹ.

### Bước 6: Upload ảnh và GPS nếu đưa vào MVP

- Xin permission camera/photo/location.
- Chụp ảnh bằng `image_picker`.
- Nén ảnh bằng `flutter_image_compress`.
- Upload multipart qua `dio`.
- Lấy GPS bằng `geolocator` khi shipper đổi trạng thái quan trọng.

### Bước 7: Test nội bộ

- Test login sai/đúng.
- Test role shipper không thấy pickup của người khác.
- Test role ops scan mã sai, mã đúng, đơn sai trạng thái.
- Test camera trên máy Android/iOS thực tế.
- Test mất mạng/timeout/API 401/API 403/API 422.

### Bước 8: Build và phân phối bản nội bộ

- Cấu hình app icon và splash.
- Tạo keystore release cho Android.
- Cấu hình signing/provisioning cho iOS.
- Build APK staging cho Android.
- Build TestFlight/ad-hoc cho iOS nếu có thiết bị iPhone.
- Cài trên máy shipper/OPS test hiện trường.
- Ghi nhận bug và tối ưu thao tác scan/pickup.

### Bước 9: Mở rộng sau MVP

- Thêm offline queue bằng `drift`.
- Thêm push notification pickup mới.
- Thêm Crashlytics/Sentry.
- Thêm batch scan nâng cao.
- Hoàn thiện iOS release pipeline nếu shipper/OPS dùng iPhone nhiều.
