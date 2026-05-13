<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\SepayGatewayIpnLog;
use App\Services\Providers\Sepay\SepayPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SepayGatewayIpnController extends Controller
{
    public function __invoke(Request $request, SepayPaymentService $sepay): JsonResponse
    {
        $rawBody = (string) $request->getContent();

        try {
            $sepay->verifyGatewayIpnRequest($request);
            $payload = $sepay->parseGatewayIpnPayload($rawBody);
        } catch (Throwable $exception) {
            Log::warning('SePay gateway IPN rejected.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $eventKey = implode(':', array_filter([
            $payload['notification_type'] ?? 'unknown',
            $payload['order']['order_id'] ?? null,
            $payload['transaction']['id'] ?? null,
        ]));

        $now = Carbon::now();

        $inserted = SepayGatewayIpnLog::query()->insertOrIgnore([
            'event_key' => $eventKey,
            'notification_type' => $payload['notification_type'] ?? null,
            'gateway_order_id' => $payload['order']['order_id'] ?? null,
            'invoice_number' => $payload['order']['order_invoice_number'] ?? null,
            'transaction_id' => $payload['transaction']['id'] ?? null,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'headers' => json_encode($request->headers->all(), JSON_UNESCAPED_UNICODE),
            'received_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            return response()->json($sepay->successResponse());
        }

        Log::info('SePay gateway IPN received.', [
            'event_key' => $eventKey,
            'notification_type' => $payload['notification_type'] ?? null,
            'invoice_number' => $payload['order']['order_invoice_number'] ?? null,
            'transaction_id' => $payload['transaction']['id'] ?? null,
            'transaction_status' => $payload['transaction']['transaction_status'] ?? null,
        ]);

        return response()->json($sepay->successResponse());
    }
}
