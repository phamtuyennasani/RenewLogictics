<?php

namespace App\Services\Payments;

use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Models\CongNo;
use App\Models\CongNoPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentInvoiceMatcher
{
    /**
     * Try to match a SePay webhook payload against an open income invoice.
     */
    public function matchCustomerDebtPayment(array $payload): ?CongNoPayment
    {
        if (($payload['transferType'] ?? null) !== 'in') {
            return null;
        }

        $code = $this->extractPaymentCode($payload);
        if (! $code) {
            return null;
        }

        $amount = (int) ($payload['transferAmount'] ?? 0);
        $transactionId = (string) ($payload['id'] ?? '');

        if ($amount <= 0) {
            Log::warning('SePay matcher: invalid or missing amount', [
                'code' => $code,
                'amount' => $payload['transferAmount'] ?? null,
            ]);

            return null;
        }

        try {
            return DB::transaction(function () use ($code, $amount, $transactionId, $payload) {
                $invoice = CongNoPayment::query()
                    ->where('qr_payment_code', $code)
                    ->where('status', InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT->value)
                    ->lockForUpdate()
                    ->first();

                if (! $invoice) {
                    Log::info('SePay matcher: no open invoice for code', ['code' => $code]);

                    return null;
                }

                $expected = (int) round((float) $invoice->amount);

                if ($expected > 0 && abs($amount - $expected) > 1) {
                    Log::warning('SePay matcher: amount mismatch', [
                        'code' => $code,
                        'expected' => $expected,
                        'received' => $amount,
                    ]);

                    return null;
                }

                $invoice->status = InvoicePaymentStatusEnum::DA_THANH_TOAN;
                $invoice->paid_at = Carbon::now();
                $invoice->method = 'bank_transfer';
                $invoice->sepay_transaction_id = $transactionId ?: $invoice->sepay_transaction_id;
                $invoice->reference = $payload['referenceCode'] ?? $invoice->reference;
                $invoice->save();

                /** @var CongNo $debt */
                $debt = $invoice->congNo()->lockForUpdate()->first();
                if ($debt && method_exists($debt, 'syncPaidAmountFromPayments')) {
                    $debt->syncPaidAmountFromPayments();
                    $debt->refresh();

                    $orderStatus = (float) $debt->remaining_amount <= 0
                        ? DebtStatusEnum::DA_THANH_TOAN->value
                        : DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN->value;

                    $debt->orders()->update([
                        'customer_payment_status' => $orderStatus,
                        'customer_paid_at' => $orderStatus === DebtStatusEnum::DA_THANH_TOAN->value ? Carbon::now() : null,
                    ]);
                }

                Log::info('SePay matcher: invoice marked paid', [
                    'invoice_id' => $invoice->id,
                    'code' => $code,
                    'amount' => $amount,
                ]);

                return $invoice;
            });
        } catch (Throwable $exception) {
            Log::error('SePay matcher failed', [
                'message' => $exception->getMessage(),
                'code' => $code,
            ]);

            return null;
        }
    }

    protected function extractPaymentCode(array $payload): ?string
    {
        $candidates = [
            $payload['code'] ?? null,
            $payload['content'] ?? null,
            $payload['description'] ?? null,
            $payload['transferContent'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            if (preg_match('/HDTH[0-9A-Z]+/i', $candidate, $matches)) {
                return strtoupper($matches[0]);
            }

            if (preg_match('/[A-Z0-9]{8,32}/i', $candidate, $matches)) {
                return strtoupper($matches[0]);
            }
        }

        return null;
    }
}
