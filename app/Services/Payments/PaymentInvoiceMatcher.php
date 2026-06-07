<?php

namespace App\Services\Payments;

use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Models\CongNo;
use App\Models\CongNoPayment;
use App\Models\MoMoWebhookLog;
use App\Models\VNPayWebhookLog;
use App\Models\SepayWebhookLog;
use App\Models\SepayGatewayIpnLog;
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

    public function matchGatewayIpnPayment(array $payload, SepayGatewayIpnLog $webhookLog): ?CongNoPayment
    {
        $notificationType = $payload['notification_type'] ?? null;
        $orderStatus = $payload['order']['order_status'] ?? null;

        if ($notificationType !== 'ORDER_PAID' || $orderStatus !== 'CAPTURED') {
            $this->markWebhook($webhookLog, 'ignored', 'Gateway IPN is not a successful order payment. type=' . ($notificationType ?? 'null') . ', status=' . ($orderStatus ?? 'null'));

            return null;
        }

        $invoiceNumber = $payload['order']['order_invoice_number'] ?? null;
        $transactionId = (string) ($payload['transaction']['id'] ?? '');
        $amount = (int) ($payload['transaction']['transaction_amount'] ?? 0);

        $webhook = new PaymentWebhookData(
            provider: 'sepay',
            reference: $invoiceNumber,
            amount: $amount,
            status: 'paid',
            providerTransactionId: $transactionId !== '' ? $transactionId : null,
            paidAt: Carbon::now(),
            raw: $payload,
            message: $payload['order']['order_description'] ?? null,
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
                            ->orWhere('qr_payment_code', $code)
                            ->orWhere('ma_hoa_don', $code);
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

                // Prepare webhook-specific fields
                $webhookFields = [
                    'method' => $invoice->method ?: 'online',
                    'payment_provider' => $webhook->provider,
                    'provider_transaction_id' => $transactionId ?: $invoice->provider_transaction_id,
                    'provider_payload' => $webhook->raw ?: $invoice->provider_payload,
                    'reference' => $webhook->message ?? $invoice->reference,
                ];

                if ($webhook->provider === 'sepay') {
                    $webhookFields['sepay_transaction_id'] = $transactionId ?: $invoice->sepay_transaction_id;
                }

                // Use InvoicePaymentSyncService to mark paid and sync
                $syncService = app(\App\Services\Invoice\InvoicePaymentSyncService::class);
                $syncService->markPaidAndSync(
                    $invoice,
                    null, // No user actor for webhook
                    $webhook->paidAt ?? Carbon::now(),
                    [
                        'action' => 'webhook_paid',
                        'provider' => $webhook->provider,
                        'provider_transaction_id' => $transactionId,
                        'amount' => $amount,
                        'reference' => $webhook->message,
                    ],
                    $webhookFields
                );

                Log::info('Payment matcher: invoice marked paid', [
                    'invoice_id' => $invoice->id,
                    'provider' => $webhook->provider,
                    'code' => $code,
                    'amount' => $amount,
                ]);

                $this->markWebhook($webhookLog, 'matched', 'Invoice marked paid from webhook.', $invoice);

                return $invoice->fresh();
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

    protected function markWebhook(SepayWebhookLog|MoMoWebhookLog|VNPayWebhookLog|SepayGatewayIpnLog|null $webhookLog, string $status, string $message, ?CongNoPayment $invoice = null): void
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
