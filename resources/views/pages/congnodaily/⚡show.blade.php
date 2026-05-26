<?php

use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Models\CongNoDaiLy;
use App\Models\CongNoDaiLyPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Flux\Flux;

new #[Layout('layouts.app')] #[Title('Chi tiết công nợ đại lý')] class extends Component {
    public CongNoDaiLy $debt;
    public string $invoiceAmount = '';
    public string $invoiceNote = '';

    public function mount(string $id): void
    {
        $this->debt = $this->loadDebt($id);

        abort_unless($this->canView(), 403);
    }

    protected function loadDebt(string $id): CongNoDaiLy
    {
        return CongNoDaiLy::query()
            ->with([
                'daily:id,namevi,nameen',
                'creator:id,fullname,username,code',
                'ketoan:id,fullname,username,code',
                'details.order.dichvu:id,namevi',
                'details.order.chiNhanhNhanHang:id,namevi',
                'details.order.packages',
                'payments.user:id,fullname,username,code',
                'payments.ketoan:id,fullname,username,code',
                'payments.approver:id,fullname,username,code',
                'payments.paymentConfirmer:id,fullname,username,code',
                'payments.cancelledBy:id,fullname,username,code',
            ])
            ->where(function ($query) use ($id) {
                $query->where('uuid', $id);

                if (ctype_digit($id)) {
                    $query->orWhere('id', (int) $id);
                }
            })
            ->firstOrFail();
    }

    public function createPaymentInvoice(): void
    {
        abort_unless($this->canManage(), 403);

        if (! $this->debt->canCreatePaymentInvoice()) {
            Flux::toast(heading: 'Chưa chốt cước', text: 'Cần chốt cước công nợ trước khi tạo hóa đơn chi.', variant: 'warning');
            return;
        }

        $data = $this->validate([
            'invoiceAmount' => ['required', 'regex:/^[0-9.,]+$/'],
            'invoiceNote' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'invoiceAmount' => 'Số tiền hóa đơn',
        ]);

        $amount = round($this->number($data['invoiceAmount']), 2);
        if ($amount <= 0) {
            Flux::toast(heading: 'Số tiền không hợp lệ', text: 'Vui lòng nhập số tiền lớn hơn 0.', variant: 'warning');
            return;
        }

        try {
            DB::transaction(function () use ($amount, $data) {
                $debt = CongNoDaiLy::query()->whereKey($this->debt->id)->lockForUpdate()->firstOrFail();
                $available = $debt->availableForNewInvoice();

                if ($amount > $available + 0.5) {
                    throw new \RuntimeException(sprintf('Số tiền vượt mức cho phép. Tối đa còn lại: %s đ.', number_format($available, 0, ',', '.')));
                }

                CongNoDaiLyPayment::create([
                    'id_congno_daily' => $debt->id,
                    'id_user' => auth()->id(),
                    'amount' => $amount,
                    'due_at' => $debt->hanthanhtoan,
                    'note' => $data['invoiceNote'] ?: 'Hóa đơn chi cho đại lý ' . ($debt->daily?->namevi ?: $debt->daily?->nameen ?: ''),
                    'status' => InvoicePaymentStatusEnum::CHO_DUYET->value,
                    'loai_hoa_don' => InvoiceTypeEnum::CHI->value,
                ]);
            });
        } catch (\RuntimeException $exception) {
            Flux::toast(heading: 'Không thể tạo', text: $exception->getMessage(), variant: 'warning');
            return;
        }

        $this->invoiceAmount = '';
        $this->invoiceNote = '';
        $this->reloadDebt();

        Flux::toast(heading: 'Đã tạo hóa đơn chi', text: 'Hóa đơn đã được lưu ở trạng thái Mới tạo.', variant: 'success');
    }

    public function markPaid(int $invoiceId): void
    {
        abort_unless($this->canManage(), 403);

        DB::transaction(function () use ($invoiceId) {
            $invoice = $this->debt->payments()->whereKey($invoiceId)->lockForUpdate()->firstOrFail();

            if (! $invoice->canMarkPaid(auth()->user())) {
                throw new \RuntimeException('Hóa đơn không thể chuyển sang đã thanh toán.');
            }

            $fromStatus = $invoice->status;

            $invoice->forceFill([
                'status' => InvoicePaymentStatusEnum::DA_THANH_TOAN->value,
                'paid_at' => now(),
                'ngay_duyet' => now(),
                'id_ketoan' => auth()->id(),
                'payment_confirmed_by' => auth()->id(),
                'method' => $invoice->method ?: 'bank_transfer',
            ])->save();

            $invoice->writeStatusLog('expense_paid', $fromStatus, InvoicePaymentStatusEnum::DA_THANH_TOAN, auth()->id());

            $debt = CongNoDaiLy::query()->whereKey($this->debt->id)->lockForUpdate()->firstOrFail();
            $debt->syncPaidAmountFromPayments();
            $debt->refresh();

            $isFullyPaid = (float) $debt->remaining_amount <= 0
                ? DebtStatusEnum::DA_THANH_TOAN->value
                : DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN->value;

            $debt->orders()->update([
                'agency_payment_status' => $isFullyPaid,
                'agency_paid_at' => $isFullyPaid === DebtStatusEnum::DA_THANH_TOAN->value ? now() : null,
            ]);
        });

        $this->reloadDebt();
        Flux::toast(heading: 'Đã đánh dấu đã chi', text: 'Hóa đơn đã được chuyển sang trạng thái Đã thanh toán.', variant: 'success');
    }

    public function cancelInvoice(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId) {
            $invoice = $this->debt->payments()->whereKey($invoiceId)->lockForUpdate()->firstOrFail();

            if (! $invoice->canCancel(auth()->user())) {
                throw new \RuntimeException('Bạn không có quyền hủy hóa đơn này.');
            }

            $fromStatus = $invoice->status;

            $invoice->forceFill([
                'status' => InvoicePaymentStatusEnum::HUY->value,
                'cancelled_at' => now(),
                'id_cancelled_by' => auth()->id(),
            ])->save();

            $invoice->writeStatusLog('cancelled', $fromStatus, InvoicePaymentStatusEnum::HUY, auth()->id());
        });

        $this->reloadDebt();
        Flux::toast(heading: 'Đã hủy hóa đơn', text: 'Hóa đơn đã được hủy.', variant: 'success');
    }

    public function removeOrder(int $detailId): void
    {
        abort_unless($this->canManage(), 403);

        if ($this->debt->canCreatePaymentInvoice()) {
            Flux::toast(heading: 'Không thể xóa', text: 'Công nợ đã chốt cước, không thể gỡ order.', variant: 'warning');
            return;
        }

        $detail = $this->debt->details()->whereKey($detailId)->firstOrFail();
        $detail->delete();
        $this->debt->syncTotalsFromDetails();
        $this->reloadDebt();

        Flux::toast(heading: 'Đã xóa order', text: 'Order đã được gỡ khỏi công nợ đại lý.', variant: 'success');
    }

    public function cancelDebt(): void
    {
        abort_unless($this->canManage(), 403);

        if ($this->debt->status === DebtStatusEnum::DA_THANH_TOAN) {
            Flux::toast(heading: 'Không thể hủy', text: 'Công nợ đã thanh toán không thể hủy.', variant: 'warning');
            return;
        }

        if ($this->debt->payments()
            ->whereIn('status', InvoicePaymentStatusEnum::pendingValues())
            ->exists()) {
            Flux::toast(heading: 'Không thể hủy', text: 'Công nợ còn hóa đơn đang xử lý.', variant: 'warning');
            return;
        }

        DB::transaction(function () {
            $this->debt->orders()->update([
                'agency_payment_status' => null,
                'agency_paid_at' => null,
            ]);

            $this->debt->delete();
        });

        Flux::toast(heading: 'Đã hủy công nợ', text: 'Công nợ đại lý đã được hủy và các order được giải phóng.', variant: 'success');
        $this->redirectRoute('congno.daily.index', navigate: true);
    }

    protected function reloadDebt(): void
    {
        $this->debt = $this->loadDebt((string) $this->debt->id);
    }

    public function canView(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'ketoan']);
    }

    public function canManage(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'ketoan']);
    }

    protected function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^\d.,-]/', '', (string) ($value ?? 0));

        if ($normalized === '' || $normalized === '-') {
            return 0;
        }

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($normalized, ',');
            $lastDot = strrpos($normalized, '.');

            if ($lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            if (preg_match('/^-?\d{1,3}(,\d{3})+$/', $normalized)) {
                $normalized = str_replace(',', '', $normalized);
            } else {
                $normalized = str_replace(',', '.', $normalized);
            }
        } elseif ($hasDot && preg_match('/^-?\d{1,3}(\.\d{3})+$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
        }

        return (float) $normalized;
    }

    public function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' đ';
    }

    public function getAvailableForNewInvoiceProperty(): float
    {
        return $this->debt->availableForNewInvoice();
    }

    public function getPendingInvoicesTotalProperty(): float
    {
        return $this->debt->pendingInvoicesTotal();
    }

    public function render()
    {
        return $this->view();
    }
};

?>

<div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route('congno.daily.index') }}" wire:navigate class="text-sm font-semibold text-primary-700 hover:text-primary-800">← Danh sách công nợ đại lý</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-neutral-950">{{ $debt->sohoadon }}</h1>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $debt->status->color() }}">{{ $debt->status->label() }}</span>
                <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Hóa đơn CHI</span>
            </div>
            <p class="mt-1 text-sm text-neutral-500">{{ $debt->daily?->namevi ?: $debt->daily?->nameen ?: 'Chưa rõ đại lý' }}</p>
        </div>
        @if ($this->canManage())
            <div class="flex flex-wrap items-center gap-2">
                @if ($debt->status !== DebtStatusEnum::DA_THANH_TOAN)
                    <button type="button" wire:click="cancelDebt" wire:confirm="Hủy công nợ này? Các order sẽ được giải phóng để tạo công nợ mới." class="rounded-xl border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-100">Hủy công nợ</button>
                @endif
            </div>
        @endif
    </div>

    <div class="grid gap-3 md:grid-cols-4">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-xs">
            <p class="text-xs font-medium uppercase text-neutral-500">Tổng cước vốn</p>
            <p class="mt-2 text-xl font-bold text-neutral-950">{{ $this->money($debt->total_cuocvon) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 shadow-xs">
            <p class="text-xs font-medium uppercase text-emerald-700">Đã chi</p>
            <p class="mt-2 text-xl font-bold text-emerald-800">{{ $this->money($debt->paid_amount) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4 shadow-xs">
            <p class="text-xs font-medium uppercase text-amber-700">HĐ chờ thanh toán</p>
            <p class="mt-2 text-xl font-bold text-amber-800">{{ $this->money($this->pendingInvoicesTotal) }}</p>
        </div>
        <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 shadow-xs">
            <p class="text-xs font-medium uppercase text-blue-700">Còn có thể tạo HĐ</p>
            <p class="mt-2 text-xl font-bold text-blue-800">{{ $this->money($this->availableForNewInvoice) }}</p>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-5">
            <div class="rounded-2xl border border-neutral-200 bg-white shadow-xs">
                <div class="border-b border-neutral-100 px-5 py-4">
                    <h2 class="font-bold text-neutral-950">Danh sách order</h2>
                    <p class="mt-1 text-sm text-neutral-500">{{ $debt->tungay?->format('d/m/Y') }} - {{ $debt->denngay?->format('d/m/Y') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100 text-sm">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Mã order</th>
                                <th class="px-4 py-3 text-left font-semibold">Dịch vụ</th>
                                <th class="px-4 py-3 text-right font-semibold">Cân nặng</th>
                                <th class="px-4 py-3 text-right font-semibold">Cước vốn</th>
                                <th class="px-4 py-3 text-right font-semibold">Cước bán</th>
                                <th class="px-4 py-3 text-right font-semibold"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse ($debt->details as $detail)
                                <tr class="hover:bg-neutral-50/70">
                                    <td class="px-4 py-4">
                                        <a href="{{ route('orders.show', $detail->order?->uuid) }}" wire:navigate class="font-bold text-primary-700">{{ $detail->order?->id_bill ?: data_get($detail->snapshot, 'order_code') }}</a>
                                        <div class="text-xs text-neutral-500">{{ $detail->order?->created_at?->format('d/m/Y H:i') }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-neutral-700">
                                        {{ $detail->order?->dichvu?->namevi ?: '-' }}
                                        <div class="text-xs text-neutral-500">{{ $detail->order?->chiNhanhNhanHang?->namevi }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-right font-semibold">{{ number_format((float) $detail->weight, 2, ',', '.') }} kg</td>
                                    <td class="px-4 py-4 text-right font-semibold text-neutral-950">{{ $this->money($detail->cuocvon) }}</td>
                                    <td class="px-4 py-4 text-right text-neutral-700">{{ $this->money($detail->cuocban) }}</td>
                                    <td class="px-4 py-4 text-right">
                                        @if ($this->canManage() && ! $debt->canCreatePaymentInvoice())
                                            <button type="button" wire:click="removeOrder({{ $detail->id }})" wire:confirm="Gỡ order này khỏi công nợ đại lý?" class="rounded-lg px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Gỡ</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-neutral-500">Công nợ chưa có order.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if (! $debt->canCreatePaymentInvoice())
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    <p class="font-semibold">Chưa thể tạo hóa đơn chi.</p>
                    <p class="mt-1">Cần chốt cước công nợ trước khi tạo hóa đơn thanh toán cho đại lý.</p>
                </div>
            @else
                <div class="rounded-2xl border border-neutral-200 bg-white shadow-xs">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
                        <div>
                            <h2 class="font-bold text-neutral-950">Hóa đơn thanh toán (Chi)</h2>
                            <p class="mt-1 text-sm text-neutral-500">Mỗi hóa đơn là một đợt ta thanh toán cho đại lý.</p>
                        </div>
                        <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-700">{{ $debt->payments->count() }} hóa đơn</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-[960px] w-full divide-y divide-neutral-100 text-sm">
                            <thead class="bg-neutral-50 text-xs uppercase text-neutral-500">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">Mã HĐ</th>
                                    <th class="px-4 py-3 text-left font-semibold">Ngày tạo</th>
                                    <th class="px-4 py-3 text-left font-semibold">Người tạo</th>
                                    <th class="px-4 py-3 text-right font-semibold">Số tiền</th>
                                    <th class="px-4 py-3 text-left font-semibold">Trạng thái</th>
                                    <th class="px-4 py-3 text-left font-semibold">Người xác nhận</th>
                                    <th class="px-4 py-3 text-left font-semibold">Ghi chú</th>
                                    <th class="px-4 py-3 text-right font-semibold"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                @forelse ($debt->payments->sortByDesc('created_at') as $invoice)
                                    <tr class="hover:bg-neutral-50/70">
                                        <td class="px-4 py-4 align-top">
                                            <p class="font-bold text-neutral-950">{{ $invoice->ma_hoa_don }}</p>
                                            @if ($invoice->paid_at)
                                                <p class="mt-1 text-xs text-neutral-500">Chi: {{ $invoice->paid_at->format('d/m/Y H:i') }}</p>
                                            @endif
                                            @if ($invoice->cancelled_at)
                                                <p class="mt-1 text-xs text-red-600">Hủy: {{ $invoice->cancelled_at->format('d/m/Y H:i') }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 align-top text-neutral-700">{{ $invoice->created_at?->format('d/m/Y H:i') }}</td>
                                        <td class="px-4 py-4 align-top text-neutral-700">{{ $invoice->user?->fullname ?: $invoice->user?->username ?: '-' }}</td>
                                        <td class="px-4 py-4 text-right align-top font-bold text-neutral-950">{{ $this->money($invoice->amount) }}</td>
                                        <td class="px-4 py-4 align-top">
                                            <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $invoice->status?->color() }}">{{ $invoice->status?->label() }}</span>
                                        </td>
                                        <td class="px-4 py-4 align-top text-neutral-700">
                                            @if ($invoice->status === \App\Enums\InvoicePaymentStatusEnum::HUY)
                                                <span class="text-xs text-red-600">{{ $invoice->cancelledBy?->fullname ?: $invoice->cancelledBy?->username ?: '-' }}</span>
                                            @else
                                                <p>{{ $invoice->ketoan?->fullname ?: $invoice->ketoan?->username ?: '-' }}</p>
                                                @if ($invoice->paymentConfirmer)
                                                    <p class="mt-1 text-xs text-emerald-700">Xác nhận: {{ $invoice->paymentConfirmer->fullname ?: $invoice->paymentConfirmer->username }}</p>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 align-top text-xs text-neutral-600">{{ $invoice->note }}</td>
                                        <td class="px-4 py-4 text-right align-top">
                                            <div class="flex flex-col items-end gap-1">
                                                @if ($invoice->canMarkPaid(auth()->user()))
                                                    <button type="button" wire:click="markPaid({{ $invoice->id }})" wire:confirm="Xác nhận đã chi tiền cho đại lý?" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Đánh dấu đã chi</button>
                                                @endif
                                                @if ($invoice->canCancel(auth()->user()))
                                                    <button type="button" wire:click="cancelInvoice({{ $invoice->id }})" wire:confirm="Hủy hóa đơn này?" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Hủy</button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-10 text-center text-neutral-500">Chưa có hóa đơn chi nào.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-5">
            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-xs">
                <h2 class="font-bold text-neutral-950">Thông tin công nợ</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Đại lý</dt><dd class="font-semibold text-neutral-900">{{ $debt->daily?->namevi ?: $debt->daily?->nameen ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Người tạo</dt><dd class="font-semibold text-neutral-900">{{ $debt->creator?->fullname ?: $debt->creator?->username ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Kế toán</dt><dd class="font-semibold text-neutral-900">{{ $debt->ketoan?->fullname ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Ngày tạo</dt><dd class="font-semibold text-neutral-900">{{ $debt->ngaytaohoadon?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Ngày chốt</dt><dd class="font-semibold text-neutral-900">{{ $debt->ngaychothoadon?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Hạn thanh toán</dt><dd class="font-semibold text-neutral-900">{{ $debt->hanthanhtoan?->format('d/m/Y') ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Tham chiếu</dt><dd class="font-semibold text-neutral-900">{{ $debt->sohoadon_thamchieu ?: '-' }}</dd></div>
                </dl>
            </div>

            @if ($this->canManage() && $debt->canCreatePaymentInvoice() && $this->availableForNewInvoice > 0)
                <div class="rounded-2xl border border-rose-100 bg-white p-5 shadow-xs">
                    <h2 class="font-bold text-neutral-950">Tạo hóa đơn chi</h2>
                    <p class="mt-1 text-xs text-neutral-500">Tối đa còn lại: <span class="font-semibold text-neutral-800">{{ $this->money($this->availableForNewInvoice) }}</span></p>
                    <div class="mt-4 space-y-3">
                        <label class="block">
                            <span class="text-sm font-semibold text-neutral-700">Số tiền</span>
                            <input type="text" wire:model="invoiceAmount" placeholder="0" class="mt-1 h-10 w-full rounded-xl border border-neutral-200 px-3 text-sm font-semibold outline-none focus:border-rose-500">
                            @error('invoiceAmount') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-neutral-700">Ghi chú</span>
                            <textarea wire:model="invoiceNote" rows="3" placeholder="Ghi chú hóa đơn (vd: chi cho đợt thanh toán ...)" class="mt-1 w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-rose-500"></textarea>
                        </label>
                        <button type="button" wire:click="createPaymentInvoice" class="w-full rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700">Tạo hóa đơn chi</button>
                    </div>
                </div>
            @elseif ($this->canManage() && $debt->canCreatePaymentInvoice())
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-800">
                    Tất cả số tiền của công nợ này đã được phân bổ vào các hóa đơn.
                </div>
            @endif
        </div>
    </div>
</div>
