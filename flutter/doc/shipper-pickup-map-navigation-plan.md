# Kế hoạch: Bản đồ chỉ đường cho Shipper Pickup (Flutter)

> Tài liệu định hướng và các bước triển khai tính năng bản đồ chỉ đường trong app
> Flutter (`shipper_ops_app`), tái dùng hạ tầng VietMap đã có ở web Laravel.

---

## 1. Bối cảnh & hiện trạng

### 1.1 Web Laravel (đã hoàn chỉnh)

Web đã có bản đồ chỉ đường đầy đủ cho shipper pickup:

| Thành phần | Vai trò |
|---|---|
| `app/Http/Controllers/Api/VietmapProxyController.php` | Proxy 4 endpoint VietMap: `search`, `place`, `reverse`, `route`. **Giấu API key ở backend** (`Setting.options.vietmap_geocode_api_key`). |
| `routes/web.php` (`api/vietmap/*`, throttle `600,1`) | Định tuyến proxy. |
| `resources/js/shipper-pickup-route-map.js` | Dùng `@vietmap/vietmap-gl-js@6.0.1` vẽ map + tuyến đường, định vị shipper, fit bounds, nút mở Google/Apple Maps. |

Logic web cốt lõi (để port sang Flutter):
- Style tile: `https://maps.vietmap.vn/maps/styles/tm/style.json?apikey=<tileApiKey>`
- Routing: `GET /api/vietmap/route?points_encoded=false&vehicle=motorcycle&point[]=lat,lng&point[]=lat,lng`
  → trả `paths[0]` với `points.coordinates` (GeoJSON `[lng,lat]` hoặc `[lat,lng]`), `distance` (m), `time` (ms).
- Vẽ: 1 polyline tuyến đường + 2 marker (shipper "Bạn", pickup "P") + `fitBounds`.
- Tile API key (client) khác Geocode/Route API key (server). Web nạp tile key qua
  `window.__VIETMAP_PUBLIC_CONFIG__.tileApiKey`.

### 1.2 Flutter (hiện chỉ mở app ngoài)

| Thành phần | Hiện trạng |
|---|---|
| `lib/core/utils/contact_actions.dart` | `openMap()` chỉ `launchUrl` ra Google Maps — **không có bản đồ trong app**, không vẽ tuyến. |
| `lib/features/shipper_pickup/domain/pickup.dart` | Đã có `PickupLocation { lat, lng, hasLocation }` — **dữ liệu toạ độ sẵn sàng**. |
| `lib/features/shipper_pickup/data/pickup_api.dart` | Gọi `/shipper/pickups`, `/shipper/pickups/{id}`, cập nhật status (kèm lat/lng). |
| `lib/core/api/dio_client.dart` + `lib/app/env.dart` | Dio client với base `API_BASE_URL + /api/mobile`. |
| `pubspec.yaml` | Có `permission_handler`, `url_launcher`. **Chưa có** package bản đồ hay geolocator. |

### 1.3 Khoảng trống cần lấp

1. **App không có bản đồ in-app** — shipper phải nhảy ra Google Maps, mất ngữ cảnh đơn pickup.
2. **Mobile API chưa expose VietMap proxy** — `routes/api.php` (`/api/mobile/*`) chỉ có auth, shipper pickups, ops scan/receive. Proxy VietMap hiện nằm ở `web.php` (`/api/vietmap/*`), **không yêu cầu auth mobile token (Sanctum)** theo cùng guard. Cần quyết định cách app truy cập.

---

## 2. Định hướng phát triển

### 2.1 Nguyên tắc

- **Tái dùng backend, không lộ secret.** App **không** hard-code API key. Route/geocode đi qua proxy backend; tile/style key (bắt buộc ở client để render tile) lấy động qua một endpoint config mobile.
- **Đồng nhất UX với web.** Cùng style VietMap, cùng logic polyline + 2 marker + fit bounds, cùng `vehicle=motorcycle`.
- **Tiệm tiến (incremental).** Giai đoạn 1 dùng map GL hiển thị tuyến (đủ cho nhu cầu "xem đường tới điểm lấy"). Giai đoạn 2 (tuỳ chọn) mới thêm điều hướng turn-by-turn giọng nói nếu nghiệp vụ cần.
- **Offline-tolerant.** Nếu tải tile/route lỗi, vẫn cho phép fallback nút "Mở Google/Apple Maps" như hiện tại (`ContactActions.openMap`).

### 2.2 Lựa chọn package (cả hai đều chính chủ VietMap)

| Tiêu chí | `vietmap_flutter_gl` | `vietmap_flutter_navigation` |
|---|---|---|
| Bản chất | Map GL (tương đương bản web) | Điều hướng turn-by-turn + giọng nói |
| Platform | Android, iOS, **web** | Android, iOS |
| Kích thước build | Nhẹ hơn | Nặng (SDK native lớn) |
| Phù hợp | Xem tuyến + marker, đồng bộ web | Dẫn đường liên tục như Google Maps Nav |
| minSdk Android | ≥ 21 | ≥ 21 |

**Quyết định:** Giai đoạn 1 dùng **`vietmap_flutter_gl`**. Để dành `vietmap_flutter_navigation`
cho Giai đoạn 2 (chỉ làm nếu shipper cần dẫn đường giọng nói liên tục).

### 2.3 Phương án truy cập VietMap từ mobile (chọn 1)

| | A. Thêm proxy vào `/api/mobile/vietmap/*` (KHUYẾN NGHỊ) | B. App gọi thẳng `/api/vietmap/*` của web |
|---|---|---|
| Auth | Theo Sanctum token mobile, đồng nhất guard | Không qua guard mobile, dễ lệch policy |
| API key | Giữ nguyên ở backend | Giữ nguyên ở backend |
| Tile key client | Trả qua `/api/mobile/config` | Cần endpoint riêng |
| Rủi ro | Thấp, sạch | Trộn 2 hệ route, throttle khác nhau |

→ **Chọn A.** Tạo `Route::prefix('mobile')->prefix('vietmap')` tái dùng `VietmapProxyController`,
cộng thêm endpoint `GET /api/mobile/config` trả `tile_api_key` (public tile key) cho app render style.

> ⚠️ Lưu ý bảo mật: tile key buộc phải xuống client để VietMap GL tải tile. Đảm bảo
> tile key dùng cho client **khác** và **giới hạn quyền** so với geocode/route key dùng ở server.

---

## 3. Kiến trúc tính năng (Flutter)

Theo cấu trúc feature-first đang có (`lib/features/<feature>/{data,domain,presentation}`):

```
lib/features/shipper_pickup/
  data/
    vietmap_api.dart          # gọi /api/mobile/vietmap/route qua DioClient
  domain/
    route_path.dart           # model: coordinates[], distanceMeters, durationMs
  presentation/
    pickup_route_map_screen.dart   # màn hình bản đồ (VietmapGL)
    pickup_route_controller.dart   # Riverpod: load route + vị trí shipper

lib/core/
  config/
    mobile_config_api.dart    # GET /api/mobile/config → tileApiKey
    mobile_config_provider.dart
  location/
    location_service.dart     # geolocator: xin quyền + lấy vị trí hiện tại
```

Route mới trong `lib/app/router.dart`:
`/shipper/pickups/:id/route` (child của shipper shell) → `PickupRouteMapScreen`.

---

## 4. Các bước thực hiện

### Giai đoạn 0 — Backend chuẩn bị (Laravel)

1. **Mở proxy cho mobile**: trong `routes/api.php`, nhóm `mobile`, thêm
   `Route::prefix('vietmap')->name('vietmap.')->group(...)` map tới
   `VietmapProxyController@search|place|reverse|routeDirections`, đặt sau middleware
   `auth:sanctum` (đồng guard với shipper). Áp throttle hợp lý (vd `throttle:120,1`).
2. **Endpoint config mobile**: `GET /api/mobile/config` trả JSON
   `{ "vietmap": { "tile_api_key": "<public-tile-key>" } }`, đọc từ `Setting.options`.
   Tạo `vietmap_tile_api_key` riêng nếu chưa có (tách khỏi geocode key).
3. **Kiểm thử proxy** bằng token shipper: gọi thử `route` 2 điểm, xác nhận trả `paths[0]`.

> Trước khi sửa các symbol controller/route, chạy `gitnexus_impact` theo CLAUDE.md.

### Giai đoạn 1 — Map GL trong Flutter (MVP)

4. **Thêm dependency** vào `pubspec.yaml`:
   - `vietmap_flutter_gl` (map GL)
   - `geolocator` (vị trí shipper — chính xác hơn `permission_handler` đơn thuần)
   Chạy `flutter pub get`.
5. **Cấu hình platform**:
   - Android: `minSdkVersion >= 21` (`android/app/build.gradle`); quyền
     `ACCESS_FINE_LOCATION` / `ACCESS_COARSE_LOCATION` trong `AndroidManifest.xml`.
   - iOS: `NSLocationWhenInUseUsageDescription` trong `Info.plist`; `platform :ios, '12.0'`+.
6. **Config provider**: `mobile_config_api.dart` + provider nạp `tileApiKey` (cache lại,
   gọi 1 lần sau login). Không lưu key vào `.env` cứng.
7. **Location service**: `location_service.dart` bọc `geolocator` —
   `ensurePermission()` + `currentPosition()`, xử lý từ chối quyền (trả lỗi rõ ràng,
   gợi ý mở settings).
8. **VietMap API client**: `vietmap_api.dart` — `getRoute({origin, destination, vehicle:'motorcycle'})`
   gọi `/vietmap/route` qua `DioClient`, parse `paths[0]` → `RoutePath`
   (`coordinates: List<LatLng>`, `distanceMeters`, `durationMs`). Port logic chuẩn hoá
   `[lng,lat]` vs `[lat,lng]` từ `routeCoordinates()` ở web JS.
9. **Màn hình bản đồ** `pickup_route_map_screen.dart`:
   - `VietmapGL` widget với `styleString` = style URL + tileApiKey.
   - Marker pickup ("P", đỏ) tại `PickupLocation`.
   - Nút "Tìm đường": lấy vị trí shipper → gọi route → vẽ polyline (line layer) +
     marker shipper → `fitBounds` (hoặc `animateCamera` với bounds).
   - Panel thông tin: mã pickup, công ty, địa chỉ, SĐT, khoảng cách, thời gian
     (tái dùng format `formatDistance/formatDuration` từ JS).
   - Nút fallback "Mở Google/Apple Maps" → `ContactActions.openMap()` (giữ nguyên).
10. **Controller** `pickup_route_controller.dart` (Riverpod): state machine
    `idle → locating → routing → drawn → error`. Tách UI khỏi logic gọi API.
11. **Routing**: thêm `AppRoutes.pickupRoute` + GoRoute con trong `router.dart`;
    thêm nút "Chỉ đường" ở `pickup_detail_screen.dart` / `pickup_card.dart`
    (chỉ hiện khi `location.hasLocation == true`).
12. **Kiểm thử thủ công**: chạy app, mở 1 pickup có toạ độ, bấm "Chỉ đường",
    kiểm tra tile render, route vẽ đúng, distance/time khớp web.

### Giai đoạn 2 — Điều hướng turn-by-turn (TUỲ CHỌN)

13. Đánh giá nhu cầu nghiệp vụ: shipper có cần dẫn đường giọng nói liên tục không?
14. Nếu có: thêm `vietmap_flutter_navigation`, tạo màn hình `NavigationView`,
    `buildRoute(waypoints)`, xử lý vòng đời (start/stop, recenter, arrival).
15. Cấu hình thêm quyền nền (background location) nếu cần theo dõi khi tắt màn hình.

---

## 5. Rủi ro & lưu ý

| Rủi ro | Giảm thiểu |
|---|---|
| Tile key lộ ở client | Dùng tile key riêng, giới hạn domain/quota; khác key server. |
| Quyền vị trí bị từ chối | Thông báo rõ + nút mở settings; fallback nút Google Maps. |
| Toạ độ pickup thiếu | Ẩn nút "Chỉ đường" khi `hasLocation == false`. |
| Định dạng coordinate đảo `[lng,lat]`/`[lat,lng]` | Port nguyên hàm chuẩn hoá từ web JS. |
| Build native nặng (Giai đoạn 2) | Chỉ thêm khi xác nhận cần navigation. |
| Web platform của `vietmap_flutter_gl` | App này chỉ Android/iOS — không cần lo web. |

## 6. Tiêu chí hoàn thành (Giai đoạn 1)

- [ ] Mở pickup có toạ độ → thấy bản đồ VietMap render trong app.
- [ ] Bấm "Tìm đường" → vẽ tuyến từ vị trí shipper tới pickup, hiện khoảng cách + thời gian.
- [ ] Từ chối quyền vị trí → thông báo rõ, không crash, có fallback.
- [ ] Không có API key nào hard-code trong source/`.env` commit.
- [ ] Chạy `gitnexus_detect_changes()` trước commit, blast radius đúng kỳ vọng.
