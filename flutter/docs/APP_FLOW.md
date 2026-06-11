# Luồng hoạt động — App Shipper & OPS (Flutter)

> Tài liệu kỹ thuật mô tả kiến trúc và luồng chạy của ứng dụng mobile.
> Mục tiêu: giúp đội kỹ thuật bám sát hệ thống khi bảo trì / nâng cấp.
> App tiêu thụ backend Laravel qua nhóm endpoint `/api/mobile/*`.

---

## 1. Tổng quan

App phục vụ 2 vai trò, điều hướng theo quyền của tài khoản:

| Module | Vai trò (role) | Chức năng chính |
|--------|----------------|-----------------|
| **Shipper** | `shipper` | Xem danh sách pickup, chi tiết, đổi trạng thái lấy hàng |
| **OPS** | `ops`, `admin`, `manager`, `cs` | Quét mã (QR/Code128), tra cứu đơn, nhập kho |

Tài khoản có **cả hai** quyền → vào màn chọn module (chooser).

### Stack kỹ thuật

- **State management:** Riverpod (Notifier / FamilyNotifier, không dùng codegen)
- **HTTP:** Dio + interceptor gắn Bearer token
- **Điều hướng:** go_router (có auth guard)
- **Token:** flutter_secure_storage (Sanctum token)
- **Cấu hình:** flutter_dotenv (`.env`)
- **Quét mã:** mobile_scanner (MLKit)
- **DTO:** `fromJson` viết tay (không freezed/json_serializable)

### Kiến trúc thư mục (feature-first, clean-lite)

```
lib/
├── app/            # env, theme, router, app shell
├── core/           # hạ tầng dùng chung (api, storage, models, utils)
├── shared/         # widget tái sử dụng (status_chip, empty/error state)
└── features/
    ├── auth/           # đăng nhập, khôi phục phiên
    ├── shipper_pickup/ # module Shipper
    ├── ops_scan/       # module OPS
    └── profile/        # hồ sơ + đăng xuất
```

Mỗi feature chia 3 lớp:
- **domain/** — model thuần + interface repository (không phụ thuộc Flutter/Dio)
- **data/** — gọi API, map envelope → domain
- **presentation/** — controller (Riverpod) + screen + widget

Quy tắc phụ thuộc: `presentation → domain ← data`. Presentation chỉ biết
interface repository ở domain, không biết Dio.

---

## 2. Khởi động app (bootstrap)

File: `lib/main.dart`

```
main()
 ├─ WidgetsFlutterBinding.ensureInitialized()
 ├─ dotenv.load('.env')          # nạp cấu hình; lỗi → log + dùng default
 ├─ initializeDateFormatting('vi')  # locale cho intl
 ├─ SharedPreferences.getInstance() # cho lịch sử scan trong phiên
 └─ runApp(ProviderScope(overrides: [sharedPreferencesProvider], ...))
```

**Lưu ý quan trọng về `.env`:**
- `.env` được khai báo là **asset** trong `pubspec.yaml` → đóng gói lúc build.
- Sửa `.env` **KHÔNG** có hiệu lực qua hot-reload/hot-restart. Phải **dừng app
  và `flutter run` lại** để asset được đóng gói lại.
- `Env` (`lib/app/env.dart`) kiểm tra `dotenv.isInitialized` trước khi đọc; nếu
  load lỗi sẽ fallback giá trị mặc định, **không crash** app.

---

## 3. Cấu hình môi trường (`.env`)

| Biến | Ý nghĩa |
|------|---------|
| `API_BASE_URL` | Base URL backend (KHÔNG kèm `/api/mobile`, app tự thêm) |
| `DEV_ALLOW_BAD_CERT` | `true` → bỏ qua cert self-signed (CHỈ dev) |
| `API_HOST_HEADER` | Ép HTTP `Host` header khi gọi qua IP tới vhost name-based (CHỈ dev) |

`Env.apiBase = API_BASE_URL + '/api/mobile'`.

### Chọn URL theo nơi chạy

| Chạy ở đâu | `API_BASE_URL` | Ghi chú |
|------------|----------------|---------|
| iOS Simulator | `https://logictics.local` | Dùng chung `/etc/hosts` với Mac |
| Android Emulator | `https://10.0.2.2` | IP host đặc biệt của emulator |
| Thiết bị thật (WiFi) | `https://<IP_LAN_Mac>` | vd `https://192.168.1.102` |
| Production | domain thật + HTTPS hợp lệ | KHÔNG bật cờ dev |

> **Gotcha vhost name-based (MAMP):** nếu backend là vhost theo tên (chỉ nhận
> đúng `Host: logictics.local`), gọi thẳng vào IP sẽ bị **404 / trả site mặc
> định**. `API_HOST_HEADER` được thiết kế để xử lý, NHƯNG Dart `HttpClient` có
> thể ghi đè `Host` theo URI khiến mẹo này không ăn. Cách chắc chắn cho thiết bị
> thật: thêm `ServerAlias`/vhost theo IP ở Apache, hoặc dùng domain resolve được.

---

## 4. Lớp mạng (core/api)

Mọi request đi qua `DioClient` (`core/api/dio_client.dart`):

```
DioClient
 ├─ baseUrl = Env.apiBase          # https://host/api/mobile
 ├─ headers['Accept'] = application/json
 ├─ headers['Host'] = API_HOST_HEADER   # chỉ khi cấu hình (dev)
 ├─ validateStatus = status < 500       # để tự đọc envelope, không ném sớm
 ├─ interceptor: AuthInterceptor        # gắn Bearer + xử lý 401
 └─ badCertificateCallback (chỉ khi DEV_ALLOW_BAD_CERT)
```

### Envelope chuẩn (contract §1.3)

```jsonc
// thành công
{ "success": true,  "message": "...", "data": { ... } }
// thất bại
{ "success": false, "message": "...", "errors": { "field": ["..."] } }
```

`ApiEnvelope.fromJson` parse 4 trường này. `dataMap` ép `data` về Map an toàn.

### Chuẩn hóa lỗi → `ApiException`

`DioClient._send()` đọc status + envelope rồi:
- `2xx` + `success: true` → trả `ApiEnvelope`.
- còn lại → ném `ApiException(kind, message, statusCode, errors)`.

`ApiErrorKind` map theo HTTP status:

| Status | Kind | Message mặc định (nếu body không có) |
|--------|------|--------------------------------------|
| 401 | `unauthorized` | Phiên hết hạn, đăng nhập lại |
| 403 | `forbidden` | Không có quyền |
| 404 | `notFound` | Không tìm thấy dữ liệu |
| 409 | `conflict` | Trạng thái đã thay đổi |
| 422 | `validation` | Dữ liệu không hợp lệ (+ `errors`) |
| 429 | `rateLimited` | Thao tác quá nhanh |
| 5xx | `server` | Lỗi hệ thống |
| timeout/offline | `network` | Không có kết nối |

> **Debug tip:** message UI "Đã có lỗi xảy ra" (fallback `unknown`) thường nghĩa
> là body **không phải JSON** → request không tới đúng app (sai vhost/redirect).
> Message "Server Error" / "Lỗi hệ thống" nghĩa là tới đúng app nhưng backend
> trả **5xx** → xem `storage/logs/laravel.log`.

### Token & 401

- `AuthInterceptor` đọc token từ `SecureTokenStorage`, gắn `Authorization: Bearer`.
  Bỏ qua khi request có `extra['skipAuth'] == true` (vd `/login`).
- Khi nhận **401**: interceptor xóa token + tăng `unauthorizedSignalProvider`.
- `AuthController` lắng nghe signal đó → đẩy state về `unauthenticated` →
  router tự redirect về `/login`. (Dùng signal để tránh vòng phụ thuộc DI giữa
  DioClient và AuthController.)

---

## 5. Xác thực (features/auth)

### Trạng thái phiên

`AuthStatus`: `unknown` → `authenticated` | `unauthenticated`.

`AuthController` (Notifier toàn cục, `authControllerProvider`) giữ `AuthState`
gồm `status`, `session`, `errorMessage`, `isSubmitting`.

### Luồng đăng nhập

```
LoginScreen (nhập username/password)
 └─ deviceNameProvider  → lấy tên thiết bị (device_info_plus)
 └─ AuthController.login(username, password, deviceName)
     └─ AuthRepository.login()
         └─ AuthApi.login()  POST /login  (skipAuth: true)
             body: { username, password, device_name? }
         └─ envelope.data → { token, user, roles, permissions, default_module }
         └─ SecureTokenStorage.write(token)   # lưu ngay để request sau có Bearer
     └─ state = authenticated(session)
        ↑ lỗi → state = unauthenticated + errorMessage (hiện SnackBar)
```

### Khôi phục phiên (lúc mở app)

```
SplashScreen → AuthController.restoreSession()
 ├─ không có token → unauthenticated
 └─ có token → GET /me
     ├─ OK   → authenticated(session)
     └─ lỗi  → xóa token → unauthenticated
```

### Đăng xuất

`AuthController.logout()` → `POST /logout` (revoke token server) →
luôn xóa token local dù server lỗi → state `unauthenticated`.

### `default_module` (backend quyết định điều hướng)

| Quyền | `default_module` | Vào màn |
|-------|------------------|---------|
| chỉ shipper | `shipper` | danh sách pickup |
| có quyền OPS | `ops` | scanner |
| cả hai | `chooser` | chọn module |

---

## 6. Module Shipper (features/shipper_pickup)

### Danh sách pickup

`PickupListScreen` + `PickupListController` (`pickupListControllerProvider`).

- **4 tab** (`PickupTab`): Mới / Đã nhận / Đang lấy / Đã lấy. Mỗi tab map sang
  `tab` query gửi backend (`new`/`accepted`/`picking`/`done`).
- **Tìm kiếm:** ô search debounce 400ms → `setKeyword`.
- **Pull-to-refresh:** `RefreshIndicator` → `refresh()`.
- **Infinite scroll:** chạm gần đáy (maxScroll - 240) → `loadMore()`.
- **Summary bar:** số pickup chờ lấy + lịch hẹn gần nhất (từ `data.summary`).

```
GET /shipper/pickups?tab=&status=&keyword=&page=&per_page=
 └─ data: { items: [...], meta: {...}, summary: {...} }
 └─ Paginated<Pickup>.fromData()
```

Phân trang theo `meta`: `current_page / per_page / total / last_page / has_more`.

### Chi tiết pickup

`PickupDetailScreen` + `PickupDetailController` (FamilyNotifier theo `pickupId`).

```
GET /shipper/pickups/{id}
 └─ PickupDetail = Pickup + orders[] + weight_gross_kg + created_at
```

Có hành động phụ: gọi điện (`tel:`) và chỉ đường (mở Google Maps) qua
`ContactActions` (url_launcher), khi pickup có SĐT / toạ độ.

### Đổi trạng thái — FSM do BACKEND quyết định

> **Nguyên tắc cốt lõi:** App **KHÔNG hardcode** máy trạng thái (FSM). App chỉ
> render đúng các nút từ mảng `allowed_transitions` mà API trả về cho từng pickup.

```
StatusActionSheet (bottom sheet)
 └─ render 1 nút cho mỗi item trong pickup.allowedTransitions
 └─ nếu status đích = 'da_huy' → BẮT BUỘC nhập lý do trước khi gửi
 └─ PickupDetailController.changeStatus(status, reason?, lat?, lng?)
     └─ PUT /shipper/pickups/{id}/status   body: { status, reason?, lat?, lng? }
     └─ response §3.3 chỉ trả subset (id + status + allowed_transitions)
        → controller GỌI LẠI detail() để tải full, tránh xoá dữ liệu đang hiển thị
```

Hệ quả khi nâng cấp: thêm/bớt trạng thái pickup chỉ cần sửa backend
(`PickupStatusEnum::allowedTransitions()`), app tự render theo, **không cần build lại app**.

---

## 7. Module OPS (features/ops_scan)

### Quét mã

`OpsScannerScreen` — dùng `mobile_scanner` (QR + Code128). Có **fallback nhập tay**
khi không quét được (camera hỏng / mã mờ).

```
Quét/nhập mã → ScanController.scan(code)
 └─ POST /ops/scan   body: { code }
 └─ ScanResult: { found, can_receive, matched_by, order?, reason? }
```

`matched_by` (`id_bill` / `tracking_code` / `mathamchieu` / `package_code`) cho biết
backend khớp mã theo trường nào.

### Kết quả scan + nhập kho

`ScanResultCard` hiển thị theo `can_receive`:
- `can_receive: true` → nút **Nhập kho**.
- `can_receive: false` → hiện `reason` (vd đơn đã nhập trước đó, sai trạng thái).

```
Nhập kho → ScanController.receive(orderId)
 └─ POST /ops/receive   body: { order_id }
 └─ ReceiveResult: { order_id, status, received_at, message }
 └─ chỉ chuyển trạng thái đơn: da_xac_nhan → da_nhan_hang
```

> OPS chỉ thực hiện **một** chuyển tiếp: `da_xac_nhan → da_nhan_hang`.
> Backend kiểm tra quyền (`role:ops|admin|manager|cs`) và FSM của order.

### Lịch sử scan (trong phiên)

`RecentScansScreen` + `RecentScansStore`. Lưu các lần scan **trong phiên** (RAM +
shared_preferences), không phải nguồn dữ liệu chính thức — chỉ để OPS tra nhanh
vừa quét gì. Không đồng bộ server (sẽ là Phase sau nếu cần).

### Bảo mật dữ liệu (contract §5)

DTO scan (`ScanOrder`, `ScanParty`) **KHÔNG** chứa trường tài chính (tiền hàng,
COD, phí...). Khi backend mở rộng, **không** thêm field tài chính vào các DTO này.

---

## 8. Điều hướng & Auth Guard (app/router.dart)

go_router với `redirect` phản ứng theo `AuthStatus`:

```
status == unknown          → giữ ở /splash (đang khôi phục phiên)
status == unauthenticated  → ép về /login
status == authenticated:
  - đang ở /splash hoặc /login → đưa tới home theo default_module
  - vào /shipper mà không có quyền shipper  → về home đúng quyền
  - vào /ops    mà không có quyền OPS       → về home đúng quyền
```

Router refresh nhờ `_AuthRefreshNotifier` lắng nghe `authControllerProvider`.
Khi gặp 401 giữa chừng → state về `unauthenticated` → guard tự đẩy về `/login`.

| Route | Màn |
|-------|-----|
| `/` | Splash |
| `/login` | Đăng nhập |
| `/chooser` | Chọn module (user 2 quyền) |
| `/shipper` | Danh sách pickup |
| `/shipper/pickups/:id` | Chi tiết pickup |
| `/ops` | Scanner |
| `/ops/recent` | Lịch sử scan |
| `/profile` | Hồ sơ + đăng xuất |

---

## 9. Yêu cầu phía Backend (BẮT BUỘC)

App phụ thuộc các điều kiện sau ở backend Laravel:

1. **Nhóm route `/api/mobile/*`** đã đăng ký (xem `routes/api.php`).
2. **Sanctum đã cài + bảng `personal_access_tokens` đã migrate.**
   - ⚠️ Lỗi thường gặp: login trả **500** với
     `SQLSTATE[42S02] ... table_personal_access_tokens doesn't exist`.
   - Nguyên nhân: migration tạo bảng Sanctum chưa chạy.
   - Khắc phục: chạy migration trên DB backend (`php artisan migrate`).
   - Lưu ý DB có prefix `table_` → bảng thật tên `table_personal_access_tokens`.
3. **Tài khoản test** có role hợp lệ (`shipper` hoặc `ops|admin|manager|cs`) và
   `isActive() == true`.
4. **Response đúng envelope** `{success, message, data, errors}` cho mọi endpoint.

### Endpoint app sử dụng

| Method | Path | Module | Quyền |
|--------|------|--------|-------|
| POST | `/login` | auth | public (throttle:mobile-login) |
| GET | `/me` | auth | token |
| POST | `/logout` | auth | token |
| GET | `/shipper/pickups` | shipper | role:shipper |
| GET | `/shipper/pickups/{id}` | shipper | role:shipper |
| PUT | `/shipper/pickups/{id}/status` | shipper | role:shipper |
| POST | `/ops/scan` | ops | role:ops\|admin\|manager\|cs |
| POST | `/ops/receive` | ops | role:ops\|admin\|manager\|cs |
| POST | `/ops/bulk-receive` | ops | role:ops\|admin\|manager\|cs |

---

## 10. Chạy & build

### Toolchain

| Cần | Lệnh kiểm tra |
|-----|---------------|
| Flutter | `flutter doctor` |
| Xcode (iOS) | trỏ đúng: `sudo xcode-select -s /Applications/Xcode.app/Contents/Developer` |
| CocoaPods | `pod --version` |

### Lệnh thường dùng

```bash
flutter pub get
flutter analyze            # phải 0 error
flutter test               # unit + widget test
flutter run                # chọn thiết bị/simulator
```

### Lỗi build iOS thường gặp

- `Framework 'Pods_Runner' not found` → pods cài dở/cache cũ. Sửa:
  ```bash
  flutter clean
  flutter pub get
  cd ios && pod install
  ```
  Hai cảnh báo "platform iOS 13.0" và "base configuration" sau `pod install` là
  bình thường với Flutter, không cần xử lý.

### Quyền native

- iOS `Info.plist`: `NSCameraUsageDescription` (quét mã) + ngoại lệ ATS cho host
  dev (`logictics.local`) — đánh dấu DEV ONLY.
- Android `AndroidManifest.xml`: `android.permission.CAMERA`.

---

## 11. Test

`flutter test` — các nhóm test hiện có:

| File | Phủ |
|------|-----|
| `test/core/api_envelope_test.dart` | parse envelope, errors 422 |
| `test/core/paginated_test.dart` | parse items + meta, fallback |
| `test/core/status_test.dart` | StatusBadge, StatusPalette |
| `test/features/pickup_parse_test.dart` | Pickup / PickupDetail fromJson |
| `test/features/scan_parse_test.dart` | ScanResult / ReceiveResult / RecentScan |
| `test/widget_test.dart` | widget StatusChip |

Khi thêm field DTO mới → bổ sung case parse tương ứng.

---

## 12. Hướng nâng cấp (gợi ý)

- **Thêm trạng thái pickup:** chỉ sửa backend FSM; app tự render theo
  `allowed_transitions`. Kiểm tra màu trạng thái mới trong `StatusPalette` (giá
  trị lạ → màu default, không crash, nhưng nên thêm cặp màu riêng).
- **Thêm field hiển thị:** thêm vào DTO (`fromJson`) + widget tương ứng + test parse.
- **Phase sau (ngoài MVP):** upload ảnh bằng chứng, GPS check-in, hàng đợi offline,
  push notification, đồng bộ lịch sử scan lên server, OPS bulk-receive UI.
- **Bảo mật:** giữ token CHỈ ở secure storage (không shared_preferences); KHÔNG
  thêm field tài chính vào DTO scan; tắt mọi cờ `DEV_*` ở production.
