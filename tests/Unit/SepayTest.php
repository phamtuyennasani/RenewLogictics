<?php

namespace Tests\Unit;

use App\Services\Payments\Sepay;
use App\Services\Providers\Sepay\SepayPaymentService;
use PHPUnit\Framework\TestCase;

class SepayTest extends TestCase
{
    public function test_it_can_build_qr_url(): void
    {
        $sepay = new SepayPaymentService(
            bank: 'Vietcombank',
            accountNumber: '0010000000355',
            qrBaseUrl: 'https://qr.sepay.vn/img',
        );

        $url = $sepay->makeQrUrl(100000, 'DH12345');

        $this->assertSame(
            'https://qr.sepay.vn/img?acc=0010000000355&bank=Vietcombank&amount=100000&des=DH12345',
            $url,
        );
    }

    public function test_it_can_verify_hmac_signature(): void
    {
        $secret = 'test-secret';
        $timestamp = time();
        $body = '{"id":92704,"code":"DH12345","transferType":"in","transferAmount":100000}';
        $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);

        $sepay = new SepayPaymentService(
            webhookSecret: $secret,
            timestampTolerance: 300,
        );

        $sepay->verifySignature($body, $signature, $timestamp);

        $this->assertTrue(true);
    }

    public function test_it_can_check_paid_status(): void
    {
        $payload = [
            'id' => 92704,
            'code' => 'DH12345',
            'transferType' => 'in',
            'transferAmount' => 100000,
        ];

        $sepay = new SepayPaymentService();

        $this->assertTrue($sepay->isPaid($payload, 'DH12345', 100000));
        $this->assertFalse($sepay->isPaid($payload, 'DH54321', 100000));
        $this->assertFalse($sepay->isPaid($payload, 'DH12345', 150000));
    }

    public function test_it_can_sign_gateway_fields(): void
    {
        $fields = [
            'order_amount' => 100000,
            'merchant' => 'MERCHANT_123',
            'currency' => 'VND',
            'operation' => 'PURCHASE',
            'order_description' => 'Thanh toan don hang #12345',
            'order_invoice_number' => 'INV_20231201_001',
            'success_url' => 'https://example.com/payment/success',
            'error_url' => 'https://example.com/payment/error',
            'cancel_url' => 'https://example.com/payment/cancel',
        ];

        $secretKey = 'gateway-secret';
        $signedString = 'order_amount=100000,merchant=MERCHANT_123,currency=VND,operation=PURCHASE,order_description=Thanh toan don hang #12345,order_invoice_number=INV_20231201_001,success_url=https://example.com/payment/success,error_url=https://example.com/payment/error,cancel_url=https://example.com/payment/cancel';

        $sepay = new SepayPaymentService(gatewaySecretKey: $secretKey);

        $this->assertSame(
            base64_encode(hash_hmac('sha256', $signedString, $secretKey, true)),
            $sepay->signGatewayFields($fields),
        );
    }

    public function test_it_can_build_gateway_checkout_form(): void
    {
        $sepay = new SepayPaymentService(
            gatewayEnvironment: 'sandbox',
            gatewayMerchantId: 'MERCHANT_123',
            gatewaySecretKey: 'gateway-secret',
        );

        $payment = $sepay->makeGatewayPaymentData(
            amount: 100000,
            invoiceNumber: 'INV_20231201_001',
            description: 'Thanh toan don hang #12345',
        );

        $this->assertStringContainsString('action="https://pay-sandbox.sepay.vn/v1/checkout/init"', $payment['form_html']);
        $this->assertStringContainsString('name="signature"', $payment['form_html']);
        $this->assertSame('MERCHANT_123', $payment['fields']['merchant']);
    }

    public function test_it_can_check_gateway_paid_status(): void
    {
        $payload = [
            'notification_type' => 'ORDER_PAID',
            'order' => [
                'order_status' => 'CAPTURED',
                'order_invoice_number' => 'INV_20231201_001',
            ],
            'transaction' => [
                'id' => 'tx-1',
                'transaction_status' => 'APPROVED',
                'transaction_amount' => '100000',
            ],
        ];

        $sepay = new SepayPaymentService();

        $this->assertInstanceOf(SepayPaymentService::class, new Sepay());

        $this->assertTrue($sepay->isGatewayOrderPaid($payload, 'INV_20231201_001', 100000));
        $this->assertFalse($sepay->isGatewayOrderPaid($payload, 'INV_20231201_002', 100000));
        $this->assertFalse($sepay->isGatewayOrderPaid($payload, 'INV_20231201_001', 150000));
    }
}
