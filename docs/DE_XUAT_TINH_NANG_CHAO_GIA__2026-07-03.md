# ĐỀ XUẤT TÍNH NĂNG MỞ RỘNG — Chuẩn bị chào giá khách hàng

- **Ngày phân tích:** 2026-07-03 (cập nhật lần 2 — cùng ngày: đã bỏ khỏi đề xuất những mục hoàn thành)
- **Mục đích:** liệt kê tính năng nên bổ sung để đóng gói chào giá, kèm đánh giá nền tảng sẵn có / khối lượng / giá trị bán hàng — làm cơ sở thảo luận sâu hơn trước khi chốt roadmap.
- **Trạng thái:** ĐỀ XUẤT — chưa chốt. Mỗi mục cần anh đánh dấu: `[ ] làm` / `[ ] bỏ` / `[ ] cần bàn thêm`.
- **Tài liệu liên quan:** `docs/PHAN_TICH_HE_THONG_MOI_RENEWLOGICTICS.md`, `docs/TEST_PLAN__VaLoi_PhanQuyen_TaoDon__2026-07-03.md` (chất lượng nền tảng hiện tại), `docs/FLUTTER_APP_SHIPPER_OPS_ROADMAP.md`.

## ✅ Đã hoàn thành — KHÔNG còn nằm trong đề xuất

| Mục (từng đề xuất) | Hoàn thành | Ghi chú |
|---|---|---|
| **A4: Tra cước công khai** `/tra-cuoc` (quote calculator) | 2026-07-03 (đợt 7) | Form dịch vụ/quốc gia/cân-kích thước → giá tham khảo; cùng công thức với tạo đơn (`ResolveServicePriceAction`); CHỈ hiện cước bán (test chốt chặn không lộ cước vốn/gốc); disclaimer; feature flag `quote` tắt/bật được; throttle chống dò bảng giá; 6 test. Test case: mục 4G test plan |
| **A1: In hàng loạt tem/bill** từ danh sách đơn | 2026-07-03 (đợt 6) | Tick nhiều đơn → 2 nút "PDF Tem"/"PDF Bill" → 1 file PDF gộp (mỗi kiện/đơn 1 trang); giới hạn 100 đơn/lần; đơn ngoài phạm vi quyền bị bỏ qua; template dùng chung với PDF từng đơn; 4 test tự động. Test case: mục 4F test plan |
| **Bug 0.1 + A2: Trang tracking công khai** `/theo-doi/{idbill}` | 2026-07-03 (đợt 5) | Sửa lỗi 500 (view thiếu) + làm luôn trang hoàn chỉnh: form tra cứu, tìm theo mã bill/tracking code, timeline hành trình, branding theo settings, che thông tin người nhận (tên viết tắt + 3 số cuối SĐT), KHÔNG lộ giá/người gửi; 6 test tự động. Test case: mục 4E test plan |
| Xuất **PDF vận đơn (bill) + tem kiện (label)** server-side | 2026-07-03 (đợt 4) | 2 nút "PDF Label"/"PDF Bill" (kèm/không CVCK) tại chi tiết đơn; Dompdf + barcode Code128, tiếng Việt đủ dấu; phân quyền per-order; 4 test tự động. **Độc lập với nút Print cũ.** Test case: mục 4D test plan |
| Bật queue thật cho push FCM | 2026-07-03 (đợt 3) | `QUEUE_CONNECTION=database` + tài liệu vận hành worker (`docs/QUEUE_OPERATIONS.md`) |

---

## Hiện trạng đã rà (để không chào trùng tính năng có sẵn)

| Đã có | Chưa có |
|---|---|
| Scan barcode (web + app mobile) | — |
| **In vận đơn (Print Bill) + in tem (Print Label)** qua hộp thoại in trình duyệt + **xuất PDF Label/Bill server-side** (kèm/không CVCK) + **in hàng loạt PDF** từ danh sách đơn (mới 2026-07-03) | — |
| **Trang tracking công khai** `/theo-doi/{mã}` — timeline, branding, che thông tin nhạy cảm (mới 2026-07-03) | — |
| Push FCM nội bộ (shipper/OPS) — queue database đã bật 2026-07-03 | Thông báo cho KHÁCH khi đổi trạng thái (email/Zalo) |
| Bảng giá theo dịch vụ + quốc gia + khoảng cân, 2 quy cách CO_DINH/DON_GIA + **trang tra cước công khai** `/tra-cuoc` (mới 2026-07-03) | — |
| Import Excel: VSVX, bảng giá dịch vụ | Import ĐƠN HÀNG hàng loạt |
| API tracking cho bên thứ 3 (1 endpoint, có middleware auth riêng) | Bộ API tạo đơn/tra cứu cho đối tác |
| Công nợ CTV + công nợ đại lý + e-invoice + QR 3 cổng (SePay/MoMo/VNPay) | COD / thu hộ (không có dấu vết nào trong code) |
| 3 report service (Sale/Country/System) + dashboard | Xuất báo cáo định kỳ, biểu đồ lợi nhuận theo tuyến |
| Settings logo/màu/tên công ty động + license-guard theo domain — **khớp sẵn mô hình mỗi KH 1 source/VPS riêng** | Quy trình cài đặt/cập nhật chuẩn hóa cho nhiều bản cài (xem C2) |
| Email e-invoice (gửi tay từng cái) | Email tự động theo sự kiện |

---

## Nhóm A — Giá trị cao, tận dụng nền có sẵn (chào gói chuẩn)

### A1. In hàng loạt tem/bill — ✅ ĐÃ HOÀN THÀNH (2026-07-03, đợt 6)

> Xem bảng "Đã hoàn thành" đầu file. Nâng cấp tùy chọn nếu KH sản lượng RẤT lớn
> (>100 đơn/lượt): render qua queue + link tải khi xong — chỉ làm khi có nhu cầu thật.

### A2. Trang tracking công khai — ✅ ĐÃ HOÀN THÀNH (2026-07-03, đợt 5)

> Xem bảng "Đã hoàn thành" đầu file. Nâng cấp tùy chọn còn lại nếu KH yêu cầu:
> nhúng bản đồ tuyến, đa ngôn ngữ (EN cho người nhận nước ngoài), widget nhúng
> vào website KH — chào như tuỳ biến nhỏ khi có nhu cầu thật.

### A3. Thông báo khách tự động khi đổi trạng thái đơn

- **Vì sao:** giảm tải CSKH của KH ("đơn tôi tới đâu rồi"), tăng cảm nhận chuyên nghiệp.
- **Nền sẵn có:** queue database đã bật + worker docs (đợt 3); Mailable pattern có sẵn; event đổi trạng thái đi qua `RecordTrackingHistoryAction` → 1 điểm hook duy nhất; **PDF bill đã có** (đợt 4) → email "Đã giao" có thể đính kèm bill PDF luôn.
- **Phạm vi gợi ý (chia 2 mức để chào giá):**
  - Mức 1 — **Email**: gửi khi Đã nhận hàng / Đang phát / Đã giao, kèm link tracking công khai (đã có, đợt 5). ~2 ngày.
  - Mức 2 — **Zalo ZNS**: chuẩn thị trường VN, cần đăng ký OA + template, tính phí theo tin → **chào như gói riêng, thu phí tin nhắn theo tháng**. ~3-5 ngày tích hợp.
- **Phụ thuộc:** cần cấu hình SMTP thật (hiện `MAIL_MAILER=log`).

Quyết định: `[ ]`

### A4. Tra cước công khai — ✅ ĐÃ HOÀN THÀNH (2026-07-03, đợt 7)

> Xem bảng "Đã hoàn thành" đầu file. Nâng cấp tùy chọn khi KH yêu cầu:
> form "để lại SĐT nhận tư vấn" (lead cho sale), so sánh nhiều dịch vụ cùng lúc,
> widget nhúng vào website KH.

---

## Nhóm B — Mở rộng nghiệp vụ (chào gói nâng cao)

### B1. Import đơn hàng loạt từ Excel

- **Vì sao:** KH sản lượng lớn (shop TMĐT gửi quốc tế) không thể tạo tay từng đơn.
- **Nền sẵn có:** pattern import Excel đã có 2 chỗ (VSVX, bảng giá); **thời điểm đúng**: luồng import sẽ đi qua `CreateOrderAction` + `OrderPaymentCalculator` vừa hợp nhất — validation và giá tự động đồng nhất với tạo tay.
- **Phạm vi gợi ý:** template Excel chuẩn (tải mẫu như 2 module kia); preview + báo lỗi từng dòng trước khi commit; import chạy qua queue (đã bật) với progress.
- **Khối lượng:** ~3-5 ngày.
- **Rủi ro:** mapping danh mục (dịch vụ/quốc gia) từ text Excel → id; cần quy ước mã rõ trong template.

Quyết định: `[ ]`

### B2. Bộ API tạo đơn cho đối tác (B2B)

- **Vì sao:** mở tệp KH là công ty có hệ thống riêng muốn đẩy đơn tự động; nâng vị thế sản phẩm từ "web nội bộ" lên "platform".
- **Nền sẵn có:** Sanctum, middleware `third-party.tracking-api` (pattern auth key), action layer tạo đơn đã tách, `docs/MOBILE_API_CONTRACT.md` làm mẫu viết contract; **PDF label đã có** (đợt 4) → API trả được link/file tem cho đối tác tự in.
- **Phạm vi gợi ý:** `POST /api/partner/orders` (tạo đơn), `GET /orders/{code}` (chi tiết + tracking), webhook đẩy trạng thái về hệ thống đối tác; quản lý API key theo khách + rate limit; trang docs API.
- **Khối lượng:** ~5-8 ngày.
- **Lưu ý:** đây chính là lúc JSON schema validation tại Action layer (nợ kỹ thuật đã ghi nhận) phải làm nghiêm túc — input không còn đi qua UI Livewire.

Quyết định: `[ ]`

### B3. COD / thu hộ

- **Vì sao:** đơn giá module cao; nhưng **chỉ đúng nếu tệp KH có nhu cầu thu hộ** (thường là giao nội địa) — vận chuyển quốc tế thuần thì bỏ qua.
- **Nền sẵn có:** gần như KHÔNG — làm mới hoàn toàn: trường tiền thu hộ trên đơn, đối soát COD với shipper, bảng kê hoàn tiền khách, báo cáo COD.
- **Khối lượng:** ~2-3 tuần (module lớn).
- **Quyết định phụ thuộc:** xác nhận tệp KH mục tiêu trước khi đầu tư.

Quyết định: `[ ]`

### B4. Đối soát chi phí đại lý/hãng bay từ file

- **Vì sao:** kế toán KH đang so tay file đại lý gửi về với công nợ — tự động khớp là điểm bán cho phòng kế toán.
- **Nền sẵn có:** công nợ đại lý hoàn chỉnh; snapshot `cuocvon`/`service_price_*` trên từng đơn đủ dữ liệu để so lệch.
- **Phạm vi gợi ý:** upload file đối soát (Excel) → tự khớp theo mã vận đơn → báo cáo lệch từng dòng (khớp / lệch giá / thiếu đơn / thừa đơn) → xác nhận điều chỉnh.
- **Khối lượng:** ~4-6 ngày.
- **Rủi ro:** format file mỗi đại lý mỗi khác → cần cơ chế mapping cột linh hoạt.

Quyết định: `[ ]`

---

## Nhóm C — Gói premium / hợp đồng dài hạn

### C1. Báo cáo nâng cao + gửi định kỳ

- **Nền sẵn có:** 3 report service; `payment_loinhuan` snapshot từng đơn → biểu đồ lợi nhuận theo tuyến/quốc gia/sale không cần tính lại.
- **Phạm vi gợi ý:** xuất Excel báo cáo tháng; email tự động gửi quản lý (cần queue — đã có); biểu đồ xu hướng theo tuyến.
- **Khối lượng:** ~3-5 ngày.
- **Lưu ý:** nếu muốn "gửi định kỳ" thì cần bật scheduler — trước đó anh đã quyết định không cần; có thể thay bằng nút "gửi ngay" thủ công, hoặc mở lại quyết định scheduler khi bán tính năng này.

Quyết định: `[ ]`

### C2. Chuẩn hóa đóng gói triển khai per-VPS (mô hình đã chốt: mỗi KH 1 source + 1 VPS riêng)

> **Điều chỉnh 2026-07-03 theo mô hình kinh doanh thực tế:** hệ thống bán cho KH
> sẽ **deploy lên VPS của chính KH, mỗi KH một source riêng** — KHÔNG làm
> multi-tenant chung database. Điều này đơn giản hóa lớn: không cần tách dữ
> liệu theo tenant, không sửa kiến trúc; dữ liệu mỗi KH cô lập vật lý (điểm
> cộng bán hàng về bảo mật). Mục này đổi thành các việc làm cho mô hình đó
> vận hành trơn tru khi số KH tăng.

- **Nền sẵn có (đã khớp mô hình này):**
  - Branding per-KH qua `.env` + settings (SYSTEM_NAME/logo/màu) — mỗi bản cài tự cấu hình, không đụng code.
  - `bee/license-guard` khóa theo domain + LICENSE_KEY — chống KH tự nhân bản source sang VPS khác.
  - Feature flags (`packages`, `invoice`, `quote`) — bật/tắt module theo gói KH mua ngay trong Cài đặt.
  - `composer setup` script + backup/restore command có sẵn.
- **Khoảng cách thật khi có nhiều KH (việc nên làm dần):**
  1. **Quy trình cài đặt chuẩn** (~1-2 ngày): checklist/script provision VPS mới — env mẫu theo gói, migrate + seed role/permission/danh mục gốc, cấu hình supervisor worker (docs đã có), route:cache. Mục tiêu: cài KH mới < 1 giờ, không sót bước (kinh nghiệm: quên `queue:restart`/route:cache là loại bug "chỉ xảy ra trên máy KH").
  2. **Quy trình cập nhật nhiều bản cài** (~1-2 ngày): script deploy chung (git pull theo tag + migrate + cache clear + queue:restart) chạy được trên từng VPS; quy ước phiên bản (tag) để biết KH nào đang chạy bản nào. Khi >3 KH cân nhắc Deployer/Ansible.
  3. **Theo dõi sức khỏe các bản cài** (~1 ngày): endpoint `/up` đã có sẵn — thêm 1 dashboard/uptime monitor tập trung (UptimeRobot hoặc tự dựng) báo VPS nào chết, license nào sắp hết hạn.
  4. **Phân nhánh tuỳ biến:** giữ 1 repo lõi duy nhất; tuỳ biến riêng của từng KH làm qua feature flag/config chứ KHÔNG fork source — fork là nguồn gốc "sửa bug lõi phải vá tay N nơi".
- **Mô hình giá gợi ý:** phí bản quyền ban đầu (theo gói Chuẩn/Nâng cao) + **phí bảo trì/cập nhật năm** (chính là giá trị của mục 2-3: KH được nhận bản vá bảo mật + tính năng mới). License-guard là công cụ thực thi hợp đồng.

Quyết định: `[ ]` (đề xuất: làm mục 1 trước khi ký KH đầu tiên; mục 2-3 khi có ≥2 KH)

---

## Gợi ý đóng gói chào giá

| Gói | Nội dung | Ghi chú |
|---|---|---|
| **Chuẩn** | Hệ thống hiện tại — đã gồm in bill/tem + **PDF bill/tem (từng đơn + hàng loạt)** + **tracking công khai** + **tra cước công khai** | Demo trọn vòng đời: khách tra cước → tạo đơn → in/PDF tem → scan → tracking → giao. **Sẵn sàng demo ngay** |
| **Nâng cao** | Chuẩn + A3 mức 1 (email) + B1 (import Excel) | Nhắm KH có sản lượng |
| **Tùy chọn tính riêng** | A3 mức 2 (Zalo ZNS — phí tin/tháng), B2 (API đối tác), B3 (COD), B4 (đối soát), C1 (báo cáo) | Mỗi mục báo giá độc lập |
| **Bảo trì năm (mọi KH)** | C2: cập nhật bản vá + tính năng mới lên VPS của KH, giám sát uptime, hỗ trợ | Doanh thu định kỳ; license-guard thực thi hợp đồng |

### Điểm mạnh sẵn có nên ghi vào tài liệu chào giá

- Phân quyền 8 role chi tiết, **đã audit bảo mật + có test hồi quy tự động** (110 test, 2026-07-03).
- **Trang tra cứu đơn công khai** cho khách cuối: timeline hành trình, branding công ty, che thông tin nhạy cảm.
- **Trang tra cước công khai**: khách tự ước tính giá theo bảng giá thật, tắt/bật được, không lộ giá vốn.
- **In vận đơn + in tem barcode ngay trên web** (Code128, kèm/không kèm CVCK, auto-print) **+ xuất PDF server-side từng đơn và HÀNG LOẠT** (tick nhiều đơn → 1 file, đủ dấu tiếng Việt).
- Thanh toán QR 3 cổng: SePay, MoMo, VNPay + hóa đơn điện tử.
- App mobile shipper/OPS (Flutter) + API contract chuẩn.
- Công nợ 2 cấp (khách + đại lý), audit log toàn hệ thống.
- Bảng giá theo dịch vụ/quốc gia có snapshot giá trên từng đơn (an toàn đối soát).
- Push notification qua queue có retry (không mất thông báo khi lỗi mạng).
- **Triển khai trên VPS riêng của KH** — dữ liệu cô lập vật lý 100%, không dùng chung hạ tầng với bất kỳ ai (điểm cộng bảo mật khi chào doanh nghiệp).

---

## Thứ tự thực hiện khuyến nghị (các mục còn lại)

1. **C2 mục 1** — chuẩn hóa quy trình cài VPS mới (~1-2 ngày) — nên xong TRƯỚC khi ký KH đầu tiên.
2. **A3 mức 1** — email thông báo khách (cần chốt SMTP trước, ~2 ngày; đính kèm PDF bill + link tracking đều đã sẵn).
3. Nhóm B theo nhu cầu KH cụ thể sau buổi chào giá đầu; C2 mục 2-3 khi có ≥2 KH.
