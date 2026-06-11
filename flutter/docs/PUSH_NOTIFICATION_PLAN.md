# Push Notification Integration Plan

## Mục tiêu

Khi một pickup được giao cho shipper, app Flutter của đúng shipper đó nhận thông báo đẩy trên iOS/Android. Khi bấm vào thông báo, app mở trực tiếp màn chi tiết pickup.

## Quyết định kỹ thuật

- Dùng Firebase Cloud Messaging (FCM) cho push notification.
- Dùng `firebase_messaging` trong Flutter để nhận token và message.
- Dùng `flutter_local_notifications` để hiển thị thông báo khi app đang foreground.
- Laravel lưu FCM token theo user/device và gửi notification qua queue.
- Payload push chỉ chứa dữ liệu định tuyến tối thiểu, không gửi thông tin nhạy cảm như số điện thoại, địa chỉ khách hàng.

## Luồng tổng thể

1. Shipper đăng nhập app.
2. Flutter xin quyền notification.
3. Flutter lấy FCM token của thiết bị.
4. Flutter gửi token lên Laravel qua API.
5. OPS/Admin gán pickup cho shipper.
6. Laravel tạo notification job.
7. Job gửi push đến các FCM token còn hiệu lực của shipper.
8. Shipper bấm notification.
9. App mở `/shipper/pickups/{pickup_id}` và gọi API lấy detail mới nhất.

## Phase 1: Firebase Setup

- Tạo Firebase project cho Renew Logistics.
- Thêm Android app với package hiện tại.
- Thêm iOS app với bundle id hiện tại.
- Tải cấu hình:
  - Android: `google-services.json`
  - iOS: `GoogleService-Info.plist`
- Cấu hình APNs cho iOS trong Firebase Console.
- Bật Push Notifications và Background Modes trong Xcode nếu cần.

## Phase 2: Flutter Client

- Thêm dependencies:
  - `firebase_core`
  - `firebase_messaging`
  - `flutter_local_notifications`
- Khởi tạo Firebase trong `main.dart`.
- Tạo service ví dụ: `core/notifications/push_notification_service.dart`.
- Service cần xử lý:
  - xin quyền notification trên iOS
  - lấy FCM token
  - lắng nghe token refresh
  - đăng ký token lên Laravel sau login
  - xóa hoặc vô hiệu hóa token khi logout
  - nhận foreground message và hiển thị local notification
  - xử lý notification tap khi app foreground/background/terminated
- Data payload đề xuất:

```json
{
  "type": "pickup_assigned",
  "pickup_id": "123",
  "pickup_code": "PICKYOF94WJ5"
}
```

## Phase 3: Laravel API

- Tạo bảng `user_device_tokens`:
  - `id`
  - `user_id`
  - `fcm_token`
  - `platform`
  - `device_name`
  - `app_version`
  - `last_seen_at`
  - `revoked_at`
  - timestamps
- Tạo endpoint mobile:
  - `POST /api/mobile/device-tokens`
  - `DELETE /api/mobile/device-tokens/{token}` hoặc `POST /api/mobile/device-tokens/revoke`
- Khi nhận token mới:
  - upsert theo `fcm_token`
  - gắn với user hiện tại
  - cập nhật `last_seen_at`
- Khi logout:
  - revoke token của thiết bị hiện tại nếu app gửi lên.

## Phase 4: Laravel Sending

- Tạo notification class: `PickupAssignedNotification`.
- Tạo queued job hoặc queued notification.
- Trigger khi pickup được gán `id_shipper`:
  - trong action/service gán pickup
  - hoặc observer nếu đã có điểm cập nhật tập trung
- Gửi đến toàn bộ token active của shipper.
- Nếu FCM trả token invalid/not registered, đánh dấu token `revoked_at`.

## Phase 5: UX Trong App

- Foreground:
  - hiện local notification hoặc in-app banner.
  - refresh danh sách pickup nếu đang ở màn `PickupListScreen`.
- Background/terminated:
  - bấm notification mở pickup detail.
- Nếu pickup không còn tồn tại hoặc không thuộc quyền shipper:
  - mở app về danh sách pickup và hiển thị snackbar lỗi nhẹ.

## Phase 6: Bảo mật và Quy tắc Payload

- Không gửi địa chỉ, số điện thoại, thông tin khách hàng trong push payload.
- Chỉ gửi `type`, `pickup_id`, `pickup_code`.
- Luôn gọi API detail sau khi user mở notification.
- Backend phải kiểm tra quyền shipper trước khi trả detail.
- FCM service account chỉ lưu ở server, không đưa vào Flutter app.

## Phase 7: Testing

- Test iOS real device vì simulator không phản ánh đầy đủ APNs.
- Test Android emulator/device.
- Các trạng thái cần test:
  - app foreground
  - app background
  - app terminated
  - user logout rồi nhận push cũ
  - token refresh
  - nhiều thiết bị cùng đăng nhập một shipper
  - pickup bị đổi shipper sau khi notification đã gửi

## Checklist Triển Khai

- [ ] Tạo Firebase project và app iOS/Android. (thủ công — Firebase Console)
- [ ] Thêm Firebase config vào Flutter. (google-services.json / GoogleService-Info.plist)
- [x] Cài dependencies Flutter. (firebase_core, firebase_messaging, flutter_local_notifications)
- [x] Tạo push notification service trong Flutter. (`core/notifications/push_notification_service.dart`)
- [x] Tạo API đăng ký/revoke device token trong Laravel. (`MobileDeviceTokenController`)
- [x] Tạo bảng `user_device_tokens`.
- [x] Tạo service gửi FCM ở Laravel. (`app/Services/Push/FcmSender.php`, kreait/laravel-firebase)
- [x] Trigger gửi push khi gán pickup cho shipper. (`PickupObserver` + `SendPickupAssignedPush` job)
- [x] Điều hướng app khi bấm notification. (`app.dart` → `/shipper/pickups/{id}`)
- [ ] Test foreground/background/terminated. (cần Firebase config + thiết bị thật)
- [x] Log lỗi gửi FCM và cleanup token không hợp lệ. (`FcmSender` revoke unknown/invalid tokens)

## Trạng thái triển khai (đã code)

Toàn bộ code hai phía đã hoàn tất và degrade an toàn khi CHƯA có Firebase config:

- **Backend (Laravel):**
  - Bảng `user_device_tokens` + model + relation `User::deviceTokens()`.
  - Bảng queue (`jobs`, `job_batches`, `failed_jobs`) cho `QUEUE_CONNECTION=database`.
  - `FcmSender`: gửi FCM HTTP v1, tự revoke token unknown/invalid. **No-op an toàn** nếu chưa set `FIREBASE_CREDENTIALS` (chỉ log, không vỡ luồng gán shipper).
  - `SendPickupAssignedPush` (queued): nhận `pickupId` + `shipperId` tường minh → tránh race khi pickup đổi shipper sau enqueue.
  - `PickupObserver`: bắn job khi `id_shipper` được set lúc tạo, hoặc đổi sang shipper mới (dùng `wasChanged`).
  - Endpoint: `POST /api/mobile/device-tokens` (upsert), `POST /api/mobile/device-tokens/revoke`.
- **Flutter:**
  - `PushNotificationService`: init Firebase an toàn (try/catch → `isAvailable`), xin quyền, lấy token, token refresh, foreground local notification, xử lý tap (foreground/background/terminated).
  - `PushRegistration` provider: đăng ký token sau login, revoke trước logout, tự đăng ký lại khi token refresh.
  - `main.dart` init push lúc bootstrap; `app.dart` gắn handler tap → điều hướng pickup detail.
- **Native:**
  - Android: `POST_NOTIFICATIONS` permission, default channel `pickup_alerts`, google-services plugin (đã có sẵn).
  - iOS: `UIBackgroundModes: remote-notification` trong Info.plist; APNs key cần cấu hình thủ công.

## Việc còn lại để KÍCH HOẠT (thủ công, ngoài code)

1. Tạo Firebase project, thêm app Android (package `com.hethong.shipper_ops_app`) + iOS (bundle `com.hethong.shipperOpsApp`).
2. Tải `google-services.json` → `flutter/android/app/`, `GoogleService-Info.plist` → `flutter/ios/Runner/` (thêm vào Xcode target).
3. Cấu hình APNs key/cert trong Firebase Console (iOS), bật Push Notifications capability trong Xcode.
4. Tải service account JSON về server, set `FIREBASE_CREDENTIALS=/đường/dẫn/service-account.json` (+ `FIREBASE_PROJECT_ID`) trong `.env` backend.
5. Chạy queue worker: `php artisan queue:work` (hoặc supervisor) để gửi push.

## Ưu tiên triển khai

1. Android trước để test nhanh FCM token và notification.
2. iOS real device sau khi có APNs key/certificate.
3. Tích hợp Laravel queue.
4. Tối ưu UX foreground banner và auto refresh list.
