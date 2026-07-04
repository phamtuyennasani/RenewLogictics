# KẾ HOẠCH THIẾT KẾ ZALO MINI APP — HỆ THỐNG LOGISTICS

> Ngày lập: 04/07/2026
> Phạm vi: Nâng cấp `zaloMiniApp/` từ 2 luồng public (tra cước + gửi yêu cầu lấy hàng) thành ứng dụng đầy đủ: **Tra cước, Tạo đơn, Xem tracking, Xem chi tiết đơn, Cập nhật giá** — với phân quyền đúng theo 8 role hiện có của hệ thống.
> Căn cứ: [Tài liệu Zalo Mini App — Getting Started](https://miniapp.zaloplatforms.com/documents/intro/getting-started/), template UI [Zalo-MiniApp/zaui-uni](https://github.com/Zalo-MiniApp/zaui-uni), `PHAN_QUYEN_CHI_TIET.md`, `app/Providers/AuthServiceProvider.php`, `docs/MOBILE_API_CONTRACT.md`.
> Nguyên tắc: **API tái dùng business action hiện có** (`CreateOrderAction`, `ResolveServicePriceAction`, `ThirdPartyOrderShippingHistory`…), Mini App KHÔNG tự quyết nghiệp vụ. FE ẩn/hiện theo quyền, BE luôn authorize lại.

---

## 1. Hiện trạng (đã có, tái dùng được)

### 1.1. Mini App (`zaloMiniApp/`)

| Thành phần | Trạng thái |
|---|---|
| React 18 + TypeScript + Vite + `zmp-ui` 1.11 + `zmp-cli` | ✅ Chạy được (`npm run dev`) |
| 1 màn duy nhất `App.tsx`: 2 tab Tra cước / Gửi yêu cầu | ✅ Hoạt động, có mock fallback |
| `services/api.ts` — client fetch + envelope `success/message/data` | ✅ |
| `zmp-sdk` đã khai báo trong package.json | ⚠️ Chưa import/sử dụng (chưa lấy `zalo_user_id`) |
| `app-config.json` chỉ có `pages/index` | ⚠️ Chưa có router đa trang |

### 1.2. Backend Laravel

| Thành phần | Tái dùng cho |
|---|---|
| `GET/POST /api/zalo-mini-app/{bootstrap,countries,quote,shipping-requests}` (public, throttle 60/1m) | Tra cước, yêu cầu lấy hàng (guest) |
| `ResolveServicePriceAction` (DIM, quy cách CO_DINH/DON_GIA) | Tra cước + tính giá khi tạo đơn |
| `CreateOrderAction` + `OrderFormData` + `GenerateOrderCodeAction` | Tạo đơn |
| `ThirdPartyOrderTrackingController` + `ThirdPartyOrderShippingHistory` | Tracking (shape response có sẵn) |
| Trang web public `/theo-doi/{idbill}` + `/tra-cuoc` | Đối chiếu hành vi |
| Mobile API Sanctum (`/api/mobile/*`) + trait `ApiResponse` + `throttle:mobile-login` | Mẫu auth token + envelope |
| Spatie roles + Gates (`orders.create`, `service-prices.manage`…) | Toàn bộ phân quyền |
| `service_price_lists` / `_countries` / `_details` + UI web import Excel | Cập nhật giá |
| `zalo_shipping_requests` (đã migrate xong 04/07, có cột `zalo_user_id`) | Lead từ guest |

⚠️ **Chặn nghiệp vụ hiện tại**: bảng `service_price_lists` đang **0 dòng** → phải nhập bảng giá trước khi demo tra cước/tạo đơn thật.

---

## 2. Kiến trúc tổng thể

```
┌──────────────────────── Zalo App (WebView) ────────────────────────┐
│  Mini App (React + zmp-ui, cấu trúc theo template zaui-uni)        │
│  ┌──────────┬──────────┬──────────┬──────────┐                     │
│  │ Trang chủ│ Tra cước │ Đơn hàng │ Cá nhân  │  ← BottomNavigation │
│  └──────────┴──────────┴──────────┴──────────┘   (tab theo quyền)  │
│  zmp-sdk: getUserID / getAccessToken / getPhoneNumber(*)           │
└───────────────┬────────────────────────────────────────────────────┘
                │ HTTPS (domain public, KHÔNG dùng .test)
┌───────────────▼────────────────────────────────────────────────────┐
│  Laravel /api/zalo-mini-app/*                                      │
│  ├─ Public:  bootstrap, countries, quote, shipping-requests,       │
│  │           tracking/{id_bill}                                    │
│  └─ Sanctum: auth/*, me, orders*, order-form/*, price-lists*       │
│     └─ Gate/Role check → tái dùng Actions + Models hiện có         │
└─────────────────────────────────────────────────────────────────────┘
```

- **Guest** (chưa đăng nhập): Tra cước, Gửi yêu cầu lấy hàng, Tracking theo mã bill.
- **Đã đăng nhập**: thêm tính năng theo role (mục 4).
- Envelope response giữ nguyên chuẩn `{success, message, data, errors?}` (trait `ApiResponse`).

---

## 3. Cấu trúc Frontend (theo template zaui-uni)

Theo pattern bộ template chính thức Zalo-MiniApp (`zaui-uni`): chia trang theo tính năng, router của `zmp-ui` (`ZMPRouter` + `AnimationRoutes`), state tập trung, service tách mock/API, dùng ZaUI components thay control HTML thuần.

```
zaloMiniApp/src/
├── app.tsx                    # ZMPApp + ZMPRouter + AnimationRoutes + BottomNav
├── main.tsx
├── app-config.json            # khai báo đủ pages
├── components/
│   ├── layout/bottom-nav.tsx  # tab ẩn/hiện theo abilities
│   ├── guards/require-auth.tsx    # chưa login → redirect /login
│   ├── guards/require-ability.tsx # thiếu quyền → màn 403 thân thiện
│   ├── order/order-card.tsx, status-badge.tsx, tracking-timeline.tsx
│   ├── price/price-row-editor.tsx
│   └── common/{notice,metric,empty-state,skeleton}.tsx  # tái dùng từ App.tsx cũ
├── pages/
│   ├── home/index.tsx         # hero + quick actions theo quyền + tra tracking nhanh
│   ├── quote/index.tsx        # Tra cước (chuyển từ tab hiện tại)
│   ├── request/index.tsx      # Gửi yêu cầu lấy hàng (guest — giữ nguyên luồng)
│   ├── tracking/index.tsx     # nhập mã bill → timeline
│   ├── tracking/result.tsx
│   ├── auth/login.tsx         # username/password (+ ghi nhớ, liên kết Zalo)
│   ├── profile/index.tsx      # thông tin user, menu quản trị theo role, logout
│   ├── orders/index.tsx       # danh sách đơn (filter trạng thái, search, infinite)
│   ├── orders/detail.tsx      # chi tiết đơn + tab tracking
│   ├── orders/create/         # wizard 3 bước (mục 5.2)
│   │   ├── step-service.tsx   # dịch vụ + quốc gia + kiện hàng → giá tạm tính
│   │   ├── step-parties.tsx   # người gửi / người nhận (sổ địa chỉ)
│   │   └── step-confirm.tsx   # khai báo hàng + xác nhận
│   └── prices/
│       ├── index.tsx          # danh sách bảng giá theo dịch vụ
│       └── detail.tsx         # sửa dòng giá inline
├── services/
│   ├── http.ts                # fetch client: base URL, Bearer token, envelope, 401 → logout
│   ├── auth.ts, quote.ts, tracking.ts, orders.ts, prices.ts
│   ├── zalo.ts                # wrapper zmp-sdk (getUserID, getAccessToken)
│   └── mock/                  # mock theo module (dev không cần backend)
├── state/
│   └── store.ts               # auth {token, user, abilities}, bootstrap cache
│                              # (zustand — nhẹ; nếu bám sát zaui-uni thì recoil)
└── types.ts
```

**Bottom navigation theo quyền** (FE đọc `abilities` từ `GET /me`):

| Tab | Guest | shipper | ops | ketoan | manager | cs / sale / ctv / admin |
|---|---|---|---|---|---|---|
| Trang chủ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Tra cước | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Đơn hàng | ❌ | ❌ | ✅ (xem) | ✅ (xem) | ✅ (xem) | ✅ |
| Cá nhân | ✅ (→ nút Đăng nhập) | ✅ | ✅ | ✅ (+ menu Bảng giá) | ✅ (+ menu Bảng giá) | ✅ (admin: + Bảng giá) |

Token lưu bằng Storage API của zmp-sdk (`setStorage`/`getStorage`) — không dựa vào localStorage của WebView.

---

## 4. Phân quyền — ánh xạ 8 role hiện có vào 5 chức năng

Nguồn sự thật: Gates trong `AuthServiceProvider` + `PHAN_QUYEN_CHI_TIET.md`. FE chỉ ẩn/hiện; **BE authorize lại từng endpoint**.

### 4.1. Ma trận chức năng × role

| Chức năng | Guest | admin | manager | ketoan | cs | sale | ops | ctv | shipper |
|---|---|---|---|---|---|---|---|---|---|
| **Tra cước** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Gửi yêu cầu lấy hàng** (lead) | ✅ | — | — | — | — | — | — | — | — |
| **Tạo đơn** (`orders.create`) | ❌ | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ✅ | ❌ |
| **Xem tracking theo mã bill** (public) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Danh sách + chi tiết đơn** (`orders.index`) | ❌ | ✅ tất cả | ✅ tất cả | ✅ tất cả | ✅ tất cả | ✅ *scope* | ✅ tất cả (read) | ✅ *scope* | ❌ |
| **Cập nhật giá** (`service-prices.manage`) | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Xóa bảng giá** (`service-prices.delete`) | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

*Scope dữ liệu* (áp trong query, giống web):
- **sale**: chỉ đơn `id_sale = mình` hoặc `id_create = mình`.
- **ctv**: chỉ đơn `id_customer = mình` hoặc `id_create = mình` — "KHÔNG xem đơn của người khác".
- **ops/ketoan/manager**: xem tất cả, **read-only** trên Mini App (không sửa/xóa đơn — thao tác nâng cao vẫn ở web).
- **shipper**: không có `orders.index` → không thấy tab Đơn hàng (pickup cho shipper đã có app Flutter riêng; Mini App chỉ để tra cước/tracking).

Ràng buộc thêm khi **tạo đơn** trên Mini App (đồng bộ web):
- `cs` tạo đơn → `id_cs` tự gán = user hiện tại (logic sẵn trong `CreateOrderAction`).
- `ctv` tạo đơn → `id_customer` ép = chính mình, không cho chọn khách khác.
- `sale` tạo đơn → `id_sale` ép = chính mình.
- Mini App **không có** sửa đơn / xóa đơn / gán sale (các quyền này phức tạp theo trạng thái — để web).

### 4.2. Đăng nhập & vai trò

- **Tất cả 8 role đều được đăng nhập Mini App** (khác app Flutter chỉ cho 5 role) — vì Mini App có tính năng cho cả sale/ctv/ketoan.
- Login: `username` + `password` (bảng `user`, pattern `MobileAuthController`), token Sanctum tên device `zalo-mini-app`.
- `GET /me` trả `roles` + **`abilities` tính sẵn từ Gates**: `{orders_view, orders_create, prices_manage, prices_delete}` → FE render menu, không hardcode role ở FE.

### 4.3. Liên kết tài khoản Zalo (auto-login)

- Thêm cột `zalo_user_id` (varchar 100, nullable, unique) vào bảng `user` (lưu ý bảng legacy — kiểu cột theo chuẩn bảng `user` hiện có, xem mục 7).
- Sau lần login username/password đầu tiên trong Mini App: FE lấy `accessToken` qua zmp-sdk → gửi `POST /auth/zalo-link` → **server gọi Zalo Open API (`graph.zalo.me/v2.0/me`) bằng access token đó để verify** rồi mới lưu `zalo_user_id`. KHÔNG BAO GIỜ tin `zalo_user_id` do client tự gửi.
- Lần mở app sau: `POST /auth/zalo` (gửi accessToken mới) → server verify → tìm user theo `zalo_user_id` → cấp token Sanctum → vào thẳng app.
- Guest gửi yêu cầu lấy hàng: đính kèm `zalo_user_id` (đã verify server-side nếu có) vào `zalo_shipping_requests` để CSKH đối chiếu.

---

## 5. Thiết kế màn hình

### 5.1. Trang chủ (`pages/home`)
- Hero + quick actions dạng grid (pattern zaui-uni): **Tra cước / Tạo đơn / Tracking / Yêu cầu lấy hàng / Bảng giá** — mỗi ô chỉ hiện khi có quyền.
- Ô nhập nhanh mã vận đơn → đi thẳng trang tracking.
- Đã đăng nhập: card "Đơn gần đây" (3 đơn mới nhất trong scope).

### 5.2. Tra cước (`pages/quote`) — nâng cấp từ màn hiện tại
- Giữ logic hiện có (service → lọc quốc gia theo bảng giá → cân/kích thước → `POST /quote`).
- Sửa bug nhỏ: bỏ `country_id` khỏi deps của effect fetchCountries (đang refetch thừa).
- Thêm: sau khi có kết quả, nút **"Tạo đơn với thông tin này"** (nếu có `orders_create`) hoặc **"Gửi yêu cầu lấy hàng"** (guest) — mang theo state sang màn tương ứng.

### 5.3. Tracking (`pages/tracking`)
- Input mã bill (`id_bill`) + nút quét từ lịch sử tra gần đây (Storage).
- Kết quả: trạng thái hiện tại (badge màu theo `OrderStatusEnum`), timeline `shipping_history` (dựng lại UI từ shape của `ThirdPartyOrderTrackingController`: thời gian, địa điểm, trạng thái, ghi chú), thông tin người nhận rút gọn, cân tính cước.
- Public + throttle riêng (mục 6) — không lộ giá/công nợ cho guest: response tracking public **không chứa** `payment_*`.

### 5.4. Danh sách đơn (`pages/orders`) — auth
- Filter chip theo trạng thái (Mới tạo / Đã xác nhận / Đã nhận hàng / Duyệt xuất / Đang phát / Đã giao / Hủy…), search theo mã bill / tên người nhận.
- Infinite scroll (paginate 20). Card: mã bill, badge trạng thái, người nhận + quốc gia, cân tính cước, cước bán (**ẩn cước vốn/lợi nhuận với ops** — đồng bộ hạn chế dashboard của ops).
- Nút ➕ tạo đơn (chỉ khi `orders_create`).

### 5.5. Chi tiết đơn (`pages/orders/detail`) — auth + scope
- Tabs: **Thông tin** (dịch vụ, người gửi/nhận, kiện hàng, khai giá) / **Tracking** (timeline như 5.3) / **Thanh toán** (cước bán, phụ phí — ẩn với ops).
- Read-only. Link "Xem đầy đủ trên web" cho role quản trị.

### 5.6. Tạo đơn (`pages/orders/create`) — wizard 3 bước
Đơn giản hóa từ form web (web Livewire rất nặng), nhưng đi qua đúng `CreateOrderAction`:
1. **Dịch vụ & kiện hàng**: dịch vụ chính, quốc gia nhận, danh sách kiện (số kiện, cân, D×R×C) → hiện giá tạm tính (gọi `/quote` chung engine).
2. **Người gửi & nhận**: chọn từ sổ địa chỉ (`sender.index`/`receiver.index` — role nào cũng có quyền xem của mình) hoặc nhập mới + cờ "lưu địa chỉ".
3. **Khai báo hàng & xác nhận**: dòng hàng hóa (tên, SL, đơn giá), ghi chú → xác nhận → `POST /orders` → màn success hiện mã bill + nút xem tracking.
- Payload map vào `OrderFormData`; server ép `id_sale`/`id_customer`/`id_cs` theo role (mục 4.1); đơn tạo ra ở trạng thái `MOI_TAO` — nhân viên xác nhận trên web.
- Nếu `CreateOrderResult.warnings` không rỗng → hiện cảnh báo "một số thông tin phụ chưa lưu được, nhân viên sẽ bổ sung".

### 5.7. Cập nhật giá (`pages/prices`) — role admin/manager/ketoan
- **Danh sách**: nhóm theo dịch vụ; card = tên bảng giá, dịch vụ, số quốc gia áp dụng, số dòng giá, cập nhật lần cuối.
- **Chi tiết/Sửa**: 
  - Sửa tên, gắn/bỏ quốc gia (multi-select).
  - Bảng dòng giá theo `quycach` (CO_DINH / DON_GIA): sửa inline `weight_from, weight_to, sale_price, cost_price, base_price`; thêm/xóa dòng; validate range không chồng lấn (unique `[list, quycach, from, to]` như DB).
  - Lưu = **bulk upsert nguyên bảng dòng giá** trong transaction (đơn giản, tránh conflict từng dòng).
- **Import Excel: KHÔNG đưa vào Mini App** (giữ trên web — file upload trong WebView phiền phức, đã có UI web + template `/mau-excel`).
- Xóa bảng giá: chỉ admin, confirm 2 bước.
- ⚠️ Đây là chức năng nhạy cảm (lộ cước vốn) → bắt buộc auth + role, không cache response.

### 5.8. Đăng nhập / Cá nhân
- Login: username + password; checkbox "Liên kết tài khoản Zalo này" (mục 4.3).
- Cá nhân: avatar/tên/role label (`RoleEnum::label`), menu: Bảng giá (role), Yêu cầu lấy hàng đã gửi (theo `zalo_user_id`), Liên kết Zalo, Đăng xuất.

---

## 6. API contract mở rộng (`/api/zalo-mini-app/*`)

Envelope `ApiResponse` thống nhất. Nhóm route mới:

```php
// PUBLIC (giữ throttle 60,1 riêng cho nhóm không auth)
GET  /bootstrap                       // ✅ có sẵn
GET  /countries?service_id=           // ✅ có sẵn
POST /quote                           // ✅ có sẵn
POST /shipping-requests               // ✅ có sẵn
GET  /tracking/{id_bill}              // MỚI — tái dùng ThirdPartyOrderShippingHistory,
                                      //   response = shape third-party TRỪ dữ liệu giá
                                      //   throttle:20,1 chống dò đơn

// AUTH
POST /auth/login                      // MỚI — throttle:mobile-login, trả token + me
POST /auth/zalo                       // MỚI — auto-login bằng Zalo accessToken (verify server-side)
POST /auth/zalo-link                  // MỚI — auth:sanctum, liên kết zalo_user_id
POST /auth/logout                     // MỚI — auth:sanctum
GET  /me                              // MỚI — user + roles + abilities (tính từ Gates)

// ORDERS — auth:sanctum + can:orders.index (+ scope trong query)
GET  /orders?status=&search=&page=    // MỚI
GET  /orders/{uuid}                   // MỚI — 404 nếu ngoài scope (không lộ tồn tại)
POST /orders                          // MỚI — can:orders.create → CreateOrderAction
GET  /order-form/bootstrap            // MỚI — catalog form tạo đơn: dịch vụ chi tiết,
                                      //   loại bưu gửi, sổ địa chỉ gửi/nhận của user, dim

// PRICES — auth:sanctum + can:service-prices.manage
GET    /price-lists?service_id=       // MỚI
GET    /price-lists/{id}              // MỚI — kèm countries + details
PUT    /price-lists/{id}              // MỚI — name + country_ids
PUT    /price-lists/{id}/details      // MỚI — bulk upsert dòng giá (transaction, validate range)
DELETE /price-lists/{id}              // MỚI — can:service-prices.delete (admin)
```

Controller mới đặt tại `app/Http/Controllers/Api/ZaloMiniApp/` (Auth, Order, PriceList, Tracking) — tách khỏi `ZaloMiniAppController` hiện tại (giữ nguyên cho public). Exception renderer trong `bootstrap/app.php` bổ sung nhánh `api/zalo-mini-app/*` (hiện chỉ có `api/mobile/*`) để 401/403/422 trả đúng envelope.

---

## 7. Thay đổi CSDL

| # | Migration | Nội dung | Lưu ý legacy |
|---|---|---|---|
| 1 | `add_zalo_user_id_to_user_table` | `zalo_user_id` varchar(100) nullable + unique index; `zalo_linked_at` timestamp nullable | Bảng `user` là bảng legacy (prefix `table_`) — chỉ ADD COLUMN, không đụng khóa |
| 2 | (không cần bảng mới) | Orders/prices dùng bảng sẵn có | ⚠️ Nhớ quy tắc dự án: FK tới `news` dùng `unsignedInteger` + `->foreign()`, FK tới `pickup` dùng `integer` — KHÔNG dùng `foreignId()` bừa (đã dính 2 lần) |

---

## 8. Lộ trình triển khai

### Phase 0 — Nền tảng (BE auth + FE restructure)
- [ ] Migration `zalo_user_id`; `ZaloMiniAppAuthController` (login/logout/me + abilities); exception renderer cho `api/zalo-mini-app/*`.
- [ ] FE: chuyển sang router đa trang theo cấu trúc mục 3 (giữ nguyên 2 luồng cũ chạy được), `http.ts` + token storage + auth state, BottomNav theo abilities, guards.
- [ ] Test Pest: login đúng/sai, abilities đúng theo từng role (8 role).
- **DoD**: login được bằng tài khoản mọi role, tab hiện đúng ma trận 4.1, 2 luồng cũ không hỏng.

### Phase 1 — Tracking + hoàn thiện public
- [ ] `GET /tracking/{id_bill}` (lọc bỏ dữ liệu giá) + màn tracking timeline; quick-action trang chủ.
- [ ] Sửa bug refetch countries; nút điều hướng chéo quote → tạo đơn/yêu cầu.
- [ ] Test: tracking đơn tồn tại/không tồn tại, throttle.
- **DoD**: guest tra được hành trình đơn bằng mã bill, UI timeline giống dữ liệu web `/theo-doi/{idbill}`.

### Phase 2 — Đơn hàng (list / detail / create)
- [ ] BE: orders index (scope theo role: sale/ctv như mục 4.1) + show + store (map `OrderFormData` → `CreateOrderAction`) + `order-form/bootstrap`.
- [ ] FE: 3 màn + wizard 3 bước; ẩn thông tin giá vốn với ops.
- [ ] Test Pest ma trận: từng role × (xem tất cả / chỉ của mình / bị chặn), ctv không xem được đơn người khác (404), tạo đơn ép id theo role, manager/ops/ketoan bị chặn POST /orders (403).
- **DoD**: ctv/sale/cs/admin tạo đơn từ Mini App ra mã bill thật, tracking được ngay; scope không rò rỉ.

### Phase 3 — Cập nhật giá
- [ ] BE: 5 endpoint price-lists (transaction, validate range chồng lấn, events/log như web nếu có).
- [ ] FE: 2 màn prices; confirm xóa 2 bước (admin).
- [ ] Test: manager/ketoan sửa được, cs/sale/ops/ctv 403, chỉ admin xóa; validate range trùng bị 422.
- **DoD**: sửa dòng giá trên Mini App → tra cước phản ánh giá mới ngay.

### Phase 4 — Liên kết Zalo + phát hành
- [ ] `POST /auth/zalo`, `/auth/zalo-link` (verify qua Zalo Open API — cần App Secret trong `.env`: `ZALO_APP_ID`, `ZALO_APP_SECRET`).
- [ ] Gắn `zalo_user_id` vào shipping-requests của guest; màn "Yêu cầu đã gửi" trong Cá nhân.
- [ ] Deploy checklist (mục 9).
- **DoD**: mở app lần 2 tự đăng nhập; app qua review Zalo, chạy môi trường Production.

---

## 9. Checklist phát hành Zalo Platform

1. Tạo Mini App trên [Zalo Developers](https://developers.zalo.me), lấy **Mini App ID** điền vào `app-config.json`; liên kết **Official Account** của công ty.
2. API base **bắt buộc HTTPS domain public** (`VITE_API_BASE_URL`) — WebView `h5.zdn.vn` không gọi được `.test`/HTTP; khai domain trong phần cấu hình domain được phép của Mini App.
3. CORS: config hiện tại đã mở `api/*` với origin `*` — ✅ đủ; nếu siết lại thì whitelist `https://h5.zdn.vn`.
4. Quyền SDK: `getUserID`/`getAccessToken` dùng cho auto-login; **`getPhoneNumber` cần đăng ký quyền + được duyệt** — chỉ dùng nếu muốn prefill SĐT ở form yêu cầu lấy hàng (để Phase 4+, không chặn release).
5. `app-config.json`: tắt `debug`, khai đủ `pages`, title/màu brand.
6. Quy trình: `npm run build` → `npx zmp login` → `npx zmp deploy` → test bản **Testing** qua QR trong Zalo thật (mọi role) → submit **review** → Production.
7. Rate limit: tách throttle nhóm auth (60/1m hiện tại là cho public; nhóm auth dùng chuẩn `throttle:60,1` theo user id).
8. Bảo mật: không log token; response public không chứa giá vốn/lợi nhuận/công nợ; `quote` public đã có feature flag + throttle trên web — Mini App giữ throttle tương đương chống dò bảng giá.

---

## 10. Rủi ro & quyết định mở

| # | Rủi ro / câu hỏi | Đề xuất |
|---|---|---|
| 1 | Bảng giá 0 dòng → demo không chạy | Nhập bảng giá qua web (có import Excel) trước Phase 1 |
| 2 | Form tạo đơn web rất phức tạp (phụ phí hải quan, nhiều dịch vụ con) | Mini App làm bản rút gọn (mục 5.6); đơn phức tạp vẫn tạo trên web; wizard hiện cảnh báo warnings |
| 3 | ops có được bấm "Đã nhận hàng" trên Mini App không? | **Không** trong phạm vi này (đã có app Flutter cho ops/shipper); tránh trùng chức năng — mở rộng sau nếu cần |
| 4 | ketoan/manager sửa giá trên màn hình nhỏ dễ nhầm | Confirm diff trước khi lưu ("3 dòng thay đổi, 1 dòng thêm"); log activity như web |
| 5 | Tài khoản `member` (khách hàng bảng `table_member`) có được login không? | Phạm vi này chỉ bảng `user` (8 role Spatie). Khách lẻ = guest + yêu cầu lấy hàng. Mở rộng cổng khách hàng member = dự án riêng |
| 6 | Chưa fetch được live 2 URL tài liệu khi lập plan | Cấu trúc template/API SDK viết theo bộ template chính thức zaui; khi bắt đầu Phase 0 cần đối chiếu lại version mới nhất của `zmp-cli`/`zmp-sdk` |

---

**Người lập**: Claude Code — 04/07/2026
**File liên quan**: `PHAN_QUYEN_CHI_TIET.md`, `docs/MOBILE_API_CONTRACT.md`, `docs/SERVICE_PRICE_CALCULATION.md`, `zaloMiniApp/README.md`
