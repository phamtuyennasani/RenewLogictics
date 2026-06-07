<?php

namespace App\Http\Controllers\Payment;

use App\Enums\InvoicePaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CongNoPayment;
use App\Models\Setting;
use App\Services\Providers\MoMo\MoMoPaymentService;
use App\Services\Providers\VNPay\VNPayPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PaymentReturnController extends Controller
{
    public function __invoke(Request $request, string $provider): View
    {
        $provider = strtolower($provider);

        abort_unless(in_array($provider, ['vnpay', 'momo'], true), 404);

        $data = match ($provider) {
            'vnpay' => $this->fromVNPay($request),
            'momo' => $this->fromMoMo($request),
        };

        $invoice = $this->findInvoice($data['reference'], $data['transaction_id']);
        $status = $this->resolveDisplayStatus($data['gateway_success'], $invoice);
        $support = $this->companySupport();

        return view('payment.return', [
            'provider' => $provider,
            'providerName' => $provider === 'vnpay' ? 'VNPay' : 'MoMo',
            'status' => $status,
            'data' => $data,
            'invoice' => $invoice,
            'supportPhone' => $support['phone'],
            'supportPhoneHref' => $support['phone_href'],
            'supportEmail' => $support['email'],
            'supportEmailHref' => $support['email_href'],
        ]);
    }


    protected function companySupport(): array
    {
        $options = (array) data_get(Setting::first(), 'options', []);
        $phone = $this->cleanString($options['company_phone'] ?? null)
            ?: $this->cleanString($options['social_hotline'] ?? null);
        $email = $this->cleanString($options['company_email'] ?? null);

        return [
            'phone' => $phone,
            'phone_href' => $this->phoneHref($phone),
            'email' => $email,
            'email_href' => $email ? 'mailto:' . $email : null,
        ];
    }

    protected function phoneHref(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $normalized = preg_replace('/[^0-9+]/', '', $phone);

        return $normalized ? 'tel:' . $normalized : null;
    }
    protected function fromVNPay(Request $request): array
    {
        $amount = (int) floor(((int) $request->query('vnp_Amount', 0)) / 100);
        $paidAt = $this->parseGatewayDate($request->query('vnp_PayDate'));
        $responseCode = $this->cleanString($request->query('vnp_ResponseCode'));
        $transactionStatus = $this->cleanString($request->query('vnp_TransactionStatus'));

        return [
            'gateway_success' => $responseCode === '00' && $transactionStatus === '00',
            'reference' => $this->cleanString($request->query('vnp_TxnRef')),
            'order_info' => $this->cleanString($request->query('vnp_OrderInfo')),
            'amount' => $amount,
            'transaction_id' => $this->cleanString($request->query('vnp_TransactionNo')),
            'bank_code' => $this->cleanString($request->query('vnp_BankCode')),
            'card_type' => $this->cleanString($request->query('vnp_CardType')),
            'response_code' => $responseCode,
            'response_message' => $this->vnpayResponseMessage($responseCode),
            'transaction_status_message' => $this->vnpayTransactionStatusMessage($transactionStatus),
            'message' => $responseCode === '00'
                ? 'Cổng thanh toán đã xác nhận giao dịch thành công.'
                : 'Cổng thanh toán trả về kết quả chưa thành công.',
            'paid_at' => $paidAt,
            'signature_status' => $this->verifySignature('vnpay', $request),
        ];
    }
    protected function fromMoMo(Request $request): array
    {
        $responseCode = $this->cleanString($request->query('resultCode'));

        return [
            'gateway_success' => (string) $request->query('resultCode', '-1') === '0',
            'reference' => $this->cleanString($request->query('orderId')),
            'order_info' => $this->cleanString($request->query('orderInfo')),
            'amount' => (int) $request->query('amount', 0),
            'transaction_id' => $this->cleanString($request->query('transId')),
            'bank_code' => $this->cleanString($request->query('payType')),
            'card_type' => null,
            'response_code' => $responseCode,
            'response_message' => $this->cleanString($request->query('message')) ?: $this->momoResponseMessage($responseCode),
            'transaction_status_message' => null,
            'message' => $this->cleanString($request->query('message'))
                ?: ((string) $request->query('resultCode', '-1') === '0'
                    ? 'Cổng thanh toán đã xác nhận giao dịch thành công.'
                    : 'Cổng thanh toán trả về kết quả chưa thành công.'),
            'paid_at' => $this->parseMoMoResponseTime($request->query('responseTime')),
            'signature_status' => $this->verifySignature('momo', $request),
        ];
    }
    protected function vnpayResponseMessage(?string $code): string
    {
        return match ($code) {
            '00' => 'Giao dịch thành công.',
            '07' => 'Giao dịch có dấu hiệu bất thường. Vui lòng liên hệ hỗ trợ để kiểm tra.',
            '09' => 'Thẻ hoặc tài khoản chưa đăng ký thanh toán trực tuyến.',
            '10' => 'Thông tin xác thực không đúng quá số lần cho phép.',
            '11' => 'Đã quá thời gian thanh toán. Vui lòng tạo lại giao dịch.',
            '12' => 'Thẻ hoặc tài khoản đang bị khóa.',
            '13' => 'Mã xác thực giao dịch không đúng.',
            '24' => 'Giao dịch đã được hủy.',
            '51' => 'Tài khoản không đủ số dư để thanh toán.',
            '65' => 'Tài khoản đã vượt hạn mức giao dịch trong ngày.',
            '75' => 'Ngân hàng đang bảo trì. Vui lòng thử lại sau.',
            '79' => 'Nhập sai mật khẩu thanh toán quá số lần cho phép.',
            '99' => 'Giao dịch chưa thành công. Vui lòng thử lại hoặc liên hệ hỗ trợ.',
            default => $code ? "Giao dịch chưa thành công. Mã đối soát: {$code}." : 'Đang cập nhật kết quả thanh toán.',
        };
    }

    protected function vnpayTransactionStatusMessage(?string $status): string
    {
        return match ($status) {
            '00' => 'Thanh toán đã hoàn tất.',
            '01' => 'Giao dịch chưa hoàn tất.',
            '02' => 'Giao dịch chưa thành công.',
            '04' => 'Giao dịch đã được hoàn tiền một phần.',
            '05' => 'Giao dịch đang được xử lý hoàn tiền.',
            '06' => 'Giao dịch đã được hoàn tiền.',
            '07' => 'Giao dịch cần được kiểm tra thêm.',
            '09' => 'Yêu cầu hoàn tiền chưa được chấp nhận.',
            default => $status ? "Trạng thái đang được kiểm tra. Mã đối soát: {$status}." : 'Đang cập nhật trạng thái thanh toán.',
        };
    }

    protected function momoResponseMessage(?string $code): string
    {
        return $code === '0'
            ? 'Giao dịch thành công.'
            : 'Giao dịch chưa thành công. Vui lòng thử lại hoặc liên hệ hỗ trợ.';
    }

    protected function findInvoice(?string $reference, ?string $transactionId): ?CongNoPayment
    {
        $reference = $this->cleanString($reference);
        $transactionId = $this->cleanString($transactionId);

        if (! $reference && ! $transactionId) {
            return null;
        }

        return CongNoPayment::query()
            ->with(['user:id,fullname,username,code'])
            ->where(function ($query) use ($reference, $transactionId): void {
                if ($reference) {
                    $query->where('payment_reference', $reference)
                        ->orWhere('qr_payment_code', $reference)
                        ->orWhere('ma_hoa_don', $reference)
                        ->orWhere('provider_intent_id', $reference);
                }

                if ($transactionId) {
                    $query->orWhere('provider_transaction_id', $transactionId);
                }
            })
            ->latest('id')
            ->first();
    }

    protected function resolveDisplayStatus(bool $gatewaySuccess, ?CongNoPayment $invoice): string
    {
        if (! $gatewaySuccess) {
            return 'failed';
        }

        if ($invoice?->status === InvoicePaymentStatusEnum::DA_THANH_TOAN) {
            return 'success';
        }

        return 'processing';
    }

    protected function verifySignature(string $provider, Request $request): string
    {
        $signatureField = $provider === 'vnpay' ? 'vnp_SecureHash' : 'signature';

        if (! $request->query($signatureField)) {
            return 'missing';
        }

        try {
            match ($provider) {
                'vnpay' => app(VNPayPaymentService::class)->verifyIpnSignature($request->query()),
                'momo' => app(MoMoPaymentService::class)->verifyIpnSignature($request->query()),
            };

            return 'valid';
        } catch (\RuntimeException $exception) {
            return str_contains(strtolower($exception->getMessage()), 'configured') ? 'unavailable' : 'invalid';
        } catch (\Throwable) {
            return 'invalid';
        }
    }

    protected function parseGatewayDate(mixed $value): ?Carbon
    {
        $value = $this->cleanString($value);

        if (! $value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('YmdHis', $value, 'Asia/Ho_Chi_Minh');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseMoMoResponseTime(mixed $value): ?Carbon
    {
        if (! is_numeric($value)) {
            return null;
        }

        try {
            return Carbon::createFromTimestampMs((int) $value)->timezone('Asia/Ho_Chi_Minh');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function cleanString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}