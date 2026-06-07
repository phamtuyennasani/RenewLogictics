<?php

namespace App\Services\Invoice;

use App\Models\CongNoPayment;
use App\Models\User;
use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\DebtStatusEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoicePaymentSyncService
{
    /**
     * Mark invoice as paid and sync related entities.
     *
     * This method consolidates the duplicate "mark paid + sync" logic that was
     * previously scattered across multiple places:
     * - InvoiceDataTableController::confirmCashPayment()
     * - InvoiceDataTableController::markPaidByAdmin()
     * - PaymentInvoiceMatcher::matchWebhookPayment()
     *
     * @param CongNoPayment $invoice Invoice to mark as paid
     * @param User|null $actor User confirming payment (null for system/webhook)
     * @param Carbon|null $paidAt Payment timestamp (null = now)
     * @param array $metadata Additional log metadata
     * @param array $additionalFields Additional fields to update on invoice (for webhook data)
     * @return void
     * @throws \Throwable
     */
    public function markPaidAndSync(
        CongNoPayment $invoice,
        ?User $actor = null,
        ?Carbon $paidAt = null,
        array $metadata = [],
        array $additionalFields = []
    ): void {
        DB::transaction(function () use ($invoice, $actor, $paidAt, $metadata, $additionalFields) {
            // Lock for update to prevent race conditions
            $locked = CongNoPayment::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Store original status for audit log
            $fromStatus = $locked->status;
            $paidAt ??= now();

            // Base fields to update
            $fields = [
                'status' => InvoicePaymentStatusEnum::DA_THANH_TOAN->value,
                'paid_at' => $paidAt,
                'id_ketoan' => $actor?->id,
                'payment_confirmed_by' => $actor?->id,
            ];

            // Merge additional fields (for webhook data like provider info)
            $fields = array_merge($fields, $additionalFields);

            // Update invoice to paid status
            $locked->forceFill($fields)->save();

            // Write audit log - use action from metadata or default
            $action = $metadata['action'] ?? 'payment_confirmed';
            $locked->writeStatusLog(
                $action,
                $fromStatus,
                InvoicePaymentStatusEnum::DA_THANH_TOAN,
                $actor?->id,
                null,  // note
                $metadata
            );

            // Sync related entities (CongNo and Order)
            $this->syncRelatedEntities($locked);
        });
    }

    /**
     * Sync CongNo and Order entities after payment confirmation.
     *
     * Updates:
     * - CongNo.paid_amount via syncPaidAmountFromPayments()
     * - Order.customer_payment_status directly
     *
     * @param CongNoPayment $invoice
     * @return void
     */
    protected function syncRelatedEntities(CongNoPayment $invoice): void
    {
        // Case 1: Invoice has direct order relationship (khách lẻ)
        if ($invoice->hasDirectOrder()) {
            $order = $invoice->order;

            if ($order) {
                // Update order payment status to paid
                $order->forceFill([
                    'customer_payment_status' => DebtStatusEnum::DA_THANH_TOAN->value,
                    'customer_paid_at' => now(),
                ])->save();

                // Sync CongNo if order has one
                if ($order->congNo) {
                    $order->congNo->syncPaidAmountFromPayments();
                }
            }
        }
        // Case 2: Invoice has CongNo relationship (công nợ)
        elseif ($invoice->id_congno) {
            $congNo = $invoice->congNo;

            if ($congNo) {
                // Sync CongNo paid amount first
                $congNo->syncPaidAmountFromPayments();
                $congNo->refresh();

                // Determine order status based on CongNo status
                $orderStatus = $congNo->status === DebtStatusEnum::DA_THANH_TOAN
                    ? DebtStatusEnum::DA_THANH_TOAN->value
                    : DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN->value;

                // Update all associated orders
                $congNo->orders()->update([
                    'customer_payment_status' => $orderStatus,
                    'customer_paid_at' => $orderStatus === DebtStatusEnum::DA_THANH_TOAN->value ? now() : null,
                ]);
            }
        }
    }

    /**
     * Check if invoice can be marked as paid.
     *
     * @param CongNoPayment $invoice
     * @return bool
     */
    public function canMarkPaid(CongNoPayment $invoice): bool
    {
        return $invoice->status?->isOpen() ?? false;
    }

    /**
     * Get validation errors if invoice cannot be marked paid.
     *
     * @param CongNoPayment $invoice
     * @return array<string> Array of error messages
     */
    public function getMarkPaidErrors(CongNoPayment $invoice): array
    {
        $errors = [];

        if ($invoice->status?->isFinal()) {
            $errors[] = "Hóa đơn đã ở trạng thái cuối: {$invoice->status->label()}";
        }

        if ($invoice->status?->isCancelled()) {
            $errors[] = "Không thể thanh toán hóa đơn đã hủy";
        }

        if ($invoice->amount <= 0) {
            $errors[] = "Số tiền hóa đơn phải lớn hơn 0";
        }

        return $errors;
    }

    /**
     * Validate and throw exception if cannot mark paid.
     *
     * @param CongNoPayment $invoice
     * @return void
     * @throws \RuntimeException
     */
    public function validateCanMarkPaid(CongNoPayment $invoice): void
    {
        $errors = $this->getMarkPaidErrors($invoice);

        if (! empty($errors)) {
            throw new \RuntimeException(
                'Không thể đánh dấu đã thanh toán: ' . implode(', ', $errors)
            );
        }
    }
}
