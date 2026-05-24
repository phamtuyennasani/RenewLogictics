<div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route('congno.index') }}" wire:navigate class="text-sm font-semibold text-primary-700 hover:text-primary-800">← Danh sách công nợ</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-neutral-950">{{ $debt->sohoadon }}</h1>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $debt->status->color() }}">{{ $debt->status->label() }}</span>
            </div>
            <p class="mt-1 text-sm text-neutral-500">{{ $debt->customer?->fullname ?: $debt->customer?->username ?: 'Chưa rõ khách hàng' }}</p>
        </div>
        @if ($this->canManage())
            <div class="flex flex-wrap items-center gap-2">
                @if (in_array($debt->status, [\App\Enums\DebtStatusEnum::MOI_TAO, \App\Enums\DebtStatusEnum::QUA_HAN], true))
                    <button type="button" wire:click="confirmDebt" class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700">Chốt cước</button>
                @endif
                @if ($debt->status !== \App\Enums\DebtStatusEnum::DA_THANH_TOAN)
                    <button type="button" wire:click="markOverdue" class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100">Quá hạn</button>
                @endif
            </div>
        @endif
    </div>

    <div class="grid gap-3 md:grid-cols-4">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-xs">
            <p class="text-xs font-medium uppercase text-neutral-500">Tổng cước</p>
            <p class="mt-2 text-xl font-bold text-neutral-950">{{ $this->money($debt->total_cuocban) }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 shadow-xs">
            <p class="text-xs font-medium uppercase text-emerald-700">Đã thanh toán</p>
            <p class="mt-2 text-xl font-bold text-emerald-800">{{ $this->money($debt->paid_amount) }}</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4 shadow-xs">
            <p class="text-xs font-medium uppercase text-amber-700">Còn lại</p>
            <p class="mt-2 text-xl font-bold text-amber-800">{{ $this->money($debt->remaining_amount) }}</p>
        </div>
        <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 shadow-xs">
            <p class="text-xs font-medium uppercase text-blue-700">Số order</p>
            <p class="mt-2 text-xl font-bold text-blue-800">{{ $debt->total_orders }}</p>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
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
                            <th class="px-4 py-3 text-right font-semibold">Tổng cước</th>
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
                                <td class="px-4 py-4 text-right font-semibold text-neutral-950">{{ $this->money($detail->cuocban) }}</td>
                                <td class="px-4 py-4 text-right">
                                    @if ($this->canManage() && $debt->status !== \App\Enums\DebtStatusEnum::DA_THANH_TOAN)
                                        <button type="button" wire:click="removeOrder({{ $detail->id }})" wire:confirm="Gỡ order này khỏi công nợ?" class="rounded-lg px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Gỡ</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-neutral-500">Công nợ chưa có order.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-5">
            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-xs">
                <h2 class="font-bold text-neutral-950">Thông tin công nợ</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Sale</dt><dd class="font-semibold text-neutral-900">{{ $debt->sale?->fullname ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Kế toán</dt><dd class="font-semibold text-neutral-900">{{ $debt->ketoan?->fullname ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Ngày tạo</dt><dd class="font-semibold text-neutral-900">{{ $debt->ngaytaohoadon?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Ngày chốt</dt><dd class="font-semibold text-neutral-900">{{ $debt->ngaychothoadon?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Hạn thanh toán</dt><dd class="font-semibold text-neutral-900">{{ $debt->hanthanhtoan?->format('d/m/Y') ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Tham chiếu</dt><dd class="font-semibold text-neutral-900">{{ $debt->sohoadon_thamchieu ?: '-' }}</dd></div>
                </dl>
            </div>

            @if ($this->canManage() && $debt->status !== \App\Enums\DebtStatusEnum::DA_THANH_TOAN)
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-xs">
                    <h2 class="font-bold text-neutral-950">Ghi nhận thanh toán</h2>
                    <div class="mt-4 space-y-3">
                        <label class="block">
                            <span class="text-sm font-semibold text-neutral-700">Số tiền</span>
                            <input type="text" wire:model="paymentAmount" class="mt-1 h-10 w-full rounded-xl border border-neutral-200 px-3 text-sm outline-none focus:border-primary-500">
                            @error('paymentAmount') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-neutral-700">Ngày thanh toán</span>
                            <input type="datetime-local" wire:model="paymentDate" class="mt-1 h-10 w-full rounded-xl border border-neutral-200 px-3 text-sm outline-none focus:border-primary-500">
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-neutral-700">Phương thức</span>
                            <input type="text" wire:model="paymentMethod" placeholder="Chuyển khoản, tiền mặt..." class="mt-1 h-10 w-full rounded-xl border border-neutral-200 px-3 text-sm outline-none focus:border-primary-500">
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-neutral-700">Mã tham chiếu</span>
                            <input type="text" wire:model="paymentReference" class="mt-1 h-10 w-full rounded-xl border border-neutral-200 px-3 text-sm outline-none focus:border-primary-500">
                        </label>
                        <textarea wire:model="paymentNote" rows="3" placeholder="Ghi chú thanh toán" class="w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-primary-500"></textarea>
                        <button type="button" wire:click="addPayment" class="w-full rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700">Lưu thanh toán</button>
                    </div>
                </div>
            @endif

            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-xs">
                <h2 class="font-bold text-neutral-950">Lịch sử thanh toán</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($debt->payments->sortByDesc('paid_at') as $payment)
                        <div class="rounded-xl border border-neutral-100 bg-neutral-50 px-3 py-2">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold text-neutral-950">{{ $this->money($payment->amount) }}</p>
                                <p class="text-xs text-neutral-500">{{ $payment->paid_at?->format('d/m/Y H:i') }}</p>
                            </div>
                            <p class="mt-1 text-xs text-neutral-500">{{ $payment->method ?: 'Thanh toán' }}{{ $payment->reference ? ' - '.$payment->reference : '' }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-neutral-500">Chưa có thanh toán.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
