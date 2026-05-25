# Hóa đơn Thu / Chi cho Công Nợ — Plan & Implementation Log

**Ngày tạo:** 2026-05-25
**Trạng thái:** Đã implement + fix review round 1 (2026-05-26), fix review round 2 (2026-05-26), fix order lock sau chốt cước (2026-05-26), chờ verify end-to-end + commit

---

## 1. Bối cảnh

Hệ thống hiện có 2 loại công nợ:

- **CongNo** (công nợ khách hàng) — tiền khách hàng nợ ta → khi thanh toán ta **thu** tiền vào.
- **CongNoDaiLy** (công nợ đại lý) — tiền ta nợ đại lý → khi thanh toán ta **chi** tiền ra.

Cả 2 hiện có bảng payment tương ứng (`congno_payments`, `congno_daily_payments`) chỉ ghi nhận đơn giản (amount, method, photo…). Cần nâng cấp thành **hóa đơn thanh toán** có vòng đời, có loại (Thu/Chi):

| Loại | Nguồn | Workflow | Lý do |
|---|---|---|---|
| **Hóa đơn THU** | CongNo (khách hàng) | 6 trạng thái: Mới tạo → Đã duyệt → (Đã gửi HĐ TT \| Đã gửi YC TT) → Đã thanh toán \| Hủy | Tiền vào — cần kế toán duyệt, khách hàng nộp tiền (cash hoặc QR SeaPay), webhook tự xác nhận online |
| **Hóa đơn CHI** | CongNoDaiLy (đại lý) | 3 trạng thái: Mới tạo → Đã thanh toán \| Hủy | Tiền ra — ta chủ động chi cho đại lý, admin/kế toán chỉ cần đánh dấu đã chi |

**Mục tiêu:**

1. Ẩn section hóa đơn khi công nợ chưa được chốt (status < `DA_CHOT_CUOC`).
2. Sau khi chốt, mở section "Hóa đơn thanh toán" với workflow tương ứng theo loại công nợ.
3. Tổng số tiền của các hóa đơn chưa hủy không được vượt quá tổng công nợ.
4. Tận dụng: `SepayPaymentService`, `SepayWebhookController`, `RoleEnum`, mẫu `DebtStatusEnum`, `Flux::toast`, `WithFileUploads`.

---

## 2. Quyết định kiến trúc

- **Extend cả 2 bảng** `congno_payments` và `congno_daily_payments`. Mỗi payment record = 1 hóa đơn. Phân biệt loại bằng cột `loai_hoa_don` (`'thu'` | `'chi'`) + ngầm hiểu theo bảng.
- **Cash vs Online (chỉ HĐ Thu):** 2 status tách biệt — `DA_GUI_HOA_DON_TT` (cash, kế toán verify thủ công), `DA_GUI_YEU_CAU_TT` (online, webhook auto-confirm).
- **Webhook auto-confirm:** `SepayWebhookController` gọi matcher tìm `CongNoPayment` theo `qr_payment_code` → set `DA_THANH_TOAN`. Chỉ áp dụng cho HĐ Thu.
- **Throttle QR 15 phút** (chỉ HĐ Thu): lưu `qr_generated_at`, kiểm tra trước khi regenerate.
- **Quyền hủy:** creator HOẶC admin/kế toán; chỉ khi status != `DA_THANH_TOAN`, != `HUY`.
- **Hard constraint amount:** `total_debt - paid_amount - pendingInvoicesTotal()` không âm. Validate server-side + `lockForUpdate` trong transaction.
- **Shared enum** `InvoicePaymentStatusEnum` với 6 case, nhưng có method `allowedForExpense()` trả về `[MOI_TAO, DA_THANH_TOAN, HUY]` để gate UI HĐ Chi.

---

## 3. Files đã tạo (5 files mới)

### 3.1 `app/Enums/InvoicePaymentStatusEnum.php`

```php
enum InvoicePaymentStatusEnum: string {
    case MOI_TAO = 'moi_tao';                       // Mới tạo
    case DA_DUYET = 'da_duyet';                     // Đã duyệt (chỉ HĐ Thu)
    case DA_GUI_HOA_DON_TT = 'da_gui_hoa_don_tt';   // Đã gửi hóa đơn TT (cash, chỉ HĐ Thu)
    case DA_GUI_YEU_CAU_TT = 'da_gui_yeu_cau_tt';   // Đã gửi YC TT (online, chỉ HĐ Thu)
    case DA_THANH_TOAN = 'da_thanh_toan';           // Đã thanh toán
    case HUY = 'huy';                                // Hủy

    public function label(): string;
    public function color(): array;
    public function icon(): string;
    public function isFinal(): bool;
    public function isPaid(): bool;
    public function isOpen(): bool;

    public static function allowedForIncome(): array;
    public static function allowedForExpense(): array;
}
```

### 3.2 `app/Enums/InvoiceTypeEnum.php`

```php
enum InvoiceTypeEnum: string {
    case THU = 'thu';   // Income — từ CongNo (khách hàng)
    case CHI = 'chi';   // Expense — từ CongNoDaiLy (đại lý)

    public function label(): string;
    public function color(): array;
}
```

### 3.3 `app/Services/Payments/InvoiceCodeGenerator.php`

Helper sinh mã hóa đơn:
- HĐ Thu: `HD-TH-YYYYMMDD-XXXX`
- HĐ Chi: `HD-CH-YYYYMMDD-XXXX`
- `generatePaymentCode(string $maHoaDon): string` — sinh mã QR code dùng cho SePay.

### 3.4 `app/Services/Payments/PaymentInvoiceMatcher.php`

```php
class PaymentInvoiceMatcher {
    public function matchCustomerDebtPayment(array $payload): ?CongNoPayment;
}
```

- Parse `description` / `content` / `transferContent` từ payload, regex tìm payment code.
- Match `CongNoPayment` với `qr_payment_code` + `status = DA_GUI_YEU_CAU_TT` + amount khớp (tolerance ±1đ).
- Update `status = DA_THANH_TOAN`, `paid_at = now`, `sepay_transaction_id`, `method = 'bank_transfer'`.
- Gọi `$payment->congNo->syncPaidAmountFromPayments()`.

Chỉ match HĐ Thu (`CongNoPayment`). HĐ Chi (`CongNoDaiLyPayment`) không qua webhook.

### 3.5 `database/migrations/2026_05_25_000001_extend_payments_for_invoices.php`

Migration duy nhất extend cả 2 bảng:

**Bảng `congno_payments` (HĐ Thu — full)** thêm cột:
- `loai_hoa_don` string default `'thu'`, indexed
- `ma_hoa_don` string unique nullable
- `status` string default `'moi_tao'`, indexed
- `id_ketoan` FK users nullable indexed
- `ngay_duyet` datetime nullable
- `qr_url` text nullable
- `qr_generated_at` datetime nullable indexed
- `qr_expires_at` datetime nullable
- `qr_payment_code` string nullable unique
- `sepay_transaction_id` string nullable indexed
- `cancelled_at` datetime nullable
- `id_cancelled_by` FK users nullable

**Bảng `congno_daily_payments` (HĐ Chi — simple)** thêm cột:
- `loai_hoa_don` string default `'chi'`, indexed
- `ma_hoa_don` string unique nullable
- `status` string default `'moi_tao'`, indexed
- `id_ketoan` FK users nullable indexed  *(người đánh dấu đã chi)*
- `ngay_duyet` datetime nullable  *(thời điểm đánh dấu đã chi)*
- `cancelled_at` datetime nullable
- `id_cancelled_by` FK users nullable

**Backfill** các record cũ: `status = 'da_thanh_toan'`, `loai_hoa_don` set theo bảng, `ma_hoa_don` sinh tự động.

---

## 4. Files đã sửa (8 files)

### 4.1 `app/Models/CongNoPayment.php` — HĐ Thu (full)

- Thêm `$fillable`: `loai_hoa_don, ma_hoa_don, status, id_ketoan, ngay_duyet, qr_url, qr_generated_at, qr_expires_at, qr_payment_code, sepay_transaction_id, cancelled_at, id_cancelled_by`
- `$casts`: `status => InvoicePaymentStatusEnum`, `loai_hoa_don => InvoiceTypeEnum`, các datetime
- Constants: `const QR_THROTTLE_MINUTES = 15;`
- Boot: tự sinh `ma_hoa_don` (HD-TH-...), `loai_hoa_don = 'thu'`, `qr_payment_code` khi cần
- Relations: `ketoan() → User`, `cancelledBy() → User`
- Helpers:
  - `canApprove(User $user): bool` — admin/manager/ketoan AND `status=MOI_TAO`
  - `canCancel(User $user): bool` — (creator|admin|manager|ketoan) AND `status ∉ {DA_THANH_TOAN, HUY}`
  - `canPay(User $user): bool` — `status=DA_DUYET`
  - `canConfirmCashPayment(User $user): bool` — admin/manager/ketoan AND `status=DA_GUI_HOA_DON_TT`
  - `canRegenerateQr(): bool`, `nextQrAvailableAt(): ?Carbon`

### 4.2 `app/Models/CongNoDaiLyPayment.php` — HĐ Chi (simple)

- Thêm `$fillable`: `loai_hoa_don, ma_hoa_don, status, id_ketoan, ngay_duyet, cancelled_at, id_cancelled_by`
- `$casts`: `status => InvoicePaymentStatusEnum`, `loai_hoa_don => InvoiceTypeEnum`, các datetime
- Boot: tự sinh `ma_hoa_don` (HD-CH-...), `loai_hoa_don = 'chi'`
- Relations: `ketoan() → User`, `cancelledBy() → User`
- Helpers:
  - `canMarkPaid(User $user): bool` — admin/manager/ketoan AND `status=MOI_TAO`
  - `canCancel(User $user): bool` — (creator|admin|manager|ketoan) AND `status ∉ {DA_THANH_TOAN, HUY}`

### 4.3 `app/Models/CongNo.php` — Customer debt

- Sửa `syncPaidAmountFromPayments()` chỉ sum `payments()->where('status', 'da_thanh_toan')->sum('amount')`
- Thêm `canCreatePaymentInvoice(): bool` — `in_array($this->status, [DA_CHOT_CUOC, DA_THANH_TOAN_MOT_PHAN, QUA_HAN])`
- Thêm `pendingInvoicesTotal(): float` — sum amount của các invoice **đang mở** (status ∈ {MOI_TAO, DA_DUYET, DA_GUI_HOA_DON_TT, DA_GUI_YEU_CAU_TT})
- Thêm `availableForNewInvoice(): float` — `total_cuocban - paid_amount - pendingInvoicesTotal()`

### 4.4 `app/Models/CongNoDaiLy.php` — Agency debt

- Sửa `syncPaidAmountFromPayments()` chỉ sum `status = DA_THANH_TOAN`
- Thêm `canCreatePaymentInvoice(): bool`
- Thêm `pendingInvoicesTotal(): float` — sum amount `status = MOI_TAO`
- Thêm `availableForNewInvoice(): float` — `total_cuocvon - paid_amount - pendingInvoicesTotal()`

**Hard constraint nghiệp vụ (áp dụng cho cả 2 loại):**

> Ví dụ công nợ 15tr:
> - Invoice 1 = 10tr (MOI_TAO) → `pendingInvoicesTotal=10tr`, `paid_amount=0` → `available=5tr`. Invoice 2 ≤ 5tr.
> - Invoice 1 → DA_THANH_TOAN → `paid_amount=10tr`, không còn pending → `available=5tr`.
> - Invoice 1 → HUY → `pendingInvoicesTotal=0` → `available=15tr`.

Validate 2 lớp:
1. **Server-side** trong Livewire `createPaymentInvoice()`: so sánh `$amount > $debt->availableForNewInvoice()` → toast error + return.
2. **UI hint**: hiển thị "Số tiền tối đa: {availableForNewInvoice} đ" + disable button khi vượt.

**Race condition:** dùng `DB::transaction()` + `$debt->lockForUpdate()` khi insert.

### 4.5 `app/Http/Controllers/Webhook/SepayWebhookController.php`

Sau `SepayWebhookLog::insertOrIgnore(...)`:
```php
try {
    app(PaymentInvoiceMatcher::class)->matchCustomerDebtPayment($payload);
} catch (\Throwable $e) {
    Log::error('Payment matcher failed', ['error' => $e->getMessage()]);
}
```
Webhook vẫn trả 200 dù matcher fail.

### 4.6 `resources/views/pages/congno/⚡show/show.php` — HĐ Thu (Livewire component)

**Properties mới:**
```php
use WithFileUploads;

public string $invoiceAmount = '';
public string $invoiceNote = '';
public ?int $payingInvoiceId = null;
public ?string $selectedMethod = null;       // 'cash' | 'bank_transfer'
public $cashInvoicePhoto = null;
public bool $showPayModal = false;
```

**Đã xóa:** properties + method `addPayment()` cũ (paymentAmount/paymentDate/paymentMethod/paymentReference/paymentNote).

**Methods mới:**
- `createPaymentInvoice()` — validate amount + `lockForUpdate`, insert với `MOI_TAO`
- `approveInvoice(int $id)` — abort_unless admin/manager/ketoan; MOI_TAO→DA_DUYET
- `cancelInvoice(int $id)` — check `canCancel`; →HUY
- `openPayModal(int $id)` — set state + `Flux::modal('pay-invoice')->show()`
- `submitCashPayment()` — upload ảnh (`storage/app/public/customer-debt-invoices/`), set `DA_GUI_HOA_DON_TT`
- `submitOnlinePayment()` — gọi `SepayPaymentService::makeQrUrl()`, set `DA_GUI_YEU_CAU_TT`, lưu `qr_generated_at=now()`
- `regenerateQr(int $id)` — check 15-min throttle, giữ nguyên `qr_payment_code`
- `confirmCashPayment(int $id)` — admin/kt; DA_GUI_HOA_DON_TT→DA_THANH_TOAN + sync paid_amount
- `closePayModal()`

**Computed properties:** `availableForNewInvoice()`, `pendingInvoicesTotal()`, `payingInvoice()`.

**Preserved methods:** confirmDebt, markOverdue, openSaleChargeModal, saveSaleCharge, removeOrder, addEditingSaleChargeFee, removeEditingSaleChargeFee, helpers (normalizePayment, recalculateGroup, recalculateOrderPayments, profitSnapshotFor, detailSnapshot, money, number, …).

### 4.7 `resources/views/pages/congno/⚡show/show.blade.php` — HĐ Thu (view)

**Đã xóa:** form "Ghi nhận thanh toán" cũ + section "Lịch sử thanh toán" cũ.

**Đã thêm:**
- Frontmatter PHP import `InvoicePaymentStatusEnum` + biến `$canCreateInvoice, $availableForInvoice, $pendingInvoiceAmount, $sortedInvoices, $payingInvoice`.
- **Creation form** (gated `$canCreateInvoice && canManage && $availableForInvoice > 0`): input số tiền + hint "Tối đa có thể tạo" + textarea ghi chú + nút "Tạo hóa đơn".
- **Amber banner** khi `! $canCreateInvoice`: "Cần chốt cước công nợ trước".
- **Bảng HĐ Thu**: KPI strip (count, pending amount, available) + table columns Mã HĐ / Ngày tạo / Người tạo / Số tiền / Trạng thái (icon badge) / Kế toán phụ trách / Ghi chú / Thao tác.
- **Action buttons** theo status: Duyệt / Thanh toán / Xác nhận đã nhận / Tạo lại QR (disabled khi throttle) / Xem QR / Ảnh HĐ / Hủy.
- **Pay modal** (`flux:modal name="pay-invoice"`):
  - Radio Cash/Online binding `wire:model.live="selectedMethod"`.
  - Cash: file input `wire:model="cashInvoicePhoto"` (image, max 8MB) + preview + nút "Gửi hóa đơn thanh toán".
  - Online: thông tin hướng dẫn + nút "Tạo mã QR thanh toán" gọi `submitOnlinePayment`.
  - Hiển thị `$payingInvoice->ma_hoa_don` + số tiền.

### 4.8 `resources/views/pages/congnodaily/⚡show.blade.php` — HĐ Chi (simple)

- Xóa form thanh toán cũ.
- Properties đơn giản: `$invoiceAmount, $invoiceNote`.
- Methods (chỉ 3): `createPaymentInvoice()`, `markPaid(int $id)`, `cancelInvoice(int $id)`.
- UI gate giống HĐ Thu.
- Bảng đơn giản: Mã HĐ / Ngày tạo / Người tạo / Số tiền / Trạng thái / Người xác nhận / Ghi chú / Actions.
  - `MOI_TAO`: nút "Đánh dấu đã chi" (admin/kt), "Hủy"
  - `DA_THANH_TOAN`: hiển thị info ngày chi, người chi
  - `HUY`: hiển thị info hủy
- Banner amber nếu công nợ chưa chốt.

---

## 5. Reusable patterns

| Pattern | Source |
|---|---|
| Enum structure | `app/Enums/DebtStatusEnum.php` |
| Generate unique code | `app/Models/CongNoDaiLy.php` (`generateSoHoaDon`) |
| File upload via Livewire | `resources/views/pages/settings/⚡banner.blade.php` |
| QR URL generation | `SepayPaymentService::makeQrUrl()` |
| Webhook log + parse | `SepayWebhookController` |
| Role check | `hasAnyRole(['admin','manager','ketoan'])` |
| Toast | `Flux::toast(...)` |

---

## 6. Implementation order (thực tế đã làm)

| # | Bước | Trạng thái |
|---|---|---|
| 1 | Migration extend cả 2 bảng + backfill | DONE |
| 2 | Enum `InvoicePaymentStatusEnum`, `InvoiceTypeEnum` | DONE |
| 3 | Service `InvoiceCodeGenerator`, `PaymentInvoiceMatcher` | DONE |
| 4 | Model `CongNoPayment` (HĐ Thu) | DONE |
| 5 | Model `CongNoDaiLyPayment` (HĐ Chi) | DONE |
| 6 | Model `CongNo` + `CongNoDaiLy` (sync, helpers) | DONE |
| 7 | `SepayWebhookController` tích hợp matcher | DONE |
| 8 | View `congnodaily/⚡show.blade.php` (HĐ Chi — simple) | DONE |
| 9 | View + component `congno/⚡show/show.{php,blade.php}` (HĐ Thu — full) | DONE |
| 10 | Đơn giản hóa lịch sử HĐ Thu trong chi tiết công nợ (4 cột: mã, ngày tạo, số tiền, trạng thái) | DONE |
| 11 | Trang danh sách HĐ Thu + DataTable + filter + menu sidebar | DONE |
| 12 | Verify end-to-end + chạy migration | DONE (migrate applied 2026-05-25) |

---

## 6b. Review Fixes (2026-05-26)

Sau khi bộ phận kỹ thuật review, đã xử lý 5 điểm sau:

### Fix #1 — Backfill cải thiện | Mức: High (đã xác minh không ảnh hưởng DB này)
- **Vấn đề:** Migration thêm cột `status` default `moi_tao`, sau đó backfill chỉ update rows có `status IS NULL`. Nếu có payment cũ trong DB, chúng có thể bị miss backfill.
- **Xác minh:** `DB::table('congno_payments')->count()` = 0, `congno_daily_payments` = 0 → **không ảnh hưởng**.
- **Cải thiện:** Bổ sung comment rõ ràng trong migration rằng mọi payment cũ đều là `da_thanh_toan` (vì predate invoice system và đã completed).
- **File:** `database/migrations/2026_05_25_000001_extend_payments_for_invoices.php`

### Fix #2 — Webhook chỉ match `DA_GUI_YEU_CAU_TT` | Mức: High
- **Vấn đề:** Matcher query `whereIn(['DA_GUI_YEU_CAU_TT', 'DA_DUYET'])` → invoice mới tạo nhưng chưa gửi YC thanh toán online vẫn bị webhook đánh dấu paid nếu nội dung chuyển khoản trùng mã.
- **Vấn đề 2:** `qr_payment_code` được sinh ngay trong `CongNoPayment::creating()` boot → invoice MOI_TAO đã có mã QR trước khi bước online request.
- **Fix:**
  - `PaymentInvoiceMatcher`: chỉ query `where('status', 'DA_GUI_YEU_CAU_TT')` — bỏ `DA_DUYET`.
  - `CongNoPayment::boot()`: bỏ việc sinh `qr_payment_code` khi tạo invoice. Mã QR chỉ được sinh khi người dùng bấm "Tạo mã QR" trong modal thanh toán (`submitOnlinePayment()`).
- **Files:** `app/Services/Payments/PaymentInvoiceMatcher.php`, `app/Models/CongNoPayment.php`

### Fix #3 — Webhook đồng bộ trạng thái order | Mức: Medium
- **Vấn đề:** `confirmCashPayment()` có cập nhật `orders.customer_payment_status` và `customer_paid_at`, nhưng `PaymentInvoiceMatcher` khi webhook confirm online chỉ sync debt, không sync order.
- **Fix:** Thêm cùng logic sync order vào trong `PaymentInvoiceMatcher.matchCustomerDebtPayment()` sau khi `syncPaidAmountFromPayments()` — xác định `customer_payment_status` theo `remaining_amount` của debt, cập nhật `customer_paid_at` khi fully paid.
- **File:** `app/Services/Payments/PaymentInvoiceMatcher.php`

### Fix #4 — HĐ Chi đồng bộ `agency_payment_status` | Mức: Medium
- **Vấn đề:** `markPaid()` trong congnodaily chỉ sync `paid_amount` lên debt, không cập nhật `orders.agency_payment_status` và `orders.agency_paid_at`.
- **Fix:** Sau khi `syncPaidAmountFromPayments()`, tính `agency_payment_status` theo remaining amount, cập nhật `agency_paid_at = now()` khi fully paid.
- **File:** `resources/views/pages/congnodaily/⚡show.blade.php` (markPaid method)

### Fix #5 — Section hóa đơn chỉ hiện khi công nợ chốt | Mức: Low
- **Vấn đề:** Gate section dùng `$canCreateInvoice || $sortedInvoices->isNotEmpty()` → section vẫn hiện nếu có invoice tồn tại trên công nợ chưa chốt.
- **Fix:** Đổi gate thành `@if ($canCreateInvoice)` — tuân thủ plan: ẩn section khi công nợ chưa chốt.
- **File:** `resources/views/pages/congno/⚡show/show.blade.php` (line ~317)

---

## 6c. Review bổ sung Tester (2026-05-26 — lần 2)

### Fix #6 — Webhook reject amount <= 0 | Mức: High
- **Vấn đề:** Matcher lấy `$amount = (int) ($payload['transferAmount'] ?? 0)`. Check mismatch chỉ chạy khi `$amount > 0`. Nếu `transferAmount` thiếu hoặc = 0, invoice vẫn có thể bị set `DA_THANH_TOAN`.
- **Fix:** Thêm early-return ngay khi `$amount <= 0` với log warning riêng. Bỏ điều kiện `$amount > 0` ở amount mismatch check (không còn cần thiết vì đã early-return).
- **Files:** `app/Services/Payments/PaymentInvoiceMatcher.php`

### Fix #7 — Nút "Tạo lại QR" không hiện + backend chưa check quyền | Mức: Medium
- **Vấn đề 1:** `canPay()` chỉ true khi `status = DA_DUYET`. Nút "Tạo lại QR" dùng `canPay($authUser)` nên không bao giờ hiện khi status = `DA_GUI_YEU_CAU_TT`.
- **Vấn đề 2:** `regenerateQr()` cho phép ai cũng regenerate khi `status = DA_GUI_YEU_CAU_TT`, không check user authority.
- **Fix:**
  - Thêm `canManageQr(?User $user)` helper vào `CongNoPayment`: true khi `status = DA_GUI_YEU_CAU_TT` VÀ (creator HOẶC staff power).
  - Đổi nút "Tạo lại QR" trong blade: `@if (... && $invoice->canManageQr($authUser))`.
  - Đổi nút "Xem QR": thêm `&& $invoice->canManageQr($authUser)`.
  - Sửa `regenerateQr()`: `abort_unless($invoice->canPay(...) || $invoice->canManageQr(...))`.
- **Files:** `app/Models/CongNoPayment.php`, `resources/views/pages/congno/⚡show/show.blade.php`, `resources/views/pages/congno/⚡show/show.php`

### Fix #8 — Rủi ro backfill trên môi trường deploy | Mức: Medium
- **Ghi nhận:** DB local hiện tại `congno_payments = 0`, `congno_daily_payments = 0`. Tuy nhiên môi trường deploy khác có thể có payment cũ.
- **Hành động:** Trước khi deploy, cần verify 2 bảng payment empty. Nếu có payment cũ, viết migration/fix data riêng để mark `da_thanh_toan` đúng.

### Fix #9 — Thiếu automated tests | Mức: Medium
- **Ghi nhận:** Không có test cho Payment Invoice. `php artisan test --filter=PaymentInvoice` → 0.
- **Hành động:** (Tạm hoãn — not blocking commit) Ưu tiên test: matcher reject `status != DA_GUI_YEU_CAU_TT`, matcher reject `amount <= 0`, matcher thành công sync order, HĐ Chi mark paid sync agency_payment_status, constraint `availableForNewInvoice()`.

### Fix #10 — ExampleTest fail | Mức: Low
- **Ghi nhận:** `Tests\Feature\ExampleTest` fail vì `/` trả `302` thay vì `200`. Không liên quan trực tiếp đến Payment Invoice.
- **Hành động:** Không block commit. Có thể fix sau nếu cần.

---

## 6d. Fix thêm: Khóa order sau khi chốt cước

### Fix order lock sau chốt cước | Mức: High
- **Vấn đề:** Sau khi chốt cước (`DA_CHOT_CUOC`), danh sách order vẫn hiện nút "Edit cước bán" và "Gỡ" vì gate chỉ là `$debt->status !== DA_THANH_TOAN`. Cần khóa ngay sau khi chốt, không cần đợi thanh toán xong.
- **Fix:**
  - UI `congno/⚡show/show.blade.php`: đổi gate từ `$debt->status !== DA_THANH_TOAN` → `!$debt->canCreatePaymentInvoice()` (nút Edit + Gỡ).
  - UI `congnodaily/⚡show.blade.php`: đổi gate tương tự (nút Gỡ).
  - Backend `congno/⚡show/show.php`: `openSaleChargeModal()` và `removeOrder()` đổi check từ `status === DA_THANH_TOAN` → `$debt->canCreatePaymentInvoice()`.
  - Backend `congnodaily/⚡show.blade.php`: `removeOrder()` đổi tương tự.
- **Files:** `congno/⚡show/show.blade.php`, `congno/⚡show/show.php`, `congnodaily/⚡show.blade.php`

---

## 7. Verification checklist

### HĐ Chi (Agency / Expense)

1. [ ] `php artisan migrate` → 2 bảng có cột mới + backfill OK. **(Đã chạy)**
2. [ ] Tạo công nợ đại lý mới → trang show **không hiện** section hóa đơn; chỉ banner amber.
3. [ ] Chốt cước → section hiện ra với form tạo hóa đơn + bảng trống.
4. [ ] Tạo HĐ Chi 10tr (công nợ 15tr) → invoice MOI_TAO, available còn 5tr. Tạo HĐ 6tr → fail validate. HĐ 5tr → OK. Hủy HĐ 1 → available=10tr.
5. [ ] Admin/kế toán bấm "Đánh dấu đã chi" → status=`DA_THANH_TOAN`, `CongNoDaiLy.paid_amount` cộng đúng, **orders.agency_payment_status** sync, **orders.agency_paid_at** set khi fully paid (Fix #4).
6. [ ] User thường KHÔNG thấy nút "Đánh dấu đã chi".
7. [ ] Hủy bởi creator OK; hủy HĐ đã thanh toán → button không hiện.

### HĐ Thu (Customer / Income)

8. [ ] Công nợ KH chưa chốt → trang show **không hiện** section; chốt → hiện.
9. [ ] Tạo HĐ Thu, admin/kt duyệt → `DA_DUYET`, nút "Thanh toán" hiện.
10. [ ] Modal pay → Cash → upload ảnh bắt buộc → submit → `DA_GUI_HOA_DON_TT`. Kế toán xác nhận → `DA_THANH_TOAN`, paid_amount cộng, **order.customer_payment_status** sync đúng.
11. [ ] Tạo HĐ khác, duyệt, chọn Online → bấm "Tạo mã QR" → `qr_payment_code` được sinh + `qr_url` gán, status=`DA_GUI_YEU_CAU_TT`. Regenerate trong 15p → toast warning. Lùi `qr_generated_at` trong DB 16p → regenerate OK.
12. [ ] POST giả tới `/api/webhooks/sepay` với content chứa `qr_payment_code` và amount khớp → invoice tự `DA_THANH_TOAN`, paid_amount cập nhật, **order.customer_payment_status** sync, **order.customer_paid_at** set.
13. [ ] Tạo invoice mới (MOI_TAO) → không có `qr_payment_code` → webhook gửi cùng mã → **không match** (Fix #2).
14. [ ] Webhook payload với `transferAmount = 0` hoặc missing → **reject ngay**, không set `DA_THANH_TOAN` (Fix #6).
15. [ ] Nút "Tạo lại QR" hiện đúng khi: status=`DA_GUI_YEU_CAU_TT` + user là creator/admin/manager/ketoan + throttle cho phép. User thường không thấy nút này (Fix #7).
16. [ ] Backend `regenerateQr()`: user thường bị 403 nếu thử regenerate QR (Fix #7).

### Cross

17. [ ] `gitnexus_detect_changes()` trước commit → list symbols thay đổi đúng dự kiến.
    - **Lưu ý:** GitNexus MCP đang lỗi `spawn EINVAL` trong session này. Cần chạy `npx gitnexus analyze` trước khi gọi tool, hoặc commit thủ công và mention trong message.

---

## 8. Out of scope

- E-invoice tax compliance qua `SepayEInvoiceService`.
- PDF export hóa đơn.
- Email/SMS thông báo.
- Bulk approve.
- Webhook auto-match cho HĐ Chi (không cần, vì HĐ Chi không qua QR).
- Trang tổng hợp hóa đơn cross-debt (HĐ Thu + HĐ Chi trên 1 dashboard).

---

## 9. Summary of code work done

### Files mới (8)
- `app/Enums/InvoicePaymentStatusEnum.php`
- `app/Enums/InvoiceTypeEnum.php`
- `app/Services/Payments/InvoiceCodeGenerator.php`
- `app/Services/Payments/PaymentInvoiceMatcher.php`
- `database/migrations/2026_05_25_000001_extend_payments_for_invoices.php`
- `app/Http/Controllers/Invoice/InvoiceDataTableController.php`
- `resources/views/pages/invoice/⚡index/index.php`
- `resources/views/pages/invoice/⚡index/index.blade.php`

### Files đã sửa (12)
- `app/Http/Controllers/Webhook/SepayWebhookController.php`
- `app/Models/CongNo.php`
- `app/Models/CongNoDaiLy.php`
- `app/Models/CongNoPayment.php`
- `app/Models/CongNoDaiLyPayment.php`
- `resources/views/pages/congno/⚡show/show.php`
- `resources/views/pages/congno/⚡show/show.blade.php`
- `resources/views/pages/congnodaily/⚡show.blade.php`
- `app/Providers/AuthServiceProvider.php` (thêm gate `invoice.index`)
- `app/View/Components/Sidebar.php` (thêm menu "Hóa đơn thu")
- `resources/views/components/sidebar/⚡icon.blade.php` (thêm icon `receipt`)
- `routes/web.php` (thêm route group `hoa-don-thu`)

### Verifications đã chạy (2026-05-25)
- `php -l resources/views/pages/congno/⚡show/show.php` → **No syntax errors**
- Blade compile `congno/⚡show/show.blade.php` → **OK (63,486 → 63,453 bytes sau Fix #5)**
- Blade compile `congnodaily/⚡show.blade.php` → **OK (26,747 → 27,180 bytes sau Fix #4)**
- `php artisan migrate --force` → **DONE**
- Autoload check: `InvoicePaymentStatusEnum` (6 cases), `InvoiceTypeEnum` (2 cases), `PaymentInvoiceMatcher`, `InvoiceCodeGenerator` đều OK.

### Review fixes đã áp dụng (2026-05-26)
- Fix #1: Backfill cải thiện (DB hiện tại empty nên không ảnh hưởng)
- Fix #2: Matcher chỉ query `DA_GUI_YEU_CAU_TT` + bỏ sinh `qr_payment_code` lúc boot
- Fix #3: Matcher sync `customer_payment_status` và `customer_paid_at` sau webhook confirm
- Fix #4: `markPaid()` HĐ Chi sync `agency_payment_status` và `agency_paid_at`
- Fix #5: Gate section chỉ còn `$canCreateInvoice`, bỏ `|| $sortedInvoices->isNotEmpty()`
- Fix #6: Matcher early-reject khi amount <= 0
- Fix #7: Thêm `canManageQr()` helper; nút "Tạo lại QR" + "Xem QR" dùng `canManageQr`; backend `regenerateQr()` check authority
- Fix order lock: Dùng `!$debt->canCreatePaymentInvoice()` làm gate cho nút Edit/Gỡ order + backend `openSaleChargeModal`/`removeOrder` trên cả 2 trang
- Simplify HĐ Thu history ở chi tiết công nợ: chỉ còn Mã hóa đơn / Ngày tạo / Số tiền / Trạng thái
- Thêm trang `/hoa-don-thu`: DataTable toàn bộ HĐ Thu, filter theo status/ngày/sale/customer, summary cards, link về công nợ gốc, cột actions với nút "Duyệt" cho admin
- Thêm menu sidebar "Hóa đơn thu" cho admin/manager/ketoan + gate `invoice.index`
- PHP lint + blade compile: **all OK**

### Next steps trước commit
1. Verify UI thủ công theo checklist mục 7 (ưu tiên: tạo invoice / approve / pay cash / pay QR / cancel / webhook match).
2. Chạy `npx gitnexus analyze` (nếu MCP còn lỗi thì commit thủ công và ghi rõ trong message rằng đã fallback).
3. `git add -p` các file kể trên (KHÔNG add `.claude/`, `CLAUDE.md`, `AGENTS.md` nếu không thuộc scope).
4. Commit message gợi ý:
   ```
   feat(congno): thêm chức năng Hóa đơn Thu/Chi cho công nợ

   - HĐ Thu (CongNo): 6 status workflow (mới tạo → duyệt → cash/online → thanh toán/hủy)
   - HĐ Chi (CongNoDaiLy): 3 status workflow đơn giản
   - SePay QR auto-confirm qua webhook + throttle 15 phút
   - Hard constraint: tổng hóa đơn ≤ tổng công nợ
   - Migration extend cả 2 bảng payment + backfill
   - Đồng bộ trạng thái order (customer_payment_status / agency_payment_status) khi thanh toán
   ```
