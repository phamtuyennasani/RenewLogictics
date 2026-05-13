<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\SepayWebhookLog;
use App\Services\Providers\Sepay\SepayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SepayWebhookController extends Controller
{
    public function __invoke(Request $request, SepayPaymentService $sepay): JsonResponse
    {
        $rawBody = (string) $request->getContent();

        try {
            $sepay->verifyRequest($request);
            $payload = $sepay->parseWebhookPayload($rawBody);
        } catch (Throwable $exception) {
            Log::warning('SePay webhook rejected.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $inserted = SepayWebhookLog::query()->insertOrIgnore([
            'transaction_id' => $payload['id'] ?? null,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'headers' => json_encode($request->headers->all(), JSON_UNESCAPED_UNICODE),
            'received_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        if ($inserted === 0) {
            return response()->json($sepay->successResponse());
        }

        Log::info('SePay webhook received.', [
            'transaction_id' => $payload['id'] ?? null,
            'payment_code' => $payload['code'] ?? null,
            'amount' => $payload['transferAmount'] ?? null,
            'transfer_type' => $payload['transferType'] ?? null,
        ]);

        return response()->json($sepay->successResponse());
    }
}
