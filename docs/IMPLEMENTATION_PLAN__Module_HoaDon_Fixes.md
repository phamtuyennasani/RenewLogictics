# Implementation Plan - Module Hóa Đơn Fixes

> **Ngày tạo:** 2026-06-07  
> **Nguồn:** VERIFICATION_REPORT__Module_HoaDon__2026-06-07.md  
> **Trạng thái:** Planning Phase

---

## Tổng Quan

Dựa trên verification report, có **5 vấn đề confirmed** cần fix. Plan này chia thành 4 phases với 4 PRs riêng biệt để dễ review.

**Ước lượng tổng thời gian:** 2-3 ngày  
**Thứ tự thực hiện:** Priority-driven (quick wins trước, complex refactor sau)

---

## Phase 1: Quick Wins (1-2 giờ)

### PR #1: Fix Sort Order Duplicate ✅ IN PROGRESS

**Branch:** `fix/invoice-sort-order-duplicate`  
**File:** `app/Enums/InvoicePaymentStatusEnum.php`  
**Priority:** Low impact but quick win  
**Effort:** 5 phút  
**Risk:** Very Low

#### Changes Required

```php
// Line 54-65
public function sortOrder(): int
{
    return match ($this) {
        self::CHO_DUYET => 1,
        self::DA_DUYET => 2,
        self::DA_GUI_HOA_DON_TT => 3,
        self::DA_GUI_YEU_CAU_TT => 4,
        self::KHONG_CHAP_NHAN => 5,      // Changed: 3 → 5
        self::DA_THANH_TOAN => 6,         // Changed: 5 → 6
        self::HUY => 7,                    // Changed: 6 → 7
    };
}
```

#### Testing Checklist

- [ ] Run unit tests (if exists)
- [ ] Manual check: Invoice list sorting
- [ ] Verify status filter dropdown order

#### Success Criteria

✅ All enum values have unique sort order  
✅ No breaking changes to existing functionality  
✅ Tests pass

---

### PR #2: Fix Summary Performance Issue

**Branch:** `perf/invoice-summary-aggregate`  
**File:** `app/Http/Controllers/Invoice/InvoiceDataTableController.php`  
**Priority:** High (Performance)  
**Effort:** 30-45 phút  
**Risk:** Low (query logic change)

#### Current Code (Line 353-370)

```php
protected function summary(Request $request): array
{
    $items = $this->query($request, includeStatus: false)->get();  // ❌ Load ALL
    $activeItems = $items->filter(fn ($inv) => ! $inv->status?->isCancelled());
    $total = (float) $activeItems->sum('amount');
    $paid = (float) $activeItems->where('status', InvoicePaymentStatusEnum::DA_THANH_TOAN->value)->sum('amount');
    $pending = (float) $activeItems->filter(fn ($inv) => $inv->status?->isOpen())->sum('amount');
    $awaiting = (float) $activeItems->where('status', InvoicePaymentStatusEnum::CHO_DUYET->value)->sum('amount');
    
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

#### Proposed Solution

```php
protected function summary(Request $request): array
{
    // Base query without status filter
    $baseQuery = $this->query($request, includeStatus: false);
    
    // Clone query for each aggregate to avoid mutation
    $total = (float) (clone $baseQuery)
        ->whereNotIn('status', [InvoicePaymentStatusEnum::HUY->value])
        ->sum('amount');
    
    $paid = (float) (clone $baseQuery)
        ->where('status', InvoicePaymentStatusEnum::DA_THANH_TOAN->value)
        ->sum('amount');
    
    $awaiting = (float) (clone $baseQuery)
        ->where('status', InvoicePaymentStatusEnum::CHO_DUYET->value)
        ->sum('amount');
    
    // Pending = all open statuses
    $pending = (float) (clone $baseQuery)
        ->whereIn('status', InvoicePaymentStatusEnum::pendingValues())
        ->whereNotIn('status', [
            InvoicePaymentStatusEnum::CHO_DUYET->value,
            InvoicePaymentStatusEnum::DA_THANH_TOAN->value,
            InvoicePaymentStatusEnum::HUY->value,
        ])
        ->sum('amount');
    
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

#### Performance Impact

| Metric | Before (get()->sum) | After (DB aggregate) | Improvement |
|--------|---------------------|----------------------|-------------|
| Memory (10k records) | ~10 MB | ~0.1 MB | **99% reduction** |
| Query time | ~500ms | ~50ms | **10x faster** |
| DB queries | 1 SELECT * | 4 SELECT SUM | More efficient |

#### Testing Checklist

- [ ] Compare results before/after with same dataset
- [ ] Test with empty dataset
- [ ] Test with various filters (date range, customer, status)
- [ ] Load test với 10,000+ records
- [ ] Verify percentages calculation correctness

#### Success Criteria

✅ Same numerical results as before  
✅ Response time < 100ms for 10k records  
✅ Memory usage reduced by >90%  
✅ All existing tests pass

---

## Phase 2: Medium Refactoring (4-6 giờ)

### PR #3: Extract InvoicePaymentSyncService

**Branch:** `refactor/extract-invoice-payment-sync-service`  
**Priority:** High (Code Quality + Maintainability)  
**Effort:** 4 giờ  
**Risk:** Medium (logic consolidation)

#### Problem

Mark paid logic duplicated in 3 places:
1. `InvoiceDataTableController::confirmCashPayment()` (line 563-618)
2. `InvoiceDataTableController::markPaidByAdmin()` (line 695-741)
3. `PaymentInvoiceMatcher::matchWebhookPayment()` (line 65-195)

Each implements:
- Update status → `DA_THANH_TOAN`
- Set `paid_at` timestamp
- Update `id_ketoan` / `payment_confirmed_by`
- Write status log
- Sync `CongNo.paid_amount` via `syncPaidAmountFromPayments()`
- Update `Order.customer_payment_status`

#### Solution Architecture

```
app/Services/Invoice/
  ├── InvoicePaymentSyncService.php  (NEW)
  └── OrderInvoiceService.php        (existing)
```

#### New Service Contract

```php
<?php

namespace App\Services\Invoice;

use App\Models\CongNoPayment;
use App\Models\User;
use App\Enums\InvoicePaymentStatusEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoicePaymentSyncService
{
    /**
     * Mark invoice as paid and sync related entities.
     * 
     * @param CongNoPayment $invoice
     * @param User|null $actor User confirming payment (null for system/webhook)
     * @param Carbon|null $paidAt Payment timestamp (null = now)
     * @param array $metadata Additional log metadata
     * @return void
     * @throws \Throwable
     */
    public function markPaidAndSync(
        CongNoPayment $invoice,
        ?User $actor = null,
        ?Carbon $paidAt = null,
        array $metadata = []
    ): void {
        DB::transaction(function () use ($invoice, $actor, $paidAt, $metadata) {
            // Lock for update to prevent race conditions
            $locked = CongNoPayment::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            $fromStatus = $locked->status;
            $paidAt ??= now();

            // Update invoice status
            $locked->forceFill([
                'status' => InvoicePaymentStatusEnum::DA_THANH_TOAN->value,
                'paid_at' => $paidAt,
                'id_ketoan' => $actor?->id,
                'payment_confirmed_by' => $actor?->id,
            ])->save();

            // Write audit log
            $locked->writeStatusLog(
                'payment_confirmed',
                $fromStatus,
                InvoicePaymentStatusEnum::DA_THANH_TOAN,
                $actor?->id,
                $metadata
            );

            // Sync related entities
            $this->syncRelatedEntities($locked);
        });
    }

    /**
     * Sync CongNo and Order entities after payment confirmation.
     */
    protected function syncRelatedEntities(CongNoPayment $invoice): void
    {
        // Sync CongNo total paid amount
        if ($invoice->hasDirectOrder()) {
            $order = $invoice->order;
            $order->congNo?->syncPaidAmountFromPayments();
            
            // Update order payment status
            $order->updateCustomerPaymentStatus();
        } elseif ($invoice->hasCongNo()) {
            $invoice->congNo->syncPaidAmountFromPayments();
            
            // Update associated order if exists
            $invoice->congNo->order?->updateCustomerPaymentStatus();
        }
    }

    /**
     * Check if invoice can be marked as paid.
     */
    public function canMarkPaid(CongNoPayment $invoice): bool
    {
        return $invoice->status?->isOpen() ?? false;
    }

    /**
     * Get validation errors if invoice cannot be paid.
     */
    public function getMarkPaidErrors(CongNoPayment $invoice): array
    {
        $errors = [];

        if ($invoice->status?->isFinal()) {
            $errors[] = "Invoice is already in final state: {$invoice->status->label()}";
        }

        if ($invoice->amount <= 0) {
            $errors[] = "Invoice amount must be greater than 0";
        }

        return $errors;
    }
}
```

#### Migration Strategy

**Step 1:** Create service class with tests
**Step 2:** Refactor `InvoiceDataTableController::confirmCashPayment()`
**Step 3:** Refactor `InvoiceDataTableController::markPaidByAdmin()`
**Step 4:** Refactor `PaymentInvoiceMatcher::matchWebhookPayment()`
**Step 5:** Remove old code, verify all tests pass

#### Files to Modify

1. **NEW:** `app/Services/Invoice/InvoicePaymentSyncService.php`
2. **UPDATE:** `app/Http/Controllers/Invoice/InvoiceDataTableController.php`
   - `confirmCashPayment()` method
   - `markPaidByAdmin()` method
3. **UPDATE:** `app/Services/Payments/PaymentInvoiceMatcher.php`
   - `matchWebhookPayment()` method

#### Testing Checklist

- [ ] Unit tests for `InvoicePaymentSyncService`
- [ ] Test với CHO_DUYET → DA_THANH_TOAN transition
- [ ] Test với DA_DUYET → DA_THANH_TOAN transition
- [ ] Test concurrency (multiple payments at same time)
- [ ] Test webhook payment matching flow
- [ ] Test manual confirmation flow
- [ ] Test admin mark paid flow
- [ ] Verify CongNo sync correctness
- [ ] Verify Order payment status update
- [ ] Check audit logs written correctly

#### Success Criteria

✅ All 3 callers use new service  
✅ Logic consistent across all flows  
✅ No duplicate code  
✅ All tests pass  
✅ Audit logs preserved correctly  
✅ Performance not degraded

---

### PR #4: Add FormRequest Validation

**Branch:** `refactor/add-invoice-form-requests`  
**Priority:** Medium (Code Quality)  
**Effort:** 2 giờ  
**Risk:** Low

#### Problem

Controller methods validate directly:
- Hard to test validation in isolation
- Validation rules not reusable
- Clutters controller logic

#### Solution

Create FormRequest classes:

```
app/Http/Requests/Invoice/
  ├── ApproveInvoiceRequest.php
  ├── CancelInvoiceRequest.php
  ├── ConfirmCashPaymentRequest.php
  ├── MarkPaidByAdminRequest.php
  ├── RejectInvoiceRequest.php
  ├── ResetInvoiceRequest.php
  ├── SubmitCashPaymentRequest.php
  └── SubmitOnlinePaymentRequest.php
```

#### Example: ApproveInvoiceRequest

```php
<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class ApproveInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice'); // Assume route model binding
        
        return $this->user()->can('approve', $invoice);
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.max' => 'Ghi chú không được vượt quá 500 ký tự',
        ];
    }
}
```

#### Example: SubmitCashPaymentRequest

```php
<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitCashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');
        
        return $this->user()->can('submitPayment', $invoice);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'bank_transfer', 'check'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Số tiền thanh toán là bắt buộc',
            'amount.min' => 'Số tiền phải lớn hơn 0',
            'paid_at.before_or_equal' => 'Ngày thanh toán không được ở tương lai',
            'payment_method.required' => 'Phương thức thanh toán là bắt buộc',
            'payment_method.in' => 'Phương thức thanh toán không hợp lệ',
            'attachments.max' => 'Tối đa 5 file đính kèm',
            'attachments.*.mimes' => 'File phải có định dạng: pdf, jpg, jpeg, png',
            'attachments.*.max' => 'Mỗi file không được vượt quá 5MB',
        ];
    }
}
```

#### Controller Update Example

**Before:**
```php
public function approve(Request $request, CongNoPayment $invoice)
{
    $request->validate([
        'note' => ['nullable', 'string', 'max:500'],
    ]);
    
    // ... business logic
}
```

**After:**
```php
public function approve(ApproveInvoiceRequest $request, CongNoPayment $invoice)
{
    // Validation + authorization already done
    // ... business logic only
}
```

#### Testing Checklist

- [ ] Test validation rules for each request
- [ ] Test authorization logic
- [ ] Test error messages in Vietnamese
- [ ] Integration test với controller
- [ ] Test file upload validation (size, types)

#### Success Criteria

✅ All controller actions use FormRequest  
✅ Validation testable in isolation  
✅ Authorization logic in FormRequest  
✅ Error messages user-friendly

---

## Phase 3: Long Term Refactoring (1-2 ngày) - DEFERRED

### PR #5: Refactor Fat Controller (OPTIONAL)

**Branch:** `refactor/invoice-controller-split`  
**Priority:** Low (Technical Debt)  
**Effort:** 1-2 ngày  
**Risk:** High (major refactor)

#### Problem

`InvoiceDataTableController.php` - 839 lines:
- DataTable query + formatting
- Workflow actions (approve, pay, cancel, etc.)
- Summary calculations
- Mixed responsibilities

#### Solution Options

**Option A: Split into 2 controllers**
```
InvoiceDataTableController.php   - DataTable only (query, columns, export)
InvoiceWorkflowController.php     - Actions only (approve, pay, cancel, etc.)
```

**Option B: Extract to Action Classes** (RECOMMENDED)
```
app/Actions/Invoice/
  ├── ApproveInvoiceAction.php
  ├── CancelInvoiceAction.php
  ├── ConfirmCashPaymentAction.php
  ├── MarkPaidByAdminAction.php
  ├── RejectInvoiceAction.php
  ├── ResetInvoiceAction.php
  ├── SubmitCashPaymentAction.php
  └── SubmitOnlinePaymentAction.php
```

Controller becomes thin:
```php
public function approve(ApproveInvoiceRequest $request, CongNoPayment $invoice)
{
    return app(ApproveInvoiceAction::class)->execute($invoice, $request->user(), $request->validated());
}
```

#### Decision: DEFER

**Reasons:**
1. Requires extensive testing
2. May need route changes
3. Low immediate business value
4. Should be done after PR #3 (service extraction)

**When to revisit:**
- After PRs #1-4 merged
- When adding new invoice features
- During major refactoring initiative

---

## Dependencies & Order

```
PR #1 (Sort Order)
  └─ No dependencies, can merge immediately

PR #2 (Summary Performance)
  └─ No dependencies, independent change

PR #3 (Extract Service)
  └─ DEPENDS ON: PR #4 (FormRequest) for cleaner controller code
  
PR #4 (FormRequest)
  └─ No dependencies, but better done BEFORE PR #3

PR #5 (Fat Controller)
  └─ DEPENDS ON: PR #3 + PR #4
  └─ DEFERRED to future iteration
```

**Recommended Order:**
1. PR #1 - Sort Order (5 min)
2. PR #4 - FormRequest (2 hours)
3. PR #2 - Summary Performance (45 min)
4. PR #3 - Extract Service (4 hours)
5. PR #5 - DEFER

---

## Risk Assessment

| PR | Risk Level | Mitigation |
|----|-----------|------------|
| #1 | Very Low | Simple enum value change, easy rollback |
| #2 | Low | Compare results before/after, load test |
| #3 | Medium | Gradual migration, extensive testing, feature flags |
| #4 | Low | Validation only, no business logic change |
| #5 | High | Defer until after other PRs stabilize |

---

## Rollback Plans

### PR #1 - Sort Order
**Rollback:** Revert commit, re-deploy  
**Time:** < 5 minutes  
**Impact:** None

### PR #2 - Summary Performance
**Rollback:** Revert to `get()->sum()` pattern  
**Time:** < 10 minutes  
**Impact:** Performance degradation back to original

### PR #3 - Extract Service
**Rollback:** Revert service calls back to inline logic  
**Time:** 30 minutes  
**Impact:** Code duplication returns

### PR #4 - FormRequest
**Rollback:** Remove FormRequest, inline validation  
**Time:** 20 minutes  
**Impact:** Loss of validation abstraction

---

## Success Metrics

### Performance
- [ ] Summary endpoint response time < 100ms (currently ~500ms)
- [ ] Memory usage reduced by 90% for summary
- [ ] No performance regression in other endpoints

### Code Quality
- [ ] Code duplication reduced from 3 instances to 1 service
- [ ] Controller line count reduced by 30%
- [ ] Test coverage > 80% for new service

### Stability
- [ ] Zero production incidents post-deployment
- [ ] All existing tests pass
- [ ] No increase in error rate

---

## Timeline

| Week | Tasks | Deliverables |
|------|-------|--------------|
| **Week 1 - Day 1** | PR #1 + PR #4 | Sort order fixed, FormRequests created |
| **Week 1 - Day 2** | PR #2 + PR #3 start | Summary optimized, Service design |
| **Week 1 - Day 3** | PR #3 complete | Service extracted, tests written |
| **Week 1 - Day 4** | Testing + Review | All PRs in review |
| **Week 1 - Day 5** | Merge + Deploy | Production deployment |

---

## Notes

- **FALSE POSITIVES** từ review không cần fix:
  - ✅ #1 Regex Payment Code - Method không tồn tại
  - ✅ #2 Race Condition - Đã có retry mechanism
  - ✅ #5 N+1 Queries - Đã có eager loading
  - ✅ #7 Mass Assignment - Đang dùng $fillable

- **Vấn đề #8** (Global request()) - Minor issue, không ưu tiên

- Tất cả PRs phải pass CI/CD checks trước khi merge

- Mỗi PR cần ít nhất 1 reviewer approval

- Deploy từng PR riêng lẻ để dễ monitor

---

## Approval

- [ ] Technical Lead Review
- [ ] QA Sign-off
- [ ] Product Owner Approval
- [ ] Deploy to Staging
- [ ] Deploy to Production

