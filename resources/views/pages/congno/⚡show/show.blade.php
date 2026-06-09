@php
    use App\Enums\InvoicePaymentStatusEnum;

    $customerCompany = data_get($debt->customer?->options, 'company', []);
    $customerName = data_get($customerCompany, 'company_name')
        ?: data_get($customerCompany, 'company_short_name')
        ?: $debt->customer?->fullname
        ?: $debt->customer?->username
        ?: 'Chưa rõ khách hàng';
    $customerCode = $debt->customer?->code ?: $debt->customer?->email ?: $debt->customer?->phone;
    $totalAmount = (float) $debt->total_cuocban;
    $paidAmount = (float) $debt->paid_amount;
    $remainingAmount = (float) $debt->remaining_amount;
    $paidPercent = $totalAmount > 0 ? min(100, round(($paidAmount / $totalAmount) * 100)) : 0;
    $remainingPercent = $totalAmount > 0 ? min(100, round(($remainingAmount / $totalAmount) * 100)) : 0;
    $canCreateInvoice = $debt->canCreatePaymentInvoice();
    $availableForInvoice = $this->availableForNewInvoice;
    $sortedInvoices = $debt->payments->sortByDesc(fn ($p) => $p->created_at?->timestamp ?? 0);
    $payingInvoice = $this->payingInvoice;
    $pendingAmount = (float) $this->pendingInvoicesTotal;
    $pendingPercent = $totalAmount > 0 ? min(100, round(($pendingAmount / $totalAmount) * 100)) : 0;
    $pendingInvoicesCount = $debt->payments
        ->filter(fn ($p) => in_array(
            $p->status instanceof InvoicePaymentStatusEnum ? $p->status->value : (string) $p->status,
            InvoicePaymentStatusEnum::pendingValues(),
            true
        ))
        ->count();
@endphp

<div class="space-y-5">
    <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
        <div class="border-b border-neutral-100 px-4 py-3 sm:px-5">
            <a href="{{ route('congno.index') }}" wire:navigate class="inline-flex items-center gap-2 text-sm font-semibold text-neutral-600 transition hover:text-primary-700">
                <flux:icon.arrow-left class="size-4" />
                Danh sách công nợ
            </a>
        </div>

        <div class="grid gap-5 px-4 py-5 sm:px-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $debt->status->color() }}">{{ $debt->status->label() }}</span>
                </div>
                <div class="mt-3 flex flex-col gap-2 xl:flex-row xl:items-end xl:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-neutral-500">Công nợ khách hàng</p>
                        <h1 class="mt-1 break-words text-2xl font-bold text-neutral-950 sm:text-3xl">{{ $debt->sohoadon }}</h1>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-neutral-600">
                            <span class="inline-flex min-w-0 items-center gap-1.5">
                                <flux:icon.user class="size-4 shrink-0 text-neutral-400" />
                                <span class="truncate font-semibold text-neutral-800">{{ $customerName }}</span>
                            </span>
                            @if ($customerCode)
                                <span class="inline-flex min-w-0 items-center gap-1.5">
                                    <flux:icon.identification class="size-4 shrink-0 text-neutral-400" />
                                    <span class="truncate">{{ $customerCode }}</span>
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1.5">
                                <flux:icon.calendar-days class="size-4 shrink-0 text-neutral-400" />
                                {{ $debt->tungay?->format('d/m/Y') ?: '-' }} - {{ $debt->denngay?->format('d/m/Y') ?: '-' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            @if ($this->canManage())
                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                    @if ($debt->status === \App\Enums\DebtStatusEnum::MOI_TAO)
                        <flux:modal.trigger name="refresh-orders">
                            <flux:button type="button" variant="filled" icon="arrow-path">
                                Làm mới
                            </flux:button>
                        </flux:modal.trigger>
                        <flux:button type="button" wire:click="confirmDebt" variant="primary" icon="check-circle">
                            Chốt cước
                        </flux:button>
                    @endif
                </div>
            @endif
        </div>

        <div class="border-t border-neutral-100 bg-neutral-50/70 px-4 py-4 sm:px-5">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-neutral-200 bg-white p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-neutral-500">Tổng cước</p>
                        <flux:icon.document-currency-dollar class="size-5 text-blue-600" />
                    </div>
                    <p class="mt-3 truncate text-2xl font-bold text-neutral-950">{{ $this->money($debt->total_cuocban) }}</p>
                    <p class="mt-1 text-xs font-medium text-neutral-500">{{ $debt->total_orders }} order trong phiếu</p>
                </div>

                <div class="rounded-lg border border-emerald-200 bg-white p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Đã thanh toán</p>
                        <flux:icon.check-circle class="size-5 text-emerald-600" />
                    </div>
                    <p class="mt-3 truncate text-2xl font-bold text-emerald-700">{{ $this->money($debt->paid_amount) }}</p>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-emerald-100">
                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ $paidPercent }}%"></div>
                    </div>
                </div>

                <div class="rounded-lg border border-amber-200 bg-white p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Còn lại</p>
                        <flux:icon.clock class="size-5 text-amber-600" />
                    </div>
                    <p class="mt-3 truncate text-2xl font-bold text-amber-700">{{ $this->money($debt->remaining_amount) }}</p>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-amber-100">
                        <div class="h-full rounded-full bg-amber-500" style="width: {{ $remainingPercent }}%"></div>
                    </div>
                </div>

                <div class="rounded-lg border {{ $pendingAmount > 0 ? 'border-blue-200' : 'border-neutral-200' }} bg-white p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-bold uppercase tracking-wide {{ $pendingAmount > 0 ? 'text-blue-700' : 'text-neutral-500' }}">Chờ thanh toán</p>
                        <flux:icon.clock class="size-5 {{ $pendingAmount > 0 ? 'text-blue-600' : 'text-neutral-500' }}" />
                    </div>
                    <p class="mt-3 truncate text-2xl font-bold {{ $pendingAmount > 0 ? 'text-blue-700' : 'text-neutral-950' }}">{{ $this->money($pendingAmount) }}</p>
                    <p class="mt-1 text-xs font-medium {{ $pendingAmount > 0 ? 'text-blue-600' : 'text-neutral-500' }}">{{ $pendingInvoicesCount }} hóa đơn đang chờ</p>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_25rem]">
        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-neutral-100 px-4 py-4 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-base font-bold text-neutral-950">Danh sách order</h2>
                    <p class="mt-1 text-sm text-neutral-500">Các đơn hàng được gom vào công nợ này</p>
                </div>
                <div class="inline-flex w-fit items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm font-semibold text-neutral-700">
                    <flux:icon.archive-box class="size-4 text-neutral-500" />
                    {{ $debt->total_orders }} order
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1320px] w-full divide-y divide-neutral-100 text-sm">
                    <thead class="bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Mã order</th>
                            <th class="px-4 py-3 text-left font-semibold">Người gửi</th>
                            <th class="px-4 py-3 text-left font-semibold">Người nhận</th>
                            <th class="px-4 py-3 text-left font-semibold">Quốc gia</th>
                            <th class="px-4 py-3 text-left font-semibold">Dịch vụ</th>
                            <th class="px-4 py-3 text-left font-semibold">Trạng thái bill</th>
                            <th class="px-4 py-3 text-right font-semibold">Cước bán</th>
                            <th class="px-4 py-3 text-right font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 bg-white">
                        @forelse ($debt->details as $detail)
                            @php
                                $order = $detail->order;
                                $sender = $order?->sender ?? [];
                                $receiver = $order?->receiver ?? [];
                                $senderCompany = data_get($sender, 'company') ?: data_get($sender, 'fullname') ?: '-';
                                $senderAddress = data_get($sender, 'address') ?: collect([
                                    data_get($sender, 'city'),
                                    data_get($sender, 'state'),
                                    data_get($sender, 'postcode'),
                                ])->filter()->implode(', ');
                                $receiverCompany = data_get($receiver, 'company') ?: data_get($receiver, 'fullname') ?: data_get($receiver, 'tenlienhe') ?: '-';
                                $receiverAddress = collect([
                                    data_get($receiver, 'address'),
                                    data_get($receiver, 'city'),
                                    data_get($receiver, 'state'),
                                    data_get($receiver, 'postcode'),
                                ])->filter()->implode(', ');
                                $country = $order?->receiverCountry ?: $order?->receiverCountryLegacy;
                                $countryName = $country?->name ?: data_get($receiver, 'country', '-');
                                $countryCode = $country?->iso2 ?: $country?->iso3;
                            @endphp
                            <tr class="transition hover:bg-neutral-50/80">
                                <td class="px-4 py-4 align-top">
                                    @if ($order?->uuid)
                                        <a href="{{ route('orders.show', $order->uuid) }}" wire:navigate class="font-bold text-primary-700 transition hover:text-primary-800">
                                            {{ $order->id_bill ?: data_get($detail->snapshot, 'order_code') }}
                                        </a>
                                    @else
                                        <span class="font-bold text-neutral-800">{{ data_get($detail->snapshot, 'order_code', '-') }}</span>
                                    @endif
                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-neutral-500">
                                        <span>{{ $order?->created_at?->format('d/m/Y H:i') ?: '-' }}</span>
                                        @if ($order?->id)
                                            <span class="font-medium text-neutral-400">#{{ $order->id }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <div class="max-w-[240px] truncate font-semibold text-neutral-900">{{ $senderCompany }}</div>
                                    <div class="mt-1 max-w-[260px] truncate text-xs text-neutral-500">{{ $senderAddress ?: '-' }}</div>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <div class="max-w-[240px] truncate font-semibold text-neutral-900">{{ $receiverCompany }}</div>
                                    <div class="mt-1 max-w-[300px] truncate text-xs text-neutral-500">{{ $receiverAddress ?: '-' }}</div>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <div class="max-w-[140px] truncate font-semibold text-neutral-900">{{ $countryName ?: '-' }}</div>
                                    @if ($countryCode)
                                        <div class="mt-1 text-xs text-neutral-500">{{ $countryCode }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <div class="max-w-[170px] truncate font-semibold text-neutral-900">{{ $order?->dichvu?->namevi ?: '-' }}</div>
                                    <div class="mt-1 max-w-[170px] truncate text-xs text-neutral-500">{{ $order?->chiNhanhNhanHang?->namevi ?: '-' }}</div>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $order?->bill_status?->color() ?? 'bg-neutral-100 text-neutral-700' }}">
                                        {{ $order?->bill_status?->label() ?? '-' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-right align-top font-bold text-neutral-950">{{ $this->money($detail->cuocban) }}</td>
                                <td class="px-4 py-4 text-right align-top">
                                    <div class="flex justify-end gap-2">
                                        @if ($this->canEditSaleCharge() && ! $debt->canCreatePaymentInvoice() && $debt->status !== \App\Enums\DebtStatusEnum::DA_THANH_TOAN && $order?->uuid)
                                            <button type="button" wire:click="openSaleChargeModal({{ $detail->id }})" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-2.5 text-xs font-semibold text-neutral-700 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700" title="Edit cước bán">
                                                <flux:icon.pencil-square class="size-4" />
                                                Cước bán
                                            </button>
                                        @endif
                                        @if ($this->canManage() && ! $debt->canCreatePaymentInvoice() && $debt->status !== \App\Enums\DebtStatusEnum::DA_THANH_TOAN)
                                            <button type="button" wire:click="removeOrder({{ $detail->id }})" wire:confirm="Gỡ order này khỏi công nợ?" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 bg-white text-neutral-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" title="Delete order" aria-label="Delete order">
                                                <flux:icon.trash class="size-4" />
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-neutral-100 text-neutral-500">
                                            <flux:icon.archive-box-x-mark class="size-5" />
                                        </div>
                                        <p class="mt-3 font-semibold text-neutral-900">Công nợ chưa có order</p>
                                        <p class="mt-1 text-sm text-neutral-500">Các order được thêm vào công nợ sẽ xuất hiện tại đây.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="space-y-5">
            <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-bold text-neutral-950">Thông tin công nợ</h2>
                    <flux:icon.clipboard-document-list class="size-5 text-neutral-400" />
                </div>
                <dl class="mt-4 divide-y divide-neutral-100 text-sm">
                    <div class="flex justify-between gap-4 py-3 first:pt-0">
                        <dt class="text-neutral-500">Sale</dt>
                        <dd class="text-right font-semibold text-neutral-900">{{ $debt->sale?->fullname ?: $debt->sale?->username ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-neutral-500">Kế toán</dt>
                        <dd class="text-right font-semibold text-neutral-900">
                            {{ $debt->ketoan?->fullname ?: $debt->ketoan?->username ?: '-' }}
                            @if ($this->hasDebtAdminPower())
                                <flux:modal.trigger name="reassign-accountant">
                                    <button type="button" class="ml-1 text-xs font-semibold text-primary-700 hover:text-primary-800">Đổi</button>
                                </flux:modal.trigger>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-neutral-500">Người tạo</dt>
                        <dd class="text-right font-semibold text-neutral-900">{{ $debt->creator?->fullname ?: $debt->creator?->username ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-neutral-500">Ngày tạo</dt>
                        <dd class="text-right font-semibold text-neutral-900">{{ $debt->ngaytaohoadon?->format('d/m/Y H:i') ?: '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-3">
                        <dt class="text-neutral-500">Tham chiếu</dt>
                        <dd class="text-right font-semibold text-neutral-900">
                            @php
                                $successfulEInvoice = $debt->einvoices->firstWhere('status', \App\Models\CongNoEInvoice::STATUS_SUCCESS);
                            @endphp
                            @if ($successfulEInvoice && $successfulEInvoice->invoice_number)
                                <span class="text-emerald-700">{{ $successfulEInvoice->invoice_number }}</span>
                            @else
                                {{ $debt->sohoadon_thamchieu ?: '-' }}
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            <livewire:debt.activity-history :debt="$debt" wire:key="customer-debt-activity-{{ $debt->id }}" />

            @if ($canCreateInvoice && $this->canManage() && $availableForInvoice > 0)
                <section class="rounded-xl border border-primary-100 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-bold text-neutral-950">Tạo hóa đơn thu</h2>
                        <flux:icon.banknotes class="size-5 text-primary-600" />
                    </div>
                    <p class="mt-1 text-xs text-neutral-500">Hóa đơn thu sẽ chờ kế toán duyệt trước khi gửi cho khách hàng thanh toán.</p>
                    <div class="mt-4 space-y-3">
                        <label class="block">
                            <span class="text-sm font-semibold text-neutral-700">Số tiền</span>
                            <input
                                type="text"
                                inputmode="numeric"
                                placeholder="0"
                                class="mt-1 h-10 w-full rounded-lg border border-neutral-200 px-3 text-sm font-semibold outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100"
                                x-data="{ raw: $wire.invoiceAmount }"
                                x-init="raw = $wire.invoiceAmount; $el.value = raw ? Number(String(raw).replace(/[^0-9]/g, '')).toLocaleString('vi-VN') : ''"
                                x-on:input="
                                    let digits = $el.value.replace(/[^0-9]/g, '');
                                    raw = digits;
                                    $el.value = digits ? Number(digits).toLocaleString('vi-VN') : '';
                                    $wire.invoiceAmount = digits;
                                "
                            >
                            <span class="mt-1 block text-xs text-neutral-500">Tối đa có thể tạo: <span class="font-semibold text-primary-700">{{ $this->money($availableForInvoice) }}</span></span>
                            @error('invoiceAmount') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-neutral-700">Ghi chú</span>
                            <textarea wire:model="invoiceNote" rows="3" placeholder="Hóa đơn thu công nợ {{ $debt->sohoadon }}..." class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100"></textarea>
                            @error('invoiceNote') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>
                        <flux:button type="button" wire:click="createPaymentInvoice" variant="primary" icon="plus" class="w-full justify-center">
                            Tạo hóa đơn
                        </flux:button>
                    </div>
                </section>
            @elseif (! $canCreateInvoice && $debt->status !== \App\Enums\DebtStatusEnum::DA_THANH_TOAN)
                <section class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <flux:icon.exclamation-triangle class="size-5 shrink-0 text-amber-600" />
                        <div>
                            <h2 class="text-base font-bold text-amber-900">Chưa thể tạo hóa đơn</h2>
                            <p class="mt-1 text-sm text-amber-700">Cần chốt cước công nợ trước khi có thể tạo hóa đơn thu cho khách hàng.</p>
                        </div>
                    </div>
                </section>
            @endif
        </aside>
    </div>

    @if ($canCreateInvoice || $sortedInvoices->isNotEmpty())
        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-neutral-100 px-4 py-4 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-base font-bold text-neutral-950">Lịch sử tạo hóa đơn</h2>
                    <p class="mt-1 text-sm text-neutral-500">Theo dõi các hóa đơn thu đã tạo, trạng thái xử lý và lịch sử thanh toán.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-neutral-700">
                        <flux:icon.document-text class="size-4 text-neutral-500" />
                        {{ $sortedInvoices->count() }} hóa đơn
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-neutral-700">
                        Còn có thể tạo: <span class="text-primary-700">{{ $this->money($availableForInvoice) }}</span>
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1040px] w-full divide-y divide-neutral-100 text-sm">
                    <thead class="bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Hóa đơn</th>
                            <th class="px-4 py-3 text-left font-semibold">Người tạo</th>
                            <th class="px-4 py-3 text-right font-semibold">Số tiền</th>
                            <th class="px-4 py-3 text-left font-semibold">Trạng thái</th>
                            <th class="px-4 py-3 text-left font-semibold">Thanh toán</th>
                            <th class="px-4 py-3 text-left font-semibold">Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 bg-white">
                        @forelse ($sortedInvoices as $invoice)
                            @php
                                $statusEnum = $invoice->status instanceof InvoicePaymentStatusEnum
                                    ? $invoice->status
                                    : InvoicePaymentStatusEnum::tryFrom((string) $invoice->status);
                                $badgeClass = $statusEnum?->color() ?? 'bg-neutral-100 text-neutral-700';
                            @endphp
                            <tr wire:key="invoice-{{ $invoice->id }}" class="transition hover:bg-neutral-50/80">
                                <td class="px-4 py-4 align-top">
                                    <p class="font-bold text-primary-700">{{ $invoice->ma_hoa_don ?: '#'.$invoice->id }}</p>
                                    <p class="mt-1 text-xs text-neutral-500">Tạo: {{ $invoice->created_at?->format('H:i d/m/Y') ?: '-' }}</p>
                                    @if ($invoice->ngay_duyet)
                                        <p class="mt-0.5 text-xs text-neutral-500">Duyệt: {{ $invoice->ngay_duyet->format('H:i d/m/Y') }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold text-neutral-900">{{ $invoice->user?->fullname ?: $invoice->user?->username ?: '-' }}</p>
                                    @if ($invoice->user?->code)
                                        <p class="mt-1 text-xs text-neutral-500">{{ $invoice->user->code }}</p>
                                    @endif
                                    @if ($invoice->ketoan)
                                        <p class="mt-1 text-xs text-neutral-500">KT: {{ $invoice->ketoan->fullname ?: $invoice->ketoan->username }}</p>
                                    @endif
                                    @if ($invoice->approver)
                                        <p class="mt-1 text-xs text-blue-600">Duyệt: {{ $invoice->approver->fullname ?: $invoice->approver->username }}</p>
                                    @endif
                                    @if ($invoice->paymentConfirmer)
                                        <p class="mt-1 text-xs text-emerald-700">Xác nhận: {{ $invoice->paymentConfirmer->fullname ?: $invoice->paymentConfirmer->username }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-right align-top font-bold text-neutral-950">
                                    {{ $this->money($invoice->amount) }}
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">
                                        {{ $statusEnum?->label() ?: '-' }}
                                    </span>
                                    @if ($invoice->cancelled_at)
                                        <p class="mt-1 text-xs text-red-600">Hủy: {{ $invoice->cancelled_at->format('H:i d/m/Y') }}</p>
                                    @endif
                                    @if ($invoice->payment_rejected_at)
                                        <p class="mt-1 text-xs text-orange-600">Từ chối: {{ $invoice->payment_rejected_at->format('H:i d/m/Y') }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <p class="font-semibold text-neutral-800">{{ $invoice->method ? ucfirst(str_replace('_', ' ', $invoice->method)) : '-' }}</p>
                                    @if ($invoice->submitted_at)
                                        <p class="mt-1 text-xs text-amber-700">Gửi bằng chứng: {{ $invoice->submitted_at->format('H:i d/m/Y') }}</p>
                                    @endif
                                    @if ($invoice->paid_at)
                                        <p class="mt-1 text-xs text-emerald-700">Đã thu: {{ $invoice->paid_at->format('H:i d/m/Y') }}</p>
                                    @elseif ($invoice->qr_generated_at && ! $statusEnum?->isCancelled())
                                        <p class="mt-1 text-xs text-indigo-700">QR: {{ $invoice->qr_generated_at->format('H:i d/m/Y') }}</p>
                                    @else
                                        <p class="mt-1 text-xs text-neutral-500">Chưa phát sinh thanh toán</p>
                                    @endif
                                    @if ($invoice->sepay_transaction_id)
                                        <p class="mt-1 max-w-[180px] truncate text-xs text-neutral-500" title="{{ $invoice->sepay_transaction_id }}">SePay: {{ $invoice->sepay_transaction_id }}</p>
                                    @endif
                                    @if (($invoice->payment_url || $invoice->qr_url) && ! $statusEnum?->isPaid() && ! $statusEnum?->isCancelled())
                                        <a href="{{ $invoice->payment_url ?: $invoice->qr_url }}" target="_blank" rel="noopener" class="mt-1 inline-flex text-xs font-semibold text-primary-700 hover:text-primary-800">Mở thanh toán</a>
                                    @endif
                                    @if ($invoice->photo)
                                        <a href="{{ asset('storage/'.$invoice->photo) }}" target="_blank" rel="noopener" class="mt-1 inline-flex text-xs font-semibold text-primary-700 hover:text-primary-800">Xem ảnh hóa đơn</a>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <p class=" truncate text-neutral-700" title="{{ $invoice->note }}">{{ $invoice->note ?: '-' }}</p>
                                    @if ($invoice->cancel_reason)
                                        <p class="mt-1  text-xs text-red-600" title="{{ $invoice->cancel_reason }}">Lý do hủy: {{ $invoice->cancel_reason }}</p>
                                    @endif
                                    @if ($invoice->cancelledBy)
                                        <p class="mt-1 text-xs text-red-600">Người hủy: {{ $invoice->cancelledBy->fullname ?: $invoice->cancelledBy->username }}</p>
                                    @endif
                                    @if ($invoice->payment_rejection_reason)
                                        <p class="mt-1  text-xs text-orange-600" title="{{ $invoice->payment_rejection_reason }}">Lý do từ chối: {{ $invoice->payment_rejection_reason }}</p>
                                    @endif
                                    @if ($invoice->paymentRejector)
                                        <p class="mt-1 text-xs text-orange-600">Người từ chối: {{ $invoice->paymentRejector->fullname ?: $invoice->paymentRejector->username }}</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-neutral-100 text-neutral-500">
                                            <flux:icon.receipt-percent class="size-5" />
                                        </div>
                                        <p class="mt-3 font-semibold text-neutral-900">Chưa có lịch sử tạo hóa đơn</p>
                                        <p class="mt-1 text-sm text-neutral-500">Tạo hóa đơn thu để bắt đầu thu tiền từ khách hàng.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- E-Invoice Section --}}
    @php
        $einvoices = $debt->einvoices->sortByDesc(fn ($e) => $e->created_at?->timestamp ?? 0);
        $hasSuccessfulEInvoice = $einvoices->contains(fn ($e) => $e->isSuccess());
        $latestEInvoice = $einvoices->first();
    @endphp

    @if ($this->eInvoiceEnabled && $this->canManage() && ($debt->status === \App\Enums\DebtStatusEnum::DA_THANH_TOAN || $einvoices->isNotEmpty()))
        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-neutral-100 px-4 py-4 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-base font-bold text-neutral-950">Hóa đơn điện tử</h2>
                    <p class="mt-1 text-sm text-neutral-500">Tạo và quản lý hóa đơn điện tử cho công nợ đã thanh toán.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($this->canCreateEInvoice)
                        <button
                            type="button"
                            wire:click="openEInvoiceModal"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                        >
                            <flux:icon.receipt-percent class="size-4" />
                            Tạo hóa đơn điện tử
                        </button>
                    @elseif ($hasSuccessfulEInvoice)
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                            <flux:icon.check-circle class="size-4" />
                            Đã có hóa đơn điện tử
                        </span>
                    @endif
                </div>
            </div>

            @if ($einvoices->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-[800px] w-full divide-y divide-neutral-100 text-sm">
                        <thead class="bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Mã tham chiếu</th>
                                <th class="px-4 py-3 text-left font-semibold">Provider</th>
                                <th class="px-4 py-3 text-right font-semibold">Số tiền</th>
                                <th class="px-4 py-3 text-left font-semibold">Trạng thái</th>
                                <th class="px-4 py-3 text-left font-semibold">Số hóa đơn</th>
                                <th class="px-4 py-3 text-left font-semibold">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 bg-white">
                            @foreach ($einvoices as $einvoice)
                                <tr wire:key="einvoice-{{ $einvoice->id }}" class="transition hover:bg-neutral-50/80">
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-bold text-primary-700">{{ $einvoice->reference }}</p>
                                        <p class="mt-1 text-xs text-neutral-500">{{ $einvoice->created_at?->format('H:i d/m/Y') ?: '-' }}</p>
                                        @if ($einvoice->tracking_code)
                                            <p class="mt-0.5 text-xs text-neutral-500" title="{{ $einvoice->tracking_code }}">Tracking: {{ $einvoice->tracking_code }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <p class="font-semibold text-neutral-900">{{ ucfirst($einvoice->provider) }}</p>
                                        @if ($einvoice->template_code)
                                            <p class="mt-1 text-xs text-neutral-500">Mẫu: {{ $einvoice->template_code }}</p>
                                        @endif
                                        @if ($einvoice->invoice_series)
                                            <p class="mt-0.5 text-xs text-neutral-500">Ký hiệu: {{ $einvoice->invoice_series }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-right align-top font-bold text-neutral-950">
                                        {{ $this->money($einvoice->amount) }}
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $einvoice->statusColor() }}">
                                            {{ $einvoice->statusLabel() }}
                                        </span>
                                        @if ($einvoice->issued_at)
                                            <p class="mt-1 text-xs text-emerald-700">Phát hành: {{ $einvoice->issued_at->format('H:i d/m/Y') }}</p>
                                        @endif
                                        @if ($einvoice->error_message)
                                            <p class="mt-1 max-w-[200px] truncate text-xs text-red-600" title="{{ $einvoice->error_message }}">{{ $einvoice->error_message }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        @if ($einvoice->invoice_number)
                                            <p class="font-bold text-emerald-700">{{ $einvoice->invoice_number }}</p>
                                        @else
                                            <p class="text-neutral-500">-</p>
                                        @endif
                                        @if ($einvoice->invoice_url)
                                            <a href="{{ $einvoice->invoice_url }}" target="_blank" rel="noopener" class="mt-1 inline-flex text-xs font-semibold text-primary-700 hover:text-primary-800">
                                                Xem online
                                            </a>
                                        @endif
                                        <div class="mt-1 flex flex-wrap items-center gap-2">
                                            @if ($einvoice->pdf_path)
                                                <a href="{{ asset($einvoice->pdf_path) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 hover:text-red-700">
                                                    <flux:icon.document class="size-3.5" /> PDF
                                                </a>
                                            @endif
                                            @if ($einvoice->xml_path)
                                                <a href="{{ asset($einvoice->xml_path) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700">
                                                    <flux:icon.code-bracket class="size-3.5" /> XML
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        @if ($einvoice->isPending() && $einvoice->tracking_code)
                                            <button
                                                type="button"
                                                wire:click="checkEInvoiceStatus({{ $einvoice->id }})"
                                                wire:loading.attr="disabled"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-neutral-50"
                                            >
                                                <flux:icon.arrow-path class="size-3.5" wire:loading.class="animate-spin" wire:target="checkEInvoiceStatus({{ $einvoice->id }})" />
                                                Kiểm tra
                                            </button>
                                        @elseif ($einvoice->isSuccess())
                                            <div class="flex flex-col items-start gap-1.5">
                                                <span class="text-xs text-emerald-600">✓ Hoàn tất</span>
                                                @if (! $einvoice->pdf_path || ! $einvoice->xml_path)
                                                    <button
                                                        type="button"
                                                        wire:click="downloadEInvoiceFiles({{ $einvoice->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="downloadEInvoiceFiles({{ $einvoice->id }})"
                                                        class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 transition hover:bg-neutral-50"
                                                    >
                                                        <flux:icon.arrow-down-tray class="size-3.5" wire:loading.class="animate-spin" wire:target="downloadEInvoiceFiles({{ $einvoice->id }})" />
                                                        Tải file
                                                    </button>
                                                @else
                                                    <span class="text-xs text-neutral-400">Đã lưu file</span>
                                                @endif

                                                @if ($einvoice->pdf_path && $debt->customer?->email)
                                                    <button
                                                        type="button"
                                                        wire:click="confirmSendEInvoiceEmail({{ $einvoice->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="confirmSendEInvoiceEmail({{ $einvoice->id }}),handleConfirmAction"
                                                        class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition {{ $einvoice->email_sent_at ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-primary-200 bg-primary-50 text-primary-700 hover:bg-primary-100' }}"
                                                    >
                                                        <flux:icon.envelope class="size-3.5" wire:loading.class="animate-spin" wire:target="handleConfirmAction" />
                                                        {{ $einvoice->email_sent_at ? 'Gửi lại email' : 'Gửi email' }}
                                                    </button>
                                                    @if ($einvoice->email_sent_at)
                                                        <span class="text-[11px] text-neutral-400">Đã gửi {{ $einvoice->email_sent_at->format('H:i d/m/Y') }}</span>
                                                    @endif
                                                @endif
                                            </div>
                                        @elseif ($einvoice->isFailed())
                                            <span class="text-xs text-red-600">Thất bại</span>
                                        @else
                                            <span class="text-xs text-neutral-500">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-4 py-10 text-center">
                    <div class="mx-auto flex max-w-sm flex-col items-center">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-neutral-100 text-neutral-500">
                            <flux:icon.receipt-percent class="size-5" />
                        </div>
                        <p class="mt-3 font-semibold text-neutral-900">Chưa có hóa đơn điện tử</p>
                        <p class="mt-1 text-sm text-neutral-500">Tạo hóa đơn điện tử sau khi công nợ đã thanh toán hết.</p>
                    </div>
                </div>
            @endif
        </section>
    @endif

    <flux:modal name="edit-sale-charge" class="w-full max-w-8xl" scroll="body">
        <form wire:submit="saveSaleCharge" class="space-y-5">
            <div>
                <h2 class="text-lg font-bold text-neutral-950">Edit cước bán</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    {{ $editingSaleChargeOrderCode ? 'Order '.$editingSaleChargeOrderCode : 'Chọn order cần cập nhật cước bán' }}
                </p>
            </div>

            <section class="rounded-xl border border-neutral-200 bg-white">
                <div class="flex flex-col gap-3 border-b border-neutral-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wide text-neutral-800">Cước bán</h3>
                        <p class="mt-1 text-sm text-neutral-500">Đơn giá bán, PPXD, VAT và tổng cước bán của order.</p>
                    </div>
                    <div class="rounded-lg bg-primary-50 px-3 py-2 text-right">
                        <p class="text-xs text-primary-700">Tổng cước</p>
                        <p class="text-sm font-semibold text-primary-800">{{ $this->money(data_get($editingSaleCharge, 'total_tongcuoc')) }}</p>
                    </div>
                </div>

                <div class="grid gap-4 p-4 md:grid-cols-12">
                    <label class="block md:col-span-3">
                        <span class="text-sm font-semibold text-neutral-700">Đơn giá bán</span>
                        <input type="text" wire:model.live.debounce.300ms="editingSaleCharge.dongiaban" inputmode="decimal" autocomplete="off" class="mt-1 h-10 w-full rounded-lg border border-neutral-200 px-3 text-sm font-semibold outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                        @error('editingSaleCharge.dongiaban') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>

                    <div class="md:col-span-3">
                        <span class="text-sm font-semibold text-neutral-700">PPXD (%)</span>
                        <div class="mt-1 grid grid-cols-[90px_minmax(0,1fr)] gap-2">
                            <input type="number" step="0.1" min="0" wire:model.live.debounce.300ms="editingSaleCharge.ppxd_percent" class="h-10 rounded-lg border border-neutral-200 px-3 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                            <div class="flex h-10 items-center justify-end rounded-lg border border-neutral-200 bg-neutral-50 px-3 text-sm font-semibold text-neutral-800">{{ $this->money(data_get($editingSaleCharge, 'ppxd_amount')) }}</div>
                        </div>
                    </div>

                    <div class="md:col-span-3">
                        <span class="text-sm font-semibold text-neutral-700">VAT (%)</span>
                        <div class="mt-1 grid grid-cols-[90px_minmax(0,1fr)] gap-2">
                            <input type="number" step="0.1" min="0" wire:model.live.debounce.300ms="editingSaleCharge.vat_percent" class="h-10 rounded-lg border border-neutral-200 px-3 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                            <div class="flex h-10 items-center justify-end rounded-lg border border-neutral-200 bg-neutral-50 px-3 text-sm font-semibold text-neutral-800">{{ $this->money(data_get($editingSaleCharge, 'vat_amount')) }}</div>
                        </div>
                    </div>

                    <div class="md:col-span-3">
                        <span class="text-sm font-semibold text-neutral-700">Tổng trước VAT</span>
                        <div class="mt-1 flex h-10 items-center justify-end rounded-lg border border-neutral-200 bg-neutral-50 px-3 text-sm font-semibold text-neutral-900">{{ $this->money(data_get($editingSaleCharge, 'total_tongcuoc_no_vat')) }}</div>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-neutral-200 bg-white">
                <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-4 py-3">
                    <div>
                        <h3 class="text-sm font-bold text-neutral-900">Phụ phí bán</h3>
                        <p class="mt-1 text-xs text-neutral-500">Tổng phụ phí = đơn giá x số lượng, VAT phụ phí được cộng vào tổng cước.</p>
                    </div>
                    <flux:button type="button" size="sm" variant="outline" icon="plus" wire:click="addEditingSaleChargeFee('phuphi')">Thêm</flux:button>
                </div>

                <div class="overflow-x-auto p-4">
                    <div class="min-w-[980px] space-y-3">
                        @forelse(data_get($editingSaleCharge, 'phuphi', []) as $index => $row)
                            <div wire:key="edit-sale-phuphi-{{ data_get($row, '_key', $index) }}" class="grid grid-cols-[minmax(220px,1.4fr)_minmax(180px,1fr)_90px_130px_90px_130px_130px_44px] items-end gap-3">
                                <label class="block">
                                    <span class="text-xs font-semibold text-neutral-600">Loại phụ phí</span>
                                    <select wire:model.live="editingSaleCharge.phuphi.{{ $index }}.id_loaiphuphi" class="mt-1 h-10 w-full rounded-lg border border-neutral-200 bg-white px-3 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                                        <option value="">-- Chọn phụ phí --</option>
                                        @foreach($feeOptions as $feeOption)
                                            <option value="{{ $feeOption['id'] }}">{{ $feeOption['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-neutral-600">Ghi chú</span>
                                    <input type="text" wire:model.live.debounce.300ms="editingSaleCharge.phuphi.{{ $index }}.note" class="mt-1 h-10 w-full rounded-lg border border-neutral-200 px-3 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-neutral-600">SL</span>
                                    <input type="number" min="0" step="1" wire:model.live.debounce.300ms="editingSaleCharge.phuphi.{{ $index }}.soluong" class="mt-1 h-10 w-full rounded-lg border border-neutral-200 px-3 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-neutral-600">Đơn giá</span>
                                    <input type="text" wire:model.live.debounce.300ms="editingSaleCharge.phuphi.{{ $index }}.price" inputmode="decimal" class="mt-1 h-10 w-full rounded-lg border border-neutral-200 px-3 text-sm text-right outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-neutral-600">VAT (%)</span>
                                    <input type="number" min="0" step="0.1" wire:model.live.debounce.300ms="editingSaleCharge.phuphi.{{ $index }}.vat_percent" class="mt-1 h-10 w-full rounded-lg border border-neutral-200 px-3 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                                </label>
                                <div>
                                    <span class="text-xs font-semibold text-neutral-600">Tổng tiền</span>
                                    <div class="mt-1 flex h-10 items-center justify-end rounded-lg border border-neutral-200 bg-neutral-50 px-3 text-sm font-semibold text-neutral-800">{{ $this->money(data_get($row, 'total')) }}</div>
                                </div>
                                <div>
                                    <span class="text-xs font-semibold text-neutral-600">Sau VAT</span>
                                    <div class="mt-1 flex h-10 items-center justify-end rounded-lg border border-primary-100 bg-primary-50 px-3 text-sm font-semibold text-primary-700">{{ $this->money(data_get($row, 'total_after_vat')) }}</div>
                                </div>
                                <button type="button" wire:click="removeEditingSaleChargeFee('phuphi', {{ $index }})" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" aria-label="Xóa phụ phí">
                                    <flux:icon.x-mark class="size-4" />
                                </button>
                            </div>
                        @empty
                            <div class="rounded-lg bg-neutral-50 px-4 py-5 text-sm text-neutral-500">Chưa có phụ phí bán.</div>
                        @endforelse
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-3 border-t border-neutral-100 bg-neutral-50 px-4 py-3 text-sm">
                    <div class="text-neutral-600">Tổng trước VAT: <span class="font-semibold text-neutral-900">{{ $this->money(data_get($editingSaleCharge, 'total_phuphi_no_vat')) }}</span></div>
                    <div class="text-neutral-600">VAT phụ phí: <span class="font-semibold text-neutral-900">{{ $this->money(data_get($editingSaleCharge, 'total_vat_phuphi')) }}</span></div>
                    <div class="text-primary-700">Sau VAT: <span class="font-semibold">{{ $this->money(data_get($editingSaleCharge, 'total_phuphi')) }}</span></div>
                </div>
            </section>

            <section class="rounded-xl border border-neutral-200 bg-white">
                <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-4 py-3">
                    <div>
                        <h3 class="text-sm font-bold text-neutral-900">Hoa hồng khách hàng</h3>
                        <p class="mt-1 text-xs text-neutral-500">Các khoản chi hoa hồng được trừ khi tính lợi nhuận.</p>
                    </div>
                    <flux:button type="button" size="sm" variant="outline" icon="plus" wire:click="addEditingSaleChargeFee('hh_khachhang')">Thêm</flux:button>
                </div>

                <div class="overflow-x-auto p-4">
                    <div class="min-w-[760px] space-y-3">
                        @forelse(data_get($editingSaleCharge, 'hh_khachhang', []) as $index => $row)
                            <div wire:key="edit-sale-hhkh-{{ data_get($row, '_key', $index) }}" class="grid grid-cols-[minmax(220px,1fr)_minmax(260px,1.3fr)_160px_44px] items-end gap-3">
                                <label class="block">
                                    <span class="text-xs font-semibold text-neutral-600">Loại chi</span>
                                    <select wire:model.live="editingSaleCharge.hh_khachhang.{{ $index }}.id_loaichi" class="mt-1 h-10 w-full rounded-lg border border-neutral-200 bg-white px-3 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                                        <option value="">-- Chọn loại chi --</option>
                                        @foreach($expenseOptions as $expenseOption)
                                            <option value="{{ $expenseOption['id'] }}">{{ $expenseOption['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-neutral-600">Diễn giải chi</span>
                                    <input type="text" wire:model.live.debounce.300ms="editingSaleCharge.hh_khachhang.{{ $index }}.diengiai_chi" class="mt-1 h-10 w-full rounded-lg border border-neutral-200 px-3 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                                </label>
                                <label class="block">
                                    <span class="text-xs font-semibold text-neutral-600">Số tiền</span>
                                    <input type="text" wire:model.live.debounce.300ms="editingSaleCharge.hh_khachhang.{{ $index }}.so_tien" inputmode="decimal" class="mt-1 h-10 w-full rounded-lg border border-neutral-200 px-3 text-sm text-right outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                                </label>
                                <button type="button" wire:click="removeEditingSaleChargeFee('hh_khachhang', {{ $index }})" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600" aria-label="Xóa hoa hồng khách hàng">
                                    <flux:icon.x-mark class="size-4" />
                                </button>
                            </div>
                        @empty
                            <div class="rounded-lg bg-neutral-50 px-4 py-5 text-sm text-neutral-500">Chưa có hoa hồng khách hàng.</div>
                        @endforelse
                    </div>
                </div>

                <div class="flex justify-end border-t border-neutral-100 bg-neutral-50 px-4 py-3 text-sm text-primary-700">
                    Tổng hoa hồng khách hàng: <span class="ml-1 font-semibold">{{ $this->money(data_get($editingSaleCharge, 'total_hh_khachhang')) }}</span>
                </div>
            </section>

            <section class="rounded-xl border border-primary-100 bg-primary-50/70 p-4">
                <div class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg bg-white px-3 py-2.5">
                        <p class="text-xs text-neutral-500">Cước vận chuyển</p>
                        <p class="mt-1 font-semibold text-neutral-900">{{ $this->money($this->number(data_get($editingSaleCharge, 'dongiaban')) + $this->number(data_get($editingSaleCharge, 'ppxd_amount'))) }}</p>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2.5">
                        <p class="text-xs text-neutral-500">Chi phí khác</p>
                        <p class="mt-1 font-semibold text-neutral-900">{{ $this->money(data_get($editingSaleCharge, 'total_phuphi_no_vat')) }}</p>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2.5">
                        <p class="text-xs text-neutral-500">Tổng VAT</p>
                        <p class="mt-1 font-semibold text-neutral-900">{{ $this->money(data_get($editingSaleCharge, 'total_vat')) }}</p>
                    </div>
                    <div class="rounded-lg bg-white px-3 py-2.5">
                        <p class="text-xs text-neutral-500">Tổng cước bán</p>
                        <p class="mt-1 font-semibold text-primary-800">{{ $this->money(data_get($editingSaleCharge, 'total_tongcuoc')) }}</p>
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-end gap-2 border-t border-neutral-100 pt-4">
                <flux:modal.close>
                    <flux:button type="button" variant="outline">Hủy</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Lưu cước bán</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="cancel-invoice" class="w-full max-w-lg" @close="$wire.closeCancelInvoiceModal()">
        <form wire:submit="submitCancelInvoice" class="space-y-4">
            <div>
                <h2 class="text-lg font-bold text-neutral-950">Hủy hóa đơn</h2>
                <p class="mt-1 text-sm text-neutral-500">Nhập lý do hủy để lưu vào lịch sử xử lý hóa đơn.</p>
            </div>

            <label class="block">
                <span class="text-sm font-semibold text-neutral-700">Lý do hủy <span class="text-rose-600">*</span></span>
                <textarea wire:model="cancelReason" rows="4" class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-100" placeholder="Nhập lý do hủy hóa đơn..."></textarea>
                @error('cancelReason') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <div class="flex items-center justify-end gap-2 border-t border-neutral-100 pt-4">
                <flux:button type="button" variant="outline" wire:click="closeCancelInvoiceModal">Đóng</flux:button>
                <flux:button type="submit" variant="danger" icon="x-circle">Xác nhận hủy</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="pay-invoice" class="w-full max-w-2xl" @close="$wire.closePayModal()">
        <div class="space-y-5">
            <div>
                <h2 class="text-lg font-bold text-neutral-950">Thanh toán hóa đơn</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    @if ($payingInvoice)
                        Mã hóa đơn <span class="font-semibold text-neutral-800">{{ $payingInvoice->ma_hoa_don }}</span>
                        - Số tiền cần thanh toán
                        <span class="font-semibold text-rose-700">{{ $this->money($payingInvoice->amount) }}</span>
                    @else
                        Chưa chọn hóa đơn.
                    @endif
                </p>
            </div>

            @if ($payingInvoice)
                <div class="grid gap-3 {{ $this->onlinePaymentEnabled ? 'sm:grid-cols-2' : '' }}">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition {{ $selectedMethod === 'cash' ? 'border-rose-400 bg-rose-50/70 ring-2 ring-rose-100' : 'border-neutral-200 bg-white hover:border-rose-200' }}">
                        <input type="radio" wire:model.live="selectedMethod" value="cash" class="mt-1 h-4 w-4 text-rose-600 focus:ring-rose-500">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-neutral-900">Tiền mặt</p>
                            <p class="mt-0.5 text-xs text-neutral-500">Khách thanh toán trực tiếp. Cần upload ảnh hóa đơn đã thu tiền.</p>
                        </div>
                    </label>
                    @if ($this->onlinePaymentEnabled)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition {{ $selectedMethod === 'bank_transfer' ? 'border-rose-400 bg-rose-50/70 ring-2 ring-rose-100' : 'border-neutral-200 bg-white hover:border-rose-200' }}">
                            <input type="radio" wire:model.live="selectedMethod" value="bank_transfer" class="mt-1 h-4 w-4 text-rose-600 focus:ring-rose-500">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-neutral-900">Chuyển khoản online</p>
                                <p class="mt-0.5 text-xs text-neutral-500">Tạo link thanh toán qua cổng. Hệ thống tự xác nhận khi nhận tiền.</p>
                            </div>
                        </label>
                    @endif
                </div>

                @if ($this->onlinePaymentEnabled && $selectedMethod === 'bank_transfer')
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50/60 p-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-neutral-600">Chọn cổng thanh toán</p>
                        <div class="grid gap-2 sm:grid-cols-3">
                            @foreach(\App\Services\Payments\PaymentProviderManager::providerLabels() as $key => $meta)
                                @if($enabledProviders[$key] ?? false)
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition {{ $selectedProvider === $key ? 'border-'.$meta['color'].'-400 bg-'.$meta['color'].'-50/70 ring-2 ring-'.$meta['color'].'-100' : 'border-neutral-200 bg-white hover:border-'.$meta['color'].'-200' }}">
                                    <input type="radio" wire:model.live="selectedProvider" value="{{ $key }}" class="h-4 w-4 text-{{ $meta['color'] }}-600 focus:ring-{{ $meta['color'] }}-500">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-neutral-900">{{ $meta['name'] }}</p>
                                        <p class="text-xs text-neutral-500">{{ $meta['description'] }}</p>
                                    </div>
                                </label>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($selectedMethod === 'cash')
                    <form wire:submit="submitCashPayment" class="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50/60 p-4">
                        <div>
                            <label class="block text-sm font-semibold text-neutral-800">Ảnh hóa đơn đã thanh toán <span class="text-rose-600">*</span></label>
                            <p class="text-xs text-neutral-500">Định dạng ảnh, tối đa 8MB.</p>
                            <input type="file" wire:model="cashInvoicePhoto" accept="image/*" class="mt-2 block w-full rounded-lg border border-neutral-200 bg-white text-sm file:mr-3 file:cursor-pointer file:rounded-l-lg file:border-0 file:bg-rose-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-rose-700">
                            @error('cashInvoicePhoto')
                                <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                            <div wire:loading wire:target="cashInvoicePhoto" class="mt-2 text-xs text-neutral-500">Đang tải ảnh lên...</div>
                            @if ($cashInvoicePhoto && method_exists($cashInvoicePhoto, 'temporaryUrl'))
                                <div class="mt-3 overflow-hidden rounded-lg border border-neutral-200 bg-white">
                                    <img src="{{ $cashInvoicePhoto->temporaryUrl() }}" alt="Preview" class="max-h-64 w-full object-contain">
                                </div>
                            @endif
                        </div>
                        <div class="flex items-center justify-end gap-2 border-t border-neutral-200 pt-3">
                            <flux:button type="button" variant="outline" wire:click="closePayModal">Hủy</flux:button>
                            <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled" wire:target="submitCashPayment,cashInvoicePhoto">
                                Gửi hóa đơn thanh toán
                            </flux:button>
                        </div>
                    </form>
                @elseif ($this->onlinePaymentEnabled && $selectedMethod === 'bank_transfer')
                    <div class="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50/60 p-4">
                        <div class="flex items-start gap-2 text-sm text-neutral-600">
                            <flux:icon.information-circle class="mt-0.5 size-4 text-rose-500" />
                            <p>Khi bấm "Tạo thanh toán", hệ thống sẽ sinh yêu cầu thanh toán theo cổng đã chọn. SePay tạo QR ngân hàng, MoMo tạo link/ví MoMo, VNPAY tạo link chuyển hướng; webhook sẽ tự cập nhật trạng thái sang "Đã thanh toán".</p>
                        </div>
                        <div class="flex items-center justify-end gap-2 border-t border-neutral-200 pt-3">
                            <flux:button type="button" variant="outline" wire:click="closePayModal">Hủy</flux:button>
                            <flux:button type="button" variant="primary" icon="{{ $selectedProvider === 'sepay' ? 'qr-code' : 'link' }}" wire:click="submitOnlinePayment" wire:loading.attr="disabled" wire:target="submitOnlinePayment">
                                {{ $selectedProvider === 'sepay' ? 'Tạo mã QR thanh toán' : 'Tạo link thanh toán' }}
                            </flux:button>
                        </div>
                    </div>
                @else
                    <div class="rounded-lg bg-neutral-50 px-4 py-6 text-center text-sm text-neutral-500">
                        Vui lòng chọn phương thức thanh toán phía trên.
                    </div>
                @endif
            @endif
        </div>
    </flux:modal>

    {{-- E-Invoice Modal --}}
    @php
        $einvoiceCompany = data_get($debt->customer?->options, 'company', []);
        $einvoiceCompanyName = data_get($einvoiceCompany, 'company_name')
            ?: $debt->customer?->company_name
            ?: $debt->customer?->fullname
            ?: $debt->customer?->username
            ?: '-';
        $einvoiceCompanyAddress = data_get($einvoiceCompany, 'address_detail')
            ?: $debt->customer?->address
            ?: '-';
        $einvoiceCompanyTaxCode = data_get($einvoiceCompany, 'tax_code') ?: '-';
        $enabledEInvoiceProvidersCount = collect($enabledEInvoiceProviders)->filter()->count();
    @endphp
    <flux:modal name="create-einvoice" class="relative w-full max-w-2xl overflow-hidden p-0" :dismissible="false">
        <form wire:submit="submitEInvoice" class="relative"
            x-on:submit="window.onbeforeunload = () => 'Đang tạo hóa đơn, vui lòng đợi.';"
            x-on:livewire:navigated.window="window.onbeforeunload = null;"
        >
            <div class="border-b border-neutral-100 bg-white px-5 py-5 sm:px-6">
                <div class="flex items-start gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100">
                        <flux:icon.receipt-percent class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-bold text-neutral-950">Tạo hóa đơn điện tử</h2>
                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                                Đã thanh toán
                            </span>
                        </div>
                        <p class="mt-1.5 text-sm text-neutral-500">
                            Công nợ <span class="font-semibold text-primary-700">{{ $debt->sohoadon }}</span>
                            sẽ được phát hành hóa đơn theo nhà cung cấp đã cấu hình.
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-5 px-5 py-5 sm:px-6">
                {{-- Provider --}}
                <div>
                    <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-neutral-800">
                        <flux:icon.building-office-2 class="size-4 text-neutral-400" />
                        Nhà cung cấp hóa đơn
                    </p>
                    <div class="grid gap-2 {{ $enabledEInvoiceProvidersCount > 1 ? 'sm:grid-cols-2' : '' }}">
                        @foreach ($this->einvoiceProviderLabels as $key => $label)
                            @if ($enabledEInvoiceProviders[$key] ?? false)
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition {{ $einvoiceProvider === $key ? 'border-emerald-400 bg-emerald-50/70 ring-2 ring-emerald-100' : 'border-neutral-200 bg-white hover:border-emerald-200' }}">
                                    <input type="radio" wire:model.live="einvoiceProvider" value="{{ $key }}" class="mt-0.5 h-4 w-4 text-emerald-600 focus:ring-emerald-500">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-neutral-900">{{ $label['name'] }}</p>
                                        <p class="mt-0.5 text-xs text-neutral-500">{{ $label['description'] }}</p>
                                    </div>
                                </label>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Thông tin xuất hóa đơn --}}
                <div class="overflow-hidden rounded-xl border border-neutral-200 bg-neutral-50/70">
                    <div class="flex items-center justify-between gap-3 border-b border-neutral-200/70 px-4 py-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-neutral-500">Thông tin hóa đơn</p>
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-neutral-500">
                            <flux:icon.archive-box class="size-3.5" />
                            {{ $debt->total_orders }} order
                        </span>
                    </div>
                    <div class="space-y-3 bg-white p-4 text-sm">
                        <div>
                            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                <flux:icon.building-office-2 class="size-3.5" />
                                Tên công ty
                            </p>
                            <p class="mt-1.5 break-words text-base font-bold text-neutral-950">{{ $einvoiceCompanyName }}</p>
                        </div>
                        <div>
                            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                <flux:icon.map-pin class="size-3.5" />
                                Địa chỉ
                            </p>
                            <p class="mt-1.5 break-words font-semibold text-neutral-900">{{ $einvoiceCompanyAddress }}</p>
                        </div>
                        <div>
                            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                <flux:icon.identification class="size-3.5" />
                                Mã số thuế
                            </p>
                            <p class="mt-1.5 font-semibold text-neutral-900">{{ $einvoiceCompanyTaxCode }}</p>
                        </div>
                    </div>
                    <div class="grid gap-px border-t border-neutral-200/70 bg-neutral-200/70 text-sm sm:grid-cols-2">
                        <div class="bg-neutral-50/70 p-4">
                            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                <flux:icon.banknotes class="size-3.5" />
                                Tổng tiền
                            </p>
                            <p class="mt-2 text-lg font-bold text-emerald-700">{{ $this->money($debt->total_cuocban) }}</p>
                        </div>
                        <div class="bg-neutral-50/70 p-4">
                            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                <flux:icon.hashtag class="size-3.5" />
                                Mã hóa đơn
                            </p>
                            <p class="mt-2 break-all font-bold text-neutral-900">{{ $debt->sohoadon }}</p>
                        </div>
                        <div class="bg-neutral-50/70 p-4 sm:col-span-2">
                            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                <flux:icon.calendar-days class="size-3.5" />
                                Kỳ công nợ
                            </p>
                            <p class="mt-2 font-semibold text-neutral-900">
                                {{ $debt->tungay?->format('d/m/Y') ?: '-' }} - {{ $debt->denngay?->format('d/m/Y') ?: '-' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-neutral-100 bg-neutral-50/80 px-5 py-4 sm:flex-row sm:items-center sm:justify-end sm:px-6">
                <button
                    type="button"
                    wire:click="closeEInvoiceModal"
                    wire:loading.attr="disabled"
                    wire:target="submitEInvoice"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 text-sm font-semibold text-neutral-700 transition hover:border-neutral-300 hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Hủy
                </button>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-200 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="submitEInvoice">
                        <flux:icon.receipt-percent class="size-4" />
                    </span>
                    <span wire:loading wire:target="submitEInvoice" class="size-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                    Tạo hóa đơn
                </button>
            </div>
        </form>

        {{-- Loading overlay nằm trong top-layer của modal để luôn phủ lên form. --}}
        <div
            wire:loading.flex
            wire:target="submitEInvoice"
            style="display: none;"
            class="absolute inset-0 z-50 items-center justify-center bg-white/95 px-5 backdrop-blur-sm"
            role="status"
            aria-live="polite"
            aria-label="Đang tạo hóa đơn điện tử"
        >
            <div class="flex w-full max-w-sm flex-col items-center gap-5 rounded-xl border border-emerald-100 bg-white px-6 py-7 text-center shadow-xl">
                <div class="relative flex size-16 items-center justify-center">
                    <span class="absolute inline-flex size-16 animate-ping rounded-full bg-emerald-200 opacity-60"></span>
                    <span class="absolute inline-flex size-16 animate-spin rounded-full border-4 border-emerald-100 border-t-emerald-600"></span>
                    <flux:icon.receipt-percent class="relative size-7 text-emerald-600" />
                </div>
                <div>
                    <p class="text-base font-bold text-neutral-900">Đang tạo hóa đơn điện tử</p>
                    <p class="mt-1.5 text-sm text-neutral-500">Hệ thống đang xử lý, vui lòng không tắt trang...</p>
                </div>
                <div class="flex items-center gap-1.5" aria-hidden="true">
                    <span class="size-2 animate-bounce rounded-full bg-emerald-500" style="animation-delay: 0ms"></span>
                    <span class="size-2 animate-bounce rounded-full bg-emerald-500" style="animation-delay: 150ms"></span>
                    <span class="size-2 animate-bounce rounded-full bg-emerald-500" style="animation-delay: 300ms"></span>
                </div>
            </div>
        </div>
    </flux:modal>

    @if ($this->hasDebtAdminPower())
        <flux:modal name="reassign-accountant" class="w-full max-w-lg">
            <form wire:submit="reassignAccountant" class="space-y-5">
                <div>
                    <h2 class="text-lg font-bold text-neutral-950">Đổi kế toán phụ trách</h2>
                    <p class="mt-1 text-sm text-neutral-500">Chọn kế toán mới cho công nợ {{ $debt->sohoadon }}.</p>
                </div>

                <label class="block">
                    <span class="text-sm font-semibold text-neutral-700">Kế toán phụ trách</span>
                    <select wire:model="reassignAccountantId" class="mt-1 h-10 w-full rounded-xl border border-neutral-200 bg-white px-3 text-sm outline-none focus:border-primary-500">
                        <option value="">-- Chọn kế toán --</option>
                        @foreach ($this->accountants as $accountant)
                            <option value="{{ $accountant->id }}">
                                {{ $accountant->fullname ?: $accountant->username }}{{ $accountant->code ? ' - '.$accountant->code : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('reassignAccountantId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </label>

                <div class="flex items-center justify-end gap-2 border-t border-neutral-100 pt-4">
                    <flux:modal.close>
                        <flux:button type="button" variant="outline">Đóng</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" icon="check">Cập nhật</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    <flux:modal name="refresh-orders" class="w-full max-w-lg">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                    <flux:icon.arrow-path class="size-5" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-neutral-950">Làm mới danh sách order</h2>
                    <p class="mt-1 text-sm text-neutral-500">Quét và bổ sung các đơn hàng thỏa điều kiện (cùng sale, cùng khách hàng, cùng khoảng ngày, chưa thuộc công nợ đang mở) vào công nợ này.</p>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-100 bg-neutral-50 p-4 text-sm text-neutral-600">
                <div class="flex justify-between gap-3"><span class="text-neutral-500">Khách hàng</span><span class="font-semibold text-neutral-900">{{ $customerName }}</span></div>
                <div class="mt-2 flex justify-between gap-3"><span class="text-neutral-500">Sale</span><span class="font-semibold text-neutral-900">{{ $debt->sale?->fullname ?: $debt->sale?->username ?: '-' }}</span></div>
                <div class="mt-2 flex justify-between gap-3"><span class="text-neutral-500">Khoảng ngày</span><span class="font-semibold text-neutral-900">{{ $debt->tungay?->format('d/m/Y') }} - {{ $debt->denngay?->format('d/m/Y') }}</span></div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-neutral-100 pt-4">
                <flux:modal.close>
                    <flux:button type="button" variant="outline">Đóng</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="primary" icon="arrow-path" wire:click="refreshOrders">Quét &amp; bổ sung</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
