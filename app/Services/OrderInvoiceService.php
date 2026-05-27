<?php

namespace App\Services;

use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Models\CongNoPayment;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\InvoiceCodeGenerator;
use Illuminate\Support\Facades\DB;

class OrderInvoiceService
{
    public function __construct(
        protected InvoiceCodeGenerator $codeGenerator,
    ) {}

    public function createForOrder(Order $order, User $user, ?string $note = null): CongNoPayment
    {
        if (! $order->isWalkIn()) {
            throw new \RuntimeException('Chỉ đơn hàng vãng lai mới được tạo hóa đơn trực tiếp.');
        }

        if ($order->isInvoiceLocked()) {
            throw new \RuntimeException('Đơn hàng đã có hóa đơn đang xử lý.');
        }

        $amount = $this->getOrderSaleTotal($order);

        if ($amount <= 0) {
            throw new \RuntimeException('Tổng cước bán phải lớn hơn 0 để tạo hóa đơn.');
        }

        return DB::transaction(function () use ($order, $user, $note, $amount) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->isInvoiceLocked()) {
                throw new \RuntimeException('Đơn hàng đã có hóa đơn đang xử lý.');
            }

            $invoice = CongNoPayment::create([
                'id_order' => $locked->id,
                'id_congno' => null,
                'id_user' => $user->id,
                'amount' => $amount,
                'note' => $note ?: 'Hóa đơn thu đơn hàng ' . ($locked->id_bill ?: 'ORDER-' . $locked->id),
                'status' => InvoicePaymentStatusEnum::DA_DUYET->value,
                'loai_hoa_don' => InvoiceTypeEnum::THU->value,
                'approved_by' => $user->id,
                'ngay_duyet' => now(),
                'order_snapshot' => $locked->payment_cuocban,
            ]);

            $invoice->writeStatusLog(
                'direct_invoice_created',
                null,
                InvoicePaymentStatusEnum::DA_DUYET,
                $user->id,
                'Tạo hóa đơn trực tiếp từ đơn hàng (auto-approve)'
            );

            return $invoice;
        });
    }

    public function cancelInvoice(CongNoPayment $invoice, User $user, string $reason): void
    {
        if (! $invoice->hasDirectOrder()) {
            throw new \RuntimeException('Chỉ hủy được hóa đơn đơn lẻ qua service này.');
        }

        if (! $invoice->canCancel($user)) {
            throw new \RuntimeException('Không có quyền hủy hóa đơn này.');
        }

        DB::transaction(function () use ($invoice, $user, $reason) {
            $locked = CongNoPayment::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $fromStatus = $locked->status;

            $locked->forceFill([
                'status' => InvoicePaymentStatusEnum::HUY->value,
                'cancelled_at' => now(),
                'id_cancelled_by' => $user->id,
                'cancel_reason' => $reason,
                'payment_url' => null,
                'qr_url' => null,
                'qr_expires_at' => null,
            ])->save();

            $locked->writeStatusLog('cancelled', $fromStatus, InvoicePaymentStatusEnum::HUY, $user->id, $reason);
        });
    }

    public function markPaid(CongNoPayment $invoice, User $user): void
    {
        if (! $invoice->hasDirectOrder()) {
            throw new \RuntimeException('Chỉ xử lý hóa đơn đơn lẻ qua service này.');
        }

        DB::transaction(function () use ($invoice, $user) {
            $locked = CongNoPayment::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($locked->id_order)->lockForUpdate()->firstOrFail();

            $fromStatus = $locked->status;

            $locked->forceFill([
                'status' => InvoicePaymentStatusEnum::DA_THANH_TOAN->value,
                'paid_at' => now(),
                'id_ketoan' => $user->id,
                'payment_confirmed_by' => $user->id,
            ])->save();

            $locked->writeStatusLog('payment_confirmed', $fromStatus, InvoicePaymentStatusEnum::DA_THANH_TOAN, $user->id);

            $order->forceFill([
                'customer_payment_status' => DebtStatusEnum::DA_THANH_TOAN->value,
                'customer_paid_at' => now(),
            ])->save();
        });
    }

    public function syncOrderPaymentStatus(CongNoPayment $invoice): void
    {
        if (! $invoice->hasDirectOrder()) {
            return;
        }

        $order = $invoice->order;

        if (! $order) {
            return;
        }

        if ($invoice->status === InvoicePaymentStatusEnum::DA_THANH_TOAN) {
            $order->forceFill([
                'customer_payment_status' => DebtStatusEnum::DA_THANH_TOAN->value,
                'customer_paid_at' => $invoice->paid_at ?? now(),
            ])->save();
        }
    }

    public function getOrderSaleTotal(Order $order): float
    {
        $payment = $order->payment_cuocban;

        if (! $payment) {
            return 0;
        }

        $total = data_get($payment, 'total_tongcuoc');

        if (is_numeric($total)) {
            return (float) $total;
        }

        return (float) data_get($payment, 'tongcuoc', 0);
    }

    public function canCreateInvoice(Order $order, User $user): bool
    {
        if (! $order->isWalkIn()) {
            return false;
        }

        if ($order->isInvoiceLocked()) {
            return false;
        }

        if ($this->getOrderSaleTotal($order) <= 0) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'manager', 'ketoan', 'sale']);
    }
}
