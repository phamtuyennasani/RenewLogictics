<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\VNPayWebhookLog;
use App\Services\Payments\PaymentInvoiceMatcher;
use App\Services\Providers\VNPay\VNPayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VNPayWebhookController extends Controller
{
    public function __invoke(Request $request, VNPayPaymentService $vnpay): JsonResponse
    {
        try {
            $webhook = $vnpay->parseWebhook($request);
            $payload = $webhook->raw;
        } catch (Throwable $exception) {
            Log::warning('VNPay IPN rejected.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'RspCode' => '97',
                'Message' => $exception->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $txnRef = (string) ($payload['vnp_TxnRef'] ?? '');
        $transactionNo = (string) ($payload['vnp_TransactionNo'] ?? '');
        $now = Carbon::now();

        $inserted = VNPayWebhookLog::query()->insertOrIgnore([
            'txn_ref' => $txnRef !== '' ? $txnRef : null,
            'amount' => isset($payload['vnp_Amount']) ? (int) ((int) $payload['vnp_Amount'] / 100) : null,
            'bank_code' => $payload['vnp_BankCode'] ?? null,
            'bank_tran_no' => $payload['vnp_BankTranNo'] ?? null,
            'card_type' => $payload['vnp_CardType'] ?? null,
            'response_code' => isset($payload['vnp_ResponseCode']) ? (string) $payload['vnp_ResponseCode'] : null,
            'transaction_no' => $transactionNo !== '' ? $transactionNo : null,
            'transaction_status' => isset($payload['vnp_TransactionStatus']) ? (string) $payload['vnp_TransactionStatus'] : null,
            'pay_date' => isset($payload['vnp_PayDate']) ? Carbon::createFromFormat('YmdHis', (string) $payload['vnp_PayDate'], 'Asia/Ho_Chi_Minh') : null,
            'order_info' => $payload['vnp_OrderInfo'] ?? null,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'headers' => json_encode($request->headers->all(), JSON_UNESCAPED_UNICODE),
            'received_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted !== 0) {
            $query = VNPayWebhookLog::query();

            if ($txnRef !== '') {
                $query->where('txn_ref', $txnRef);
            }

            if ($transactionNo !== '') {
                $query->where('transaction_no', $transactionNo);
            }

            $webhookLog = $query->latest('id')->first();

            try {
                app(PaymentInvoiceMatcher::class)->matchWebhookPayment($webhook, $webhookLog);
            } catch (Throwable $exception) {
                Log::error('VNPay invoice matcher failed.', [
                    'message' => $exception->getMessage(),
                ]);

                $webhookLog?->forceFill([
                    'processed_status' => 'error',
                    'processed_message' => $exception->getMessage(),
                    'processed_at' => Carbon::now(),
                ])->save();
            }
        }

        return response()->json([
            'RspCode' => '00',
            'Message' => 'Success',
        ]);
    }
}
