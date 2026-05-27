<?php

namespace App\Services\Payments;

use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Models\CongNo;
use App\Models\CongNoPayment;
use App\Models\MoMoWebhookLog;
use App\Models\VNPayWebhookLog;
use App\Models\SepayWebhookLog;
use App\Services\Payments\Data\PaymentWebhookData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentInvoiceMatcher
{
    public function matchCustomerDebtPayment(array $payload, ?SepayWebhookLog $webhookLog = null): ?CongNoPayment
    {
        $webhook = new PaymentWebhookData(
            provider: 'sepay',
            reference: $this->extractPaymentCode($payload),
            amount: (int) ($payload['transferAmount'] ?? 0),
            status: ($payload['transferType'] ?? null) === 'in' ? 'paid' : 'ignored',
            providerTransactionId: (string) ($payload['id'] ?? ''),
            paidAt: Carbon::now(),
            raw: $payload,
            message: $payload['referenceCode'] ?? null,
        );

        return $this->matchWebhookPayment($webhook, $webhookLog);
    }

    public function matchWebhookPayment(PaymentWebhookData $webhook, SepayWebhookLog|MoMoWebhookLog|VNPayWebhookLog|null $webhookLog = null): ?CongNoPayment
    {
        if (! $webhook->isPaid()) {
            $this->markWebhook($webhookLog, 'ignored', 'Webhook status is not paid.');
            return null;
        }

        $code = $webhook->reference;
        if (! $code) {
            $this->markWebhook($webhookLog, 'no_code', 'No payment reference found in webhook payload.');
            return null;
        }

        $amount = $webhook->amount;
        $transactionId = (string) ($webhook->providerTransactionId ?? '');

        if ($amount <= 0) {
            Log::warning('Payment matcher: invalid or missing amount', [
                'provider' => $webhook->provider,
                'code' => $code,
                'amount' => $webhook->amount,
            ]);

            $this->markWebhook($webhookLog, 'invalid_amount', 'Invalid or missing transfer amount.');

            return null;
        }

        try {
            return DB::transaction(function () use ($code, $amount, $transactionId, $webhook, $webhookLog) {
                $invoice = CongNoPayment::query()
                    ->where(function ($query) use ($code) {
                        $query->where('payment_reference', $code)
                            ->orWhere('qr_payment_code', $code);
                    })
                    ->where('status', InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT->value)
                    ->lockForUpdate()
                    ->first();

                if (! $invoice) {
                    Log::info('Payment matcher: no open invoice for code', [
                        'provider' => $webhook->provider,
                        'code' => $code,
                    ]);
                    $this->markWebhook($webhookLog, 'no_open_invoice', 'No open invoice matched the payment reference.');

                    return null;
                }

                $expected = (int) round((float) $invoice->amount);

                if ($expected > 0 && abs($amount - $expected) > 1) {
                    Log::warning('Payment matcher: amount mismatch', [
                        'provider' => $webhook->provider,
                        'code' => $code,
                        'expected' => $expected,
                        'received' => $amount,
                    ]);

                    $this->markWebhook($webhookLog, 'amount_mismatch', "Expected {$expected}, received {$amount}.", $invoice);

                    return null;
                }

                $fromStatus = $invoice->status;
                $invoice->status = InvoicePaymentStatusEnum::DA_THANH_TOAN;
                $invoice->paid_at = $webhook->paidAt ?? Carbon::now();
                $invoice->method = $invoice->method ?: 'online';
                $invoice->payment_provider = $webhook->provider;
                $invoice->provider_transaction_id = $transactionId ?: $invoice->provider_transaction_id;
                $invoice->provider_payload = $webhook->raw ?: $invoice->provider_payload;
                $invoice->sepay_transaction_id = $webhook->provider === 'sepay'
                    ? ($transactionId ?: $invoice->sepay_transaction_id)
                    : $invoice->sepay_transaction_id;
                $invoice->reference = $webhook->message ?? $invoice->reference;
                $invoice->save();
                $invoice->writeStatusLog('webhook_paid', $fromStatus, InvoicePaymentStatusEnum::DA_THANH_TOAN, null, null, [
                    'provider' => $webhook->provider,
                    'provider_transaction_id' => $transactionId,
                    'amount' => $amount,
                    'reference' => $webhook->message,
                ]);

                /** @var CongNo $debt */
                $debt = $invoice->congNo()->lockForUpdate()->first();
                if ($debt && method_exists($debt, 'syncPaidAmountFromPayments')) {
                    $debt->syncPaidAmountFromPayments();
                    $debt->refresh();

                    $orderStatus = $debt->status === DebtStatusEnum::DA_THANH_TOAN
                        ? DebtStatusEnum::DA_THANH_TOAN->value
                        : DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN->value;

                    $debt->orders()->update([
                        'customer_payment_status' => $orderStatus,
                        'customer_paid_at' => $orderStatus === DebtStatusEnum::DA_THANH_TOAN->value ? Carbon::now() : null,
                    ]);
                } elseif ($invoice->hasDirectOrder()) {
                    $order = $invoice->order()->lockForUpdate()->first();
                    if ($order) {
                        $order->forceFill([
                            'customer_payment_status' => DebtStatusEnum::DA_THANH_TOAN->value,
                            'customer_paid_at' => $invoice->paid_at ?? Carbon::now(),
                        ])->save();
                    }
                }

                Log::info('Payment matcher: invoice marked paid', [
                    'invoice_id' => $invoice->id,
                    'provider' => $webhook->provider,
                    'code' => $code,
                    'amount' => $amount,
                ]);

                $this->markWebhook($webhookLog, 'matched', 'Invoice marked paid from webhook.', $invoice);

                return $invoice;
            });
        } catch (Throwable $exception) {
            Log::error('Payment matcher failed', [
                'message' => $exception->getMessage(),
                'provider' => $webhook->provider,
                'code' => $code,
            ]);

            $this->markWebhook($webhookLog, 'error', $exception->getMessage());

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
            $payload['orderId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            if (preg_match('/HDTH[0-9A-Z]+/i', $candidate, $matches)) {
                return strtoupper($matches[0]);
            }

            if (preg_match('/[A-Z0-9\-_]{8,64}/i', $candidate, $matches)) {
                return strtoupper($matches[0]);
            }
        }

        return null;
    }

    protected function markWebhook(SepayWebhookLog|MoMoWebhookLog|VNPayWebhookLog|null $webhookLog, string $status, string $message, ?CongNoPayment $invoice = null): void
    {
        if (! $webhookLog) {
            return;
        }

        $webhookLog->forceFill([
            'matched_congno_payment_id' => $invoice?->id,
            'processed_status' => $status,
            'processed_message' => $message,
            'processed_at' => Carbon::now(),
        ])->save();
    }
}
