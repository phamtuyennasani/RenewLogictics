<?php

namespace App\Services\Providers\MoMo;

use App\Models\Setting;
use App\Services\Payments\Contracts\PaymentProvider;
use App\Services\Payments\Data\PaymentIntentData;
use App\Services\Payments\Data\PaymentRequestData;
use App\Services\Payments\Data\PaymentWebhookData;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class MoMoPaymentService implements PaymentProvider
{
    public function __construct(
        protected ?string $partnerCode = null,
        protected ?string $accessKey = null,
        protected ?string $secretKey = null,
        protected ?string $environment = null,
        protected ?string $redirectUrl = null,
        protected ?string $ipnUrl = null,
        protected ?string $requestType = null,
        protected ?string $partnerName = null,
        protected ?string $storeId = null,
        protected ?string $endpoint = null,
    ) {
        $this->loadFromSettings();
    }

    protected function loadFromSettings(): void
    {
        try {
            $options = data_get(Setting::first(), 'options', []);

            $this->partnerCode ??= $options['payment_momo_partner_code'] ?? null;
            $this->accessKey ??= $options['payment_momo_access_key'] ?? null;
            $this->secretKey ??= $options['payment_momo_secret_key'] ?? null;
            $this->environment ??= $options['payment_momo_environment'] ?? 'sandbox';
        } catch (\Throwable) {
            // fallback to config file
            if (function_exists('app') && app()->bound('config')) {
                $config = (array) app('config')->get('momo', []);
                $this->partnerCode ??= Arr::get($config, 'partner_code');
                $this->accessKey ??= Arr::get($config, 'access_key');
                $this->secretKey ??= Arr::get($config, 'secret_key');
                $this->environment ??= Arr::get($config, 'environment', 'sandbox');
                $this->redirectUrl ??= Arr::get($config, 'redirect_url');
                $this->ipnUrl ??= Arr::get($config, 'ipn_url');
                $this->requestType ??= Arr::get($config, 'request_type', 'captureWallet');
                $this->partnerName ??= Arr::get($config, 'partner_name', 'Test');
                $this->storeId ??= Arr::get($config, 'store_id', 'MoMoTestStore');
                $this->endpoint ??= Arr::get($config, 'endpoint');
            }
        }

        $this->redirectUrl ??= config('momo.redirect_url');
        $this->ipnUrl ??= config('momo.ipn_url');
        $this->requestType ??= config('momo.request_type', 'captureWallet');
        $this->partnerName ??= config('momo.partner_name', 'Test');
        $this->storeId ??= config('momo.store_id', 'MoMoTestStore');
        $this->endpoint ??= config('momo.endpoint');
    }

    public function key(): string
    {
        return 'momo';
    }

    public static function configSchema(): array
    {
        return [
            [
                'key' => 'payment_momo_partner_code',
                'label' => 'Partner Code',
                'type' => 'text',
                'required' => true,
                'sensitive' => true,
                'placeholder' => 'VD: MOMOBKUN...',
            ],
            [
                'key' => 'payment_momo_access_key',
                'label' => 'Access Key',
                'type' => 'text',
                'required' => true,
                'sensitive' => true,
                'placeholder' => 'Access key từ MoMo',
            ],
            [
                'key' => 'payment_momo_secret_key',
                'label' => 'Secret Key',
                'type' => 'password',
                'required' => true,
                'sensitive' => true,
                'placeholder' => 'Secret key từ MoMo',
            ],
        ];
    }

    public function createPayment(PaymentRequestData $data): PaymentIntentData
    {
        $endpoint = $this->getEndpoint();
        $requestId = $data->metadata['request_id'] ?? ($data->reference.'-'.now()->format('YmdHis'));
        $orderId = $data->reference;
        $amount = (int) $data->amount;
        $orderInfo = $data->description ?: $data->reference;
        $extraData = (string) ($data->metadata['extra_data'] ?? '');
        $lang = (string) ($data->metadata['lang'] ?? 'vi');
        $autoCapture = array_key_exists('auto_capture', $data->metadata)
            ? (bool) $data->metadata['auto_capture']
            : true;
        $requestType = $data->metadata['request_type'] ?? $this->requestType;

        $payload = [
            'partnerCode' => $this->requireConfig($this->partnerCode, 'MOMO_PARTNER_CODE'),
            'partnerName' => $this->partnerName,
            'storeId' => $this->storeId,
            'requestId' => $requestId,
            'amount' => (string) $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $this->requireConfig($this->redirectUrl, 'MOMO_REDIRECT_URL'),
            'ipnUrl' => $this->requireConfig($this->ipnUrl, 'MOMO_IPN_URL'),
            'lang' => $lang,
            'requestType' => $requestType,
            'extraData' => $extraData,
        ];

        $payload['signature'] = $this->signCreatePayment($payload);

        $response = Http::asJson()->post($endpoint, $payload);
        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('MoMo create payment request failed.');
        }

        if (! $response->ok()) {
            throw new RuntimeException((string) ($body['message'] ?? 'MoMo create payment request failed.'));
        }

        if ((int) ($body['resultCode'] ?? -1) !== 0) {
            throw new RuntimeException((string) ($body['message'] ?? 'MoMo payment request was rejected.'));
        }

        $payUrl = $body['payUrl'] ?? null;
        $deeplink = $body['deeplink'] ?? null;
        $qrCodeUrl = $body['qrCodeUrl'] ?? $deeplink ?? $payUrl;
        $expiresAt = $data->expiresAt ?? Carbon::now()->addMinutes(15);

        return new PaymentIntentData(
            provider: $this->key(),
            channel: 'redirect',
            reference: $orderId,
            amount: $amount,
            paymentUrl: is_string($payUrl) ? $payUrl : null,
            qrUrl: is_string($qrCodeUrl) ? $qrCodeUrl : null,
            providerIntentId: isset($body['requestId']) ? (string) $body['requestId'] : $requestId,
            expiresAt: $expiresAt,
            raw: $body,
        );
    }

    public function parseWebhook(Request $request): PaymentWebhookData
    {
        $payload = $this->parseWebhookPayload((string) $request->getContent());
        $this->verifyIpnSignature($payload);

        $resultCode = (int) ($payload['resultCode'] ?? -1);
        $responseTime = isset($payload['responseTime']) ? Carbon::createFromTimestampMs((int) $payload['responseTime']) : Carbon::now();

        return new PaymentWebhookData(
            provider: $this->key(),
            reference: isset($payload['orderId']) ? (string) $payload['orderId'] : null,
            amount: (int) ($payload['amount'] ?? 0),
            status: $resultCode === 0 ? 'paid' : 'ignored',
            providerTransactionId: isset($payload['transId']) ? (string) $payload['transId'] : null,
            paidAt: $responseTime,
            raw: $payload,
            message: $payload['message'] ?? null,
        );
    }

    public function parseWebhookPayload(string $rawBody): array
    {
        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            throw new InvalidArgumentException('Invalid MoMo payload.');
        }

        return $payload;
    }

    public function verifyIpnSignature(array $payload): void
    {
        $signature = (string) ($payload['signature'] ?? '');
        if ($signature === '') {
            throw new RuntimeException('Missing MoMo signature.');
        }

        $expected = $this->signIpnPayload($payload);

        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Invalid MoMo signature.');
        }
    }

    public function signCreatePayment(array $payload): string
    {
        $raw = implode('&', [
            'accessKey='.$this->requireConfig($this->accessKey, 'MOMO_ACCESS_KEY'),
            'amount='.$payload['amount'],
            'extraData='.$payload['extraData'],
            'ipnUrl='.$payload['ipnUrl'],
            'orderId='.$payload['orderId'],
            'orderInfo='.$payload['orderInfo'],
            'partnerCode='.$payload['partnerCode'],
            'redirectUrl='.$payload['redirectUrl'],
            'requestId='.$payload['requestId'],
            'requestType='.$payload['requestType'],
        ]);

        return hash_hmac('sha256', $raw, $this->requireConfig($this->secretKey, 'MOMO_SECRET_KEY'));
    }

    public function signIpnPayload(array $payload): string
    {
        $fields = [
            'accessKey' => $this->requireConfig($this->accessKey, 'MOMO_ACCESS_KEY'),
            'amount' => (string) ($payload['amount'] ?? ''),
            'extraData' => (string) ($payload['extraData'] ?? ''),
            'message' => (string) ($payload['message'] ?? ''),
            'orderId' => (string) ($payload['orderId'] ?? ''),
            'orderInfo' => (string) ($payload['orderInfo'] ?? ''),
            'orderType' => (string) ($payload['orderType'] ?? ''),
            'partnerCode' => (string) ($payload['partnerCode'] ?? ''),
            'payType' => (string) ($payload['payType'] ?? ''),
            'requestId' => (string) ($payload['requestId'] ?? ''),
            'responseTime' => (string) ($payload['responseTime'] ?? ''),
            'resultCode' => (string) ($payload['resultCode'] ?? ''),
            'transId' => (string) ($payload['transId'] ?? ''),
        ];

        $raw = collect($fields)
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode('&');

        return hash_hmac('sha256', $raw, $this->requireConfig($this->secretKey, 'MOMO_SECRET_KEY'));
    }

    protected function getEndpoint(): string
    {
        if ($this->endpoint) {
            return $this->endpoint;
        }

        return $this->environment === 'production'
            ? 'https://payment.momo.vn/v2/gateway/api/create'
            : 'https://test-payment.momo.vn/v2/gateway/api/create';
    }

    protected function requireConfig(?string $value, string $key): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("{$key} is not configured.");
        }

        return $value;
    }
}
