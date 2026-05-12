<div
    class="mx-auto space-y-6"
    x-data="{
        agreedToTerms: false,
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

            this.$wire.submit(this.agreedToTerms);
        }
    }"
>
    {{-- Header --}}
    <div class="flex items-center gap-3">
        <div>
            <p class="text-sm text-neutral-500 capitalize">Tác vụ / Đơn hàng</p>
            <h1 class="text-2xl font-bold text-neutral-900">Tạo mới đơn hàng</h1>
        </div>
    </div>
    {{-- Form --}}
    <form x-ref="orderForm" x-on:submit.prevent="validateAndSubmit()" novalidate class="space-y-6">
        <div class="grid grid-cols-2 gap-5">
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
            <div></div>
            <div><livewire:order.sender wire:model="sender" :list-customer="$listCustomer" :list-sender="$listSender" :id-sale="$idSale" wire:key="sender" /></div>
            <div><livewire:order.receiver wire:model="receiver" :list-receiver="$listReceiver" :id-sale="$idSale" :sender-id="$this->sender['id']" wire:key="receiver" /></div>
            <div class="col-span-full">
                <livewire:order.service wire:model="service" :item-services="$itemServices" wire:key="service" />
            </div>
            <div class="col-span-full"><livewire:order.packages wire:model="packages" :service="$service" :dim="$dim" wire:key="packages" /></div>
            <div class="col-span-full">
                <livewire:order.phuphi wire:model="phuphihaiquan" wire:key="phuphihaiquan" />
            </div>
            <div class=""></div>
            <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 space-y-3">
                <div class="flex items-center justify-end">
                    <flux:field variant="inline" >
                        <flux:checkbox x-model="agreedToTerms" />
                        <flux:label> Tôi đã đọc và đồng ý với <b class="text-blue-500 ml-1 cursor-pointer">Quy định tạo đơn</b> </flux:label>
                    </flux:field>
                </div>
                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('orders.index') }}" class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                        Thoát
                    </a>
                    <flux:button variant="primary" type="submit" x-bind:disabled="!agreedToTerms" :loading="false">Tạo đơn</flux:button>
                </div>
            </div>
        </div>
    </form>
</div>
