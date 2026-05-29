<?php

namespace App\Services\Providers\Sepay;

use App\Models\Setting;
use App\Services\EInvoices\Contracts\EInvoiceProvider;
use App\Services\EInvoices\Data\EInvoiceRequestData;
use App\Services\EInvoices\Data\EInvoiceResultData;
use App\Services\EInvoices\Data\EInvoiceStatusData;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class SepayEInvoiceService implements EInvoiceProvider
{
    public function __construct(
        protected ?string $environment = null,
        protected ?string $clientId = null,
        protected ?string $clientSecret = null,
        protected ?string $baseUrl = null,
        protected ?string $accessToken = null,
        protected ?string $tokenType = null,
    ) {
        $this->loadFromSettings();
    }

    protected function loadFromSettings(): void
    {
        // Load from Setting (trang cấu hình hệ thống) nếu có
        try {
            $options = data_get(Setting::first(), 'options', []);

            $this->environment ??= $options['einvoice_sepay_environment'] ?? $options['sepay_einvoice_environment'] ?? null;
            $this->clientId ??= $options['einvoice_sepay_client_id'] ?? $options['sepay_einvoice_client_id'] ?? null;
            $this->clientSecret ??= $options['einvoice_sepay_client_secret'] ?? $options['sepay_einvoice_client_secret'] ?? null;
            $this->baseUrl ??= $options['einvoice_sepay_base_url'] ?? $options['sepay_einvoice_base_url'] ?? null;
        } catch (\Throwable) {
            // fallback to config
        }

        // Fallback to config file
        $config = [];
        if (function_exists('app') && app()->bound('config')) {
            $config = (array) app('config')->get('sepay.einvoice', []);
        }

        $this->environment ??= Arr::get($config, 'environment', 'sandbox');
        $this->clientId ??= Arr::get($config, 'client_id');
        $this->clientSecret ??= Arr::get($config, 'client_secret');
        $this->baseUrl ??= Arr::get($config, 'base_url');
    }

    public function createToken(): array
    {
        if (! $this->clientId || ! $this->clientSecret) {
            throw new RuntimeException('SePay eInvoice client credentials are not configured.');
        }

        $data = $this->request(
            method: 'POST',
            uri: '/v1/token',
            options: ['basic_auth' => true],
            accessToken: null,
        );

        $this->accessToken = $data['access_token'] ?? null;
        $this->tokenType = $data['token_type'] ?? 'Bearer';

        if (! $this->accessToken) {
            throw new RuntimeException('SePay eInvoice token response does not contain access_token.');
        }

        return $data;
    }

    public function getAccessToken(bool $refresh = false): string
    {
        if (! $refresh && $this->accessToken) {
            return $this->accessToken;
        }

        return (string) ($this->createToken()['access_token'] ?? '');
    }

    public function listProviderAccounts(int $page = 1, int $perPage = 20): array
    {
        return $this->request('GET', '/v1/provider-accounts', [
            'query' => [
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function getProviderAccount(string $id): array
    {
        return $this->request('GET', '/v1/provider-accounts/' . urlencode($id));
    }

    public function createInvoice(array $payload): array
    {
        return $this->request('POST', '/v1/invoices/create', [
            'json' => $payload,
        ]);
    }

    public function checkCreateInvoiceStatus(string $trackingCode): array
    {
        return $this->request('GET', '/v1/invoices/create/check/' . urlencode($trackingCode));
    }

    public function deleteDraftInvoice(string $referenceCode): array
    {
        return $this->request('POST', '/v1/invoices/delete/' . urlencode($referenceCode));
    }

    public function issueInvoice(string $referenceCode): array
    {
        return $this->request('POST', '/v1/invoices/issue', [
            'json' => [
                'reference_code' => $referenceCode,
            ],
        ]);
    }

    public function checkIssueInvoiceStatus(string $trackingCode): array
    {
        return $this->request('GET', '/v1/invoices/issue/check/' . urlencode($trackingCode));
    }

    public function listInvoices(array $query = []): array
    {
        return $this->request('GET', '/v1/invoices', [
            'query' => $query,
        ]);
    }

    public function getInvoice(string $referenceCode): array
    {
        return $this->request('GET', '/v1/invoices/' . urlencode($referenceCode));
    }

    public function downloadInvoice(string $trackingCode, string $type = 'pdf'): array
    {
        $normalizedType = strtolower($type);

        if (! in_array($normalizedType, ['pdf', 'xml'], true)) {
            throw new InvalidArgumentException('Download type must be pdf or xml.');
        }

        return $this->request('GET', '/v1/invoices/' . urlencode($trackingCode) . '/download', [
            'query' => [
                'type' => $normalizedType,
            ],
        ]);
    }

    public function decodeDownloadedInvoice(array $downloadData): string
    {
        $content = $downloadData['content'] ?? null;

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('SePay eInvoice download response does not contain file content.');
        }

        $decoded = base64_decode($content, true);

        if ($decoded === false) {
            throw new RuntimeException('Unable to decode SePay eInvoice file content.');
        }

        return $decoded;
    }

    public function getUsage(): array
    {
        return $this->request('GET', '/v1/usage');
    }

    public function makeBuyerPayload(string $name, array $overrides = []): array
    {
        return array_filter([
            'type' => $overrides['type'] ?? null,
            'name' => $name,
            'legal_name' => $overrides['legal_name'] ?? null,
            'tax_code' => $overrides['tax_code'] ?? null,
            'address' => $overrides['address'] ?? null,
            'email' => $overrides['email'] ?? null,
            'phone' => $overrides['phone'] ?? null,
            'buyer_code' => $overrides['buyer_code'] ?? null,
            'national_id' => $overrides['national_id'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    public function makeInvoiceItem(int $lineNumber, int $lineType, string $itemName, array $overrides = []): array
    {
        return array_filter([
            'line_number' => $lineNumber,
            'line_type' => $lineType,
            'item_code' => $overrides['item_code'] ?? null,
            'item_name' => $itemName,
            'unit' => $overrides['unit'] ?? null,
            'quantity' => $overrides['quantity'] ?? null,
            'unit_price' => $overrides['unit_price'] ?? null,
            'total_amount' => $overrides['total_amount'] ?? null,
            'tax_rate' => $overrides['tax_rate'] ?? null,
            'tax_amount' => $overrides['tax_amount'] ?? null,
            'discount_tax' => $overrides['discount_tax'] ?? null,
            'discount_amount' => $overrides['discount_amount'] ?? null,
            'before_discount_and_tax_amount' => $overrides['before_discount_and_tax_amount'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    public function makeInvoicePayload(
        string $providerAccountId,
        string $templateCode,
        string $invoiceSeries,
        string $issuedDate,
        array $buyer,
        array $items,
        array $overrides = [],
    ): array {
        if ($items === []) {
            throw new InvalidArgumentException('Invoice items must not be empty.');
        }

        return array_filter([
            'template_code' => $templateCode,
            'invoice_series' => $invoiceSeries,
            'issued_date' => $issuedDate,
            'currency' => $overrides['currency'] ?? 'VND',
            'provider_account_id' => $providerAccountId,
            'payment_method' => $overrides['payment_method'] ?? null,
            'is_draft' => $overrides['is_draft'] ?? false,
            'buyer' => $buyer,
            'items' => $items,
            'notes' => $overrides['notes'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    public function isSuccessfulStatus(array $responseData): bool
    {
        return strtolower((string) ($responseData['status'] ?? '')) === 'success';
    }

    public function getBaseUrl(): string
    {
        if ($this->baseUrl) {
            return rtrim($this->baseUrl, '/');
        }

        return $this->isSandbox()
            ? 'https://einvoice-api-sandbox.sepay.vn'
            : 'https://einvoice-api.sepay.vn';
    }

    protected function request(string $method, string $uri, array $options = [], ?string $accessToken = null): array
    {
        $request = Http::acceptJson();

        if (($options['basic_auth'] ?? false) === true) {
            $request = $request->withBasicAuth(
                username: (string) $this->clientId,
                password: (string) $this->clientSecret,
            );
        } else {
            $request = $request->withToken($accessToken ?: $this->getAccessToken());
        }

        $requestOptions = [];

        if (isset($options['query'])) {
            $requestOptions['query'] = $options['query'];
        }

        if (array_key_exists('json', $options)) {
            $requestOptions['json'] = $options['json'];
        }

        $response = $request->send($method, $this->getBaseUrl() . $uri, $requestOptions);

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Invalid SePay eInvoice response.');
        }

        if ($response->failed() || (($payload['success'] ?? true) === false)) {
            throw new RuntimeException($this->formatErrorMessage($payload, $response->status()));
        }

        return (array) ($payload['data'] ?? []);
    }

    protected function formatErrorMessage(array $payload, int $status): string
    {
        $errorCode = $payload['error']['code'] ?? null;
        $message = $payload['error']['message'] ?? $payload['message'] ?? 'Unknown SePay eInvoice error.';

        if ($errorCode) {
            return sprintf('SePay eInvoice API error [%s] (HTTP %d): %s', $errorCode, $status, $message);
        }

        return sprintf('SePay eInvoice API error (HTTP %d): %s', $status, $message);
    }

    protected function isSandbox(): bool
    {
        return strtolower((string) $this->environment) !== 'production';
    }

    // =========================================================================
    // EInvoiceProvider Interface Implementation
    // =========================================================================

    public function key(): string
    {
        return 'sepay';
    }

    public static function configSchema(): array
    {
        return [
            [
                'key' => 'einvoice_sepay_environment',
                'label' => 'Môi trường',
                'type' => 'select',
                'required' => true,
                'sensitive' => true,
                'options' => [
                    'sandbox' => 'Sandbox',
                    'production' => 'Production',
                ],
            ],
            [
                'key' => 'einvoice_sepay_client_id',
                'label' => 'Client ID',
                'type' => 'text',
                'required' => true,
                'sensitive' => true,
                'placeholder' => 'Client ID từ SePay',
            ],
            [
                'key' => 'einvoice_sepay_client_secret',
                'label' => 'Client Secret',
                'type' => 'password',
                'required' => true,
                'sensitive' => true,
                'placeholder' => 'Client Secret từ SePay',
            ],
        ];
    }

    public function create(EInvoiceRequestData $data): EInvoiceResultData
    {
        $buyer = $this->makeBuyerPayload($data->buyer['name'] ?? '', $data->buyer);

        $items = array_map(function ($item, $index) {
            return $this->makeInvoiceItem(
                lineNumber: $index + 1,
                lineType: $item['line_type'] ?? 1,
                itemName: $item['item_name'] ?? $item['name'] ?? '',
                overrides: $item,
            );
        }, $data->items, array_keys($data->items));

        $payload = $this->makeInvoicePayload(
            providerAccountId: $data->providerAccountId,
            templateCode: $data->templateCode,
            invoiceSeries: $data->invoiceSeries,
            issuedDate: $data->issuedDate,
            buyer: $buyer,
            items: $items,
            overrides: [
                'currency' => $data->currency,
                'payment_method' => $data->paymentMethod,
                'is_draft' => $data->isDraft,
                'notes' => $data->notes,
            ],
        );

        $response = $this->createInvoice($payload);

        return new EInvoiceResultData(
            provider: $this->key(),
            reference: $data->reference,
            trackingCode: $response['tracking_code'] ?? null,
            trackingUrl: $response['tracking_url'] ?? null,
            message: $response['message'] ?? null,
            raw: $response,
        );
    }

    public function issue(string $referenceCode): EInvoiceResultData
    {
        $response = $this->issueInvoice($referenceCode);

        return new EInvoiceResultData(
            provider: $this->key(),
            reference: $referenceCode,
            trackingCode: $response['tracking_code'] ?? null,
            trackingUrl: $response['tracking_url'] ?? null,
            message: $response['message'] ?? null,
            raw: $response,
        );
    }

    public function status(string $trackingOrReferenceCode): EInvoiceStatusData
    {
        // Thử check issue status trước (tracking_code từ issue)
        try {
            $response = $this->checkIssueInvoiceStatus($trackingOrReferenceCode);
        } catch (RuntimeException) {
            // Fallback: check create status
            $response = $this->checkCreateInvoiceStatus($trackingOrReferenceCode);
        }

        $rawStatus = strtolower($response['status'] ?? '');
        $status = match ($rawStatus) {
            'success' => EInvoiceStatusData::STATUS_SUCCESS,
            'failed', 'error' => EInvoiceStatusData::STATUS_FAILED,
            default => EInvoiceStatusData::STATUS_PENDING,
        };

        return new EInvoiceStatusData(
            provider: $this->key(),
            trackingCode: $trackingOrReferenceCode,
            status: $status,
            invoiceNumber: $response['invoice_number'] ?? null,
            providerReferenceCode: $response['reference_code'] ?? null,
            message: $response['message'] ?? null,
            raw: $response,
        );
    }

    public function download(string $trackingCode, string $type = 'pdf'): string
    {
        $response = $this->downloadInvoice($trackingCode, $type);

        return $this->decodeDownloadedInvoice($response);
    }
}
