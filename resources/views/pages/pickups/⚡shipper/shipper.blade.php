<div class="pb-20">
    @php $summary = $this->summary; @endphp
    <div class="bg-gradient-to-br from-primary-600 to-primary-800 text-white px-4 py-4 relative overflow-hidden">
        <p class="text-xs font-medium opacity-90">Đơn hàng chưa nhận</p>
        <p class="text-3xl font-bold mt-0.5">{{ $summary['pending_count'] }} đơn hàng</p>
        <p class="text-xs mt-1 opacity-80">
            Thời gian lấy hàng gần nhất:
            <span class="font-semibold">
                {{ $summary['nearest_time'] ? \Carbon\Carbon::parse($summary['nearest_time'])->format('h:i A (d/m/Y)') : 'Chưa có' }}
            </span>
        </p>
        <div class="absolute right-4 top-4 rounded-full border border-white/20 bg-white/10 p-2">
            <svg class="w-9 h-9 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
    </div>
    <div id="shipper-pickup-scan" class="hidden scroll-mt-20 bg-white border-b border-neutral-200 p-4" x-data="{
        cameraActive: false,
        html5QrCode: null,
        scannerPromise: null,
        lastCode: '',
        lastAt: 0,
        duplicateWindowMs: 4000,
        statusMsg: 'Nhấn để bật camera quét mã kiện',
        statusType: 'neutral',

        async loadScanner() {
            if (typeof window.loadHtml5Qrcode !== 'function') {
                throw new Error('Scanner assets not available.');
            }
            this.scannerPromise ??= window.loadHtml5Qrcode();
            return this.scannerPromise;
        },

        cameraErrorMessage(error) {
            const name = error?.name || '';
            if (name === 'NotAllowedError') return 'Bạn đã từ chối quyền camera.';
            if (name === 'NotFoundError') return 'Không tìm thấy camera trên thiết bị.';
            if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
                return 'Camera chỉ hoạt động trên HTTPS hoặc localhost.';
            }
            return error?.message || 'Không thể bật camera.';
        },

        handleDecodedText(decodedText) {
            const text = decodedText?.trim().toUpperCase();
            if (!text) return;
            if (!/^[A-Z0-9-]+-\d{2,}$/.test(text)) {
                this.statusMsg = 'Mã không hợp lệ: ' + text;
                this.statusType = 'neutral';
                return;
            }
            const now = Date.now();
            if (text === this.lastCode && now - this.lastAt < this.duplicateWindowMs) return;
            this.lastCode = text;
            this.lastAt = now;
            this.statusMsg = 'Đã đọc: ' + text;
            this.statusType = 'success';
            $wire.processShipperScan(text).finally(() => {
                setTimeout(() => this.recoverCameraAfterScan(), 250);
            });
        },

        async recoverCameraAfterScan() {
            if (!this.cameraActive) return;

            const reader = this.$refs.pickupScannerReader;
            if (!reader || !reader.querySelector('video')) {
                await this.startCamera();
                return;
            }

            this.statusMsg = 'Đưa barcode tiếp theo vào khung hình';
            this.statusType = 'success';
        },

        async startCamera() {
            await this.stopCamera();
            this.statusMsg = 'Đang bật camera...';
            this.statusType = 'neutral';
            try {
                const { Html5Qrcode, Html5QrcodeSupportedFormats } = await this.loadScanner();
                this.$refs.pickupScannerReader.innerHTML = '';
                this.html5QrCode = new Html5Qrcode(this.$refs.pickupScannerReader.id, false);
                await this.html5QrCode.start(
                    { facingMode: 'environment' },
                    {
                        fps: 12,
                        aspectRatio: 4 / 3,
                        rememberLastUsedCamera: true,
                        formatsToSupport: [Html5QrcodeSupportedFormats.CODE_128],
                        qrbox: (width, height) => ({
                            width: Math.floor(width * 0.78),
                            height: Math.min(Math.floor(height * 0.58), 260),
                        }),
                    },
                    (decodedText) => this.handleDecodedText(decodedText),
                    () => {}
                );
                this.cameraActive = true;
                this.statusMsg = 'Đưa barcode vào khung hình';
                this.statusType = 'success';
            } catch (error) {
                this.statusMsg = this.cameraErrorMessage(error);
                this.statusType = 'error';
            }
        },

        async stopCamera() {
            if (this.html5QrCode) {
                try { await this.html5QrCode.stop(); } catch {}
                try { this.html5QrCode.clear(); } catch {}
                this.html5QrCode = null;
            }
            if (this.$refs.pickupScannerReader) {
                this.$refs.pickupScannerReader.innerHTML = '';
            }
            this.cameraActive = false;
        },
    }">
        <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-950">
            <div class="relative isolate overflow-hidden">
                <div wire:ignore id="shipper-pickup-barcode-reader" x-ref="pickupScannerReader" class="relative z-0 aspect-[4/3] w-full [&_video]:h-full [&_video]:w-full [&_video]:object-cover"></div>
                <div class="pointer-events-none absolute inset-0 z-10">
                    <div class="absolute inset-0 bg-black/35"></div>
                    <div class="absolute inset-x-[11%] top-[19%] bottom-[19%] rounded-xl border-2 border-white/80" style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.35);"></div>
                    <div class="absolute left-[11%] top-[19%] h-6 w-6 rounded-tl-lg border-l-4 border-t-4 border-emerald-400"></div>
                    <div class="absolute right-[11%] top-[19%] h-6 w-6 rounded-tr-lg border-r-4 border-t-4 border-emerald-400"></div>
                    <div class="absolute left-[11%] bottom-[19%] h-6 w-6 rounded-bl-lg border-b-4 border-l-4 border-emerald-400"></div>
                    <div class="absolute right-[11%] bottom-[19%] h-6 w-6 rounded-br-lg border-b-4 border-r-4 border-emerald-400"></div>
                </div>
                <div class="absolute bottom-0 inset-x-0 z-20 bg-black/65 px-4 py-2 text-center text-xs font-semibold"
                     x-bind:class="statusType === 'success' ? 'text-emerald-300' : (statusType === 'error' ? 'text-red-300' : 'text-white/80')"
                     x-text="statusMsg"></div>
                <button type="button"
                        x-on:click="cameraActive ? stopCamera() : startCamera()"
                        class="absolute right-3 top-3 z-20 flex h-10 w-10 items-center justify-center rounded-full bg-black/55 text-white backdrop-blur-sm active:bg-black/75">
                    <svg x-show="!cameraActive" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <svg x-show="cameraActive" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                </button>
            </div>
            <div class="flex gap-2 border-t border-white/10 bg-neutral-900 p-3">
                <input type="text"
                       wire:model="scanBarcodeInput"
                       x-on:keydown.enter.prevent="$wire.submitShipperScan()"
                       placeholder="Nhập mã kiện..."
                       class="h-10 min-w-0 flex-1 rounded-xl border border-white/10 bg-white px-3 font-mono text-sm font-bold text-neutral-900 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <button type="button"
                        wire:click="submitShipperScan"
                        wire:loading.attr="disabled"
                        class="h-10 rounded-xl bg-primary-600 px-4 text-sm font-bold text-white active:bg-primary-700">
                    Quét
                </button>
            </div>
        </div>

        @if($scanResult['message'])
            <div class="mt-3 rounded-2xl border {{ $scanResult['type'] === 'error' ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50' }} p-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold {{ $scanResult['type'] === 'error' ? 'text-red-800' : 'text-emerald-800' }}">
                            {{ $scanResult['message'] }}
                        </p>
                        @if($scanResult['pickup_code'])
                            <p class="mt-1 font-mono text-xs font-bold text-primary-700">{{ $scanResult['pickup_code'] }} / {{ $scanResult['order_code'] }}</p>
                        @elseif($scanResult['package_code'])
                            <p class="mt-1 font-mono text-xs font-bold text-neutral-700">{{ $scanResult['package_code'] }}</p>
                        @endif
                    </div>
                    @if($scanResult['package_count'])
                        <span class="shrink-0 rounded-full bg-white px-2 py-1 text-xs font-bold text-neutral-700">{{ $scanResult['package_count'] }} kiện</span>
                    @endif
                </div>

                @if($scanResult['pickup_code'])
                    <div class="mt-3 space-y-1.5 text-xs text-neutral-700">
                        <p><span class="font-semibold text-neutral-950">Khách hàng:</span> {{ $scanResult['customer'] }}</p>
                        @if($scanResult['address'])
                            <p><span class="font-semibold text-neutral-950">Địa chỉ:</span> {{ $scanResult['address'] }}</p>
                        @endif
                        @if($scanResult['phone'])
                            <p><span class="font-semibold text-neutral-950">SĐT:</span> <a href="tel:{{ $scanResult['phone'] }}" class="font-semibold text-primary-700">{{ $scanResult['phone'] }}</a></p>
                        @endif
                        <p><span class="font-semibold text-neutral-950">Trạng thái:</span> {{ $scanResult['pickup_status'] }}</p>
                    </div>

                    <button type="button"
                            wire:click="receiveScannedPickup"
                            wire:loading.attr="disabled"
                            @disabled($scanResult['pickup_status'] === \App\Enums\PickupStatusEnum::PICKUP_DA_LAY->label())
                            class="mt-3 flex h-11 w-full items-center justify-center rounded-xl bg-emerald-600 text-sm font-bold text-white active:bg-emerald-700 disabled:bg-emerald-200">
                        Nhận hàng
                    </button>
                @endif
            </div>
        @endif
    </div>

    <div class="bg-white border-b border-neutral-200 px-4 py-3">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text"
                   wire:model.live.debounce.400ms="keyword"
                   placeholder="Tìm mã pickup, tên, SĐT..."
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-neutral-200 bg-neutral-50 text-sm focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500">
        </div>
    </div>
    <div class="bg-neutral-50 px-4 pt-3 pb-1">
        <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-2">Danh sách địa chỉ lấy hàng</p>
        <div class="flex border-b border-neutral-200">
            @foreach([
                'new'      => 'Mới giao',
                'accepted' => 'Tiếp nhận',
                'picking'  => 'Đang lấy',
                'done'     => 'Đã lấy',
            ] as $tabKey => $tabLabel)
                @php
                    $tabStatus = match ($tabKey) {
                        'new' => \App\Enums\PickupStatusEnum::MOI_TAO_PICKUP,
                        'accepted' => \App\Enums\PickupStatusEnum::DA_XAC_NHAN,
                        'picking' => \App\Enums\PickupStatusEnum::PICKUP_DANG_LAY,
                        'done' => \App\Enums\PickupStatusEnum::PICKUP_DA_LAY,
                    };
                @endphp
                <button wire:click="$set('tab', '{{ $tabKey }}')"
                        class="flex-1 py-2.5 text-xs font-semibold text-center border-b-2 rounded-t-lg transition-colors
                            {{ $tab === $tabKey
                                ? 'border-current '.$tabStatus->color()
                                : 'border-transparent text-neutral-500 hover:text-neutral-700' }}">
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>
    </div>
    <div class="px-4 pt-3 space-y-3">
        @forelse($this->pickups as $index => $pickup)
            @php
                $customer = $pickup->info_khachhang ?? [];
                $info = $pickup->info_pickup ?? [];
                $isExpanded = $this->expandedId === $pickup->id;
                $phone = data_get($customer, 'phone', '');
                $address = data_get($customer, 'address', '');
                $scheduledAt = data_get($info, 'ngayhen');
                $companyName = data_get($customer, 'company') ?: data_get($customer, 'fullname', '-');
                $country = data_get($customer, 'country', '');
                $creatorName = $pickup->user?->fullname ?: $pickup->user?->username ?: '-';
                $pickupLat = data_get($customer, 'pickup_lat');
                $pickupLng = data_get($customer, 'pickup_lng');
                $hasPickupLocation = is_numeric($pickupLat) && is_numeric($pickupLng);
                $scheduledLabel = $scheduledAt ? \Carbon\Carbon::parse($scheduledAt)->format('d/m/Y H:i') : '';
                $weightLabel = number_format((float) $pickup->total_c_weight, 0, ',', '.').' Kg';
            @endphp
            <div class="bg-white rounded-xl border border-neutral-200 shadow-sm overflow-hidden" wire:key="pickup-{{ $pickup->id }}">
                <div class="px-4 pt-3 pb-2">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center {{ $pickup->status?->color() ?? 'bg-neutral-100 text-neutral-700' }}">
                            <span class="text-sm font-bold">{{ $this->pickups->firstItem() + $index }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between">
                                <p class="text-sm font-bold text-neutral-900 leading-tight">{{ $companyName }}</p>
                            </div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="inline-flex items-center gap-1 text-xs">
                                    <svg class="w-3 h-3 text-primary-500" fill="currentColor" viewBox="0 0 20 20"><rect width="20" height="14" rx="2" y="3"/></svg>
                                    <span class="font-semibold text-primary-700">{{ $pickup->ma_pickup }}</span>
                                </span>
                                @if($country)
                                    <span class="text-xs font-medium text-accent-700 bg-accent-50 px-1.5 py-0.5 rounded">{{ $country }}</span>
                                @endif
                            </div>
                            @if($address || $phone)
                                <div class="mt-1.5 flex items-start gap-1 text-xs text-neutral-600">
                                    <svg class="w-3 h-3 text-primary-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="leading-snug">
                                        {{ $address }}
                                        @if($phone)
                                            / <a href="tel:{{ $phone }}" class="text-primary-700 font-medium">{{ $phone }}</a>
                                            @if(data_get($customer, 'fullname'))
                                                ({{ data_get($customer, 'fullname') }})
                                            @endif
                                        @endif
                                    </span>
                                </div>
                            @endif
                            <div class="mt-2 flex items-center gap-3 text-xs text-neutral-500">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    Kiện hàng: <span class="font-semibold text-neutral-700">{{ $pickup->numb }}</span>
                                </span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                                    Cân nặng: <span class="font-semibold text-neutral-700">{{ number_format((float) $pickup->total_c_weight, 0, ',', '.') }} Kg</span>
                                </span>
                                @if($pickup->status)
                                    <span class="ml-auto inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $pickup->status->color() }}">
                                        {{ $pickup->status->label() }}
                                    </span>
                                @endif
                            </div>
                            <div class="mt-2 flex items-center gap-4 text-xs text-neutral-500">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    {{ $creatorName }}
                                </span>
                                @if($scheduledAt)
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ \Carbon\Carbon::parse($scheduledAt)->format('(d/m/Y) h:i A') }}
                                    </span>
                                @endif
                            </div>
                            @if($pickup->note)
                                <div class="mt-1.5 flex items-start gap-1 text-xs text-neutral-500">
                                    <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span>Ghi chú: {{ $pickup->note }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($pickup->status === \App\Enums\PickupStatusEnum::PICKUP_DANG_LAY)
                    <div class="border-t border-neutral-100 px-4 py-3 space-y-3 bg-neutral-50/50">
                        <div class="flex gap-2">
                            @if($phone)
                                <a href="tel:{{ $phone }}" class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl bg-primary-50 text-primary-700 text-xs font-semibold active:bg-primary-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    Gọi điện
                                </a>
                            @endif
                            <button type="button"
                                    data-shipper-route-button
                                    data-pickup-id="{{ $pickup->id }}"
                                    data-pickup-code="{{ e($pickup->ma_pickup) }}"
                                    data-pickup-company="{{ e($companyName) }}"
                                    data-pickup-address="{{ e($address) }}"
                                    data-pickup-phone="{{ e($phone) }}"
                                    data-pickup-packages="{{ e((string) $pickup->numb) }}"
                                    data-pickup-weight="{{ e($weightLabel) }}"
                                    data-pickup-scheduled="{{ e($scheduledLabel) }}"
                                    data-pickup-lat="{{ $hasPickupLocation ? $pickupLat : '' }}"
                                    data-pickup-lng="{{ $hasPickupLocation ? $pickupLng : '' }}"
                                    @disabled(! $hasPickupLocation)
                                    class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl text-xs font-semibold
                                        {{ $hasPickupLocation
                                            ? 'bg-accent-50 text-accent-700 active:bg-accent-100'
                                            : 'bg-neutral-100 text-neutral-400 cursor-not-allowed' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $hasPickupLocation ? 'Chỉ đường' : 'Chưa có vị trí' }}
                            </button>
                        </div>
                    </div>
                @endif
                <div class="flex border-t border-neutral-100">
                @if($pickup->status === \App\Enums\PickupStatusEnum::MOI_TAO_PICKUP)
                    <button wire:click="updateStatus({{ $pickup->id }}, '{{ \App\Enums\PickupStatusEnum::DA_XAC_NHAN->value }}')"
                            wire:loading.attr="disabled"
                            class="flex-1 py-2 bg-primary-600 text-white text-[0] font-bold uppercase tracking-wide active:bg-primary-700">
                        <span class="text-sm">Tiếp nhận</span>
                    </button>
                @elseif($pickup->status === \App\Enums\PickupStatusEnum::DA_XAC_NHAN)
                    <button wire:click="updateStatus({{ $pickup->id }}, '{{ \App\Enums\PickupStatusEnum::PICKUP_DANG_LAY->value }}')"
                            wire:loading.attr="disabled"
                            class="flex-1 py-2 bg-primary-700 text-white text-sm font-bold uppercase tracking-wide active:bg-primary-800">
                        <span class="text-sm">Bắt đầu lấy hàng</span>
                    </button>
                @elseif($pickup->status === \App\Enums\PickupStatusEnum::PICKUP_DANG_LAY)
                    <button wire:click="openProofModal({{ $pickup->id }}, 'card')"
                            wire:loading.attr="disabled"
                            class="flex-1 py-2 bg-emerald-600 text-white text-sm font-bold uppercase tracking-wide active:bg-emerald-700">
                        <span class="text-sm">Đã nhận hàng</span>
                    </button>
                @elseif($pickup->status === \App\Enums\PickupStatusEnum::PICKUP_DA_LAY)
                    <div class="flex-1 py-2.5 bg-emerald-50 text-emerald-700 text-xs font-semibold text-center">
                        <span class="text-sm">✓ Đã nhận hàng</span>
                    </div>
                @endif
                @if(! $pickup->status->isFinal())
                    <button wire:click="cancelPickup({{ $pickup->id }})"
                            wire:confirm="Bạn có chắc muốn hủy phiếu pickup này?"
                            wire:loading.attr="disabled"
                            class="flex-1 py-2 border-l border-red-100 bg-red-50 text-red-600 text-[0] font-bold uppercase tracking-wide active:bg-red-100">
                        <span class="text-sm">Hủy</span>
                    </button>
                @endif
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-neutral-400">
                <svg class="w-16 h-16 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <p class="text-sm font-medium">Không có phiếu pickup nào</p>
            </div>
        @endforelse
        {{-- Pagination --}}
        @if($this->pickups->hasPages())
            <div class="py-3">
                {{ $this->pickups->links() }}
            </div>
        @endif
    </div>

    <div id="shipper-route-overlay"
         class="fixed inset-0 z-[100] hidden bg-neutral-950"
         aria-hidden="true">
        <div id="shipper-route-map" class="absolute inset-0 h-dvh min-h-screen w-full" style="height: 100dvh; min-height: 100vh;"></div>

        <button type="button"
                id="shipper-route-close"
                class="absolute left-3 top-3 z-20 flex h-11 w-11 items-center justify-center rounded-full bg-white text-neutral-800 shadow-lg active:bg-neutral-100"
                aria-label="Đóng bản đồ">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>

        <div class="absolute bottom-3 left-3 right-3 z-20 overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-4 py-3">
                <div class="min-w-0">
                    <p id="shipper-route-code" class="truncate text-xs font-bold text-primary-700">-</p>
                    <p id="shipper-route-company" class="truncate text-sm font-bold text-neutral-950">-</p>
                </div>
                <button type="button"
                        id="shipper-route-info-toggle"
                        class="shrink-0 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-bold text-neutral-700 active:bg-neutral-50">
                    <span id="shipper-route-info-toggle-label">Ẩn</span>
                </button>
            </div>

            <div id="shipper-route-info-body" class="space-y-3 px-4 py-3">
                <button type="button"
                        id="shipper-route-location"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white active:bg-primary-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657A8 8 0 105.64 5.64a8 8 0 0012.017 11.017z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11h.01"/>
                    </svg>
                    Tìm đường
                </button>

                <button type="button"
                        id="shipper-route-native"
                        class="hidden w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white active:bg-emerald-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 18l6-6-6-6"/>
                    </svg>
                    Chỉ đường
                </button>

                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-lg bg-neutral-50 px-3 py-2">
                        <p class="text-neutral-500">Quãng đường</p>
                        <p id="shipper-route-distance" class="mt-0.5 font-bold text-neutral-950">-</p>
                    </div>
                    <div class="rounded-lg bg-neutral-50 px-3 py-2">
                        <p class="text-neutral-500">Thời gian</p>
                        <p id="shipper-route-duration" class="mt-0.5 font-bold text-neutral-950">-</p>
                    </div>
                    <div class="rounded-lg bg-neutral-50 px-3 py-2">
                        <p class="text-neutral-500">Kiện hàng</p>
                        <p id="shipper-route-packages" class="mt-0.5 font-bold text-neutral-950">-</p>
                    </div>
                    <div class="rounded-lg bg-neutral-50 px-3 py-2">
                        <p class="text-neutral-500">Cân nặng</p>
                        <p id="shipper-route-weight" class="mt-0.5 font-bold text-neutral-950">-</p>
                    </div>
                </div>

                <div class="space-y-1.5 text-xs text-neutral-600">
                    <p class="font-semibold text-neutral-900">Địa chỉ</p>
                    <p id="shipper-route-address" class="leading-snug">-</p>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 pt-1">
                        <span>SĐT: <span id="shipper-route-phone" class="font-semibold text-primary-700">-</span></span>
                        <span>Hẹn: <span id="shipper-route-scheduled" class="font-semibold text-neutral-800">-</span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal up ảnh bằng chứng khi xác nhận đã lấy hàng --}}
    @if($showProofModal)
        <div class="fixed inset-0 z-[110] flex items-end justify-center bg-black/50 sm:items-center" wire:key="proof-modal">
            <div class="w-full max-w-md rounded-t-2xl bg-white p-4 shadow-xl sm:rounded-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-neutral-100 pb-3">
                    <div>
                        <h2 class="text-base font-bold text-neutral-950">Ảnh chứng minh đã lấy hàng</h2>
                        <p class="mt-0.5 text-xs text-neutral-500">Chụp hoặc chọn ít nhất 1 ảnh trước khi xác nhận.</p>
                    </div>
                    <button type="button" wire:click="closeProofModal"
                            class="rounded-lg p-1.5 text-neutral-400 active:bg-neutral-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="py-4">
                    {{-- Nút chụp/chọn ảnh --}}
                    <label class="flex w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-primary-300 bg-primary-50 py-6 text-primary-700 active:bg-primary-100">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="text-sm font-bold">Chụp ảnh / Chọn ảnh</span>
                        <input type="file" wire:model="proofImages" accept="image/*" capture="environment" multiple class="hidden">
                    </label>

                    <div wire:loading wire:target="proofImages" class="mt-2 text-center text-xs text-neutral-500">Đang tải ảnh...</div>

                    @error('proofImages.*') <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                    @error('proofPhotos') <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p> @enderror

                    {{-- Preview ảnh đã tích lũy (gom nhiều lần chụp/chọn) --}}
                    @if(count($proofPhotos) > 0)
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            @foreach($proofPhotos as $idx => $img)
                                @if(is_object($img) && method_exists($img, 'temporaryUrl'))
                                    <div class="relative aspect-square overflow-hidden rounded-lg border border-neutral-200" wire:key="proof-prev-{{ $idx }}">
                                        <img src="{{ $img->temporaryUrl() }}" class="h-full w-full object-cover" alt="Ảnh {{ $idx + 1 }}">
                                        <button type="button" wire:click="removeProofPhoto({{ $idx }})"
                                                class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-black/60 text-white active:bg-black/80">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-neutral-500">Đã có {{ count($proofPhotos) }}/8 ảnh. Bấm "Chụp ảnh / Chọn ảnh" để thêm.</p>
                    @endif
                </div>

                <div class="flex gap-2 border-t border-neutral-100 pt-3">
                    <button type="button" wire:click="closeProofModal"
                            class="flex-1 rounded-xl border border-neutral-200 bg-white py-2.5 text-sm font-bold text-neutral-700 active:bg-neutral-50">
                        Hủy
                    </button>
                    <button type="button" wire:click="confirmReceivedWithProof"
                            wire:loading.attr="disabled" wire:target="confirmReceivedWithProof,proofImages"
                            class="flex-1 rounded-xl bg-emerald-600 py-2.5 text-sm font-bold text-white active:bg-emerald-700 disabled:bg-emerald-300">
                        Xác nhận đã nhận
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
