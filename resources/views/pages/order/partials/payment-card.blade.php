@php
    $inputClass = 'w-full px-4 py-2.5 text-sm border transition-all placeholder:text-neutral-400 focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500';
    $moneyMask = "\$money(\$input, '.', ',', 0)";
@endphp

<section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
    <div class="flex flex-col gap-3 border-b border-neutral-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-800">{{ $title }}</h2>
            <p class="mt-1 text-sm text-neutral-500">{{ $subtitle }}</p>
        </div>
        <div class="rounded-lg bg-primary-50 px-3 py-2 text-right">
            <p class="text-xs text-primary-700">Tổng cước</p>
            <p class="text-sm font-semibold text-primary-800">{{ $this->money(data_get($payment, "$group.total_tongcuoc")) }}</p>
        </div>
    </div>

    <div class="space-y-5 p-5">
        <div class="grid gap-4 md:grid-cols-12">
            <flux:field class="col-span-2">
                <flux:label>{{ $priceLabel }}</flux:label>
                <flux:input
                    type="text"
                    wire:model.live.debounce.300ms="payment.{{ $group }}.{{ $priceKey }}"
                    placeholder=""
                    mask:dynamic="$money($input, '.', ',', 0)"
                    :class:input="$inputClass"
                />
            </flux:field>
            <flux:field class="col-span-3">
                <flux:label>PPXD (%)</flux:label>
                <flux:field class="!grid !gap-4 !grid-cols-3">
                    <flux:input
                        type="text"
                        wire:model.live.debounce.300ms="payment.{{ $group }}.ppxd_percent"
                        placeholder=""
                        :class:input="$inputClass"
                    />
                    <flux:input
                        type="text"
                        wire:model="payment.{{ $group }}.ppxd_amount"
                        placeholder=""
                        readonly
                        variant="filled"
                        mask:dynamic="$money($input, '.', ',', 0)"
                        class="col-span-2"
                        :class:input="$inputClass"
                    />
                </flux:field>
            </flux:field>
            <flux:field class="col-span-3">
                <flux:label>VAT (%)</flux:label>
                <flux:field class="!grid !gap-4 !grid-cols-3">
                    <flux:input
                        type="text"
                        wire:model.live.debounce.300ms="payment.{{ $group }}.vat_percent"
                        placeholder=""
                        :class:input="$inputClass"
                    />
                    <flux:input
                        type="text"
                        wire:model="payment.{{ $group }}.vat_amount"
                        placeholder=""
                        readonly
                        variant="filled"
                        class="col-span-2"
                        mask:dynamic="$money($input, '.', ',', 0)"
                        :class:input="$inputClass"
                    />
                </flux:field>
            </flux:field>
            <flux:field class="col-span-4">
                <flux:label>Tổng tiền</flux:label>
                <flux:field class="!grid !gap-4 !grid-cols-2">
                    <flux:input
                        type="text"
                        wire:model="payment.{{ $group }}.total_tongcuoc_no_vat" 
                        placeholder=""
                        readonly
                        variant="filled"
                        mask:dynamic="$money($input, '.', ',', 0)"
                        :class:input="$inputClass"
                    />
                    <flux:input
                        type="text"
                        wire:model="payment.{{ $group }}.total_tongcuoc"
                        placeholder=""
                        readonly
                        variant="filled"
                        mask:dynamic="$money($input, '.', ',', 0)"
                        :class:input="$inputClass"
                    />
                </flux:field>
            </flux:field>
            
        </div>

        @if($group === 'cuocvon')
            <div class="grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>Bonus sale (%)</flux:label>
                    <flux:input type="number" min="0" step="0.01" wire:model.live.debounce.300ms="payment.cuocvon.bonus_sale_percent" />
                </flux:field>
                <div class="rounded-xl border border-neutral-100 bg-neutral-50 px-4 py-3">
                    <p class="text-xs text-neutral-500">Bonus sale tạm tính</p>
                    <p class="mt-1 text-sm font-semibold text-neutral-900">{{ $this->money(data_get($payment, 'cuocvon.bonus_sale_amount')) }}</p>
                </div>
            </div>
        @endif

        @foreach($buckets as $bucket)
            @php
                $bucketKey = $bucket['key'];
                $rows = data_get($payment, "$group.$bucketKey", []);
                $feeOptionsForBucket = $this->feeOptionsForBucket($bucketKey);
            @endphp

            <div class=" rounded-xl border border-neutral-100">
                <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-neutral-800">{{ $bucket['label'] }}</p>
                        <p class="text-xs text-neutral-500">
                            {{ $bucketKey === 'hh_khachhang' ? 'Chọn loại chi, nhập diễn giải và số tiền chi hoa hồng.' : ($bucketKey === 'phichiho' ? 'Chọn loại chi hộ, nhập ghi chú nếu có và giá chi hộ.' : 'Tổng tiền phụ phí = đơn giá x số lượng; VAT phụ phí được cộng vào giá sau VAT.') }}
                        </p>
                    </div>
                    <flux:button type="button" size="sm" wire:click="addFee('{{ $group }}', '{{ $bucketKey }}')">+ Thêm</flux:button>
                </div>

                <div class="p-4">
                    <div class="{{ $bucketKey === 'hh_khachhang' ? 'min-w-[760px]' : ($bucketKey === 'phichiho' ? 'min-w-[760px]' : 'min-w-[1260px]') }} space-y-4">
                        @forelse($rows as $index => $row)
                            @if($bucketKey === 'hh_khachhang')
                                <div wire:key="{{ $group }}-{{ $bucketKey }}-{{ data_get($row, '_key', $index) }}" class="grid grid-cols-[minmax(220px,1fr)_minmax(260px,1.4fr)_160px_56px] items-end gap-3">
                                    <flux:field class="min-w-0 [&_.ts-control]:min-h-[42px] [&_.ts-control]:whitespace-nowrap [&_.ts-control_input]:min-w-0">
                                        <flux:label badge="Bắt buộc">Loại chi</flux:label>
                                        <div wire:ignore>
                                            <select
                                                class="tomselectEml"
                                                data-placeholder="Chọn loại chi"
                                                data-livewire-model="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.id_loaichi"
                                                data-livewire-live="false"
                                                required
                                                autocomplete="off"
                                            >
                                                <option value="">-- Chọn loại chi --</option>
                                                @foreach($expenseOptions as $expenseOption)
                                                    <option value="{{ $expenseOption['id'] }}" @selected((int) data_get($payment, "$group.$bucketKey.$index.id_loaichi") === (int) $expenseOption['id'])>
                                                        {{ $expenseOption['name'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <input type="hidden" wire:model="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.name">
                                    </flux:field>

                                    <flux:field class="min-w-0">
                                        <flux:label>Diễn giải chi</flux:label>
                                        <flux:input
                                            type="text"
                                            wire:model="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.diengiai_chi"
                                            :class:input="$inputClass"
                                        />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>Số tiền</flux:label>
                                        <flux:input
                                            type="text"
                                            required
                                            wire:model.live.debounce.300ms="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.so_tien"
                                            placeholder=""
                                            :class:input="$inputClass"
                                            mask:dynamic="$money($input, '.', ',', 0)"
                                        />
                                    </flux:field>

                                    <div class="flex justify-end pb-[1px]">
                                        <button type="button" wire:click="removeFee('{{ $group }}', '{{ $bucketKey }}', {{ $index }})" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 hover:border-red-200 hover:bg-red-50 hover:text-red-600" aria-label="Xóa khoản chi">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @elseif($bucketKey === 'phichiho')
                                <div wire:key="{{ $group }}-{{ $bucketKey }}-{{ data_get($row, '_key', $index) }}" class="grid grid-cols-[minmax(240px,1fr)_minmax(260px,1.3fr)_160px_56px] items-end gap-3">
                                    <flux:field class="min-w-0 [&_.ts-control]:min-h-[42px] [&_.ts-control]:whitespace-nowrap [&_.ts-control_input]:min-w-0">
                                        <flux:label badge="Bắt buộc">Loại chi hộ</flux:label>
                                        <div wire:ignore>
                                            <select
                                                class="tomselectEml"
                                                data-placeholder="Chọn loại chi hộ"
                                                data-livewire-model="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.id_loaiphuphi"
                                                data-livewire-live="false"
                                                required
                                                autocomplete="off"
                                            >
                                                <option value="">-- Chọn loại chi hộ --</option>
                                                @foreach($feeOptionsForBucket as $feeOption)
                                                    <option value="{{ $feeOption['id'] }}" @selected((int) data_get($payment, "$group.$bucketKey.$index.id_loaiphuphi") === (int) $feeOption['id'])>
                                                        {{ $feeOption['name'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <input type="hidden" wire:model="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.name">
                                    </flux:field>

                                    <flux:field class="min-w-0">
                                        <flux:label>Ghi chú</flux:label>
                                        <flux:input
                                            type="text"
                                            wire:model="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.note"
                                            :class:input="$inputClass"
                                        />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>Giá</flux:label>
                                        <flux:input
                                            type="text"
                                            required
                                            wire:model.live.debounce.300ms="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.price"
                                            placeholder=""
                                            :class:input="$inputClass"
                                            mask:dynamic="$money($input, '.', ',', 0)"
                                        />
                                    </flux:field>

                                    <div class="flex justify-end pb-[1px]">
                                        <button type="button" wire:click="removeFee('{{ $group }}', '{{ $bucketKey }}', {{ $index }})" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 hover:border-red-200 hover:bg-red-50 hover:text-red-600" aria-label="Xóa khoản chi hộ">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            @else
                            <div wire:key="{{ $group }}-{{ $bucketKey }}-{{ data_get($row, '_key', $index) }}" class="grid grid-cols-[minmax(260px,1.6fr)_minmax(180px,1fr)_110px_150px_110px_150px_150px_160px_56px] items-end gap-3">
                                <flux:field class="min-w-0 [&_.ts-control]:min-h-[42px] [&_.ts-control]:whitespace-nowrap [&_.ts-control_input]:min-w-0">
                                    <flux:label badge="Bắt buộc">Loại phụ phí</flux:label>
                                    <div wire:ignore>
                                        <select
                                            class="tomselectEml"
                                            data-placeholder="Chọn loại phụ phí"
                                            data-livewire-model="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.id_loaiphuphi"
                                            required
                                            autocomplete="off"
                                        >
                                            <option value="">-- Chọn loại phụ phí --</option>
                                            @foreach($feeOptionsForBucket as $feeOption)
                                                <option value="{{ $feeOption['id'] }}" @selected((int) data_get($payment, "$group.$bucketKey.$index.id_loaiphuphi") === (int) $feeOption['id'])>
                                                    {{ $feeOption['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <input type="hidden" wire:model="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.name">
                                </flux:field>

                                <flux:field class="min-w-0">
                                    <flux:label>Ghi chú</flux:label>
                                    <flux:input
                                        type="text"
                                        wire:model="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.note"
                                        :class:input="$inputClass"
                                    />
                                </flux:field>

                                <flux:field>
                                    <flux:label>Số lượng</flux:label>
                                    <flux:input
                                        type="text"
                                        required
                                        wire:model.live.debounce.300ms="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.soluong"
                                        placeholder=""
                                        :class:input="$inputClass"
                                        mask:dynamic="Math.max(1, parseInt($input.replace(/\D/g, '')) || 1).toString()"
                                    />
                                </flux:field>

                                <flux:field>
                                    <flux:label>Đơn giá</flux:label>
                                    <flux:input
                                        type="text"
                                        required
                                        wire:model.live.debounce.300ms="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.price"
                                        placeholder=""
                                        :class:input="$inputClass"
                                        mask:dynamic="$money($input, '.', ',', 0)"
                                    />
                                </flux:field>

                                <flux:field>
                                    <flux:label>VAT (%)</flux:label>
                                    <flux:input
                                        type="text"
                                        wire:model.live.debounce.300ms="payment.{{ $group }}.{{ $bucketKey }}.{{ $index }}.vat_percent"
                                        placeholder=""
                                        :class:input="$inputClass"
                                    />
                                </flux:field>

                                <div class="min-w-0">
                                    <label class="mb-2 block text-sm font-medium text-neutral-700">Tổng tiền</label>
                                    <div class="whitespace-nowrap rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-right text-sm font-semibold text-neutral-800">
                                        {{ $this->money(data_get($payment, "$group.$bucketKey.$index.total")) }}
                                    </div>
                                </div>

                                <div class="min-w-0">
                                    <label class="mb-2 block text-sm font-medium text-neutral-700">VAT phụ phí</label>
                                    <div class="whitespace-nowrap rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2.5 text-right text-sm text-neutral-700">
                                        {{ $this->money(data_get($payment, "$group.$bucketKey.$index.vat_amount")) }}
                                    </div>
                                </div>

                                <div class="min-w-0">
                                    <label class="mb-2 block text-sm font-medium text-neutral-700">Giá sau VAT</label>
                                    <div class="whitespace-nowrap rounded-lg border border-primary-100 bg-primary-50 px-3 py-2.5 text-right text-sm font-semibold text-primary-700">
                                        {{ $this->money(data_get($payment, "$group.$bucketKey.$index.total_after_vat")) }}
                                    </div>
                                </div>

                                <div class="flex justify-end pb-[1px]">
                                    <button type="button" wire:click="removeFee('{{ $group }}', '{{ $bucketKey }}', {{ $index }})" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 hover:border-red-200 hover:bg-red-50 hover:text-red-600" aria-label="Xóa khoản phí">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            @endif
                        @empty
                            <div class="rounded-lg bg-neutral-50 px-4 py-5 text-sm text-neutral-500">Chưa có khoản phí.</div>
                        @endforelse
                    </div>
                </div>
                @if($bucketKey === 'hh_khachhang')
                    <div class="flex flex-wrap justify-end gap-3 border-t border-neutral-100 bg-neutral-50 px-4 py-3 text-sm">
                        <div class="text-primary-700">Tổng hoa hồng khách hàng: <span class="font-semibold">{{ $this->money(data_get($payment, "$group.total_hh_khachhang")) }}</span></div>
                    </div>
                @elseif($bucketKey === 'phichiho')
                    <div class="flex flex-wrap justify-end gap-3 border-t border-neutral-100 bg-neutral-50 px-4 py-3 text-sm">
                        <div class="text-primary-700">Tổng phí chi hộ: <span class="font-semibold">{{ $this->money(data_get($payment, "$group.total_phichiho")) }}</span></div>
                    </div>
                @else
                <div class="flex flex-wrap justify-end gap-3 border-t border-neutral-100 bg-neutral-50 px-4 py-3 text-sm">
                    <div class="text-neutral-600">Tổng trước VAT: <span class="font-semibold text-neutral-900">{{ $this->money(data_get($payment, "$group.total_phuphi_no_vat")) }}</span></div>
                    <div class="text-neutral-600">VAT phụ phí: <span class="font-semibold text-neutral-900">{{ $this->money(data_get($payment, "$group.total_vat_phuphi")) }}</span></div>
                    <div class="text-primary-700">Sau VAT: <span class="font-semibold">{{ $this->money(data_get($payment, "$group.total_phuphi")) }}</span></div>
                </div>
                @endif
            </div>
        @endforeach
    </div>
</section>
