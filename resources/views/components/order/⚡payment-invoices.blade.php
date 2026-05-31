<?php

use App\Enums\InvoicePaymentStatusEnum;
use App\Models\CongNoPayment;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Order $order;

    #[Computed]
    public function invoices(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->order->congNoPayments()
            ->with(['user:id,fullname,username', 'paymentConfirmer:id,fullname,username'])
            ->latest('id')
            ->get();
    }

    /**
     * Section "Hóa đơn thu" chỉ áp dụng cho đơn khách lẻ và chỉ hiển thị
     * khi đã phát sinh ít nhất 1 hóa đơn — đơn của đại lý/khách hàng có công
     * nợ riêng nên không cần xem ở đây.
     */
    #[Computed]
    public function shouldDisplay(): bool
    {
        return $this->order->isWalkIn() && $this->invoices->isNotEmpty();
    }

    public function money(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 0, ',', '.') . ' đ' : '—';
    }

    public function render()
    {
        return $this->view();
    }
};

?>

<div>
@if ($this->shouldDisplay)
<section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
    <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-neutral-900 uppercase">Hóa đơn thu</h2>
            <p class="text-xs text-neutral-500">Lịch sử hóa đơn thanh toán của đơn hàng</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-700">{{ $this->invoices->count() }} hóa đơn</span>
        </div>
    </div>

    @if($this->invoices->isNotEmpty())
        <div class="divide-y divide-neutral-100">
            @foreach($this->invoices as $invoice)
                @php
                    $status = $invoice->status ?? InvoicePaymentStatusEnum::CHO_DUYET;
                @endphp
                <div class="px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm font-semibold text-neutral-900">{{ $invoice->ma_hoa_don ?? '-' }}</span>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $status->color() }}">
                                    {{ $status->label() }}
                                </span>
                            </div>
                            <p class="mt-1 text-lg font-bold text-primary-700">{{ $this->money($invoice->amount) }}</p>
                        </div>
                        <div class="text-right text-xs text-neutral-500">
                            <p>Tạo: {{ $invoice->created_at?->format('d/m/Y H:i') ?? '-' }}</p>
                            @if($invoice->paid_at)
                                <p class="text-emerald-600">Thanh toán: {{ $invoice->paid_at->format('d/m/Y H:i') }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-500">
                        @if($invoice->user)
                            <span>Người tạo: <span class="font-medium text-neutral-700">{{ $invoice->user->fullname ?: $invoice->user->username }}</span></span>
                        @endif
                        @if($invoice->method)
                            <span>Phương thức: <span class="font-medium text-neutral-700">{{ $invoice->method === 'cash' ? 'Tiền mặt' : ($invoice->payment_provider ?: 'Online') }}</span></span>
                        @endif
                        @if($invoice->paymentConfirmer)
                            <span>Xác nhận bởi: <span class="font-medium text-neutral-700">{{ $invoice->paymentConfirmer->fullname ?: $invoice->paymentConfirmer->username }}</span></span>
                        @endif
                    </div>

                    @if($status === InvoicePaymentStatusEnum::KHONG_CHAP_NHAN && $invoice->payment_rejection_reason)
                        <div class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">
                            <span class="font-semibold">Lý do từ chối:</span> {{ $invoice->payment_rejection_reason }}
                        </div>
                    @endif

                    @if($status === InvoicePaymentStatusEnum::HUY && $invoice->cancel_reason)
                        <div class="mt-2 rounded-lg bg-neutral-100 px-3 py-2 text-xs text-neutral-600">
                            <span class="font-semibold">Lý do hủy:</span> {{ $invoice->cancel_reason }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</section>
@endif
</div>
