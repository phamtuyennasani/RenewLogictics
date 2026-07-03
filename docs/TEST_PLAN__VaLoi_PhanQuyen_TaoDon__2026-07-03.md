# TEST PLAN — Kiểm chứng đợt vá lỗi Phân quyền & Tạo đơn

- **Ngày thực hiện:** 2026-07-03
- **Phạm vi:**
  - **Đợt 1:** vá lỗ hổng phân quyền route, defense-in-depth công nợ/global search, transaction luồng tạo đơn, sửa test suite, vệ sinh log.
  - **Đợt 2 (cùng ngày):** hợp nhất toàn bộ tính toán payment (cước/VAT/PPXD/phụ phí/lợi nhuận) về một nguồn `OrderPaymentCalculator` — dùng chung cho màn tạo đơn và màn cập nhật giá. Xem mục 4B.
  - **Đợt 3 (cùng ngày):** bật queue thật (`QUEUE_CONNECTION=database`) — push FCM không còn chạy sync trong HTTP request. **Yêu cầu chạy queue worker trên server.** Xem mục 4C.
  - **Đợt 4 (cùng ngày):** tính năng MỚI — xuất PDF vận đơn (bill) + tem kiện (label) server-side. **Không đụng luồng in trình duyệt sẵn có** (Print Bill / Print Label vẫn nguyên trạng). Xem mục 4D.
  - **Đợt 5 (cùng ngày):** sửa trang tra cứu đơn công khai `/theo-doi/{idbill}` — trước đó lỗi 500 (route trỏ view không tồn tại), giờ là trang tracking hoàn chỉnh cho khách cuối. Xem mục 4E.
  - **Đợt 6 (cùng ngày):** in hàng loạt PDF tem/bill từ danh sách đơn — chọn nhiều đơn bằng checkbox → 1 file PDF gộp. Xem mục 4F.
  - **Đợt 7 (cùng ngày):** trang tra cước công khai `/tra-cuoc` (quote calculator, không cần đăng nhập) — có feature flag tắt/bật. Xem mục 4G.
- **Trạng thái sau vá:** `php artisan test` → **110/110 passed** (trước vá: 57/70; đợt 1: 78; đợt 2: 90; đợt 4: 94; đợt 5: 100; đợt 6: 104).
- **Tài liệu liên quan:** `docs/PHAN_TICH_HE_THONG_MOI_RENEWLOGICTICS.md` (mục cập nhật 2026-07-03), `PHAN_QUYEN_CHI_TIET.md` (ma trận quyền chuẩn), `docs/payment-calculation-guide.md` + `docs/SERVICE_PRICE_CALCULATION.md` (công thức tính tiền chuẩn), `docs/QUEUE_OPERATIONS.md` (vận hành queue worker).

---

## 0. Danh sách file thay đổi

### Đợt 1 — Phân quyền & luồng tạo đơn

| Loại | File | Nội dung |
|---|---|---|
| Sửa | `routes/web.php` | Sửa 17 group middleware `can:` bị vô hiệu |
| Sửa | `app/Http/Controllers/CongNo/CongNoDaiLyDataTableController.php` | Thêm check quyền `congno_daily.view` vào `__invoke`, `export` |
| Sửa | `app/Http/Controllers/CongNo/CongNoDataTableController.php` | Thêm check quyền `congno.index` vào `__invoke`, `export`, `customers` |
| Sửa | `app/Http/Controllers/Api/GlobalSearchController.php` | Lọc kết quả search theo phạm vi đơn của user |
| Sửa | `app/Http/Controllers/Order/OrderDataTableController.php` | Dùng filter role dùng chung `OrderAccess::scopeVisibleTo` |
| Sửa | `app/Support/OrderAccess.php` | Thêm method `scopeVisibleTo()` (row-level filter dùng chung) |
| Sửa | `app/Actions/Order/CreateOrderAction.php` | Transaction phần lõi; soft-fail có cảnh báo; transaction save contact; xóa dead code |
| Mới | `app/DataTransferObjects/CreateOrderResult.php` | DTO kết quả tạo đơn (order + warnings) |
| Sửa | `resources/views/pages/order/⚡create/create.php` | Toast cảnh báo khi có phần chưa lưu được |
| Sửa | `resources/views/pages/congno/⚡index/index.php` | Guard `Gate congno.index` trong `mount()` |
| Sửa | `resources/views/pages/congnodaily/⚡index/index.php` | Guard `Gate congno_daily.view` trong `mount()` |
| Mới | `database/migrations/2026_07_03_000001_add_unique_member_code_index.php` | Unique index `member.code` (có pre-check dữ liệu trùng) |
| Sửa | `phpunit.xml` | `PUSH_ENABLED=false` cho môi trường test |
| Sửa | `tests/Feature/ExampleTest.php` | Root `/` assert redirect về login |
| Sửa | `tests/Feature/CreatePickupActionTest.php` | Bổ sung bảng `user` vào schema test |
| Sửa | `tests/Feature/Mobile/BuildsMobileSchema.php` | Bổ sung bảng `activity_logs`, `user_device_tokens` |
| Mới | `tests/Feature/RoutePermissionTest.php` | Test hồi quy phân quyền route |
| Mới | `tests/Feature/CreateOrderActionTest.php` | Test luồng tạo đơn (5 case đợt 1 + 1 case phụ phí đợt 2) |
| Sửa | `.env` | `LOG_STACK=daily` (log rotate theo ngày) |

### Đợt 2 — Hợp nhất tính toán payment

| Loại | File | Nội dung |
|---|---|---|
| Mới | `app/Support/OrderPaymentCalculator.php` | **Nguồn sự thật duy nhất** của công thức tiền: parse số, PPXD/VAT/tổng cước, VAT từng dòng phụ phí, chi hộ, hoa hồng, bonus sale, snapshot lợi nhuận |
| Sửa | `app/Actions/Order/CreateOrderAction.php` | `calculatePrice()` giao phần tính cho calculator; xóa ~160 dòng công thức trùng lặp (`defaultPayment`, `buildPaymentGroup`, `normalizeFeeRows`, `profitSnapshot`, parser riêng) |
| Sửa | `resources/views/pages/order/⚡payment.blade.php` | `recalculateAll`/`profitSnapshot`/`number`/`normalizePayment` thành wrapper gọi calculator; xóa ~120 dòng công thức trùng lặp. UI + quyền khóa giá KHÔNG đổi |
| Sửa | `resources/views/pages/order/⚡create/create.php` | Bỏ đoạn `str_replace` parse số thô (từng biến `1,000.50` thành `100050`) — parser chuẩn nằm trong calculator |
| Mới | `tests/Unit/OrderPaymentCalculatorTest.php` | 11 test số liệu theo đúng ví dụ trong guide + test bất động điểm (idempotent) |
| Sửa | `docs/payment-calculation-guide.md`, `docs/SERVICE_PRICE_CALCULATION.md` | Ghi chú nguồn code là calculator; semantics `total_phuphi` = SAU VAT |

### Đợt 3 — Bật queue thật cho push FCM

| Loại | File | Nội dung |
|---|---|---|
| Sửa | `.env` | `QUEUE_CONNECTION=sync` → `database` (job push ghi vào bảng `jobs`, worker nền xử lý) |
| Mới | `docs/QUEUE_OPERATIONS.md` | Hướng dẫn vận hành worker: supervisor / cron cPanel, giám sát, xử lý failed job, quy tắc `queue:restart` sau deploy |
| Mới | `docs/deploy/supervisor-queue-worker.conf` | File cấu hình supervisor mẫu (sửa path + user rồi copy vào server) |

> Không đổi code PHP nào ở đợt 3 — 4 job push (`SendPickupAssignedPush`,
> `SendPickupAssignedOpsPush`, `SendOrderAssignedPush`, `SendNotificationPush`)
> vốn đã implement `ShouldQueue` với `tries=3, backoff=10s`; bảng
> `jobs`/`failed_jobs` đã có sẵn từ migration cũ. Chỉ đổi connection + bổ sung
> tài liệu vận hành.

### Đợt 4 — PDF vận đơn + tem kiện (tính năng mới)

| Loại | File | Nội dung |
|---|---|---|
| Mới | `app/Services/Pdf/OrderPdfRenderer.php` | Render PDF bằng Dompdf (font DejaVu — đủ dấu tiếng Việt) + barcode Code128 PNG (Picqer); label khổ A6, bill khổ A4 (khớp print CSS hiện hành) |
| Mới | `app/Http/Controllers/Order/OrderPdfController.php` | 2 endpoint tải PDF, check `OrderAccess::canView` per-order |
| Mới | `resources/views/pdf/order-label.blade.php` | Template tem: 1 kiện/trang, barcode + người nhận/gửi/cân/kích thước |
| Mới | `resources/views/pdf/order-bill.blade.php` | Template bill A4 + trang CVCK tùy chọn (layout table — Dompdf không hỗ trợ flex/grid) |
| Sửa | `routes/web.php` | Thêm 2 route `orders/{uuid}/pdf/label` + `/pdf/bill` trong group `can:orders.index` |
| Sửa | `resources/views/pages/order/⚡show.blade.php` | **CHỈ THÊM** (41 dòng, 0 dòng xóa — git diff xác nhận): 2 nút "PDF Label"/"PDF Bill" + modal chọn CVCK riêng. Nút Print Bill/Print Label cũ nguyên trạng |
| Mới | `tests/Feature/OrderPdfTest.php` | 4 test: trả đúng PDF, sale không tải được đơn người khác (403), sale tải được đơn mình, uuid lạ 404 |

### Đợt 5 — Trang tra cứu đơn công khai (fix bug + hoàn thiện)

| Loại | File | Nội dung |
|---|---|---|
| Mới | `app/Http/Controllers/TrackingPageController.php` | Tìm đơn theo `id_bill` HOẶC `tracking_code`; validate keyword (chặn input rác/XSS); timeline từ `order_history`; che tên ("N*** V*** Đ***") + SĐT (3 số cuối) người nhận |
| Mới | `resources/views/tracking/index.blade.php` | Trang public standalone (không cần đăng nhập): form tra cứu, badge trạng thái, timeline, branding logo/màu theo settings, responsive; theo pattern trang 403/404 sẵn có |
| Sửa | `routes/web.php` | Route `/theo-doi/{idbill?}` trỏ controller mới; idbill thành optional (vào thẳng `/theo-doi` để nhập mã); giữ nguyên throttle 10/phút |
| Mới | `tests/Feature/PublicTrackingPageTest.php` | 6 test: form trống, tìm theo bill/tracking code, che thông tin + không lộ người gửi, mã lạ ra thông báo (không 500), input độc hại bị loại |

> **Nguyên tắc dữ liệu trang public:** chỉ hiện mã đơn, trạng thái, timeline,
> người nhận đã che, điểm đến, ngày tạo/dự kiến giao. KHÔNG hiện giá/cước,
> KHÔNG hiện bất kỳ thông tin người gửi nào. KHÔNG gọi TrackingMore (tốn quota
> + chậm trang public) — timeline lấy từ `order_history` hệ thống tự ghi.

### Đợt 6 — In hàng loạt PDF tem/bill từ danh sách đơn

| Loại | File | Nội dung |
|---|---|---|
| Sửa | `app/Services/Pdf/OrderPdfRenderer.php` | Thêm `bulkLabelPdf()` / `bulkBillPdf()` (gộp nhiều đơn 1 file) + hằng `BULK_MAX_ORDERS = 100` |
| Sửa | `resources/views/pdf/*` | Tách template thành partials dùng chung (`partials/label-styles`, `label-pages`, `bill-styles`, `bill-pages`) — single và bulk cùng 1 nguồn markup, sửa 1 chỗ ăn cả 2; thêm `bulk-labels.blade.php`, `bulk-bills.blade.php` |
| Sửa | `app/Http/Controllers/Order/OrderPdfController.php` | Thêm `bulkLabel`/`bulkBill` (GET `?ids=1,2,3`): lọc id hợp lệ, chặn >100 đơn (422), **row-level filter theo `OrderAccess::scopeVisibleTo`** — đơn ngoài phạm vi bị bỏ qua lặng lẽ, không còn đơn hợp lệ → 404 |
| Sửa | `routes/web.php` | 2 route `orders/pdf/bulk-label` + `bulk-bill` (đặt TRƯỚC route `{uuid}` để không bị nuốt) |
| Sửa | `resources/views/pages/order/⚡index/partials/bulk-actions.blade.php` | 2 nút "PDF Tem"/"PDF Bill" cạnh các nút bulk sẵn có, disable khi chưa chọn đơn |
| Sửa | `resources/views/pages/order/⚡index/index.php` + `index.blade.php` | Route mới vào JS; click → `window.open` PDF gộp theo `ids` đã chọn |
| Sửa | `tests/Feature/OrderPdfTest.php` | +4 test bulk: gộp đúng số trang, 422 khi ids rỗng/rác/quá 100, sale bị lọc đơn ngoài phạm vi |

> Bulk bill KHÔNG kèm CVCK (công văn ký riêng từng lô — không thuộc ngữ cảnh in
> hàng loạt). Cần CVCK thì dùng nút PDF Bill ở chi tiết đơn.

### Đợt 7 — Trang tra cước công khai `/tra-cuoc`

| Loại | File | Nội dung |
|---|---|---|
| Mới | `app/Http/Controllers/PublicQuoteController.php` | Form dịch vụ + quốc gia + cân/kích thước → giá THAM KHẢO. Dùng lại nguyên vẹn `ResolveServicePriceAction` (cùng công thức tạo đơn); chỉ liệt kê dịch vụ CÓ bảng giá; quốc gia lọc theo dịch vụ đã chọn; DIM đọc từ settings (fallback 6000) |
| Mới | `resources/views/quote/index.blade.php` | Trang public standalone, branding theo settings, kết quả kèm cân tính cước + đơn giá + **disclaimer bắt buộc** (chưa gồm phụ phí/VAT) |
| Sửa | `config/features.php` | Feature flag mới `quote` (default bật) — KH tắt được trang qua option `feature_quote_enabled` |
| Sửa | `routes/web.php` | Route `/tra-cuoc` + middleware `feature:quote` + `throttle:20,1` (chống dò bảng giá) |
| Mới | `tests/Feature/PublicQuotePageTest.php` | 6 test: render không cần login, tính đúng DON_GIA (2.3kg→2.5kg×450k=1.125.000) và CO_DINH, **không lộ cước vốn/gốc dưới mọi định dạng số**, vượt khoảng cân ra thông báo thân thiện (không giá 0đ), feature flag tắt → 404 |

> **Nguyên tắc dữ liệu trang public:** CHỈ hiện cước bán. Cước vốn/cước gốc
> tuyệt đối không xuất hiện trong response (có test riêng chốt chặn). Giá kèm
> disclaimer "tham khảo, chưa gồm phụ phí/VAT".

---

## 1. Bối cảnh lỗi đã vá (tester cần hiểu để kiểm đúng chỗ)

### Lỗi 1 — Route group middleware `can:` vô hiệu (BẢO MẬT — nghiêm trọng)

Pattern cũ `Route::prefix(...)->group(...)->middleware('can:x')` **không áp** middleware
vào các route bên trong group (Laravel đã đăng ký route xong trước khi middleware được set).
Hệ quả trước khi vá:

- **Mọi user đăng nhập (kể cả CTV = tài khoản khách hàng)** gọi được `GET /cong-no-dai-ly/datatable`
  và `/cong-no-dai-ly/export` → tải toàn bộ công nợ đại lý **kèm giá vốn**.
- **CS và OPS** xem được toàn bộ công nợ khách hàng qua `GET /cong-no/datatable`, `/cong-no/export`.
- **Mọi user đăng nhập** search được **mọi đơn hàng trong hệ thống** qua `GET /api/global-search`.

Đã sửa thành `Route::middleware('can:x')->prefix(...)->group(...)` cho **17 group**:
orders, pickups, cong-no, cong-no-dai-ly, khach-hang, sender, receiver, ctv, nhan-su,
dich-vu, don-vi, phan-loai, doi-tac, phu-phi, place, chinh-sach, cai-dat.

### Lỗi 2 — Luồng tạo đơn silent-fail

Trước vá: nếu tạo kiện/khai báo/ảnh/contact lỗi, user vẫn thấy toast "Thành công",
không ai biết đơn thiếu dữ liệu. Sau vá:

- **Phần lõi** (mã đơn + record `orders` + tracking history "Mới tạo") nằm trong transaction —
  lỗi là rollback sạch, không có đơn "nửa vời".
- **Phần bổ sung** (kiện, khai báo hàng hóa, ảnh, liên hệ) vẫn fail mềm (nghiệp vụ cho phép
  bổ sung sau), nhưng UI hiện **toast vàng liệt kê phần chưa lưu được**.
- Lưu contact được bọc transaction → hết nguy cơ 2 user cùng lúc sinh trùng mã `CUSxxxxxx`;
  thêm unique index `member.code` làm lưới an toàn cuối.

### Lỗi 3 — Test suite gãy 13 test

Nguyên nhân: `.env` bật `PUSH_ENABLED=true` leak vào môi trường test → observer dispatch
push job sync → query bảng không tồn tại trong schema test. Đã chặn ở `phpunit.xml`.

### Đợt 2 — Logic tính tiền bị nhân bản 2 nơi (tiềm ẩn lệch số)

Trước vá: công thức cước/VAT/PPXD/phụ phí/lợi nhuận tồn tại **2 bản riêng** —
một trong `CreateOrderAction` (màn tạo đơn), một trong màn cập nhật giá — kèm 4 lệch ngầm:

1. Màn tạo đơn **ép VAT phụ phí = 0** kể cả khi dòng có `vat_percent`; màn payment tính thật.
2. `total_phuphi` lệch nghĩa: create lưu *trước VAT*, payment lưu *sau VAT* (VAT=0 nên chưa lộ).
3. **3 parser số khác nhau**; parser màn tạo đơn dùng `str_replace` thô, biến `1,000.50` thành `100050`.
4. Sửa công thức 1 nơi quên nơi kia là kịch bản chắc chắn xảy ra theo thời gian.

Sau vá: toàn bộ công thức nằm trong **`app/Support/OrderPaymentCalculator.php`** —
2 màn hình chỉ gọi qua. Semantics thống nhất: `total_phuphi` = tổng phụ phí **SAU VAT**
(`total_phuphi_no_vat` = trước VAT); ở bước tạo đơn VAT phụ phí = 0 nên **số ra không đổi
so với trước** — đơn cũ không bị ảnh hưởng, đơn mới ra số y hệt.

---

## 2. Kiểm chứng tự động (chạy trước tiên)

| ID | Lệnh | Kỳ vọng |
|---|---|---|
| AUTO-01 | `php artisan test` | **90 passed, 0 failed** |
| AUTO-02 | `php artisan test --filter=RoutePermissionTest` | 3 passed — route thiếu gate sẽ làm test này fail |
| AUTO-03 | `php artisan test --filter=CreateOrderActionTest` | 6 passed |
| AUTO-04 | `php artisan route:list --path=cong-no` | Mọi route hiển thị có `Authorize` (can:) trong middleware |
| AUTO-05 | `php artisan test --filter=OrderPaymentCalculatorTest` | 11 passed — số liệu khớp đúng ví dụ trong `docs/payment-calculation-guide.md`, gồm test bất động điểm (recalc lần 2 số không đổi) |
| AUTO-06 | `php artisan test --filter=OrderPdfTest` | 4 passed — PDF hợp lệ + phân quyền per-order |
| AUTO-07 | `php artisan route:list --path=pdf` | 2 route `orders.pdf.label` / `orders.pdf.bill` có `Authorize:orders.index` |
| AUTO-08 | `php artisan test --filter=PublicTrackingPageTest` | 6 passed — trang tracking công khai render, tìm đúng, che thông tin |
| AUTO-09 | `php artisan test --filter=OrderPdfTest` | 8 passed (4 single + 4 bulk) |
| AUTO-10 | `php artisan test --filter=PublicQuotePageTest` | 6 passed — trong đó có test chốt chặn không lộ cước vốn/gốc |

> **Quy tắc hồi quy:** `RoutePermissionTest` quét toàn bộ route theo prefix nhạy cảm.
> Nếu dev thêm module mới theo pattern group→middleware sai, test này fail ngay.
> Tester KHÔNG được bỏ qua/skip test này khi thấy fail — đó là lỗ hổng thật.

---

## 3. Test case thủ công — Phân quyền (ưu tiên cao nhất)

Chuẩn bị: mỗi role 1 tài khoản (admin, manager, ketoan, cs, sale, ops, ctv, shipper).
Cách gọi endpoint JSON trực tiếp: đăng nhập web rồi mở URL trên trình duyệt
(hoặc dùng cookie phiên trong Postman).

### 3.1. Công nợ đại lý (lỗ nặng nhất trước vá — chứa GIÁ VỐN)

| ID | Role | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|---|
| PQ-01 | CTV | Mở `/cong-no-dai-ly/datatable` | **403** (trước vá: 200 + full data) | ☐ |
| PQ-02 | CTV | Mở `/cong-no-dai-ly/export` | **403** | ☐ |
| PQ-03 | Sale | Mở `/cong-no-dai-ly` và `/cong-no-dai-ly/datatable` | **403** cả hai | ☐ |
| PQ-04 | CS | Mở `/cong-no-dai-ly/datatable` | **403** | ☐ |
| PQ-05 | Ketoan | Vào menu Công nợ đại lý, xem danh sách, export | Hoạt động bình thường | ☐ |
| PQ-06 | Manager | Như PQ-05 | Hoạt động bình thường | ☐ |
| PQ-07 | Admin | Như PQ-05 + xóa công nợ | Hoạt động bình thường | ☐ |

### 3.2. Công nợ khách hàng

| ID | Role | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|---|
| PQ-08 | CS | Mở `/cong-no/datatable`, `/cong-no/export` | **403** (trước vá: thấy toàn bộ) | ☐ |
| PQ-09 | OPS (trên PC) | Mở `/cong-no/datatable` | **403** | ☐ |
| PQ-10 | Sale | Vào `/cong-no`, xem danh sách | Chỉ thấy công nợ đơn của **chính mình** | ☐ |
| PQ-11 | CTV | Vào `/cong-no` | Chỉ thấy công nợ của **chính mình** | ☐ |
| PQ-12 | Ketoan/Manager/Admin | Vào `/cong-no` | Thấy tất cả, thao tác bình thường | ☐ |

### 3.3. Global search (thanh tìm kiếm chung)

| ID | Role | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|---|
| PQ-13 | CTV | Search mã đơn của khách hàng KHÁC (`/api/global-search?q=<mã>`) | **Không có kết quả** (trước vá: thấy mã + link tracking) | ☐ |
| PQ-14 | CTV | Search mã đơn của chính mình | Thấy kết quả | ☐ |
| PQ-15 | Sale | Search mã đơn không thuộc sale mình | Không có kết quả | ☐ |
| PQ-16 | Admin | Search mã đơn bất kỳ | Thấy kết quả | ☐ |

### 3.4. Các module còn lại (route đã bật lại gate)

| ID | Role | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|---|
| PQ-17 | Shipper | Mở `/orders` trên trình duyệt | Redirect về `/shipper/pickups` | ☐ |
| PQ-18 | Sale | Mở `/nhan-su/sale` | **403** | ☐ |
| PQ-19 | Sale | Mở `/cai-dat` | **403** (gate settings.index: admin/manager/ketoan/cs) | ☐ |
| PQ-20 | Ketoan | Mở `/dich-vu/dichvuchinh` | **403** (gate dulieu.index: admin/manager/cs) | ☐ |
| PQ-21 | CTV | Mở `/khach-hang`, `/ctv`, `/chinh-sach` | **403** cả ba | ☐ |
| PQ-22 | Từng role hợp lệ | Đi hết các menu hiển thị trên sidebar của role đó | Không gặp 403 ở menu mà sidebar cho hiện | ☐ |

> **PQ-22 quan trọng:** sidebar ẩn/hiện menu theo role khớp với gate, nên user hợp lệ
> không được phép gặp 403 ở bất kỳ menu nào họ nhìn thấy. Nếu gặp → báo bug ngay
> (gate và sidebar lệch nhau).

---

## 4. Test case thủ công — Luồng tạo đơn

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| TD-01 | Tạo đơn đầy đủ (kiện, khai báo, ảnh, tick lưu người gửi/nhận) | Toast **xanh** "Tạo đơn hàng ... thành công"; redirect trang chi tiết; đủ kiện theo số lượng khai (1 dòng × số kiện N → N record, mã `<mã đơn>-01`, `-02`...); có history "Mới tạo" | ☐ |
| TD-02 | Tạo đơn KHÔNG nhập kiện/khai báo/ảnh | Đơn vẫn tạo thành công, toast xanh (các phần rỗng là hợp lệ — bổ sung sau) | ☐ |
| TD-03 | Tạo đơn, tick "Lưu thông tin người gửi" với người gửi MỚI | Bảng khách hàng có contact mới, mã dạng `CUSxxxxxx` không trùng | ☐ |
| TD-04 | 2 tài khoản cùng lúc tạo đơn + lưu contact mới (2 máy/2 trình duyệt, bấm gần đồng thời) | 2 mã `CUS` **khác nhau**; không có lỗi 500. Nếu hy hữu đụng unique index → 1 bên báo lỗi lưu "thông tin liên hệ" qua toast vàng, đơn vẫn tạo | ☐ |
| TD-05 | 2 tài khoản cùng lúc tạo đơn | 2 mã đơn khác nhau, số thứ tự liên tiếp không trùng | ☐ |
| TD-06 | Double-click nút tạo đơn / mở 2 tab cùng submit | Chỉ tạo 1 đơn (cache lock 15s), tab kia báo "đang được tạo" | ☐ |
| TD-07 | (Khó dựng thủ công — đã cover bằng AUTO-03) Bước phụ fail khi tạo đơn | Đơn VẪN tạo + toast **vàng** "Đơn ... đã tạo, nhưng chưa lưu được: <tên phần>. Vui lòng bổ sung trong trang chi tiết" | ☐ |

> TD-07: trên môi trường staging có thể giả lập bằng cách tạm rename bảng
> `order_package` rồi tạo đơn (nhớ khôi phục). Bình thường chỉ cần tin AUTO-03.

---

## 4B. Test case thủ công — Tính toán payment (đợt 2, QUAN TRỌNG: đụng tiền)

Mục tiêu: xác nhận việc hợp nhất công thức về `OrderPaymentCalculator` **không làm
đổi số** ở cả 2 màn hình. Test trên staging với dữ liệu giống production càng tốt.

### 4B.1. Số không đổi trên đơn CŨ (kiểm hồi quy quan trọng nhất)

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| PM-01 | Chọn 3–5 đơn cũ có đủ: phụ phí, chi hộ, hoa hồng KH, bonus sale, VAT/PPXD ≠ 0. **Ghi lại số hiện tại** (cước bán/vốn/gốc, tổng, lợi nhuận, tỷ suất) | — | ☐ |
| PM-02 | Mở màn "Cập nhật giá" từng đơn ở PM-01, bấm nút tính lại (recalc), **CHƯA lưu** | Mọi con số trên màn hình **y hệt** số đã ghi ở PM-01 | ☐ |
| PM-03 | Lưu 1 đơn trong số đó, mở lại | Số sau lưu = số trước lưu; lịch sử đơn ghi nhận "cập nhật giá đơn hàng" nhưng before/after **không lệch giá trị** | ☐ |

> PM-02/03 là hiện thân thủ công của test tự động "bất động điểm" (AUTO-05):
> dữ liệu tính bởi code cũ, recalc bằng code mới → số phải trùng.

### 4B.2. Màn tạo đơn — parse số và phụ phí

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| PM-04 | Tạo đơn có 2 dòng phụ phí hải quan, nhập giá kiểu VN `150.000` (SL 2) và `80.000` (SL 1) | Đơn lưu tổng phụ phí **380.000** (không phải 380 hay 15080); phụ phí xuất hiện giống nhau ở cả 3 nhóm cước bán/vốn/gốc trong màn cập nhật giá | ☐ |
| PM-05 | Tạo đơn có bảng giá dịch vụ khớp (dịch vụ + quốc gia có bảng giá, cân nằm trong khoảng) | `dongiaban/dongiavon/dongiagoc` đúng theo bảng giá (CO_DINH lấy nguyên giá; DON_GIA nhân cân tính cước); mở màn cập nhật giá thấy đúng các số này | ☐ |
| PM-06 | Sau PM-05, vào màn cập nhật giá bấm recalc rồi lưu, không sửa gì | Số y hệt lúc tạo đơn (create ↔ payment cùng công thức) | ☐ |

### 4B.2b. Bảng giá theo DỊCH VỤ + QUỐC GIA (menu Phụ phí / Bảng giá dịch vụ)

Chuẩn bị: tạo 1 bảng giá gắn dịch vụ A, áp dụng cho quốc gia X (và không áp dụng
quốc gia Y), có các dòng cân `CO_DINH` và `DON_GIA`. Công thức cân tính cước:
`cân thể tích = dài×rộng×cao / DIM`; lấy max với cân thực; **dưới 21kg làm tròn
lên mốc 0.5kg, từ 21kg làm tròn lên kg nguyên**; nhân số kiện.

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| BG-01 | Tạo đơn dịch vụ A → quốc gia X, kiện rơi vào dòng `CO_DINH` (vd 0–0.5kg giá 250.000) | `dongiaban` = **250.000 nguyên giá** (không nhân cân) | ☐ |
| BG-02 | Tạo đơn dịch vụ A → quốc gia X, kiện rơi vào dòng `DON_GIA` (vd đơn giá 450.000, cân tính cước 2.5kg) | `dongiaban` = **450.000 × 2.5 = 1.125.000** (đơn giá × cân) | ☐ |
| BG-03 | Kiểm tra làm tròn cân: kiện 10×10×10cm, DIM 5000, cân thực 1.2kg | Cân thể tích 0.2 < cân thực → cân tính cước = **1.5kg** (1.2 làm tròn lên mốc 0.5); nếu cân thực 21.3kg → **22kg** (tròn lên kg nguyên) | ☐ |
| BG-04 | Tạo đơn dịch vụ A → quốc gia **Y** (bảng giá KHÔNG áp dụng cho Y) | Đơn **VẪN tạo được**, cước = 0 (không chặn — bổ sung giá sau ở màn cập nhật giá). Metadata `service_price_list_id` trống | ☐ |
| BG-05 | Tạo đơn dịch vụ A → quốc gia X nhưng tổng cân **vượt mọi khoảng cân** trong bảng giá | Đơn vẫn tạo, cước = 0, metadata có `service_price_list_id` nhưng không có dòng giá | ☐ |
| BG-06 | Tạo đơn **chưa chọn dịch vụ** hoặc **chưa chọn quốc gia nhận** hoặc **không nhập kiện** | Bị CHẶN với thông báo tương ứng ("Vui lòng chọn dịch vụ/quốc gia/nhập kiện để tính cước") — khác BG-04/05 là không chặn | ☐ |
| BG-07 | Có 2 bảng giá cùng khớp dịch vụ A + quốc gia X | Hệ thống dùng bảng giá có `updated_at` **mới nhất** | ☐ |
| BG-08 | Sau khi tạo đơn (PM-05/BG-01), sửa giá trong bảng giá gốc rồi mở lại đơn | Số trên đơn **KHÔNG đổi** (snapshot `service_price_*` giữ giá tại thời điểm tạo) | ☐ |
| BG-09 | Đối chiếu metadata: mở JSON payment của đơn BG-01/BG-02 (hoặc hỏi dev query) | Có đủ `service_price_list_id/name`, `service_price_quycach`, `service_price_weight`, `weight_from/to`, `sale/cost/base_unit`, `sale/cost/base_amount`; 3 nhóm cước bán/vốn/gốc cùng list_id | ☐ |

> **Lưu ý cho tester:** tài liệu cũ ghi "không có bảng giá → chặn tạo đơn" —
> hành vi thực tế (và đã cập nhật lại doc `SERVICE_PRICE_CALCULATION.md`) là
> **không chặn, cước = 0**. Chỉ chặn khi thiếu dịch vụ / quốc gia / kiện (BG-06).

### 4B.3. Màn cập nhật giá — công thức theo guide

Số kỳ vọng lấy đúng ví dụ trong `docs/payment-calculation-guide.md`:

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| PM-07 | Đặt đơn giá bán 10.000.000, PPXD 10%, VAT 8% | PPXD = 1.000.000; VAT = 880.000 ((10tr+1tr)×8%); tổng sau VAT = 11.880.000 | ☐ |
| PM-08 | Cước bán trước VAT 10.000.000, thêm hoa hồng KH 500.000, cước vốn trước VAT 7.000.000, bonus sale 5% | Bonus = 500.000; lợi nhuận tạm tính = 2.500.000; lợi nhuận = 2.000.000; tỷ suất 25% / 20% | ☐ |
| PM-09 | Thêm dòng phụ phí có VAT 10% (giá 100.000, SL 2) ở màn cập nhật giá | Dòng hiện total 200.000 + VAT 20.000; tổng sau VAT của nhóm tăng 220.000; tổng trước VAT tăng 200.000 | ☐ |
| PM-10 | Thêm dòng chi hộ 400.000 vào nhóm cước vốn | Dòng chi hộ có total riêng, **tổng cước vốn KHÔNG tăng** (chi hộ không cộng vào tổng) | ☐ |
| PM-11 | Kiểm tra quyền khóa giá không đổi: sale sau khi giá bị chốt (`lockSaleCharge`) | Sale không sửa được cước bán như trước đây (hành vi quyền giữ nguyên, chỉ công thức được hợp nhất) | ☐ |

---

## 4C. Test case — Queue & Push notification (đợt 3)

**Điều kiện tiên quyết:** queue worker đang chạy trên môi trường test
(xem `docs/QUEUE_OPERATIONS.md`; dev local dùng `composer dev` là có sẵn).
App Flutter shipper/OPS đã đăng nhập để có device token, `PUSH_ENABLED=true`
và `FIREBASE_CREDENTIALS` đã cấu hình.

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| QU-01 | Gán shipper vào 1 pickup (web hoặc app OPS) | Thao tác gán phản hồi **ngay lập tức** (không chờ FCM); app shipper nhận push "Pickup mới được giao" trong vài giây | ☐ |
| QU-02 | Gán OPS vào pickup / gán OPS vào đơn | App OPS nhận push tương ứng; màn hình người thao tác không bị treo chờ | ☐ |
| QU-03 | Tạo thông báo nội bộ (màn Cài đặt → Thông báo) | Các thiết bị thuộc role nhận được push | ☐ |
| QU-04 | **Worker chết:** tắt worker (`supervisorctl stop ...` / tắt tab queue), gán shipper vào pickup | Gán vẫn thành công bình thường; bảng `jobs` tăng 1 record; app **chưa** nhận push | ☐ |
| QU-05 | Bật lại worker sau QU-04 | Job tồn đọng được xử lý, push tới app (trễ nhưng không mất); bảng `jobs` về 0 | ☐ |
| QU-06 | Kiểm tra sau deploy: chạy `php artisan queue:restart` rồi lặp lại QU-01 | Push vẫn hoạt động (worker nhận code mới) | ☐ |
| QU-07 | Giả lập job fail (vd tạm điền sai `FIREBASE_CREDENTIALS` path, gán shipper, đợi 3 lần retry × 10s) | Job KHÔNG kẹt vô hạn: sau 3 lần thử rơi vào `failed_jobs` (`php artisan queue:failed` thấy record); nghiệp vụ gán không bị ảnh hưởng | ☐ |
| QU-08 | So sánh độ trễ trước/sau (nếu đo được): thao tác nhận đơn của shipper trên app khi FCM chậm | Trước vá: request treo theo FCM. Sau vá: phản hồi tức thì, push đến sau — **không còn phụ thuộc tốc độ FCM** | ☐ |

> **QU-04/05 là case quan trọng nhất:** chứng minh nghiệp vụ không phụ thuộc
> worker sống. Rủi ro vận hành mới duy nhất của đợt 3 là *quên chạy worker* —
> khi đó mọi thứ vẫn chạy trừ push notification (job nằm chờ trong bảng `jobs`,
> không mất).

---

## 4D. Test case — PDF vận đơn & tem kiện (đợt 4, tính năng MỚI)

**Nguyên tắc kiểm quan trọng nhất:** đợt 4 KHÔNG được làm thay đổi bất kỳ hành vi
nào của 2 nút **Print Label / Print Bill** cũ (in qua hộp thoại trình duyệt).
PDF là 2 nút MỚI đứng cạnh, luồng độc lập.

### 4D.1. Hồi quy luồng in cũ (chạy trước)

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| PD-01 | Mở chi tiết đơn → bấm **Print Label** (nút cũ) | Hộp thoại in trình duyệt mở, layout tem y hệt trước đợt 4 | ☐ |
| PD-02 | Bấm **Print Bill** (nút cũ) → chọn Không kèm / Kèm CVCK | In như trước, modal cũ hoạt động bình thường | ☐ |
| PD-03 | Auto-print URL cũ `orders/{uuid}?print=label` và `?print=bill&cvck=1` | Vẫn tự mở hộp thoại in như trước | ☐ |

### 4D.2. Tính năng PDF mới

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| PD-04 | Chi tiết đơn → bấm **PDF Label** | Tab mới mở file PDF khổ A6; mỗi kiện 1 trang (đơn 3 kiện → 3 trang); barcode + mã kiện đúng từng kiện; tiếng Việt đủ dấu | ☐ |
| PD-05 | Bấm **PDF Bill** → "Không kèm CVCK" | PDF A4 đủ khối: header + barcode mã đơn, From/To, hàng hóa + value USD, tổng kiện/cân, bảng kiện, ô chữ ký | ☐ |
| PD-06 | Bấm **PDF Bill** → "Kèm CVCK" | PDF có thêm trang Công văn cam kết (font serif, bảng invoice, tổng số lượng/value khớp đơn) | ☐ |
| PD-07 | Quét barcode trên bản in PDF Label bằng app scan của hệ thống | Scan ra đúng mã kiện, tìm được đơn (barcode PDF tương thích hệ scan) | ☐ |
| PD-08 | So dữ liệu PDF vs màn chi tiết đơn: người gửi/nhận, cân, kích thước, mã kiện, value invoice | Khớp 100% | ☐ |
| PD-09 | Đơn KHÔNG có kiện nào → PDF Label | Vẫn ra 1 trang tem với mã fallback `<mã đơn>-K1` (không lỗi 500) | ☐ |

### 4D.3. Phân quyền PDF (per-order như màn chi tiết)

| ID | Role | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|---|
| PD-10 | Sale | Mở URL `orders/{uuid}/pdf/label` của đơn **sale khác** | **403** | ☐ |
| PD-11 | CTV | Mở URL PDF của đơn khách khác | **403** | ☐ |
| PD-12 | Sale/CTV | Mở URL PDF đơn của chính mình | PDF tải bình thường | ☐ |
| PD-13 | Bất kỳ | URL PDF với uuid không tồn tại | **404** | ☐ |

---

## 4E. Test case — Trang tra cứu đơn công khai (đợt 5)

**Không cần đăng nhập** — test bằng cửa sổ ẩn danh. Trước đợt 5, mọi URL
`/theo-doi/...` đều lỗi 500; giờ phải là trang hoàn chỉnh.

### 4E.1. Chức năng

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| TR-01 | Mở `/theo-doi` (không mã) — ẩn danh | Trang form tra cứu, có logo/tên công ty theo settings, KHÔNG lỗi 500, KHÔNG bị đá về login | ☐ |
| TR-02 | Nhập mã bill đúng (vd `BEE...`) → Tra cứu | Hiện mã đơn, badge trạng thái đúng màu, timeline hành trình (mới nhất trên cùng, chấm xanh), ngày tạo | ☐ |
| TR-03 | Mở `/theo-doi/<tracking_code>` (mã tracking, không phải mã bill) | Vẫn tìm ra đơn (tra được theo cả 2 loại mã) | ☐ |
| TR-04 | Nhập mã không tồn tại | Thông báo vàng "Không tìm thấy đơn hàng" + gợi ý kiểm tra lại — KHÔNG lỗi 500 | ☐ |
| TR-05 | Cập nhật trạng thái đơn ở web nội bộ → F5 trang tracking | Timeline hiện sự kiện mới | ☐ |
| TR-06 | Mở trên điện thoại (hoặc DevTools mobile) | Layout responsive, form + timeline hiển thị tốt | ☐ |

### 4E.2. Bảo mật dữ liệu (quan trọng nhất — trang PUBLIC)

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| TR-07 | Xem kỹ toàn bộ trang + View Source với 1 đơn thật | **Tên người nhận che** dạng "N*** V*** Đ***"; **SĐT chỉ còn 3 số cuối** | ☐ |
| TR-08 | Tìm trong View Source: tên công ty người gửi, SĐT người gửi, địa chỉ người gửi | **Tuyệt đối không xuất hiện** | ☐ |
| TR-09 | Tìm trong View Source: cước, giá, VND, số tiền bất kỳ của đơn | **Tuyệt đối không xuất hiện** | ☐ |
| TR-10 | Nhập mã dạng `<script>alert(1)</script>` hoặc ký tự lạ | Trang quay về form trống, không echo lại payload, không lỗi | ☐ |
| TR-11 | Gõ liên tục >10 lần tra cứu trong 1 phút | Bị throttle 429 (chống dò quét mã đơn hàng loạt) | ☐ |

---

## 4F. Test case — In hàng loạt PDF tem/bill (đợt 6)

**Vị trí:** màn Danh sách đơn → tick checkbox các đơn → 2 nút mới **PDF Tem** /
**PDF Bill** trong thanh thao tác hàng loạt (cạnh Xóa/Hủy/Nhận hàng...).

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| BL-01 | Chưa tick đơn nào | 2 nút PDF mờ (disabled); bấm không có gì xảy ra | ☐ |
| BL-02 | Tick 3 đơn → bấm **PDF Tem** | Tab mới mở 1 file PDF gộp: đủ tem của CẢ 3 đơn nối tiếp (đơn nhiều kiện ra nhiều trang), barcode + mã kiện đúng từng đơn | ☐ |
| BL-03 | Tick 3 đơn → bấm **PDF Bill** | 1 file PDF, mỗi đơn 1 trang A4 bắt đầu trang mới, **KHÔNG có trang CVCK** (chủ đích — CVCK chỉ có ở PDF Bill từng đơn) | ☐ |
| BL-04 | So nội dung tem trong file bulk với PDF Label từng đơn (đợt 4) | Layout + dữ liệu y hệt (dùng chung template) | ☐ |
| BL-05 | Tick cả đơn ở trang 2 DataTable (chọn xong chuyển trang, chọn thêm) | File gộp đủ đơn đã tick ở mọi trang (selection giữ qua trang) | ☐ |
| BL-06 | Sale tick lẫn đơn của sale khác (nếu dựng được dữ liệu) rồi in | File chỉ chứa đơn thuộc phạm vi sale; đơn người khác bị bỏ qua lặng lẽ | ☐ |
| BL-07 | Sửa URL `?ids=` thủ công: rỗng, `abc,-1`, hoặc >100 id | **422** kèm thông báo; >100 báo "Tối đa 100 đơn mỗi lần in" | ☐ |
| BL-08 | Tick ~30-50 đơn thật → PDF Tem | File ra trong thời gian chấp nhận được (<15s), mở/in được bình thường | ☐ |
| BL-09 | Hồi quy: các nút bulk cũ (Xóa/Hủy/Nhận hàng/Duyệt xuất/Xuất hàng) | Hoạt động y như trước — nút PDF chỉ THÊM vào, không đổi hành vi cũ | ☐ |

---

## 4G. Test case — Trang tra cước công khai (đợt 7)

**Không cần đăng nhập** — test bằng cửa sổ ẩn danh tại `/tra-cuoc`.
Chuẩn bị: ít nhất 1 bảng giá có cả dòng `DON_GIA` và `CO_DINH` (mục 4B.2b đã dựng).

### 4G.1. Chức năng

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| QT-01 | Mở `/tra-cuoc` ẩn danh | Form render với branding công ty; dropdown dịch vụ CHỈ chứa dịch vụ có bảng giá | ☐ |
| QT-02 | Chọn dịch vụ | Trang reload, dropdown quốc gia chỉ còn quốc gia áp dụng của dịch vụ đó | ☐ |
| QT-03 | Nhập cân rơi vào dòng `DON_GIA` (vd 2.3kg, đơn giá 450k, khoảng 0-5kg) | Giá = đơn giá × cân đã làm tròn (2.3→2.5kg → **1.125.000 đ**); hiện "Đơn giá: 450.000 đ/kg" + cân tính cước 2.5 kg | ☐ |
| QT-04 | Nhập cân rơi vào dòng `CO_DINH` | Giá = nguyên giá dòng; hiện "Giá cố định theo khoảng cân" | ☐ |
| QT-05 | Nhập kích thước lớn để cân thể tích > cân thực (vd 50×50×50cm, 1kg, DIM 6000 → 20.8kg) | Cân tính cước lấy theo cân thể tích (làm tròn); giá tính theo đó | ☐ |
| QT-06 | Nhập cân vượt mọi khoảng trong bảng giá (vd 50kg) | Thông báo "Chưa có bảng giá cho tuyến này..." — KHÔNG hiện giá 0 đ, KHÔNG lỗi 500 | ☐ |
| QT-07 | Kết quả bất kỳ | Luôn kèm disclaimer "*Giá tham khảo, chưa bao gồm phụ phí... VAT" | ☐ |
| QT-08 | So sánh chéo: cùng dịch vụ/quốc gia/kiện, tạo đơn thật ở web nội bộ | `dongiaban` trên đơn = giá trang tra cước (cùng một công thức) | ☐ |

### 4G.2. Bảo mật & vận hành

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| QT-09 | View Source trang kết quả, tìm các số cước vốn/gốc của dòng giá (hỏi kế toán số liệu) | **Tuyệt đối không xuất hiện** — chỉ có cước bán | ☐ |
| QT-10 | Tra cứu liên tục >20 lần/phút | Throttle 429 (chống đối thủ dò nguyên bảng giá) | ☐ |
| QT-11 | Cài đặt → Hệ thống: tắt feature "Tra cước công khai" (`feature_quote_enabled` = false trong options settings) | `/tra-cuoc` trả 404; bật lại → hoạt động | ☐ |
| QT-12 | Nhập giá trị âm / chữ / cân 99999 | Validation chặn với thông báo tiếng Việt, không lỗi 500 | ☐ |

---

## 5. Test case — Vận hành / môi trường

| ID | Thao tác | Kỳ vọng | Kết quả |
|---|---|---|---|
| VH-01 | Sau deploy, kiểm tra `storage/logs/` sang ngày mới | File log mới dạng `laravel-YYYY-MM-DD.log` (rotate daily) | ☐ |
| VH-02 | Chạy `php artisan migrate` trên môi trường có dữ liệu | Migration `add_unique_member_code_index` chạy DONE. Nếu fail kèm thông báo "bảng member đang có mã trùng [...]" → chuyển bộ phận dữ liệu làm sạch rồi chạy lại (đây là hành vi đúng, không phải bug) | ☐ |
| VH-03 | Server dùng route cache: sau deploy chạy `php artisan route:clear && php artisan route:cache`, rồi chạy lại PQ-01 | Vẫn 403 | ☐ |

---

## 6. Checklist deploy (cho DevOps, tester xác nhận sau deploy)

1. ☐ `php artisan migrate` (chú ý pre-check mã trùng — xem VH-02).
2. ☐ `php artisan route:clear && php artisan route:cache` (bắt buộc nếu có route cache).
3. ☐ `php artisan config:clear` (đổi `.env` LOG_STACK + QUEUE_CONNECTION).
4. ☐ **Cài queue worker** theo `docs/QUEUE_OPERATIONS.md` (supervisor hoặc cron) — thiếu bước này là mất push notification. Xác nhận: gán shipper 1 pickup → bảng `jobs` tăng rồi về 0.
5. ☐ Từ nay về sau, mỗi lần deploy code: thêm `php artisan queue:restart` vào quy trình.
6. ☐ Chạy nhanh PQ-01, PQ-08, PQ-13 trên production bằng tài khoản test — 3 case đại diện cho 3 lỗ hổng chính.
7. ☐ Chạy PM-01/PM-02 trên 2–3 đơn thật (mở màn cập nhật giá, recalc, KHÔNG lưu) — xác nhận số không đổi sau hợp nhất calculator.

## 7. Hành vi MỚI người dùng sẽ thấy (để CSKH không nhầm là bug)

- CS/OPS **không còn** vào được màn công nợ (trước đây vào "chui" được do lỗi) — đây là chủ đích, khớp `PHAN_QUYEN_CHI_TIET.md`.
- Khi tạo đơn mà một phần dữ liệu phụ không lưu được: toast **vàng** thay vì xanh, đơn vẫn tạo, user bổ sung ở trang chi tiết.
- Kết quả global search của sale/ctv/cs/ops chỉ còn trong phạm vi đơn họ phụ trách.
- **Đợt 2 — KHÔNG có hành vi mới nhìn thấy được:** hợp nhất calculator là refactor nội bộ, mọi con số phải y hệt trước. Nếu user/tester thấy bất kỳ số tiền nào đổi so với trước deploy → **báo bug ngay lập tức**, đính kèm mã đơn + ảnh chụp số cũ/mới.
- **Đợt 3:** thao tác gán đơn/pickup phản hồi nhanh hơn (không chờ FCM); push notification đến sau vài giây thay vì tức thì. Nếu push **hoàn toàn không đến** trong nhiều phút → khả năng cao worker không chạy, báo DevOps kiểm tra theo `docs/QUEUE_OPERATIONS.md` (mục Giám sát).
- **Đợt 4:** chi tiết đơn có thêm 2 nút mới **PDF Label** và **PDF Bill** (mở file PDF trong tab mới) đứng cạnh 2 nút Print cũ. Nút Print cũ giữ nguyên — user quen luồng nào dùng luồng đó.
- **Đợt 5:** link tra cứu `/theo-doi/<mã đơn>` giờ hoạt động (trước đây lỗi trắng trang) — CSKH có thể gửi link này cho khách; khách thấy hành trình nhưng KHÔNG thấy giá và thông tin người gửi (chủ đích, không phải thiếu).
- **Đợt 6:** danh sách đơn có thêm 2 nút **PDF Tem** / **PDF Bill** trong thanh thao tác hàng loạt — tick nhiều đơn in 1 file. Bulk bill không kèm CVCK là chủ đích (cần CVCK thì vào chi tiết đơn).
- **Đợt 7:** có trang tra cước công khai `/tra-cuoc` — sale/CSKH có thể gửi link cho khách tự ước tính giá; giá hiện là cước bán tham khảo (chưa phụ phí/VAT), có thể tắt trang trong Cài đặt nếu không muốn công khai bảng giá.
