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

            let invalidFields = [];

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
                    const label = el.closest('.field, [data-field]')?.querySelector('label')?.textContent?.trim()
                        || el.getAttribute('data-label')
                        || el.getAttribute('placeholder')
                        || el.name
                        || 'Trường không xác định';
                    invalidFields.push({ el, scrollEl, checkEl, label: label.replace(/\s*Bắt buộc\s*/gi, '').trim() });
                }
            }

            if (invalidFields.length > 0) {
                const first = invalidFields[0];
                requestAnimationFrame(() => {
                    first.scrollEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    first.checkEl.focus?.({ preventScroll: true });
                });
                const labels = invalidFields.map(f => f.label);
                this.$wire.showRequiredFieldsToast(labels);
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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
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
            <div class="hidden lg:block"></div>
            <div><livewire:order.sender wire:model="sender" :list-customer="$listCustomer" :list-sender="$listSender" :id-sale="$idSale" wire:key="sender" /></div>
            <div><livewire:order.receiver wire:model="receiver" :list-receiver="$listReceiver" :id-sale="$idSale" :sender-id="$this->sender['id']" wire:key="receiver" /></div>
            <div class="col-span-full">
                <livewire:order.service wire:model="service" :item-services="$itemServices" :can-see-extra-services="auth()->user()->hasAnyRole(['admin','cs'])" wire:key="service" />
            </div>
            <div class="col-span-full"><livewire:order.packages wire:model="packages" :service="$service" :dim="$dim" wire:key="packages" /></div>
            <div class="col-span-full">
                <livewire:order.phuphi wire:model="phuphihaiquan" wire:key="phuphihaiquan" />
            </div>
            <div class="col-span-full">
                <livewire:order.invoice wire:model="invoices" wire:key="invoices" />
            </div>
            <div class="col-span-full">
                <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">
                    <div class="px-6 py-5 border-b border-neutral-100 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            Ghi chú đơn hàng
                        </h2>
                    </div>

                    <div class="space-y-4 p-6">
                        <flux:field>
                            <flux:label>Ghi chú đơn hàng:</flux:label>
                            <flux:textarea wire:model="ghichu.note" resize="none" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Ảnh đính kèm kiện hàng:</flux:label>
                            <div class="space-y-3">
                                <label
                                    for="order-photos"
                                    class="flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-neutral-300 bg-neutral-50 px-4 py-5 text-center transition hover:border-primary-400 hover:bg-primary-50"
                                    wire:loading.class="opacity-60"
                                    wire:target="newPhotos"
                                >
                                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-primary-600 shadow-sm">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </span>
                                    <span class="mt-2 text-sm font-medium text-neutral-700">Chọn tối đa 5 hình ảnh</span>
                                    <span class="mt-1 text-xs text-neutral-500">PNG, JPG, JPEG, WEBP. Tối đa 20MB mỗi ảnh.</span>
                                </label>

                                <input id="order-photos" type="file" class="hidden" wire:model="newPhotos" accept="image/*" multiple>

                                <div wire:loading wire:target="newPhotos" class="rounded-lg border border-primary-100 bg-primary-50/70 p-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-white text-primary-600 shadow-sm">
                                            <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                                            </svg>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-3">
                                                <p class="text-sm font-medium text-primary-700">Đang tải ảnh lên</p>
                                                <span class="text-xs text-primary-600">Vui lòng chờ</span>
                                            </div>
                                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white">
                                                <div class="h-full w-1/2 animate-pulse rounded-full bg-primary-500"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @error('newPhotos') <flux:error>{{ $message }}</flux:error> @enderror
                                @error('newPhotos.*') <flux:error>{{ $message }}</flux:error> @enderror

                                @if(!empty($ghichu['photos']))
                                    <div class="divide-y divide-neutral-100 overflow-hidden rounded-lg border border-neutral-200 bg-white">
                                        @foreach($ghichu['photos'] as $index => $photo)
                                            @if(is_object($photo))
                                                <div class="flex items-center gap-3 p-3">
                                                    <div class="h-16 w-16 flex-none overflow-hidden rounded-md border border-neutral-200 bg-neutral-100">
                                                        <img src="{{ $photo->temporaryUrl() }}" alt="Ảnh đính kèm {{ $index + 1 }}" class="h-full w-full object-cover">
                                                    </div>

                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-sm font-medium text-neutral-800">
                                                            {{ $photo->getClientOriginalName() }}
                                                        </p>
                                                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-neutral-500">
                                                            <span>{{ $this->fileSize($photo) }}</span>
                                                            <span>{{ $this->imageDimensions($photo) }}</span>
                                                        </div>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        wire:click="removeOrderPhoto({{ $index }})"
                                                        class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-600 hover:border-red-500 hover:bg-red-500 hover:text-white"
                                                        aria-label="Xóa ảnh"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </flux:field>
                    </div>
                </div>
            </div>
            <div class=""></div>
            <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 space-y-3">
                <div class="flex items-center justify-end">
                    <flux:field variant="inline" class="!flex items-center !gap-0">
                        <flux:checkbox x-model="agreedToTerms" />
                        <flux:label class="ml-2 mr-1 text-sm">Tôi đã đọc và đồng ý với</flux:label>
                        <flux:modal.trigger name="quy-dinh-tao-don">
                            <b class="text-blue-500 hover:text-blue-600 text-sm cursor-pointer">Quy định tạo đơn</b>
                        </flux:modal.trigger>
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

    <flux:modal name="quy-dinh-tao-don" class="max-w-[80rem] w-[80rem]" scroll="body">
        <div class="space-y-6">
            <div class="prose prose-neutral max-w-none text-neutral-700 show-content">
                {!! $this->chinhsach !!}
            </div>
            <div class="flex">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button type="button" variant="primary">Đóng</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
