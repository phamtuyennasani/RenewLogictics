<?php

namespace App\Services\Providers\VNPay;

use App\Services\Payments\Contracts\PaymentProvider;
use App\Services\Payments\Data\PaymentIntentData;
use App\Services\Payments\Data\PaymentRequestData;
use App\Services\Payments\Data\PaymentWebhookData;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class VNPayPaymentService implements PaymentProvider
{
    public function __construct(
        protected ?string $tmnCode = null,
        protected ?string $hashSecret = null,
        protected ?string $environment = null,
        protected ?string $returnUrl = null,
        protected ?string $ipnUrl = null,
        protected ?string $command = null,
        protected ?string $orderType = null,
        protected ?string $locale = null,
        protected ?string $currency = null,
    ) {
        $config = [];

        if (function_exists('app') && app()->bound('config')) {
            $config = (array) app('config')->get('vnpay', []);
        }

        $this->tmnCode ??= Arr::get($config, 'tmn_code');
        $this->hashSecret ??= Arr::get($config, 'hash_secret');
        $this->environment ??= Arr::get($config, 'environment', 'sandbox');
        $this->returnUrl ??= Arr::get($config, 'return_url');
        $this->ipnUrl ??= Arr::get($config, 'ipn_url');
        $this->command ??= Arr::get($config, 'command', 'pay');
        $this->orderType ??= Arr::get($config, 'order_type', 'billpayment');
        $this->locale ??= Arr::get($config, 'locale', 'vn');
        $this->currency ??= Arr::get($config, 'currency', 'VND');
    }

    public function key(): string
    {
        return 'vnpay';
    }

    public function createPayment(PaymentRequestData $data): PaymentIntentData
    {
        $endpoint = $this->getEndpoint();
        $txnRef = $data->reference;
        $amount = (int) $data->amount;
        $orderInfo = $data->description ?: $data->reference;
        $clientIp = $this->getClientIp();
        $createDate = Carbon::now('Asia/Ho_Chi_Minh')->format('YmdHis');
        $expireDate = $data->expiresAt
            ? Carbon::parse($data->expiresAt, 'Asia/Ho_Chi_Minh')->format('YmdHis')
            : Carbon::now('Asia/Ho_Chi_Minh')->addMinutes(15)->format('YmdHis');

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => $this->requireConfig($this->command, 'VNPAY_COMMAND'),
            'vnp_TmnCode' => $this->requireConfig($this->tmnCode, 'VNPAY_TMN_CODE'),
            'vnp_Amount' => (string) ($amount * 100),
            'vnp_CurrCode' => $this->requireConfig($this->currency, 'VNPAY_CURRENCY'),
            'vnp_TxnRef' => $txnRef,
            'vnp_OrderInfo' => $orderInfo,
            'vnp_OrderType' => $this->requireConfig($this->orderType, 'VNPAY_ORDER_TYPE'),
            'vnp_Locale' => $this->requireConfig($this->locale, 'VNPAY_LOCALE'),
            'vnp_ReturnUrl' => $this->requireConfig($this->returnUrl, 'VNPAY_RETURN_URL'),
            'vnp_IpAddr' => $clientIp,
            'vnp_CreateDate' => $createDate,
            'vnp_ExpireDate' => $expireDate,
        ];

        ksort($params);
        $hashData = $this->buildHashData($params);
        $params['vnp_SecureHash'] = hash_hmac('sha512', $hashData, $this->requireConfig($this->hashSecret, 'VNPAY_HASH_SECRET'));

        $paymentUrl = $endpoint . '?' . $this->buildQueryString($params);

        return new PaymentIntentData(
            provider: $this->key(),
            channel: 'redirect',
            reference: $txnRef,
            amount: $amount,
            paymentUrl: $paymentUrl,
            qrUrl: null,
            providerIntentId: $txnRef,
            expiresAt: $data->expiresAt ?? Carbon::now('Asia/Ho_Chi_Minh')->addMinutes(15),
            raw: $params,
        );
    }

    public function parseWebhook(Request $request): PaymentWebhookData
    {
        $query = $request->query();
        $this->verifyIpnSignature($query);

        $responseCode = (string) ($query['vnp_ResponseCode'] ?? '-1');
        $transactionNo = (string) ($query['vnp_TransactionNo'] ?? '');
        $amount = (int) (($query['vnp_Amount'] ?? 0) / 100);
        $txnRef = isset($query['vnp_TxnRef']) ? (string) $query['vnp_TxnRef'] : null;
        $payDate = isset($query['vnp_PayDate'])
            ? Carbon::createFromFormat('YmdHis', (string) $query['vnp_PayDate'], 'Asia/Ho_Chi_Minh')
            : Carbon::now('Asia/Ho_Chi_Minh');

        return new PaymentWebhookData(
            provider: $this->key(),
            reference: $txnRef,
            amount: $amount,
            status: $responseCode === '00' ? 'paid' : 'ignored',
            providerTransactionId: $transactionNo !== '' ? $transactionNo : null,
            paidAt: $payDate,
            raw: $query,
            message: $query['vnp_OrderInfo'] ?? null,
        );
    }

    public function parseWebhookPayload(string $rawBody): array
    {
        parse_str($rawBody, $params);

        if (! is_array($params) || empty($params)) {
            throw new InvalidArgumentException('Invalid VNPay payload.');
        }

        return $params;
    }

    public function verifyIpnSignature(array $params): void
    {
        $secureHash = (string) ($params['vnp_SecureHash'] ?? '');

        if ($secureHash === '') {
            throw new RuntimeException('Missing VNPay signature.');
        }

        $expected = $this->signIpnPayload($params);

        if (! hash_equals($expected, $secureHash)) {
            throw new RuntimeException('Invalid VNPay signature.');
        }
    }

    public function signIpnPayload(array $params): string
    {
        $signedFields = [
            'vnp_Amount',
            'vnp_BankCode',
            'vnp_BankTranNo',
            'vnp_CardType',
            'vnp_OrderInfo',
            'vnp_PayDate',
            'vnp_ResponseDate',
            'vnp_ResponseId',
            'vnp_TmnCode',
            'vnp_TransactionNo',
            'vnp_TransactionStatus',
            'vnp_TxnRef',
            'vnp_Vlan',
        ];

        $data = [];
        foreach ($signedFields as $field) {
            if (isset($params[$field]) && (string) $params[$field] !== '') {
                $data[$field] = $params[$field];
            } else {
                $data[$field] = '';
            }
        }

        ksort($data);
        $hashData = $this->buildHashData($data);

        return hash_hmac('sha512', $hashData, $this->requireConfig($this->hashSecret, 'VNPAY_HASH_SECRET'));
    }

    protected function getEndpoint(): string
    {
        return $this->environment === 'production'
            ? 'https://pay.vnpay.vn/vpcpay.html'
            : 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
    }

    protected function getClientIp(): string
    {
        if (function_exists('request')) {
            try {
                return (string) request()->ip();
            } catch (\Throwable) {
            }
        }

        return '127.0.0.1';
    }

    protected function buildHashData(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $parts[] = $key . '=' . urlencode((string) $value);
            }
        }

        return implode('&', $parts);
    }

    protected function buildQueryString(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = urlencode($key) . '=' . urlencode((string) $value);
        }

        return implode('&', $parts);
    }

    protected function requireConfig(?string $value, string $key): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("{$key} is not configured.");
        }

        return $value;
    }
}
