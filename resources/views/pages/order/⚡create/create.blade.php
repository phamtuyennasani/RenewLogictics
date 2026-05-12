<div
    class="mx-auto space-y-6"
    x-data="{
        isEmptyField(field) {
            if (field.type === 'checkbox') {
                return !field.checked;
            }

            return !String(field.value ?? '').trim();
        },
        validateAndSubmit() {
            const form = this.$refs.orderForm;
            if (!form) return;

            const requiredEls = [...form.querySelectorAll('[required]')].filter((f) => !f.disabled);

            let firstInvalid = null;

            for (const el of requiredEls) {
                let checkEl = el;
                let scrollEl = el;

                // Handle TomSelect: original <select> is hidden, check TS wrapper instead
                if (el.offsetParent === null) {
                    const tsWrapper = el.closest('.ts-wrapper')?.querySelector('.ts-control input, .ts-dropdown');
                    if (tsWrapper) {
                        checkEl = tsWrapper;
                        scrollEl = el.closest('.ts-wrapper') || el;
                    }
                }

                const isEmpty = checkEl.type === 'checkbox' ? !checkEl.checked : !String(checkEl.value ?? '').trim();

                if (isEmpty) {
                    firstInvalid = { checkEl, scrollEl, nativeEl: el };
                    break;
                }
            }

            if (firstInvalid) {
                requestAnimationFrame(() => {
                    firstInvalid.scrollEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.checkEl.focus?.({ preventScroll: true });
                });
                this.$wire.showRequiredFieldsToast();
                return;
            }

            this.$wire.submit();
        }
    }"
>
    {{-- Header --}}
    <div class="flex items-center gap-3">
        <button wire:click="goBack"
                class="p-2 rounded-xl text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-all cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <div>
            <p class="text-sm text-neutral-500 capitalize">Tác vụ / Đơn hàng</p>
            <h1 class="text-2xl font-bold text-neutral-900">Tạo mới đơn hàng</h1>
        </div>
    </div>
    {{-- Form --}}
    <form x-ref="orderForm" x-on:submit.prevent="validateAndSubmit()" novalidate class="space-y-6">
        <div class="grid grid-cols-2 gap-5">
            {{-- Left Column --}}
            <div></div>
            @if($showSaleSelector)
                <div wire:ignore>
                     <flux:field >
                        <flux:label badge="Bắt buộc">Sale phụ trách</flux:label>
                        <select class="tomselectEml " data-placeholder="-- Chọn nhân viên SALE --" wire:model.live="idSale" required autocomplete="off">
                            <option value="">-- Chọn nhân viên SALE --</option>
                            @foreach($listSale as $item)
                                <option value="{{ $item['id'] }}">{{ $item['fullname'].' - Mã Nhân Viên: '.$item['code'] }}</option>
                            @endforeach
                        </select>
                    </flux:field>
                </div>
            @endif
            <div class="space-y-5">
                <livewire:order.service wire:model="service" :item-services="$itemServices" wire:key="service" />
                <livewire:order.packages wire:model="packages" :service="$service" :dim="$dim" wire:key="packages" />
            </div>
            {{-- Right Column --}}
            <div class="space-y-5">
                <livewire:order.sender wire:model="sender" :list-customer="$listCustomer" :list-sender="$listSender" :id-sale="$idSale" wire:key="sender" />
                <livewire:order.receiver wire:model="receiver" :list-receiver="$listReceiver" :id-sale="$idSale" :sender-id="$this->sender['id']" wire:key="receiver" />
                <div class="bg-white rounded-lg p-5">
                    <h2 class="text-main-1 font-medium mb-4">Ghi chú</h2>
                    <textarea wire:model="notes" rows="4" class="w-full border border-neutral-200 rounded-lg p-3" placeholder="Nhập ghi chú cho đơn hàng..."></textarea>
                </div>
                <div class="bg-white rounded-lg p-5">
                    @error('submit')
                        <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm">
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="flex items-center justify-end gap-3 mb-3">
                        <a href="{{ route('orders.index') }}" class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                            Thoát
                        </a>
                        <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                            Tạo đơn
                        </button>
                    </div>

                    <div class="flex items-center justify-end">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.live="agreedToTerms" class="w-4 h-4 text-blue-500 border-neutral-300 rounded focus:ring-blue-500"/>
                            <span class="text-sm">
                                Tôi đã đọc và đồng ý với
                                <button type="button" class="text-blue-500 font-medium" data-bs-toggle="modal" data-bs-target="#termsModal">
                                    Quy định tạo đơn
                                </button>
                            </span>
                        </label>
                    </div>
                    @error('agreedToTerms')
                        <p class="text-red-500 text-sm mt-2 text-right">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
    </form>
</div>
