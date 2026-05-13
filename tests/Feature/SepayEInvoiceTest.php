<?php

namespace Tests\Feature;

use App\Services\Payments\SepayEInvoice;
use App\Services\Providers\Sepay\SepayEInvoiceService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SepayEInvoiceTest extends TestCase
{
    public function test_it_can_fetch_and_reuse_access_token(): void
    {
        config([
            'sepay.einvoice.environment' => 'sandbox',
            'sepay.einvoice.client_id' => 'client-id',
            'sepay.einvoice.client_secret' => 'client-secret',
        ]);

        Http::fake([
            'https://einvoice-api-sandbox.sepay.vn/v1/token' => Http::response([
                'success' => true,
                'data' => [
                    'access_token' => 'token-123',
                    'token_type' => 'Bearer',
                    'expires_in' => 86400,
                ],
            ], 200),
            'https://einvoice-api-sandbox.sepay.vn/v1/usage' => Http::response([
                'success' => true,
                'data' => [
                    'quota_remaining' => '534',
                ],
            ], 200),
        ]);

        $service = app(SepayEInvoiceService::class);

        $this->assertSame('token-123', $service->getAccessToken());
        $this->assertSame('token-123', $service->getAccessToken());
        $this->assertSame(['quota_remaining' => '534'], $service->getUsage());

        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://einvoice-api-sandbox.sepay.vn/v1/token'
                && $request->hasHeader('Authorization', 'Basic ' . base64_encode('client-id:client-secret'));
        });
        Http::assertSent(function ($request) {
            return $request->url() === 'https://einvoice-api-sandbox.sepay.vn/v1/usage'
                && $request->hasHeader('Authorization', 'Bearer token-123');
        });
    }

    public function test_it_can_create_invoice_with_bearer_token(): void
    {
        Http::fake([
            'https://einvoice-api-sandbox.sepay.vn/v1/invoices/create' => Http::response([
                'success' => true,
                'data' => [
                    'tracking_code' => 'track-001',
                    'tracking_url' => 'https://einvoice-api-sandbox.sepay.vn/v1/invoices/create/check/track-001',
                    'message' => 'ok',
                ],
            ], 200),
        ]);

        $service = new SepayEInvoiceService(
            environment: 'sandbox',
            accessToken: 'fixed-token',
        );

        $buyer = $service->makeBuyerPayload('Cong ty ABC', [
            'tax_code' => '0101234567',
            'email' => 'buyer@example.com',
        ]);

        $item = $service->makeInvoiceItem(1, 1, 'San pham A', [
            'unit' => 'cai',
            'quantity' => 2,
            'unit_price' => 100000,
            'tax_rate' => 10,
        ]);

        $payload = $service->makeInvoicePayload(
            providerAccountId: 'provider-001',
            templateCode: '1',
            invoiceSeries: 'C26TSE',
            issuedDate: '2026-05-14 10:00:00',
            buyer: $buyer,
            items: [$item],
            overrides: [
                'payment_method' => 'CK',
                'is_draft' => true,
                'notes' => 'Hoa don test',
            ],
        );

        $response = $service->createInvoice($payload);

        $this->assertSame('track-001', $response['tracking_code']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://einvoice-api-sandbox.sepay.vn/v1/invoices/create'
                && $request->hasHeader('Authorization', 'Bearer fixed-token')
                && str_contains($request->body(), '"provider_account_id":"provider-001"')
                && str_contains($request->body(), '"is_draft":true');
        });
    }

    public function test_it_throws_meaningful_exception_for_api_error(): void
    {
        Http::fake([
            'https://einvoice-api-sandbox.sepay.vn/v1/invoices/issue' => Http::response([
                'success' => false,
                'error' => [
                    'code' => 'REGISTRATION_NOT_TAX_APPROVED',
                    'message' => 'Chua duoc CQT duyet.',
                ],
            ], 422),
        ]);

        $service = new SepayEInvoiceService(
            environment: 'sandbox',
            accessToken: 'fixed-token',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('REGISTRATION_NOT_TAX_APPROVED');

        $service->issueInvoice('draft-ref-001');
    }

    public function test_legacy_alias_remains_available(): void
    {
        $this->assertInstanceOf(SepayEInvoiceService::class, new SepayEInvoice());
    }
}
