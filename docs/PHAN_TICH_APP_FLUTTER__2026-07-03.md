# PHÂN TÍCH APP FLUTTER — shipper_ops_app

- **Ngày phân tích:** 2026-07-03
- **Phạm vi:** app mobile `flutter/` cho Shipper (pickup hiện trường) và OPS (scan/nhập kho) — tiêu thụ backend Laravel qua `/api/mobile/*`.
- **Trạng thái đo được lúc phân tích:** `flutter analyze` → **0 issue**; `flutter test` → **40/40 passed**; 0 TODO/FIXME trong code; build release AAB ✓ (sau fix đợt 8).
- **Tài liệu liên quan:** `flutter/docs/APP_FLOW.md` (kiến trúc + luồng chạy chi tiết), `docs/MOBILE_API_CONTRACT.md` (contract API), `docs/TEST_PLAN__VaLoi_PhanQuyen_TaoDon__2026-07-03.md` mục 4H (test release signing), `docs/DE_XUAT_TINH_NANG_CHAO_GIA__2026-07-03.md` mục C2 (mô hình per-VPS/per-KH).

---

## 1. Tổng quan

| Thành phần | Công nghệ | Ghi chú |
|---|---|---|
| State/DI | Riverpod 2.6 | Không codegen; provider singleton + cơ chế session reset |
| Routing | go_router 14 | Auth guard theo `AuthStatus`; redirect theo `default_module` (shipper/ops/chooser) |
| HTTP | Dio 5 | Envelope chuẩn `success/message/data/errors` khớp backend |
| Token | flutter_secure_storage | Keychain iOS / Keystore Android — không dùng shared_preferences |
| Scan | mobile_scanner | QR + Code128 (khớp barcode tem PDF/print của web) |
| Bản đồ | vietmap_flutter_gl + geolocator | Chỉ đường shipper tới điểm pickup |
| Push | firebase_messaging + flutter_local_notifications | Deep-link theo payload `type/pickup_id/order_id/news_id` |
| Khác | local_auth (sinh trắc học), image_picker, connectivity_plus, dotenv | |

Quy mô: **106 file Dart / ~17.3k dòng**, cấu trúc feature-first:
`auth`, `shipper_pickup`, `shipper_scan`, `ops_scan`, `ops_orders`, `ops_pickups`,
`notifications`, `profile` — mỗi feature tách `data/domain/presentation`,
repository interface ở domain (không biết Dio). Test: 7 file / 40 case
(parse model, envelope, paginated, status, pending store).

2 vai trò: **Shipper** (role `shipper`) và **OPS** (role `ops|admin|manager|cs`);
tài khoản có cả 2 quyền → màn chọn module.

## 2. Các điểm làm ĐÚNG (giữ nguyên, dùng làm chuẩn khi mở rộng)

1. **Networking một cửa** (`core/api/dio_client.dart`): mọi request qua `DioClient`
   — parse envelope, map HTTP status → `ApiErrorKind`, message fallback tiếng Việt,
   debug log **tự che password/token**. 401 xử lý tập trung ở interceptor
   (xóa token + đẩy về login), không rải rác từng màn.
2. **Bảo mật chuẩn:**
   - Token Sanctum trong Keychain/Keystore.
   - Cờ dev (`DEV_ALLOW_BAD_CERT`, `DEV_RESOLVE_IP`) **tự vô hiệu** khi build
     release hoặc backend không phải host `.local` — không thể quên tắt.
   - VietMap tile key lấy runtime từ `/config`; geocode/route key giấu sau proxy server.
   - Đăng nhập sinh trắc học (local_auth).
3. **Offline queue cho nghiệp vụ hiện trường**
   (`shipper_pickup/data/pending_status_sync.dart`): đổi trạng thái pickup khi
   mất mạng → hàng đợi; tự drain khi online; phân loại lỗi đúng
   (conflict/validation/404 → bỏ; lỗi mạng/401 → giữ lại); stream báo màn đang
   mở tự refresh.
4. **Session hygiene** (`core/session/session_reset.dart`): reset ~17 provider
   giữ state user mỗi khi đổi phiên — user mới không thấy dữ liệu user cũ.
   **Quy tắc khi thêm controller mới giữ dữ liệu user: thêm 1 dòng vào
   `_sessionScopedProviders`.**
5. **Khớp contract 100%:** app gọi đủ và đúng **27/27 endpoint** `/api/mobile/*`
   (đối chiếu `routes/api.php` 2026-07-03) — không gọi endpoint không tồn tại.
6. **Docs nội bộ:** `flutter/docs/APP_FLOW.md`, `PUSH_NOTIFICATION_PLAN.md`,
   `UI_ANALYSIS.md`, plan bản đồ.

## 3. Vấn đề & trạng thái xử lý

### ✅ P0 — Android release ký debug key — ĐÃ FIX (2026-07-03, đợt 8)

- **Trước:** `buildTypes.release` dùng `signingConfigs.getByName("debug")` →
  không phát hành store được; mỗi máy build chữ ký khác nhau → user không
  update đè được.
- **Đã fix:** signingConfig `release` đọc từ `android/key.properties`
  (gitignore); keystore RSA 2048 hạn 30 năm tại `android/keystore/release.jks`
  (gitignore); **fail-fast** — thiếu `key.properties` là build release fail
  ngay với hướng dẫn, không âm thầm ký debug.
- **Đã kiểm chứng:** AAB build ✓, fingerprint SHA-256 khớp keystore;
  fail-fast hoạt động; build debug không ảnh hưởng.
- **⚠️ Vận hành:** backup `release.jks` + `key.properties` ở ≥2 nơi an toàn
  (xem `flutter/android/keystore/README.md`) — mất keystore = mất khả năng
  update app vĩnh viễn. Máy đang cài bản debug-signed phải gỡ + cài lại
  MỘT LẦN khi nhận bản chính thức đầu tiên.
- Test case cho tester: mục **4H** test plan (FL-01→07).

### 🟡 P1 — Chưa có chiến lược build per-KH (gắn với mô hình per-VPS đã chốt)

Hiện trạng: `.env` (API_BASE_URL) đóng gói vào binary lúc build;
`applicationId` chung `com.hethong.shipper_ops_app`; **1 Firebase project**
commit sẵn (`google-services.json` / `GoogleService-Info.plist`);
iOS bundle id `com.hethong.shipperOpsApp` (lệch style Android — lưu ý khi
đăng ký Firebase per-KH).

Với mô hình mỗi KH 1 source + 1 VPS: mỗi KH cần build app riêng
(URL backend riêng) + **Firebase project riêng** (push FCM không dùng chung
được khi khác backend) + app id/tên/icon riêng nếu KH tự đứng tên store.

**Việc cần làm khi có KH đầu tiên mua app** (~2-3 ngày):
1. Android **product flavors** (hoặc script build) tham số hóa: `.env`,
   `applicationId`, app name/icon, `google-services.json`.
2. iOS tương ứng: scheme/xcconfig per-KH + `GoogleService-Info.plist` per-KH.
3. Mỗi KH một keystore riêng nếu KH tự đứng tên store (lệnh có sẵn trong
   `android/key.properties.example`).
4. Ghi bước "build app theo KH" vào quy trình cài KH mới (C2 mục 1).

**Câu hỏi kinh doanh cần chốt trước khi làm:** KH tự đứng tên app trên
store của họ, hay mình phát hành hộ dưới tài khoản của mình?

Quyết định: `[ ]`

### 🟡 P1 — Test mỏng ở tầng luồng nghiệp vụ

40 test hiện có đều là parse/store thuần. Chưa có widget/integration test cho:
login → guard redirect đúng module; scan → receive; **đổi trạng thái offline →
online → drain** (luồng đáng tiền nhất). Đề xuất bổ sung 3 luồng đó trước
(~1-2 ngày), dùng `ProviderContainer` override repository giả — kiến trúc
DI hiện tại hỗ trợ sẵn.

Quyết định: `[ ]`

### 🟢 P2 — Vệ sinh nhỏ (làm khi tiện)

- 2 màn detail ~1.100 dòng/file (`pickup_detail_screen.dart`,
  `ops_pickup_detail_screen.dart`) — ứng viên tách widget.
- `flutter/README.md` còn là boilerplate — nên trỏ sang `docs/APP_FLOW.md`
  + hướng dẫn build/keystore.
- `version: 1.0.0+1` chưa từng bump — cần quy ước bump theo release khi giao KH
  (versionCode tăng mỗi bản phát hành).

## 4. Kết luận

Chất lượng app cao hơn mặt bằng chung app Flutter nghiệp vụ: kiến trúc sạch,
bảo mật đúng từ đầu, offline queue thông minh, khớp 100% contract, analyze/test
xanh. Không có nợ kiến trúc đáng kể. Blocker phát hành duy nhất (release
signing) đã xử lý xong; còn lại là việc chuẩn bị nhân bản theo KH (P1 build
flavors — chỉ làm khi chốt câu hỏi kinh doanh ai đứng tên store) và dày thêm
test luồng chính.
