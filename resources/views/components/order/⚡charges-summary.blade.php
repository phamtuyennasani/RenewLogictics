<?php

use App\Actions\Order\RecordOrderEditHistoryAction;
use App\Models\Order;
use App\Support\OrderAccess;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public Order $order;
    public array $paymentForm = [];

    public function mount(): void
    {
        $this->fillPaymentForm();
    }

    public function fillPaymentForm(): void
    {
        $this->paymentForm = [
            'cost' => $this->paymentTotal($this->order->payment_cuocvon),
            'base' => $this->paymentTotal($this->order->payment_cuocgoc),
            'sale' => $this->paymentTotal($this->order->payment_cuocban),
            'profit' => $this->paymentTotal($this->order->payment_loinhuan, 'loinhuan') ?: ($this->paymentTotal($this->order->payment_cuocban) - $this->paymentTotal($this->order->payment_cuocvon)),
        ];
    }

    public function money(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 0) . ' đ' : '—';
    }

    public function paymentTotal(?array $payment, string $preferredKey = 'total_tongcuoc'): float
    {
        if (!$payment) {
            return 0;
        }

        $preferred = data_get($payment, $preferredKey);

        if (is_numeric($preferred)) {
            return (float) $preferred;
        }

        return (float) data_get($payment, 'tongcuoc', 0);
    }

    public function canEditPayment(): bool
    {
        return OrderAccess::canEditPayment(auth()->user(), $this->order);
    }

    public function savePayment(): void
    {
        abort_unless($this->canEditPayment(), 403);

        OrderAccess::assignCsOnEdit(auth()->user(), $this->order);

        $this->validate([
            'paymentForm.cost' => 'required|numeric|min:0',
            'paymentForm.base' => 'required|numeric|min:0',
            'paymentForm.sale' => 'required|numeric|min:0',
            'paymentForm.profit' => 'required|numeric',
        ]);

        $before = $this->paymentSnapshot();
        $cost = $this->normalizeMoneyValue($this->paymentForm['cost'] ?? 0);
        $base = $this->normalizeMoneyValue($this->paymentForm['base'] ?? 0);
        $sale = $this->normalizeMoneyValue($this->paymentForm['sale'] ?? 0);
        $profit = $this->normalizeMoneyValue($this->paymentForm['profit'] ?? 0);

        $this->order->forceFill([
            'payment_cuocvon' => array_merge($this->order->payment_cuocvon ?? [], [
                'total_tongcuoc' => $cost,
                'tongcuoc' => $cost,
            ]),
            'payment_cuocgoc' => array_merge($this->order->payment_cuocgoc ?? [], [
                'total_tongcuoc' => $base,
                'tongcuoc' => $base,
            ]),
            'payment_cuocban' => array_merge($this->order->payment_cuocban ?? [], [
                'total_tongcuoc' => $sale,
                'tongcuoc' => $sale,
            ]),
            'payment_loinhuan' => array_merge($this->order->payment_loinhuan ?? [], [
                'cuocvon' => $cost,
                'cuocgoc' => $base,
                'cuocban' => $sale,
                'loinhuan' => $profit,
            ]),
        ])->save();

        $this->order->refresh();
        RecordOrderEditHistoryAction::execute($this->order, 'edit_payment', 'payment', $before, $this->paymentSnapshot(), 'sửa payment');
        $this->fillPaymentForm();
        $this->dispatch('order-history-updated');

        Flux::modal('edit-payment')->close();
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật cước & thanh toán.', variant: 'success');
    }

    protected function paymentSnapshot(): array
    {
        return [
            'cuoc_von' => $this->paymentTotal($this->order->payment_cuocvon),
            'cuoc_goc' => $this->paymentTotal($this->order->payment_cuocgoc),
            'cuoc_ban' => $this->paymentTotal($this->order->payment_cuocban),
            'loi_nhuan' => $this->paymentTotal($this->order->payment_loinhuan, 'loinhuan') ?: ($this->paymentTotal($this->order->payment_cuocban) - $this->paymentTotal($this->order->payment_cuocvon)),
        ];
    }

    protected function normalizeMoneyValue(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^\d.-]/', '', (string) $value);

        return $normalized === '' || $normalized === '-' ? 0 : (float) $normalized;
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
    $cost = $this->paymentTotal($order->payment_cuocvon);
    $base = $this->paymentTotal($order->payment_cuocgoc);
    $sale = $this->paymentTotal($order->payment_cuocban);
    $profit = $this->paymentTotal($order->payment_loinhuan, 'loinhuan');
@endphp

<section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
    <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-neutral-900 uppercase">Cước & thanh toán</h2>
            <p class="text-xs text-neutral-500">Tóm tắt cước vốn, cước gốc, cước bán và lợi nhuận</p>
        </div>
        @if($this->canEditPayment())
            <flux:modal.trigger name="edit-payment">
                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Sửa payment">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                    </svg>
                </button>
            </flux:modal.trigger>
        @endif
    </div>
    <div class="space-y-3 p-5">
        <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-3">
            <span class="text-sm text-neutral-500">Cước vốn</span>
            <span class="text-sm font-semibold text-neutral-800">{{ $this->money($cost) }}</span>
        </div>
        <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-3">
            <span class="text-sm text-neutral-500">Cước gốc</span>
            <span class="text-sm font-semibold text-neutral-800">{{ $this->money($base) }}</span>
        </div>
        <div class="flex items-center justify-between rounded-lg bg-primary-50 px-4 py-3">
            <span class="text-sm text-primary-600">Cước bán</span>
            <span class="text-sm font-semibold text-primary-700">{{ $this->money($sale) }}</span>
        </div>
        <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-4 py-3">
            <span class="text-sm text-emerald-600">Lợi nhuận</span>
            <span class="text-sm font-semibold text-emerald-700">{{ $this->money($profit ?: ($sale - $cost)) }}</span>
        </div>
        <div class="grid grid-cols-2 gap-3 pt-2">
            <div class="rounded-lg border border-neutral-100 p-3">
                <p class="text-xs text-neutral-400">Kế toán</p>
                <p class="mt-1 text-sm font-semibold {{ $order->ketoan_success ? 'text-emerald-700' : 'text-amber-700' }}">{{ $order->ketoan_success ? 'Đã xác nhận' : 'Đang chờ' }}</p>
            </div>
            <div class="rounded-lg border border-neutral-100 p-3">
                <p class="text-xs text-neutral-400">Sale</p>
                <p class="mt-1 text-sm font-semibold {{ $order->sale_success ? 'text-emerald-700' : 'text-amber-700' }}">{{ $order->sale_success ? 'Đã xác nhận' : 'Đang chờ' }}</p>
            </div>
        </div>
    </div>

    <flux:modal name="edit-payment" class="w-full max-w-2xl">
        <form wire:submit="savePayment" class="space-y-6">
            <div>
                <flux:heading size="lg">Sửa cước & thanh toán</flux:heading>
                <flux:subheading>Cập nhật nhanh các tổng tiền đang hiển thị trong đơn.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Cước vốn</flux:label>
                    <flux:input type="number" min="0" step="1" wire:model="paymentForm.cost" />
                    @error('paymentForm.cost') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>Cước gốc</flux:label>
                    <flux:input type="number" min="0" step="1" wire:model="paymentForm.base" />
                    @error('paymentForm.base') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>Cước bán</flux:label>
                    <flux:input type="number" min="0" step="1" wire:model="paymentForm.sale" />
                    @error('paymentForm.sale') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>Lợi nhuận</flux:label>
                    <flux:input type="number" step="1" wire:model="paymentForm.profit" />
                    @error('paymentForm.profit') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Hủy</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Lưu</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
