<?php

namespace App\Services\Providers\Sepay;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SepayPaymentService
{
    protected const GATEWAY_SIGNED_FIELDS = [
        'order_amount',
        'merchant',
        'currency',
        'operation',
        'order_description',
        'order_invoice_number',
        'customer_id',
        'payment_method',
        'success_url',
        'error_url',
        'cancel_url',
    ];

    public function __construct(
        protected ?string $bank = null,
        protected ?string $accountNumber = null,
        protected ?string $accountName = null,
        protected ?string $qrBaseUrl = null,
        protected ?string $authMode = null,
        protected ?string $apiKey = null,
        protected ?string $webhookSecret = null,
        protected int $timestampTolerance = 300,
        protected ?string $gatewayEnvironment = null,
        protected ?string $gatewayMerchantId = null,
        protected ?string $gatewaySecretKey = null,
        protected ?string $gatewayIpnSecretKey = null,
        protected ?string $gatewayApiBaseUrl = null,
        protected ?string $gatewayCheckoutBaseUrl = null,
        protected ?string $gatewaySuccessUrl = null,
        protected ?string $gatewayErrorUrl = null,
        protected ?string $gatewayCancelUrl = null,
    ) {
        $config = [];

        if (function_exists('app') && app()->bound('config')) {
            $config = (array) app('config')->get('sepay', []);
        }

        $this->bank ??= Arr::get($config, 'bank');
        $this->accountNumber ??= Arr::get($config, 'account_number');
        $this->accountName ??= Arr::get($config, 'account_name');
        $this->qrBaseUrl ??= Arr::get($config, 'qr_base_url', 'https://qr.sepay.vn/img');
        $this->authMode ??= Arr::get($config, 'auth_mode', 'hmac');
        $this->apiKey ??= Arr::get($config, 'api_key');
        $this->webhookSecret ??= Arr::get($config, 'webhook_secret');
        $this->timestampTolerance = (int) Arr::get($config, 'timestamp_tolerance', $this->timestampTolerance);

        $gatewayConfig = (array) Arr::get($config, 'gateway', []);

        $this->gatewayEnvironment ??= Arr::get($gatewayConfig, 'environment', 'sandbox');
        $this->gatewayMerchantId ??= Arr::get($gatewayConfig, 'merchant_id');
        $this->gatewaySecretKey ??= Arr::get($gatewayConfig, 'secret_key');
        $this->gatewayIpnSecretKey ??= Arr::get($gatewayConfig, 'ipn_secret_key');
        $this->gatewayApiBaseUrl ??= Arr::get($gatewayConfig, 'api_base_url');
        $this->gatewayCheckoutBaseUrl ??= Arr::get($gatewayConfig, 'checkout_base_url');
        $this->gatewaySuccessUrl ??= Arr::get($gatewayConfig, 'default_success_url');
        $this->gatewayErrorUrl ??= Arr::get($gatewayConfig, 'default_error_url');
        $this->gatewayCancelUrl ??= Arr::get($gatewayConfig, 'default_cancel_url');
    }

    public function makeQrUrl(int $amount, string $description, ?string $bank = null, ?string $accountNumber = null): string
    {
        $bank ??= $this->bank;
        $accountNumber ??= $this->accountNumber;

        if (! $bank || ! $accountNumber) {
            throw new RuntimeException('SePay bank/account number is not configured.');
        }

        if ($amount < 0) {
            throw new InvalidArgumentException('Amount must be greater than or equal to zero.');
        }

        return rtrim((string) $this->qrBaseUrl, '?') . '?' . http_build_query([
            'acc' => $accountNumber,
            'bank' => $bank,
            'amount' => $amount,
            'des' => $description,
        ]);
    }

    public function makePaymentData(int $amount, string $paymentCode, array $overrides = []): array
    {
        $bank = $overrides['bank'] ?? $this->bank;
        $accountNumber = $overrides['account_number'] ?? $this->accountNumber;
        $description = $overrides['description'] ?? $paymentCode;

        return [
            'gateway' => 'sepay',
            'payment_code' => $paymentCode,
            'amount' => $amount,
            'bank' => $bank,
            'account_number' => $accountNumber,
            'account_name' => $overrides['account_name'] ?? $this->accountName,
            'description' => $description,
            'qr_url' => $this->makeQrUrl(
                amount: $amount,
                description: $description,
                bank: $bank,
                accountNumber: $accountNumber,
            ),
            'expires_at' => $overrides['expires_at'] ?? Carbon::now()->addMinutes(15)->toDateTimeString(),
        ];
    }

    public function makeGatewayPaymentData(
        int $amount,
        string $invoiceNumber,
        string $description,
        array $overrides = [],
    ): array {
        $fields = [
            'order_amount' => $amount,
            'merchant' => $overrides['merchant'] ?? $this->gatewayMerchantId,
            'currency' => $overrides['currency'] ?? 'VND',
            'operation' => $overrides['operation'] ?? 'PURCHASE',
            'order_description' => $description,
            'order_invoice_number' => $invoiceNumber,
            'customer_id' => $overrides['customer_id'] ?? null,
            'payment_method' => $overrides['payment_method'] ?? null,
            'success_url' => $overrides['success_url'] ?? $this->gatewaySuccessUrl,
            'error_url' => $overrides['error_url'] ?? $this->gatewayErrorUrl,
            'cancel_url' => $overrides['cancel_url'] ?? $this->gatewayCancelUrl,
        ];

        $fields = array_filter($fields, static fn ($value) => $value !== null && $value !== '');

        if (! isset($fields['merchant'])) {
            throw new RuntimeException('SePay gateway merchant id is not configured.');
        }

        $fields['signature'] = $this->signGatewayFields($fields);

        return [
            'gateway' => 'sepay_payment_gateway',
            'environment' => $this->gatewayEnvironment,
            'checkout_url' => $this->getGatewayCheckoutUrl(),
            'fields' => $fields,
            'form_html' => $this->buildGatewayCheckoutForm($fields),
        ];
    }

    public function parseWebhookPayload(string $rawBody): array
    {
        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            throw new InvalidArgumentException('Invalid SePay payload.');
        }

        return $payload;
    }

    public function verifyRequest(Request $request): void
    {
        $authMode = Str::lower((string) $this->authMode);

        if ($authMode === 'none') {
            return;
        }

        if ($authMode === 'apikey') {
            $this->verifyApiKey($request->header('Authorization'));

            return;
        }

        if ($authMode === 'hmac') {
            $this->verifySignature(
                rawBody: (string) $request->getContent(),
                signature: (string) $request->header('X-SePay-Signature'),
                timestamp: (int) $request->header('X-SePay-Timestamp'),
            );

            return;
        }

        throw new RuntimeException("Unsupported SePay auth mode [{$this->authMode}].");
    }

    public function verifyApiKey(?string $authorizationHeader): void
    {
        if (! $this->apiKey) {
            throw new RuntimeException('SEPAY_API_KEY is not configured.');
        }

        $authorizationHeader ??= '';

        if (! Str::startsWith($authorizationHeader, 'Apikey ')) {
            throw new RuntimeException('Invalid SePay API key header.');
        }

        $receivedKey = substr($authorizationHeader, 7);

        if (! hash_equals($this->apiKey, $receivedKey)) {
            throw new RuntimeException('Invalid SePay API key.');
        }
    }

    public function verifySignature(string $rawBody, string $signature, int $timestamp): void
    {
        if (! $this->webhookSecret) {
            throw new RuntimeException('SEPAY_WEBHOOK_SECRET is not configured.');
        }

        if ($timestamp <= 0) {
            throw new RuntimeException('Missing SePay timestamp.');
        }

        if (abs(time() - $timestamp) > $this->timestampTolerance) {
            throw new RuntimeException('Expired SePay timestamp.');
        }

        $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $this->webhookSecret);

        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid SePay signature.');
        }
    }

    public function isPaid(array $payload, string $paymentCode, ?int $expectedAmount = null): bool
    {
        if (($payload['transferType'] ?? null) !== 'in') {
            return false;
        }

        if (($payload['code'] ?? null) !== $paymentCode) {
            return false;
        }

        if ($expectedAmount !== null && (int) ($payload['transferAmount'] ?? 0) < $expectedAmount) {
            return false;
        }

        return true;
    }

    public function signGatewayFields(array $fields): string
    {
        if (! $this->gatewaySecretKey) {
            throw new RuntimeException('SEPAY_GATEWAY_SECRET_KEY is not configured.');
        }

        $signed = [];

        foreach (self::GATEWAY_SIGNED_FIELDS as $field) {
            if (! array_key_exists($field, $fields)) {
                continue;
            }

            $signed[] = $field . '=' . $fields[$field];
        }

        return base64_encode(hash_hmac('sha256', implode(',', $signed), $this->gatewaySecretKey, true));
    }

    public function buildGatewayCheckoutForm(array $fields, array $attributes = []): string
    {
        $action = $attributes['action'] ?? $this->getGatewayCheckoutUrl();
        $method = strtoupper((string) ($attributes['method'] ?? 'POST'));
        $buttonText = $attributes['button_text'] ?? 'Thanh toan';
        $formAttributes = $attributes['form_attributes'] ?? [];

        $formAttributeString = collect($formAttributes)
            ->map(fn ($value, $key) => sprintf('%s="%s"', e((string) $key), e((string) $value)))
            ->implode(' ');

        $hiddenInputs = collect($fields)
            ->map(fn ($value, $key) => sprintf(
                '<input type="hidden" name="%s" value="%s" />',
                e((string) $key),
                e((string) $value),
            ))
            ->implode(PHP_EOL);

        $formTag = sprintf(
            '<form action="%s" method="%s"%s>',
            e($action),
            e($method),
            $formAttributeString ? ' ' . $formAttributeString : '',
        );

        return implode(PHP_EOL, [
            $formTag,
            $hiddenInputs,
            sprintf('<button type="submit">%s</button>', e((string) $buttonText)),
            '</form>',
        ]);
    }

    public function getGatewayCheckoutUrl(): string
    {
        if ($this->gatewayCheckoutBaseUrl) {
            return rtrim($this->gatewayCheckoutBaseUrl, '/') . '/v1/checkout/init';
        }

        $baseUrl = $this->isGatewaySandbox()
            ? 'https://pay-sandbox.sepay.vn'
            : 'https://pay.sepay.vn';

        return $baseUrl . '/v1/checkout/init';
    }

    public function getGatewayApiBaseUrl(): string
    {
        if ($this->gatewayApiBaseUrl) {
            return rtrim($this->gatewayApiBaseUrl, '/');
        }

        return $this->isGatewaySandbox()
            ? 'https://pgapi-sandbox.sepay.vn'
            : 'https://pgapi.sepay.vn';
    }

    public function getGatewayOrderDetails(string $orderId): array
    {
        if (! $this->gatewayMerchantId || ! $this->gatewaySecretKey) {
            throw new RuntimeException('SePay gateway merchant credentials are not configured.');
        }

        $response = Http::acceptJson()
            ->withBasicAuth($this->gatewayMerchantId, $this->gatewaySecretKey)
            ->get($this->getGatewayApiBaseUrl() . '/v1/order/detail/' . urlencode($orderId));

        $response->throw();

        return $response->json();
    }

    public function verifyGatewayIpnRequest(Request $request): void
    {
        if (! $this->gatewayIpnSecretKey) {
            return;
        }

        $secretKey = (string) $request->header('X-Secret-Key');

        if (! hash_equals($this->gatewayIpnSecretKey, $secretKey)) {
            throw new RuntimeException('Invalid SePay gateway IPN secret key.');
        }
    }

    public function parseGatewayIpnPayload(string $rawBody): array
    {
        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            throw new InvalidArgumentException('Invalid SePay gateway IPN payload.');
        }

        return $payload;
    }

    public function isGatewayOrderPaid(array $payload, ?string $invoiceNumber = null, ?int $expectedAmount = null): bool
    {
        if (($payload['notification_type'] ?? null) !== 'ORDER_PAID') {
            return false;
        }

        if (($payload['order']['order_status'] ?? null) !== 'CAPTURED') {
            return false;
        }

        if (($payload['transaction']['transaction_status'] ?? null) !== 'APPROVED') {
            return false;
        }

        if ($invoiceNumber !== null && ($payload['order']['order_invoice_number'] ?? null) !== $invoiceNumber) {
            return false;
        }

        if ($expectedAmount !== null && (int) ($payload['transaction']['transaction_amount'] ?? 0) < $expectedAmount) {
            return false;
        }

        return true;
    }

    public function extractGatewayPaymentResult(array $payload, ?string $invoiceNumber = null, ?int $expectedAmount = null): array
    {
        return [
            'paid' => $this->isGatewayOrderPaid($payload, $invoiceNumber, $expectedAmount),
            'notification_type' => $payload['notification_type'] ?? null,
            'gateway_order_id' => $payload['order']['order_id'] ?? null,
            'invoice_number' => $payload['order']['order_invoice_number'] ?? null,
            'order_status' => $payload['order']['order_status'] ?? null,
            'transaction_id' => $payload['transaction']['id'] ?? null,
            'transaction_code' => $payload['transaction']['transaction_id'] ?? null,
            'payment_method' => $payload['transaction']['payment_method'] ?? null,
            'transaction_status' => $payload['transaction']['transaction_status'] ?? null,
            'received_amount' => (int) ($payload['transaction']['transaction_amount'] ?? 0),
            'expected_amount' => $expectedAmount,
            'raw' => $payload,
        ];
    }

    public function extractPaymentResult(array $payload, string $paymentCode, ?int $expectedAmount = null): array
    {
        $paid = $this->isPaid($payload, $paymentCode, $expectedAmount);

        return [
            'paid' => $paid,
            'payment_code' => $paymentCode,
            'expected_amount' => $expectedAmount,
            'received_amount' => (int) ($payload['transferAmount'] ?? 0),
            'transaction_id' => $payload['id'] ?? null,
            'bank' => $payload['gateway'] ?? null,
            'account_number' => $payload['accountNumber'] ?? null,
            'reference_code' => $payload['referenceCode'] ?? null,
            'transaction_date' => $payload['transactionDate'] ?? null,
            'raw' => $payload,
        ];
    }

    public function successResponse(): array
    {
        return ['success' => true];
    }

    protected function isGatewaySandbox(): bool
    {
        return Str::lower((string) $this->gatewayEnvironment) !== 'production';
    }
}
