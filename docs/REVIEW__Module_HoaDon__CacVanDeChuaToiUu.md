# Review Module Hóa Đơn - Các Vấn Đề Chưa Tối Ưu

> **Ngày review:** 2026-06-07
> **Phạm vi:** Models, Controllers, Services, Enums liên quan đến hóa đơn (CongNoPayment, OrderInvoiceService, PaymentInvoiceMatcher, InvoiceDataTableController)

---

## Tổng Quan Kiến Trúc

Module hóa đơn gồm 3 hệ thống con:
- **Invoice (Hàng hóa đơn hàng)** - thông tin hàng hóa trên đơn hàng
- **CongNo (Công nợ)** - quản lý nợ, đối soát, thanh toán CTV/đại lý
- **EInvoice (Hóa đơn điện tử)** - phát hành HĐĐT qua provider (SePay)

---

## Điểm Mạnh

1. **Workflow rõ ràng** - Enum `InvoicePaymentStatusEnum` đầy đủ helper methods (`isFinal`, `isOpen`, `isPaid`)
2. **Audit trail tốt** - `writeStatusLog()` ghi lại mọi chuyển trạng thái với actor, metadata
3. **Concurrency handling** - Sử dụng `lockForUpdate()` đúng chỗ trong các transaction quan trọng
4. **Authorization granular** - Các method `canApprove()`, `canPay()`, `canCancel()` tách biệt rõ ràng
5. **Multi-provider payment** - `PaymentProviderManager` + `PaymentInvoiceMatcher` thiết kế mở rộng tốt

---

## Vấn Đề Cần Cải Thiện

### 1. Controller Quá Lớn (Fat Controller)

**Mức độ:** Cao
**File:** `app/Http/Controllers/Invoice/InvoiceDataTableController.php`
**Dòng:** 839

**Mô tả:**
Controller vừa xử lý DataTable (query, format columns), vừa chứa toàn bộ business logic workflow (approve, pay, cancel, confirm, reject, reset, mark paid). Quá nhiều responsibility trong 1 file.

**Hướng xử lý:**
- Tách `InvoiceDataTableController` - chỉ xử lý DataTable + query
- Tạo `InvoiceWorkflowController` - các action workflow
- Hoặc tốt hơn: dùng **Action Classes** (`ApproveInvoiceAction`, `CancelInvoiceAction`, `SubmitCashPaymentAction`...) để tái sử dụng logic giữa controller và service

```php
// Ví dụ cấu trúc sau khi tách
app/
  Http/Controllers/Invoice/
    InvoiceDataTableController.php    // DataTable only
    InvoiceWorkflowController.php      // Actions only
  Actions/Invoice/
    ApproveInvoiceAction.php
    CancelInvoiceAction.php
    SubmitCashPaymentAction.php
    SubmitOnlinePaymentAction.php
    ConfirmCashPaymentAction.php
    MarkPaidAction.php
```

---

### 2. Logic Trùng Lặp (DRY Violation)

**Mức độ:** Cao
**Files:** Nhiều file

**Mô tả:**
Logic "mark paid + sync order/debt status" lặp ở **4 chỗ**:

| Vị trí | Dòng |
|--------|------|
| `InvoiceDataTableController::confirmCashPayment()` | 563-618 |
| `InvoiceDataTableController::markPaidByAdmin()` | 695-741 |
| `PaymentInvoiceMatcher::matchWebhookPayment()` | 130-171 |
| `OrderInvoiceService::markPaid()` | 101-127 |

Mỗi chỗ đều tự viết lại:
- Update `CongNoPayment.status = DA_THANH_TOAN`
- Update `paid_at`
- Gọi `syncPaidAmountFromPayments()` trên CongNo
- Update `customer_payment_status` trên Order

**Hướng xử lý:**
Tạo `InvoicePaymentSyncService` duy nhất:

```php
class InvoicePaymentSyncService
{
    public function markPaidAndSync(
        CongNoPayment $invoice,
        ?User $actor,
        ?Carbon $paidAt = null
    ): void {
        DB::transaction(function () use ($invoice, $actor, $paidAt) {
            $locked = CongNoPayment::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $fromStatus = $locked->status;
            $locked->forceFill([
                'status' => InvoicePaymentStatusEnum::DA_THANH_TOAN->value,
                'paid_at' => $paidAt ?? now(),
                'id_ketoan' => $actor?->id,
                'payment_confirmed_by' => $actor?->id,
            ])->save();

            $locked->writeStatusLog(
                'payment_confirmed',
                $fromStatus,
                InvoicePaymentStatusEnum::DA_THANH_TOAN,
                $actor?->id
            );

            $this->syncRelatedEntities($locked);
        });
    }

    protected function syncRelatedEntities(CongNoPayment $invoice): void
    {
        if ($invoice->hasDirectOrder()) {
            $order = $invoice->order;
            $order->forceFill([
                'customer_payment_status' => DebtStatusEnum::DA_THANH_TOAN->value,
                'customer_paid_at' => $invoice->paid_at,
            ])->save();
        } else {
            $debt = $invoice->congNo;
            if ($debt && method_exists($debt, 'syncPaidAmountFromPayments')) {
                $debt->syncPaidAmountFromPayments();
                $debt->refresh();

                $orderStatus = $debt->status === DebtStatusEnum::DA_THANH_TOAN
                    ? DebtStatusEnum::DA_THANH_TOAN->value
                    : DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN->value;

                $debt->orders()->update([
                    'customer_payment_status' => $orderStatus,
                    'customer_paid_at' => $orderStatus === DebtStatusEnum::DA_THANH_TOAN->value
                        ? now()
                        : null,
                ]);
            }
        }
    }
}
```

---

### 3. N+1 Query / Load Toàn Bộ Records Trong Summary

**Mức độ:** Cao
**File:** `app/Http/Controllers/Invoice/InvoiceDataTableController.php`
**Dòng:** 353-371

**Mô tả:**
```php
protected function summary(Request $request): array
{
    $items = $this->query($request, includeStatus: false)->get(); // Load ALL records!
    $activeItems = $items->filter(fn ($inv) => ! $inv->status?->isCancelled());
    $total = (float) $activeItems->sum('amount');
    // ...
}
```

Khi có hàng ngàn hóa đơn, `->get()` load tất cả vào memory chỉ để tính tổng. Đây là anti-pattern nghiêm trọng.

**Hướng xử lý:**
Dùng aggregate query:
```php
protected function summary(Request $request): array
{
    $baseQuery = $this->query($request, includeStatus: false)
        ->where('status', '!=', InvoicePaymentStatusEnum::HUY->value);

    $summary = (clone $baseQuery)
        ->selectRaw("
            SUM(amount) as total,
            SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as paid,
            SUM(CASE WHEN status IN (?, ?, ?, ?) THEN amount ELSE 0 END) as pending,
            SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as awaiting
        ", [
            InvoicePaymentStatusEnum::DA_THANH_TOAN->value,
            InvoicePaymentStatusEnum::DA_DUYET->value,
            InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT->value,
            InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT->value,
            InvoicePaymentStatusEnum::KHONG_CHAP_NHAN->value,
            InvoicePaymentStatusEnum::CHO_DUYET->value,
        ])
        ->first();

    $total = (float) ($summary->total ?? 0);
    $paid = (float) ($summary->paid ?? 0);
    $pending = (float) ($summary->pending ?? 0);
    $awaiting = (float) ($summary->awaiting ?? 0);

    return [
        'total' => $total,
        'paid' => $paid,
        'pending' => $pending,
        'awaiting' => $awaiting,
        'paid_percent' => $this->percentOf($paid, $total),
        'pending_percent' => $this->percentOf($pending, $total),
        'awaiting_percent' => $this->percentOf($awaiting, $total),
    ];
}
```

---

### 4. Nhiều Queries Trên Mỗi Request DataTable

**Mức độ:** Trung bình
**File:** `app/Http/Controllers/Invoice/InvoiceDataTableController.php`

**Mô tả:**
Mỗi request DataTable thực hiện **3 queries riêng biệt**:
1. Main data query (DataTables tự xử lý)
2. `statusCounts()` - query lại toàn bộ với groupBy status
3. `summary()` - query lại toàn bộ với aggregate

Cả 2 method đều gọi `$this->query()` xây dựng lại từ đầu với cùng filter conditions.

**Hướng xử lý:**
Kết hợp vào 1 query duy nhất sử dụng window functions hoặc subqueries:

```php
protected function queryWithAggregates(Request $request): Builder
{
    return CongNoPayment::query()
        ->with([...])
        ->select([
            'congno_payments.*',
            DB::raw("(
                SELECT COUNT(*) FROM congno_payments cp2
                WHERE cp2.loai_hoa_don = 'thu'
                AND cp2.status = congno_payments.status
            ) as status_count"),
        ])
        ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
        ->latest('id');
}
```

Hoặc đơn giản hơn: cache `statusCounts` và `summary` ở client-side và chỉ refresh khi có thay đổi.

---

### 5. Race Condition Trong InvoiceCodeGenerator

**Mức độ:** Cao
**File:** `app/Services/Payments/InvoiceCodeGenerator.php`
**Dòng:** 18-26

**Mô tả:**
```php
for ($attempt = 0; $attempt < 8; $attempt++) {
    $candidate = sprintf('%s%s%04d', $prefix, $datePart, random_int(0, 9999));
    if (! $this->codeExists($type, $candidate)) {
        return $candidate;
    }
}
```

Check-then-insert không atomic. Hai request concurrent có thể generate cùng code. Cần kiểm tra xem `ma_hoa_don` có unique constraint ở database chưa.

**Hướng xử lý:**
1. Thêm unique index trên `congno_payments.ma_hoa_don`:
```php
Schema::table('congno_payments', function (Blueprint $table) {
    $table->unique('ma_hoa_don');
});
```

2. Wrap trong transaction với retry on duplicate:
```php
public function generate(InvoiceTypeEnum $type, ?Carbon $when = null): string
{
    $when ??= Carbon::now();
    $datePart = $when->format('Ymd');
    $prefix = $type->codePrefix();

    for ($attempt = 0; $attempt < 8; $attempt++) {
        $candidate = sprintf('%s%s%04d', $prefix, $datePart, random_int(0, 9999));

        try {
            DB::transaction(function () use ($type, $candidate) {
                DB::table($this->tableName($type))
                    ->insert(['ma_hoa_don' => $candidate]);
            });
            return $candidate;
        } catch (QueryException $e) {
            if ($this->isDuplicateKeyException($e)) {
                continue;
            }
            throw $e;
        }
    }

    return sprintf('%s%s%s', $prefix, $datePart, strtoupper(Str::random(6)));
}
```

---

### 6. Regex Quá Rộng Trong extractPaymentCode()

**Mức độ:** Cao
**File:** `app/Services/Payments/PaymentInvoiceMatcher.php`
**Dòng:** 212-218

**Mô tả:**
```php
if (preg_match('/[A-Z0-9\-_]{8,64}/i', $candidate, $matches)) {
    return strtoupper($matches[0]);
}
```

Fallback regex match bất kỳ chuỗi 8-64 ký tự alphanumeric. Có thể match nhầm nội dung chuyển khoản không liên quan, dẫn đến false positive, auto mark paid sai hóa đơn. Đây là **rủi ro tài chính nghiêm trọng**.

**Hướng xử lý:**
Restrict fallback hoặc loại bỏ hoàn toàn. Chỉ match nếu content bắt đầu bằng prefix hợp lệ:
```php
protected function extractPaymentCode(array $payload): ?string
{
    $candidates = [
        $payload['code'] ?? null,
        $payload['content'] ?? null,
        $payload['description'] ?? null,
        $payload['transferContent'] ?? null,
        $payload['orderId'] ?? null,
    ];

    $validPrefixes = ['HDTH', 'HDCH', 'PAY', 'INV'];

    foreach ($candidates as $candidate) {
        if (! is_string($candidate) || trim($candidate) === '') {
            continue;
        }

        // Uu tien match chinh xac prefix
        if (preg_match('/(HDTH|HDCH|PAY|INV)[0-9A-Z]+/i', $candidate, $matches)) {
            return strtoupper($matches[0]);
        }

        // Neu ca chuoi trung voi prefix hop le
        $upper = strtoupper(trim($candidate));
        foreach ($validPrefixes as $prefix) {
            if (str_starts_with($upper, $prefix) && strlen($upper) >= 8) {
                return $upper;
            }
        }
    }

    return null;
}
```

---

### 7. Mass Assignment Quá Rộng

**Mức độ:** Trung bình
**File:** `app/Models/CongNoPayment.php`
**Dòng:** 20-58

**Mô tả:**
58 fields trong `$fillable` bao gồm cả `status`, `approved_by`, `payment_confirmed_by`, `id_ketoan`. Nếu có chỗ nào dùng `$request->all()` + `create/update` thay vì `forceFill()` thì có thể bị privilege escalation (user thường có thể tự approve hóa đơn).

**Hướng xử lý:**
Tách `$fillable` thành 2 nhóm:
```php
// Chi user-input fields
protected $fillable = [
    'id_congno',
    'id_order',
    'id_user',
    'amount',
    'due_at',
    'method',
    'reference',
    'photo',
    'note',
    'loai_hoa_don',
];

// Internal/admin-only fields - khong mass assign duoc
// Su dung forceFill() cho cac field nay
```

---

### 8. Sử Dụng Global request() Helper Trong Closure

**Mức độ:** Thấp
**File:** `app/Http/Controllers/Invoice/InvoiceDataTableController.php`
**Dòng:** 487

**Mô tả:**
```php
DB::transaction(function () use ($invoice, $providerKey, $requestType) {
    $locked = CongNoPayment::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
    if (! $locked->canPay(request()->user())) { // global helper
```

Vi phạm explicit dependency principle, khó test, không traceable.

**Hướng xử lý:**
```php
public function submitOnlinePayment(Request $request, int $id): JsonResponse
{
    $user = $request->user(); // Inject ro rang
    // ...
    DB::transaction(function () use ($invoice, $providerKey, $requestType, $user) {
        // ...
        if (! $locked->canPay($user)) {
```

---

### 9. Thiếu Validation FormRequest Cho Một Số Action

**Mức độ:** Trung bình
**File:** `app/Http/Controllers/Invoice/InvoiceDataTableController.php`

**Mô tả:**
- `submitOnlinePayment()` - không dùng FormRequest, validate inline
- `markPaidByAdmin()` - không yêu cầu `note` hay `reason` (admin mark paid không ghi lý do, khó audit)

**Hướng xử lý:**
Tạo FormRequest classes riêng:
```php
// app/Http/Requests/Invoice/
class SubmitOnlinePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string'],
            'request_type' => ['nullable', 'string'],
        ];
    }
}

class MarkPaidByAdminRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

---

### 10. Thứ Tự Sort Trong Enum Không Nhất Quán

**Mức độ:** Thấp
**File:** `app/Enums/InvoicePaymentStatusEnum.php`
**Dòng:** 54-65

**Mô tả:**
```php
public function sortOrder(): int
{
    return match ($this) {
        self::CHO_DUYET => 1,
        self::DA_DUYET => 2,
        self::DA_GUI_HOA_DON_TT => 3,
        self::KHONG_CHAP_NHAN => 3,  // Trung sort order
        self::DA_GUI_YEU_CAU_TT => 4,
        self::DA_THANH_TOAN => 5,
        self::HUY => 6,
    };
}
```

`KHONG_CHAP_NHAN` và `DA_GUI_HOA_DON_TT` cùng sort order = 3. Không gây lỗi nhưng không nhất quán về mặt ngữ nghĩa.

**Hướng xử lý:**
```php
public function sortOrder(): int
{
    return match ($this) {
        self::CHO_DUYET => 1,
        self::DA_DUYET => 2,
        self::DA_GUI_HOA_DON_TT => 3,
        self::DA_GUI_YEU_CAU_TT => 4,
        self::KHONG_CHAP_NHAN => 5,
        self::DA_THANH_TOAN => 6,
        self::HUY => 7,
    };
}
```

---

## Bảng Tổng Hợp

| # | Van de | Muc do | Effort sua |
|---|--------|--------|------------|
| 1 | Controller qua lon | Cao | Cao |
| 2 | Logic trung lap (mark paid + sync) | Cao | Trung binh |
| 3 | Summary load all records | Cao | Thap |
| 4 | Nhieu queries/request | Trung binh | Trung binh |
| 5 | Race condition code generator | Cao | Thap |
| 6 | Regex qua rong (false positive) | Cao | Thap |
| 7 | Mass assignment rong | Trung binh | Thap |
| 8 | Global request() trong closure | Thap | Thap |
| 9 | Thieu FormRequest validation | Trung binh | Thap |
| 10 | Sort order khong nhat quan | Thap | Thap |

---

## Thu Tu Uu Tien Khuyen Nghi

1. **Sua regex `extractPaymentCode()`** - Rui ro tai chinh nghiem trong nhat
2. **Them unique index + retry** cho `InvoiceCodeGenerator` - Tranh duplicate codes
3. **Extract `InvoicePaymentSyncService`** - Giam trung lap, de maintain
4. **Chuyen `summary()` sang aggregate query** - Performance cai thien ngay
5. **Tach Controller** - Neu co thoi gian, cai thien maintainability lau dai
