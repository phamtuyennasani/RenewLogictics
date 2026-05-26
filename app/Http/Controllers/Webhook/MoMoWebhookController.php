<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\MoMoWebhookLog;
use App\Services\Payments\PaymentInvoiceMatcher;
use App\Services\Providers\MoMo\MoMoPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MoMoWebhookController extends Controller
{
    public function __invoke(Request $request, MoMoPaymentService $momo): JsonResponse
    {
        try {
            $webhook = $momo->parseWebhook($request);
            $payload = $webhook->raw;
        } catch (Throwable $exception) {
            Log::warning('MoMo IPN rejected.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'resultCode' => Response::HTTP_UNAUTHORIZED,
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $eventId = (string) ($payload['requestId'] ?? $payload['orderId'] ?? '');
        $transId = (string) ($payload['transId'] ?? '');
        $now = Carbon::now();

        $inserted = MoMoWebhookLog::query()->insertOrIgnore([
            'event_id' => $eventId !== '' ? $eventId : null,
            'order_id' => $payload['orderId'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'trans_id' => $transId !== '' ? $transId : null,
            'result_code' => isset($payload['resultCode']) ? (string) $payload['resultCode'] : null,
            'message' => $payload['message'] ?? null,
            'payment_option' => $payload['payType'] ?? null,
            'response_time' => isset($payload['responseTime']) ? Carbon::createFromTimestampMs((int) $payload['responseTime']) : null,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'headers' => json_encode($request->headers->all(), JSON_UNESCAPED_UNICODE),
            'received_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted !== 0) {
            $query = MoMoWebhookLog::query();

            if ($eventId !== '') {
                $query->where('event_id', $eventId);
            } else {
                $query->where('order_id', $payload['orderId'] ?? null);
            }

            if ($transId !== '') {
                $query->where('trans_id', $transId);
            }

            $webhookLog = $query->latest('id')->first();

            try {
                app(PaymentInvoiceMatcher::class)->matchWebhookPayment($webhook, $webhookLog);
            } catch (Throwable $exception) {
                Log::error('MoMo invoice matcher failed.', [
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
            'resultCode' => 0,
            'message' => 'Success',
        ]);
    }
}
