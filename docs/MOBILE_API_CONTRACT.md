# Mobile API Contract — Shipper & OPS

> Ngày lập: 10/06/2026
> Phạm vi: API mobile cho app Flutter (Shipper pickup + OPS scan/nhập kho)
> Backend: Laravel + Sanctum 4.3 + Spatie Permission
> Trạng thái: API contract — chốt trước khi code Phase 1
> Nguyên tắc: API tái sử dụng business action hiện có (`TransitionPickupStatusAction`, `RecordTrackingHistoryAction`), enum hiện có (`PickupStatusEnum`, `OrderStatusEnum`, `RoleEnum`). App KHÔNG tự quyết nghiệp vụ.

---

## 1. Quy Ước Chung

### 1.1. Base URL & versioning

```text
{APP_URL}/api/mobile
```

- MVP chưa version path. Khi cần, thêm header `Accept: application/vnd.hethong.v1+json` hoặc prefix `/api/mobile/v1` ở Phase 4 (mục 4.8 roadmap).
- Mọi request gửi header `Accept: application/json` để Laravel trả JSON thay vì redirect.

### 1.2. Authentication

- Cơ chế: **Laravel Sanctum personal access token** (Bearer token), KHÔNG dùng session cookie.
- Mọi route (trừ `login`) yêu cầu header:

```http
Authorization: Bearer {token}
Accept: application/json
```

- Token lưu phía app bằng `flutter_secure_storage`.
- Thiếu/sai token → `401`. Đủ token nhưng sai quyền → `403`.

### 1.3. Response envelope (chuẩn hóa toàn bộ API mobile)

Thành công:

```json
{
  "success": true,
  "message": "Thành công.",
  "data": { }
}
```

Thất bại:

```json
{
  "success": false,
  "message": "Mô tả lỗi cho người dùng.",
  "errors": {
    "field": ["Chi tiết lỗi từng field."]
  }
}
```

Quy ước:
- `success`: luôn có, boolean.
- `message`: luôn có, string tiếng Việt, hiển thị được cho người dùng.
- `data`: chỉ có khi `success = true`. Có thể là object, array, hoặc `null`.
- `errors`: chỉ có khi validation lỗi (`422`). Dạng `{ field: [messages] }` chuẩn Laravel.

> Lý do chọn format này: khớp với controller API đang chạy trong dự án (`ThirdPartyOrderTrackingController` dùng `success`/`message`/`data`), bổ sung `errors` cho validation.

### 1.4. HTTP status code

| Code | Khi nào | App xử lý |
| --- | --- | --- |
| `200` | Thành công | Hiển thị data |
| `201` | Tạo mới thành công (nếu có) | — |
| `401` | Token thiếu/sai/hết hạn | Xóa token, về màn Login |
| `403` | Đúng token, sai quyền/role | Toast "Không có quyền" |
| `404` | Không tìm thấy resource | Toast lỗi |
| `409` | Xung đột trạng thái (FSM/đã xử lý) | Toast lý do, refresh |
| `422` | Validation lỗi | Hiển thị `errors` theo field |
| `429` | Vượt rate limit | Toast "Thao tác quá nhanh" |
| `500` | Lỗi server | Toast "Lỗi hệ thống" + retry |

### 1.5. Pagination (cho list)

Theo chuẩn Laravel paginator, gói trong `data`:

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "items": [ ],
    "meta": {
      "current_page": 1,
      "per_page": 15,
      "total": 42,
      "last_page": 3,
      "has_more": true
    }
  }
}
```

### 1.6. Rate limit

| Nhóm route | Giới hạn đề xuất | Lý do |
| --- | --- | --- |
| `login` | 5 req / phút / IP | Chống brute-force |
| `ops/scan` | 60 req / phút / user | Quét liên tục nhưng tránh spam |
| `ops/*/receive`, `bulk-receive` | 30 req / phút / user | Ghi DB, ghi history |
| Còn lại | 120 req / phút / user | Mặc định |

Định nghĩa bằng `RateLimiter::for()` trong `AppServiceProvider`/`bootstrap`.

### 1.7. Định dạng dữ liệu

- Datetime: ISO 8601 có timezone, ví dụ `2026-06-10T08:30:00+07:00`.
- Số cân nặng: number (kg), không format chuỗi ở API (app tự format hiển thị).
- Enum status: luôn trả object `{ "value": "...", "label": "..." }`, kèm `color` nếu UI cần.

---

## 2. Auth API

### 2.1. `POST /api/mobile/login`

Đăng nhập, cấp Sanctum token.

**Public** (không cần token). Rate limit `5/phút/IP`.

Request:

```json
{
  "username": "shipper01",
  "password": "secret",
  "device_name": "Pixel 7 - shipper01"
}
```

| Field | Bắt buộc | Ghi chú |
| --- | --- | --- |
| `username` | ✓ | Khớp cột `user.username` |
| `password` | ✓ | — |
| `device_name` | ✗ | Tên token (gửi từ `device_info_plus`); mặc định `"mobile"` nếu trống |

Response `200`:

```json
{
  "success": true,
  "message": "Đăng nhập thành công.",
  "data": {
    "token": "12|abcdef...",
    "user": {
      "id": 12,
      "username": "shipper01",
      "fullname": "Nguyễn Văn A",
      "phone": "0901234567",
      "email": "a@example.com",
      "avatar": "https://.../avatar.jpg"
    },
    "roles": ["shipper"],
    "permissions": ["pickups.index"],
    "default_module": "shipper"
  }
}
```

- `roles`: lấy từ Spatie `getRoleNames()`.
- `permissions`: `getAllPermissions()->pluck('name')`. App chỉ dùng để ẩn/hiện UI; backend vẫn là nguồn quyết định.
- `default_module`: backend tính theo role để app điều hướng:
  - chỉ có `shipper` → `"shipper"`
  - có role OPS-capable (`ops`/`admin`/`manager`/`cs`) → `"ops"`
  - có cả hai → `"chooser"` (app hiện màn chọn module)

Response `401`:

```json
{
  "success": false,
  "message": "Tài khoản hoặc mật khẩu không đúng."
}
```

Response `403` (tài khoản bị khóa — `user.status = false`):

```json
{
  "success": false,
  "message": "Tài khoản đã bị khóa."
}
```

Response `422`:

```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ.",
  "errors": {
    "username": ["Vui lòng nhập tên đăng nhập."]
  }
}
```

**Quy tắc nghiệp vụ:**
- Chỉ cho login nếu user có ít nhất một trong các role mobile dùng được: `shipper`, `ops`, `admin`, `manager`, `cs`. Role khác (ví dụ chỉ `ctv`) → `403` "Tài khoản không có quyền dùng ứng dụng."
- Kiểm tra `user.status` (active) trước khi cấp token.

---

### 2.2. `GET /api/mobile/me`

Lấy thông tin user đang đăng nhập. App gọi sau khi khôi phục token để xác thực còn hiệu lực và lấy role điều hướng.

**Auth required.**

Response `200`: giống block `data.user` + `roles` + `permissions` + `default_module` của login (không có `token`).

Response `401`: token hết hạn/bị revoke.

---

### 2.3. `POST /api/mobile/logout`

Thu hồi token hiện tại.

**Auth required.**

Request: rỗng.

Response `200`:

```json
{
  "success": true,
  "message": "Đã đăng xuất."
}
```

Backend: `$request->user()->currentAccessToken()->delete()`.

---

## 3. Shipper Pickup API

> Tất cả route nhóm này **bắt buộc** filter `id_shipper = auth()->id()`. Shipper không bao giờ thấy/đụng pickup của người khác. Gate hiện có: `pickups.index`.

Lưu ý cấu trúc dữ liệu thật (đã verify trong model `Pickup` + component shipper):
- Địa chỉ/khách hàng nằm trong JSON cột `info_khachhang`: `company`, `fullname`, `phone`, `address`, `country`, `province_id`, `ward_id`, `pickup_lat`, `pickup_lng`.
- Lịch hẹn nằm trong JSON cột `info_pickup`: `ngayhen`, `id_phuongtien`.
- Số kiện = cột `numb`. Cân nặng tính phí = `total_c_weight`. Cân nặng gross = `total_weight`.

### 3.1. `GET /api/mobile/shipper/pickups`

Danh sách pickup của shipper đang đăng nhập.

**Auth + role `shipper`** (hoặc admin/manager nếu muốn xem hỗ trợ — mặc định MVP chỉ `shipper`).

Query params:

| Param | Bắt buộc | Ghi chú |
| --- | --- | --- |
| `tab` | ✗ | `new` / `accepted` / `picking` / `done` — map status (xem §6.1). Mặc định `new` |
| `status` | ✗ | Lọc trực tiếp theo value `PickupStatusEnum` (thay cho `tab`) |
| `keyword` | ✗ | Tìm trong `ma_pickup` và `info_khachhang` (tên/SĐT/địa chỉ) |
| `page` | ✗ | Mặc định 1 |
| `per_page` | ✗ | Mặc định 15 |

Mặc định (không truyền filter): ẩn pickup `DA_HUY`.

Response `200`:

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "summary": {
      "pending_count": 5,
      "nearest_schedule_at": "2026-06-10T09:00:00+07:00"
    },
    "items": [
      {
        "id": 101,
        "ma_pickup": "PICKAB12CD34",
        "status": { "value": "moi_tao_pickup", "label": "Mới tạo", "color": "bg-neutral-100 text-neutral-700" },
        "customer": {
          "company": "Công ty TNHH ABC",
          "fullname": "Trần Thị B",
          "phone": "0907654321",
          "address": "123 Lê Lợi, Q1, TP.HCM",
          "country": "VN"
        },
        "location": {
          "lat": 10.7769,
          "lng": 106.7009,
          "has_location": true
        },
        "scheduled_at": "2026-06-10T09:00:00+07:00",
        "package_count": 3,
        "weight_kg": 12.5,
        "orders_count": 2,
        "note": "Gọi trước khi đến",
        "created_by": "Nhân viên CS",
        "allowed_transitions": [
          { "value": "da_xac_nhan", "label": "Đã xác nhận" },
          { "value": "da_huy", "label": "Đã hủy" }
        ]
      }
    ],
    "meta": {
      "current_page": 1, "per_page": 15, "total": 5, "last_page": 1, "has_more": false
    }
  }
}
```

- `allowed_transitions`: lấy từ `$pickup->status->allowedTransitions()`. **App chỉ hiển thị nút theo danh sách này** — không hardcode FSM phía app.
- `weight_kg`: số thực (kg), từ `total_c_weight`.
- `summary`: phục vụ header app (tổng đơn chưa lấy + giờ hẹn gần nhất).

### 3.2. `GET /api/mobile/shipper/pickups/{pickup}`

Chi tiết một pickup.

**Auth + role `shipper`**, và `pickup.id_shipper = auth()->id()` (nếu không → `404`, không tiết lộ tồn tại).

Response `200`: như item ở §3.1 nhưng đầy đủ hơn, kèm danh sách đơn:

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "id": 101,
    "ma_pickup": "PICKAB12CD34",
    "status": { "value": "pickup_dang_lay", "label": "Đang lấy hàng", "color": "bg-amber-100 text-amber-700" },
    "customer": {
      "company": "Công ty TNHH ABC",
      "fullname": "Trần Thị B",
      "phone": "0907654321",
      "address": "123 Lê Lợi, Q1, TP.HCM",
      "country": "VN"
    },
    "location": { "lat": 10.7769, "lng": 106.7009, "has_location": true },
    "scheduled_at": "2026-06-10T09:00:00+07:00",
    "package_count": 3,
    "weight_kg": 12.5,
    "weight_gross_kg": 14.0,
    "note": "Gọi trước khi đến",
    "created_by": "Nhân viên CS",
    "created_at": "2026-06-09T17:00:00+07:00",
    "orders": [
      { "id": 555, "id_bill": "HT250609001", "tracking_code": "TRK123", "uuid": "..." }
    ],
    "allowed_transitions": [
      { "value": "pickup_da_lay", "label": "Đã lấy hàng" },
      { "value": "da_huy", "label": "Đã hủy" }
    ]
  }
}
```

### 3.3. `POST /api/mobile/shipper/pickups/{pickup}/status`

Cập nhật trạng thái pickup (tiếp nhận, bắt đầu lấy, đã lấy, hủy).

**Auth + role `shipper`**, và `pickup.id_shipper = auth()->id()`.

Request:

```json
{
  "status": "pickup_dang_lay",
  "reason": "Khách hẹn lại",
  "lat": 10.7769,
  "lng": 106.7009
}
```

| Field | Bắt buộc | Ghi chú |
| --- | --- | --- |
| `status` | ✓ | Phải là value hợp lệ `PickupStatusEnum`; phải nằm trong `allowedTransitions()` của trạng thái hiện tại |
| `reason` | ✗ | Bắt buộc khi `status = da_huy` (khuyến nghị) |
| `lat`, `lng` | ✗ | Optional ở MVP — chỉ lưu nếu gửi (GPS check-in). Xem §7 |

Backend xử lý:
1. Validate `status` thuộc `PickupStatusEnum::values()`.
2. Gọi `TransitionPickupStatusAction::execute($pickup, PickupStatusEnum::from($status))`.
   - Action tự kiểm tra `canTransitionTo()` (lock row, FSM). Sai FSM → ném `RuntimeException`.
   - Nếu `status = da_huy`, action tự set `id_shipper = null` để điều phối gán lại (logic đã có sẵn).
3. Ghi log thao tác (shipper, pickup, from→to status, time).

Response `200`:

```json
{
  "success": true,
  "message": "Đã cập nhật trạng thái.",
  "data": {
    "id": 101,
    "status": { "value": "pickup_dang_lay", "label": "Đang lấy hàng", "color": "bg-amber-100 text-amber-700" },
    "allowed_transitions": [
      { "value": "pickup_da_lay", "label": "Đã lấy hàng" },
      { "value": "da_huy", "label": "Đã hủy" }
    ]
  }
}
```

Response `409` (sai FSM — bắt từ `RuntimeException`):

```json
{
  "success": false,
  "message": "Không thể chuyển Pickup từ Đã lấy hàng sang Đang lấy hàng."
}
```

Response `422` (status không hợp lệ):

```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ.",
  "errors": { "status": ["Trạng thái không hợp lệ."] }
}
```

### 3.4. Phase sau (không thuộc MVP)

| Endpoint | Trạng thái | Ghi chú |
| --- | --- | --- |
| `POST /api/mobile/shipper/pickups/{pickup}/photos` | Phase 4 | Upload ảnh lấy hàng (multipart, nén phía app). Cần migration bảng ảnh pickup |
| `POST /api/mobile/shipper/pickups/{pickup}/location` | Phase 4 | GPS check-in/out riêng. **Lưu ý:** cột `pickup_lat`/`pickup_lng` hiện nằm trong JSON `info_khachhang`; bảng `pickup` chưa có cột tọa độ phẳng. Cần chốt nơi lưu trước khi làm |

---

## 4. OPS Scan API

> Role được phép: `ops`, `admin`, `manager`, `cs` (đồng bộ với web — OPS chỉ chuyển `DA_XAC_NHAN → DA_NHAN_HANG`).

Thứ tự match mã (đã verify field tồn tại trong model `Order`/`OrderPackage`):
1. `orders.id_bill`
2. `orders.tracking_code`
3. `orders.mathamchieu`
4. `order_package.code` → trả về order cha

### 4.1. `POST /api/mobile/ops/scan`

Tra cứu đơn theo mã quét. **Chỉ đọc, không thay đổi dữ liệu.**

**Auth + role OPS-capable.** Rate limit `60/phút/user`.

Request:

```json
{ "code": "HT250609001" }
```

Response `200` — tìm thấy:

```json
{
  "success": true,
  "message": "Tìm thấy đơn hàng.",
  "data": {
    "found": true,
    "matched_by": "id_bill",
    "matched_package_code": null,
    "order": {
      "id": 555,
      "id_bill": "HT250609001",
      "tracking_code": "TRK123",
      "mathamchieu": "REF456",
      "status": { "value": "da_xac_nhan", "label": "Đã xác nhận", "color": "bg-blue-100 text-blue-700" },
      "sender": { "company": "ABC", "fullname": "Trần Thị B", "phone": "0907654321" },
      "receiver": { "fullname": "John Doe", "country": "USA" },
      "package_count": 3,
      "weight_kg": 12.5,
      "sale_name": "Nguyễn Văn S",
      "locked": false,
      "received_at": null
    },
    "can_receive": true,
    "reason": null
  }
}
```

- `matched_by`: một trong `id_bill | tracking_code | mathamchieu | package_code`.
- `matched_package_code`: điền khi `matched_by = package_code` (mã kiện đã quét), ngược lại `null`.
- `can_receive`: `true` chỉ khi đơn ở `DA_XAC_NHAN` **và** `lock_order = false` **và** user đủ role.
- `reason`: lý do không nhập kho được (khi `can_receive = false`), ví dụ:
  - `"Đơn đang ở trạng thái Đã nhận hàng, không cần nhập lại."`
  - `"Đơn đã bị khóa, không thể nhập kho."`
  - `"Đơn đã hủy."`

Response `200` — không tìm thấy (KHÔNG dùng 404 để app xử lý mượt khi quét sai):

```json
{
  "success": true,
  "message": "Không tìm thấy đơn khớp mã.",
  "data": { "found": false, "matched_by": null, "order": null, "can_receive": false, "reason": "Không tìm thấy đơn khớp mã." }
}
```

> Quyết định contract: scan trả `200 { found: false }` thay vì `404`, vì "quét mã lạ" là luồng bình thường ngoài kho, không phải lỗi HTTP. App phân biệt bằng `data.found`.

### 4.2. `POST /api/mobile/ops/orders/{order}/receive`

Xác nhận nhập kho một đơn.

**Auth + role OPS-capable.** Rate limit `30/phút/user`.

Request: rỗng (hoặc `{ "code": "..." }` để backend đối chiếu lại mã đã quét — optional).

Backend xử lý (tái dùng logic web):
1. Kiểm tra role.
2. Kiểm tra `order.lock_order = false`, nếu khóa → `409`.
3. Kiểm tra `order.bill_status === DA_XAC_NHAN`, nếu không → `409` kèm lý do.
4. Trong transaction:
   - `update(['bill_status' => DA_NHAN_HANG])`.
   - Set `ngaynhanhang = now()` nếu còn null.
   - `RecordTrackingHistoryAction::execute($order, OrderStatusEnum::DA_NHAN_HANG, now())`.

Response `200`:

```json
{
  "success": true,
  "message": "Đã nhập kho đơn HT250609001.",
  "data": {
    "order": {
      "id": 555,
      "id_bill": "HT250609001",
      "status": { "value": "da_nhan_hang", "label": "Đã nhận hàng", "color": "bg-cyan-100 text-cyan-700" },
      "received_at": "2026-06-10T08:35:00+07:00"
    }
  }
}
```

Response `409` (sai trạng thái / bị khóa):

```json
{
  "success": false,
  "message": "Đơn đang ở trạng thái Đã nhận hàng, không thể nhập kho lại."
}
```

Response `403`: role không đủ. `404`: không tồn tại order.

### 4.3. `POST /api/mobile/ops/orders/bulk-receive`

Nhập kho hàng loạt (batch scan).

**Auth + role OPS-capable.** Rate limit `30/phút/user`.

Request:

```json
{ "codes": ["HT250609001", "HT250609002", "TRK999"] }
```

hoặc:

```json
{ "order_ids": [555, 556, 557] }
```

| Field | Bắt buộc | Ghi chú |
| --- | --- | --- |
| `codes` | một trong hai | Mảng mã, match như §4.1 |
| `order_ids` | một trong hai | Mảng id đơn |

Giới hạn đề xuất: tối đa 50 phần tử/request.

Response `200` (luôn 200, kết quả từng đơn nằm trong `data`):

```json
{
  "success": true,
  "message": "Đã xử lý 3 đơn: 2 thành công, 1 lỗi.",
  "data": {
    "succeeded": [
      { "code": "HT250609001", "order_id": 555, "status": "da_nhan_hang" },
      { "code": "HT250609002", "order_id": 556, "status": "da_nhan_hang" }
    ],
    "failed": [
      { "code": "TRK999", "order_id": null, "reason": "Không tìm thấy đơn khớp mã." }
    ]
  }
}
```

Mỗi đơn xử lý độc lập (một đơn lỗi không rollback đơn khác). Dùng chung logic §4.2 cho từng đơn.

### 4.4. `GET /api/mobile/ops/recent-scans` — Optional

Lịch sử nhập kho gần đây của user (phục vụ màn RecentScans).

**Auth + role OPS-capable.**

> **Quyết định MVP:** roadmap (mục 9) nói "lưu local lịch sử scan trong phiên hiện tại". Vì vậy MVP **ưu tiên client-side** (app tự lưu trong RAM/`shared_preferences`), endpoint này là **optional**. Nếu cần server-side, query `OrderHistory` theo `id_user = auth()->id()`, `action = 'tracking_status_auto'`, `trangthai = 'Đã nhận hàng'`, sắp xếp mới nhất, giới hạn 50.

Query params: `limit` (mặc định 30).

Response `200`:

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "items": [
      {
        "order_id": 555,
        "id_bill": "HT250609001",
        "received_at": "2026-06-10T08:35:00+07:00",
        "status": { "value": "da_nhan_hang", "label": "Đã nhận hàng" }
      }
    ]
  }
}
```

---

## 5. Bảo Mật & Phân Quyền

| Quy tắc | Thực thi |
| --- | --- |
| Token thay vì session | Sanctum `auth:sanctum` middleware cho group `/api/mobile/*` (trừ login) |
| Shipper chỉ thấy pickup của mình | Mọi query pickup ép `where('id_shipper', auth()->id())` |
| OPS chỉ đúng role | Middleware `role:ops|admin|manager|cs` |
| FSM không lệch web | Bắt buộc gọi `TransitionPickupStatusAction` / kiểm `OrderStatusEnum`, không copy logic |
| Không lộ tài chính | Response pickup/scan KHÔNG trả `total_cuoc`, `payment_*`, `congno*` |
| Ghi vết | Log đổi trạng thái pickup; `RecordTrackingHistoryAction` khi nhập kho |
| Revoke token | `POST /logout` xóa current token |
| Rate limit | login, scan, receive (§1.6) |

---

## 6. Enum & FSM Tham Chiếu

### 6.1. Pickup status (`PickupStatusEnum`)

| Value | Label | Tab app |
| --- | --- | --- |
| `moi_tao_pickup` | Mới tạo | `new` |
| `da_xac_nhan` | Đã xác nhận | `accepted` |
| `pickup_dang_lay` | Đang lấy hàng | `picking` |
| `pickup_da_lay` | Đã lấy hàng | `done` |
| `da_huy` | Đã hủy | (ẩn mặc định) |

FSM (`allowedTransitions()`):

```text
moi_tao_pickup  → da_xac_nhan, da_huy
da_xac_nhan     → pickup_dang_lay, da_huy
pickup_dang_lay → pickup_da_lay, da_huy
pickup_da_lay   → (kết thúc)
da_huy          → (kết thúc)
```

> App tuyệt đối chỉ render nút theo `allowed_transitions` mà API trả về.

### 6.2. Order status liên quan OPS (`OrderStatusEnum`)

| Value | Label |
| --- | --- |
| `da_xac_nhan` | Đã xác nhận |
| `da_nhan_hang` | Đã nhận hàng |

OPS chỉ thực hiện chuyển `da_xac_nhan → da_nhan_hang`. Các trạng thái khác → `can_receive = false` kèm `reason`.

---

## 7. GPS, Ảnh — Ghi Chú Triển Khai

- **GPS (lat/lng) là optional ở MVP.** API `status` chấp nhận `lat`/`lng` nhưng không bắt buộc.
- ⚠️ Bảng `pickup` **chưa có cột tọa độ phẳng**; hiện `pickup_lat`/`pickup_lng` nằm trong JSON `info_khachhang` (do người tạo phiếu nhập). Nếu MVP muốn lưu GPS check-in của shipper, cần chốt: ghi vào `info_pickup` (JSON) hay thêm cột/bảng mới. **Đề xuất:** Phase 4 mới làm, MVP bỏ qua.
- **Upload ảnh:** Phase 4. App nén ảnh trước (`flutter_image_compress`), backend giới hạn dung lượng.

---

## 8. Checklist Triển Khai Backend (Phase 1)

- [ ] Cấu hình Sanctum cho mobile (`config/sanctum.php`, token abilities nếu cần).
- [ ] Tạo route group `routes/api.php` prefix `mobile`, middleware `auth:sanctum` (trừ login).
- [ ] `MobileAuthController`: `login`, `me`, `logout` (kèm check role + status).
- [ ] `MobileShipperPickupController`: `index`, `show`, `updateStatus` (ép `id_shipper`).
- [ ] `MobileOpsScanController`: `scan`, `receive`, `bulkReceive`, (`recentScans` optional).
- [ ] API Resource/transformer chuẩn hóa envelope §1.3 (đề xuất `JsonResource` + macro `success/error`).
- [ ] Đăng ký rate limiter §1.6.
- [ ] Tái dùng `TransitionPickupStatusAction`, `RecordTrackingHistoryAction`, enum — KHÔNG copy logic.
- [ ] Ẩn field tài chính khỏi response mobile.
- [ ] Test feature: login (đúng/sai/khóa/sai role), pickup visibility (shipper khác không thấy), pickup FSM (hợp lệ + sai FSM 409), OPS scan (4 kiểu match + không thấy), OPS receive (đúng/sai trạng thái/bị khóa/sai role), bulk-receive (mix thành công/thất bại).
- [ ] HTTP collection (`.http`/Postman) để Flutter team test.

---

## 9. Tổng Hợp Endpoint

| Method | Path | Role | Thay đổi DB |
| --- | --- | --- | --- |
| POST | `/api/mobile/login` | public | — |
| GET | `/api/mobile/me` | any auth | — |
| POST | `/api/mobile/logout` | any auth | xóa token |
| GET | `/api/mobile/shipper/pickups` | shipper | — |
| GET | `/api/mobile/shipper/pickups/{pickup}` | shipper | — |
| POST | `/api/mobile/shipper/pickups/{pickup}/status` | shipper | pickup status (+log) |
| POST | `/api/mobile/ops/scan` | ops/admin/manager/cs | — |
| POST | `/api/mobile/ops/orders/{order}/receive` | ops/admin/manager/cs | order status + history |
| POST | `/api/mobile/ops/orders/bulk-receive` | ops/admin/manager/cs | order status + history |
| GET | `/api/mobile/ops/recent-scans` | ops/admin/manager/cs | — (optional) |
| POST | `/api/mobile/shipper/pickups/{pickup}/photos` | shipper | Phase 4 |
| POST | `/api/mobile/shipper/pickups/{pickup}/location` | shipper | Phase 4 |
