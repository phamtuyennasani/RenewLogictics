# Phản hồi kỹ thuật: Hóa đơn cho đơn hàng vãng lai

> Ngày phản hồi: 2026-05-27
> Phạm vi: Phản hồi từng điểm trong `REVIEW__HoaDonDonHangVangLai__CanCaiThien.md`

---

## Mức độ cao

### 1. Chưa chặn tạo hóa đơn cho đơn không phải vãng lai

**Tình trạng:** ĐÚNG — guard `isWalkIn()` chưa có.

**Phân tích:**
- `OrderInvoiceService::createForOrder()` hiện chỉ kiểm tra `isInvoiceLocked()` và amount > 0.
- Không có kiểm tra `$order->isWalkIn()`.
- Điều này cho phép tạo hóa đơn trực tiếp cho đơn có khách hàng thuộc công nợ — sai nghiệp vụ.

**Hành động:** Cần thêm guard ở `createForOrder()` và `canCreateInvoice()`.

```php
// OrderInvoiceService.php — cần thêm:
if (! $order->isWalkIn()) {
    throw new \RuntimeException('Chỉ đơn hàng vãng lai mới được tạo hóa đơn trực tiếp.');
}

// canCreateInvoice():
if (! $order->isWalkIn()) {
    return false;
}
```

**Cập nhật test plan:** Không cần — test plan đã đúng yêu cầu "đơn vãng lai". Bug nằm ở implementation.

---

### 2. Webhook thanh toán online chưa sync trạng thái đơn hàng vãng lai

**Tình trạng:** ĐÚNG — `PaymentInvoiceMatcher` chỉ sync qua `$invoice->congNo`, không handle hóa đơn đơn lẻ.

**Phân tích:**
- `PaymentInvoiceMatcher::matchWebhookPayment()` (dòng 119–133): sau khi đánh dấu `DA_THANH_TOAN`, chỉ gọi `$debt->syncPaidAmountFromPayments()` và cập nhật orders qua `$debt->orders()`.
- Với hóa đơn đơn lẻ: `$invoice->congNo()` trả `null` → khối `if ($debt && ...)` không chạy → đơn hàng không được sync.

**Hành động:** Thêm branch `hasDirectOrder()` trong `PaymentInvoiceMatcher`.

```php
// Trong PaymentInvoiceMatcher, sau khi $invoice->save() và trước return $invoice
if ($invoice->hasDirectOrder()) {
    app(\App\Services\OrderInvoiceService::class)->syncOrderPaymentStatus($invoice);
}
```

Hoặc gọi trực tiếp:

```php
$order = $invoice->order;
if ($order) {
    $order->forceFill([
        'customer_payment_status' => \App\Enums\DebtStatusEnum::DA_THANH_TOAN->value,
        'customer_paid_at' => $invoice->paid_at ?? Carbon::now(),
    ])->save();
}
```

**Cập nhật test plan:** Không cần — TC-3.6 và TC-3.7 đã cover.

---

### 3. Không thể hủy QR online trong 15 phút đầu

**Tình trạng:** ĐÚNG — `canCancel()` ở `CongNoPayment.php` (dòng 189–191) chặn hủy khi `DA_GUI_YEU_CAU_TT` và chưa `canRegenerateQr()`.

```php
// CongNoPayment.php:189
if ($status === InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT && ! $this->canRegenerateQr()) {
    return false;
}
```

**Phân tích:**
- Logic hiện tại: hủy bị ràng buộc với throttle regenerate QR.
- Nghiệp vụ thực tế: hủy hóa đơn đang chờ thanh toán nên được phép bất kể throttle — vì hủy là hủy, không phải tạo lại.
- Throttle chỉ nên áp dụng cho hành động **tạo lại QR**, không phải hủy.

**Hành động:** Sửa `canCancel()` — chỉ chặn khi `DA_THANH_TOAN` hoặc `HUY`.

```php
public function canCancel(?User $user): bool
{
    if (! $user) {
        return false;
    }

    // Không cho hủy khi đã thanh toán hoặc đã hủy rồi
    if ($this->status === InvoicePaymentStatusEnum::DA_THANH_TOAN
        || $this->status === InvoicePaymentStatusEnum::HUY) {
        return false;
    }

    return $this->isCreator($user) || $this->hasStaffPower($user);
}
```

**Cập nhật test plan:** Không cần — TC-3.3 nói "hủy khi `DA_THANH_TOAN` → bị chặn", không nói gì về throttle.

---

## Mức độ trung bình

### 4. Sau khi hủy, trang payment không hiển thị lý do hủy

**Tình trạng:** ĐÚNG — `getActiveInvoice()` loại `HUY`, nên sau hủy `$this->invoice = null`, section hiển thị "chưa có hóa đơn".

**Phân tích UX:**
- Trang payment chỉ hiển thị invoice **active** (không HUY).
- Component `payment-invoices.blade.php` trên trang chi tiết hiển thị **tất cả** hóa đơn (bao gồm HUY) — nên lý do hủy đã hiển thị đúng ở trang chi tiết.
- Trang payment: sau khi hủy, nên hiển thị **invoice đã hủy gần nhất** kèm lý do, đồng thời cho tạo invoice mới.

**Hành động:** Sửa `loadInvoice()` để load invoice gần nhất (kể cả HUY) thay vì `getActiveInvoice()`.

```php
protected function loadInvoice(): void
{
    // Lấy invoice gần nhất (không loại HUY) để hiển thị lý do hủy/từ chối
    $this->invoice = $this->order->congNoPayments()->latest('id')->first();
}
```

Lúc này:
- Nếu invoice là `HUY` → hiển thị trạng thái "Đã hủy" + lý do hủy, hiện nút "Tạo hóa đơn mới".
- Nếu không có invoice → hiển thị "Chưa có hóa đơn" + nút "Tạo hóa đơn".
- Nếu invoice active → hiển thị bình thường với các action tương ứng.

**Cập nhật test plan:** Thêm checkpoint: TC-3.3 sau khi hủy → trang payment hiển thị lý do hủy.

---

### 5. Gate route danh sách hóa đơn không cho sale truy cập

**Tình trạng:** ĐÚNG — `Gate::invoice.index` chỉ cho `admin`, `ketoan`, `manager`. Sale không vào được `/hoa-don-thu`.

**Phân tích:**
- Test plan bước 3.4, 3.5 yêu cầu sale thao tác từ `/invoices` → cần vào danh sách.
- Nhưng test plan bước 3.4 cũng mô tả "Truy cập `/invoices` → tìm hóa đơn → Chi tiết → Xác nhận" — đây là hành động của kế toán/admin, không phải sale.
- Sale chỉ cần thao tác trên **trang payment** của đơn hàng — đã có đủ action (tạo, gửi tiền mặt, QR, hủy).

**Hành động:**
- **Chốt nghiệp vụ:** Sale có cần vào `/hoa-don-thu` không?
  - Nếu KHÔNG → giữ gate như hiện, test plan TC-3.4 bước "Đăng nhập sale → truy cập `/hoa-don-thu`" là sai.
  - Nếu CÓ → thêm `sale` vào gate và cập nhật query filter theo sale.
- Hiện tại: sale thao tác tạo hóa đơn, gửi tiền mặt, QR trên trang payment — đủ cho nghiệp vụ. Việc xác nhận/từ chối thanh toán là của kế toán/admin.

**Đề xuất:** Giữ gate như hiện, sửa test plan TC-3.4 — bước xác nhận/thanh toán từ danh sách chỉ dành cho `admin | ketoan`.

---

### 6. Tạo lại QR không sinh payment code mới

**Tình trạng:** ĐÚNG — cả `InvoiceDataTableController::fillQrPayment()` (dòng 748–750) và `regenerateOrderQr()` (dòng 925–927) đều ưu tiên reuse code cũ:

```php
$code = $invoice->payment_reference
    ?: $invoice->qr_payment_code
    ?: app(InvoiceCodeGenerator::class)->generatePaymentCode(...);
```

**Phân tích:**
- Việc giữ cùng mã thanh toán giúp đối soát dễ hơn khi khách chuyển nhầm/sai.
- Tuy nhiên, khi tạo lại QR (sau 15 phút), nếu mã cũ đã bị hết hạn hoặc không còn valid trên cổng thanh toán, việc dùng lại mã có thể gây lỗi.
- Webhook matching tìm theo `payment_reference` hoặc `qr_payment_code` — nếu giữ cùng mã, webhook vẫn match đúng.

**Hành động:** **Chốt nghiệp vụ:**
- Nếu giữ cùng mã → không cần sửa, test plan TC-3.6 cần sửa mô tả.
- Nếu bắt buộc mã mới → cần tạo mã mới mỗi lần regenerate.

**Đề xuất:** Giữ nguyên (reuse code) vì:
1. Thuận tiện đối soát khi khách chuyển nhầm.
2. Webhook vẫn match đúng qua mã cũ.
3. Mã mới chỉ cần khi mã cũ không hợp lệ trên cổng thanh toán.

---

## Lệch giữa test plan và code

### 7. URL danh sách hóa đơn

**Tình trạng:** ĐÚNG — route hiện là `/hoa-don-thu`, test plan ghi `/invoices`.

**Hành động:** Sửa test plan TC-3.4, TC-3.5, TC-3.7, TC-3.8, TC-3.9 → `/hoa-don-thu`.

---

### 8. Format mã hóa đơn

**Tình trạng:** ĐÚNG — code dùng `HD-TH-YYYYMMDD-XXXX` theo `InvoiceTypeEnum::THU`, test plan ghi `INV-YYYYMMDD-XXXX`.

**Phân tích:**
- `InvoiceCodeGenerator` sinh theo prefix từ `InvoiceTypeEnum`.
- `InvoiceTypeEnum::THU->codePrefix()` trả `HD-TH`.
- `InvoiceTypeEnum::CHI->codePrefix()` trả `HD-CHI`.
- `INV` không phải format hiện tại.

**Hành động:** Sửa test plan → `HD-TH-YYYYMMDD-XXXX`. Không đổi code.

---

### 9. Nút "Lưu cước bán" vs "Lưu giá"

**Tình trạng:** ĐÚNG — UI hiện là "Lưu giá".

**Hành động:** Sửa test plan TC-3.2 → "Lưu giá".

---

## Các điểm nên kiểm tra thêm

### 10. Nút lưu vẫn hiển thị khi cước bán bị khóa

**Tình trạng:** Cần xác minh thực tế trên UI.

**Phân tích:**
- `canEditSaleCharge()` trả `false` khi `isInvoiceLocked()`. Trên UI, form có `readonly: !canEditSaleCharge()`.
- Tuy nhiên, "Lưu giá" có thể vẫn hiển thị vì trang payment cho lưu `cuocvon`/`cuocgoc` theo quyền riêng.
- Guard backend có ở dòng 214: `if ($group === 'cuocban' && ! $this->canEditSaleCharge())`.

**Hành động:** Cần tester xác minh — nếu "Lưu giá" submit được cuocban khi bị khóa → cần thêm guard ở backend (kiểm tra `canEditSaleCharge()` ở action save chính).

---

### 11. `Order::hasActiveInvoice()` định nghĩa chưa đồng nhất

**Tình trạng:** ĐÚNG — có sự lệch giữa các method.

**Phân tích:**

| Method | Loại `HUY`? | Loại `DA_THANH_TOAN`? |
|---|---|---|
| `hasActiveInvoice()` | ✅ loại | ✅ loại |
| `getActiveInvoice()` | ✅ loại | ❌ không loại |
| `isInvoiceLocked()` | ✅ loại | ❌ không loại |

**Vấn đề:**
- `isInvoiceLocked()` trả `true` kể cả khi invoice đã thanh toán → cước bán vẫn bị khóa sau khi thanh toán.
- Nghiệp vụ: sau khi thanh toán thành công, đơn hàng đã hoàn tất → cước bán không cần sửa nữa → **đúng** khi vẫn khóa.
- Nhưng `hasActiveInvoice()` cho rằng `DA_THANH_TOAN` không phải active → lẫn lộn khái niệm.

**Hành động:** Đổi tên / làm rõ:
- `isInvoiceLocked()`: khóa cước bán — chỉ loại `HUY` (đúng hiện tại, giữ nguyên).
- `hasActiveInvoice()`: có invoice đang xử lý (chưa final) — đúng hiện tại, giữ nguyên.
- `getActiveInvoice()`: lấy invoice mới nhất kể cả HUY/DA_THANH_TOAN — đề xuất đổi tên thành `getLatestInvoice()` hoặc sửa để trả về invoice gần nhất không loại gì cả.

**Cập nhật test plan:** Không cần — hành vi hiện tại phù hợp nghiệp vụ.

---

### 12. Logic thanh toán trên trang payment và controller bị trùng

**Tình trạng:** ĐÚNG — có logic trùng giữa Livewire page và `InvoiceDataTableController`.

**Phân tích các điểm trùng:**

| Hành động | Livewire (payment.blade.php) | Controller |
|---|---|---|
| Submit cash | `submitOrderCashPayment()` | `submitCashPayment()` |
| Submit online | `submitOrderOnlinePayment()` | `submitOnlinePayment()` |
| Regenerate QR | `regenerateOrderQr()` | `regenerateQr()` |
| Confirm cash | `confirmOrderCash()` | `confirmCashPayment()` |
| Reject payment | `submitRejectPayment()` | `rejectCashPayment()` |
| Cancel | `submitCancelInvoice()` | `cancel()` |
| Reset channel | — | `resetPaymentChannel()` |
| Admin mark paid | `submitMarkPaid()` | `markPaidByAdmin()` |

**Rủi ro:** 2 nơi sửa → có thể lệch logic. Đặc biệt `regenerateQr()` trùng code dùng lại payment code (điểm 6).

**Hành động:** Dồn logic vào service để tái sử dụng:
- `OrderInvoiceService` hiện có: `createForOrder`, `cancelInvoice`, `markPaid`, `syncOrderPaymentStatus`.
- Cần bổ sung: `submitCashPayment`, `submitOnlinePayment`, `regenerateQr`, `approveCash`, `rejectPayment`, `resetChannel`.
- Livewire và Controller gọi service thay vì viết inline.

**Ưu tiên:** Thấp — chỉ ảnh hưởng đến maintainability, không ảnh hưởng nghiệp vụ. Có thể refactor sau khi feature ổn định.

---

## Tổng hợp hành động

| # | Điểm | Mức | Hành động | Thực hiện |
|---|---|---|---|---|
| 1 | Chặn tạo invoice cho đơn không vãng lai | Cao | Thêm guard `isWalkIn()` ở service | Cần sửa |
| 2 | Webhook chưa sync order đơn lẻ | Cao | Thêm `hasDirectOrder()` branch trong `PaymentInvoiceMatcher` | Cần sửa |
| 3 | Không hủy được QR trong 15 phút | Cao | Sửa `canCancel()` — bỏ throttle khỏi điều kiện hủy | Cần sửa |
| 4 | Trang payment không hiển thị lý do hủy | Trung bình | Đổi `loadInvoice()` → lấy invoice gần nhất kể cả HUY | Cần sửa |
| 5 | Sale không vào `/hoa-don-thu` | Trung bình | Chốt nghiệp vụ: sale chỉ thao tác trên trang payment | Chốt với nghiệp vụ |
| 6 | Regenerate QR reuse code | Trung bình | Giữ nguyên (reuse code), sửa test plan | Sửa test plan |
| 7 | URL trong test plan | Thấp | Sửa test plan: `/invoices` → `/hoa-don-thu` | Sửa test plan |
| 8 | Format mã hóa đơn | Thấp | Sửa test plan: `INV-` → `HD-TH-` | Sửa test plan |
| 9 | Label nút "Lưu giá" | Thấp | Sửa test plan: "Lưu cước bán" → "Lưu giá" | Sửa test plan |
| 10 | Nút lưu vẫn hiển thị khi khóa | Thấp | Xác minh thực tế, thêm guard backend nếu cần | Cần tester check |
| 11 | Định nghĩa active/locked chưa đồng nhất | Thấp | Giữ nguyên, đổi tên `getActiveInvoice()` → `getLatestInvoice()` | Tùy chọn |
| 12 | Logic trùng giữa page và controller | Thấp | Dồn vào service — ưu tiên sau khi feature ổn | Ưu tiên thấp |

---

## Thứ tự ưu tiên sửa

1. **Fix ngay** — Ảnh hưởng nghiệp vụ:
   - [ ] 1. Thêm guard `isWalkIn()` ở `OrderInvoiceService`
   - [ ] 2. Webhook sync order đơn lẻ trong `PaymentInvoiceMatcher`
   - [ ] 3. Sửa `canCancel()` — bỏ throttle

2. **Sửa UX**:
   - [ ] 4. `loadInvoice()` lấy invoice gần nhất

3. **Sửa test plan cho khớp code**:
   - [ ] 5. URL: `/invoices` → `/hoa-don-thu`
   - [ ] 6. Format mã: `INV-` → `HD-TH-`
   - [ ] 7. Label: "Lưu cước bán" → "Lưu giá"
   - [ ] 8. TC-3.6 mô tả regenerate: ghi rõ "dùng lại payment code cũ"

4. **Xác minh**:
   - [ ] 9. Tester check TC-10: nút lưu có submit được khi khóa không
