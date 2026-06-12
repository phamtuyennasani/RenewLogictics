<?php

use App\Actions\Pickup\TransitionPickupStatusAction;
use App\Enums\PickupStatusEnum;
use App\Models\OrderPackage;
use App\Models\Pickup;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.mobile')] #[Title('Quét mã nhận Pickup')] class extends Component
{
    public string $barcodeInput = '';
    public ?int $scannedPickupId = null;
    public ?int $scannedOrderId = null;
    public int $successCount = 0;
    public int $errorCount = 0;
    public array $scanLog = [];

    public array $scanResult = [
        'message' => '',
        'type' => 'neutral',
        'package_code' => '',
        'order_code' => '',
        'pickup_code' => '',
        'pickup_status' => '',
        'customer' => '',
        'address' => '',
        'phone' => '',
        'package_count' => 0,
        'can_receive' => false,
    ];

    public function mount(): void
    {
        abort_unless(\Gate::allows('pickups.index'), 403);
    }

    public function submitScan(): void
    {
        $code = trim($this->barcodeInput);
        $this->barcodeInput = '';

        if (blank($code)) {
            return;
        }

        $this->processScan($code);
    }

    public function processScan(string $code): void
    {
        $code = trim(strtoupper($code));

        $package = OrderPackage::query()
            ->with(['order:id,id_bill,tracking_code'])
            ->where('code', $code)
            ->first();

        if (! $package || ! $package->order) {
            $this->clearScannedPickup();
            $this->errorCount++;
            $this->setScanResult('Không tìm thấy đơn hàng từ mã kiện này.', 'error', $code);
            $this->pushLog($code, 'Không tìm thấy đơn hàng', 'error');
            return;
        }

        $pickup = Pickup::query()
            ->where('id_shipper', auth()->id())
            ->whereHas('orders', fn ($query) => $query->whereKey($package->order->id))
            ->where('status', '!=', PickupStatusEnum::DA_HUY->value)
            ->with(['orders' => fn ($query) => $query->whereKey($package->order->id)->withCount('packages')])
            ->first();

        if (! $pickup) {
            $this->clearScannedPickup();
            $this->errorCount++;
            $this->setScanResult('Không tìm thấy pickup được gán cho bạn từ mã kiện này.', 'error', $code);
            $this->pushLog($code, 'Không tìm thấy pickup được gán', 'error');
            return;
        }

        $order = $pickup->orders->first() ?: $package->order->loadCount('packages');
        $packageCount = max(1, (int) ($order->packages_count ?: 1));
        $canReceive = $pickup->status !== PickupStatusEnum::PICKUP_DA_LAY;

        $this->scannedPickupId = $pickup->id;
        $this->scannedOrderId = $order->id;
        $this->scanResult = [
            'message' => $canReceive
                ? 'Đã tìm thấy pickup. Kiểm tra thông tin rồi bấm nhận hàng.'
                : 'Pickup này đã được nhận hàng.',
            'type' => 'success',
            'package_code' => $package->code,
            'order_code' => $order->id_bill ?: $order->tracking_code ?: '-',
            'pickup_code' => $pickup->ma_pickup,
            'pickup_status' => $pickup->status?->label() ?? '-',
            'customer' => data_get($pickup->info_khachhang, 'company')
                ?: data_get($pickup->info_khachhang, 'fullname')
                ?: '-',
            'address' => data_get($pickup->info_khachhang, 'address', ''),
            'phone' => data_get($pickup->info_khachhang, 'phone', ''),
            'package_count' => $packageCount,
            'can_receive' => $canReceive,
        ];

        $this->pushLog($code, $this->scanResult['message'], 'success');
    }

    public function receiveScannedPickup(): void
    {
        if (! $this->scannedPickupId || ! $this->scannedOrderId) {
            Flux::toast(heading: 'Lỗi', text: 'Vui lòng quét mã kiện trước khi nhận hàng.', variant: 'warning');
            return;
        }

        $pickup = Pickup::query()
            ->where('id_shipper', auth()->id())
            ->whereHas('orders', fn ($query) => $query->whereKey($this->scannedOrderId))
            ->findOrFail($this->scannedPickupId);

        try {
            $pickup = $this->markPickupAsReceived($pickup);
        } catch (\RuntimeException $e) {
            Flux::toast(heading: 'Lỗi', text: $e->getMessage(), variant: 'warning');
            return;
        }

        $packageCount = max(1, (int) ($this->scanResult['package_count'] ?? 1));
        $message = $packageCount > 1
            ? "Shiper đã nhận đủ {$packageCount} kiện hàng"
            : 'Shiper đã nhận hàng thành công';

        $this->successCount++;
        $this->scanResult['message'] = $message;
        $this->scanResult['type'] = 'success';
        $this->scanResult['pickup_status'] = $pickup->status?->label() ?? PickupStatusEnum::PICKUP_DA_LAY->label();
        $this->scanResult['can_receive'] = false;
        $this->pushLog($this->scanResult['package_code'], $message, 'success');

        Flux::toast(heading: 'Thành công', text: $message, variant: 'success');
    }

    public function clearLog(): void
    {
        $this->scanLog = [];
        $this->successCount = 0;
        $this->errorCount = 0;
    }

    protected function clearScannedPickup(): void
    {
        $this->scannedPickupId = null;
        $this->scannedOrderId = null;
    }

    protected function setScanResult(string $message, string $type = 'neutral', string $code = ''): void
    {
        $this->scanResult = [
            'message' => $message,
            'type' => $type,
            'package_code' => $code,
            'order_code' => '',
            'pickup_code' => '',
            'pickup_status' => '',
            'customer' => '',
            'address' => '',
            'phone' => '',
            'package_count' => 0,
            'can_receive' => false,
        ];
    }

    protected function markPickupAsReceived(Pickup $pickup): Pickup
    {
        $pickup = $pickup->fresh();

        if ($pickup->status === PickupStatusEnum::PICKUP_DA_LAY) {
            return $pickup;
        }

        foreach ([PickupStatusEnum::DA_XAC_NHAN, PickupStatusEnum::PICKUP_DANG_LAY, PickupStatusEnum::PICKUP_DA_LAY] as $nextStatus) {
            if ($pickup->status->canTransitionTo($nextStatus)) {
                $pickup = TransitionPickupStatusAction::execute($pickup, $nextStatus);
            }
        }

        if ($pickup->status !== PickupStatusEnum::PICKUP_DA_LAY) {
            throw new \RuntimeException('Không thể chuyển pickup sang trạng thái đã lấy hàng.');
        }

        if (blank($pickup->ngay_nhanhang)) {
            $pickup->forceFill(['ngay_nhanhang' => now()])->save();
            $pickup = $pickup->fresh();
        }

        return $pickup;
    }

    protected function pushLog(string $code, string $message, string $type = 'info'): void
    {
        array_unshift($this->scanLog, [
            'code' => $code,
            'message' => $message,
            'type' => $type,
            'time' => now()->format('H:i:s'),
        ]);

        $this->scanLog = array_slice($this->scanLog, 0, 30);
    }
};

?>

<div class="flex min-h-[calc(100vh-3.5rem)] flex-col pb-4" x-data="{
    cameraActive: false,
    html5QrCode: null,
    scannerPromise: null,
    lastCode: '',
    lastAt: 0,
    duplicateWindowMs: 5000,
    candidateCode: '',
    candidateAt: 0,
    candidateCount: 0,
    confirmationWindowMs: 2200,
    statusMsg: 'Nhấn nút để bật camera',
    statusType: 'neutral',
    showManualInput: false,

    async loadScanner() {
        if (typeof window.loadHtml5Qrcode !== 'function') {
            throw new Error('Scanner assets not available.');
        }
        this.scannerPromise ??= window.loadHtml5Qrcode();
        return this.scannerPromise;
    },

    async ensureCameraDevices() {
        const { Html5Qrcode } = await this.loadScanner();
        let devices = await Html5Qrcode.getCameras();
        if (devices.length > 0) return devices;
        if (!navigator.mediaDevices?.getUserMedia) throw new Error('Camera not supported.');
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        stream.getTracks().forEach(t => t.stop());
        return await Html5Qrcode.getCameras();
    },

    cameraErrorMessage(error) {
        const name = error?.name || '';
        if (name === 'NotAllowedError') return 'Bạn đã từ chối quyền camera. Vui lòng cấp quyền và thử lại.';
        if (name === 'NotFoundError') return 'Không tìm thấy camera trên thiết bị.';
        if (name === 'NotReadableError') return 'Camera đang được ứng dụng khác sử dụng.';
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
        if (text !== this.candidateCode || now - this.candidateAt > this.confirmationWindowMs) {
            this.candidateCode = text;
            this.candidateAt = now;
            this.candidateCount = 1;
            this.statusMsg = 'Xác nhận: ' + text;
            this.statusType = 'neutral';
            return;
        }
        this.candidateCount += 1;
        this.candidateAt = now;
        if (this.candidateCount < 2) return;
        if (text === this.lastCode && now - this.lastAt < this.duplicateWindowMs) return;
        this.lastCode = text;
        this.lastAt = now;
        this.candidateCode = '';
        this.candidateAt = 0;
        this.candidateCount = 0;
        this.statusMsg = 'Đã đọc: ' + text;
        this.statusType = 'success';
        $wire.processScan(text).finally(() => {
            setTimeout(() => this.recoverCameraAfterScan(), 250);
        });
    },

    async recoverCameraAfterScan() {
        if (!this.cameraActive) return;
        const reader = this.$refs.cameraReader;
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
            await this.ensureCameraDevices();
            this.$refs.cameraReader.innerHTML = '';
            this.html5QrCode = new Html5Qrcode(this.$refs.cameraReader.id, false);
            await this.html5QrCode.start(
                { facingMode: 'environment' },
                {
                    fps: 12,
                    aspectRatio: 4 / 3,
                    rememberLastUsedCamera: true,
                    formatsToSupport: [Html5QrcodeSupportedFormats.CODE_128],
                    qrbox: (width, height) => ({
                        width: Math.floor(width * 0.8),
                        height: Math.min(Math.floor(height * 0.64), 320),
                    }),
                    experimentalFeatures: {
                        useBarCodeDetectorIfSupported: true,
                    },
                },
                (decodedText) => this.handleDecodedText(decodedText),
                () => {}
            );
            this.cameraActive = true;
            this.statusMsg = 'Đưa barcode vào khung hình';
            this.statusType = 'success';
        } catch (e) {
            this.statusMsg = this.cameraErrorMessage(e);
            this.statusType = 'error';
        }
    },

    async stopCamera() {
        if (this.html5QrCode) {
            try { await this.html5QrCode.stop(); } catch {}
            try { this.html5QrCode.clear(); } catch {}
            this.html5QrCode = null;
        }
        if (this.$refs.cameraReader) {
            this.$refs.cameraReader.innerHTML = '';
        }
        this.cameraActive = false;
        this.candidateCode = '';
        this.candidateAt = 0;
        this.candidateCount = 0;
        this.statusMsg = 'Camera đã tắt';
        this.statusType = 'neutral';
    },

    init() {
        this.startCamera();
        window.addEventListener('beforeunload', () => this.stopCamera());
    }
}">
    <div class="relative isolate flex-shrink-0 overflow-hidden bg-black">
        <div wire:ignore id="shipper-scan-reader" x-ref="cameraReader" class="relative z-0 w-full aspect-[4/3] [&_video]:h-full [&_video]:w-full [&_video]:object-cover"></div>

        <div class="pointer-events-none absolute inset-0 z-10">
            <div class="absolute inset-0 bg-black/40"></div>
            <div class="absolute inset-x-[10%] top-[15%] bottom-[15%] bg-transparent border-2 border-white/80 rounded-xl"
                 style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.4);"></div>
            <div class="absolute left-[10%] top-[15%] w-6 h-6 border-t-4 border-l-4 border-emerald-400 rounded-tl-lg"></div>
            <div class="absolute right-[10%] top-[15%] w-6 h-6 border-t-4 border-r-4 border-emerald-400 rounded-tr-lg"></div>
            <div class="absolute left-[10%] bottom-[15%] w-6 h-6 border-b-4 border-l-4 border-emerald-400 rounded-bl-lg"></div>
            <div class="absolute right-[10%] bottom-[15%] w-6 h-6 border-b-4 border-r-4 border-emerald-400 rounded-br-lg"></div>
        </div>

        <div x-show="cameraActive" x-cloak class="shipper-scan-line pointer-events-none absolute inset-x-[10%] z-20 border-t-2 border-emerald-400 shadow-[0_0_8px_#34d399]"></div>

        <div class="absolute bottom-0 inset-x-0 z-30 px-4 py-2 bg-black/60 backdrop-blur-sm">
            <p class="text-center text-xs font-semibold"
               x-bind:class="statusType === 'success' ? 'text-emerald-300' : (statusType === 'error' ? 'text-red-300' : 'text-white/80')"
               x-text="statusMsg"></p>
        </div>

        <button x-on:click="cameraActive ? stopCamera() : startCamera()"
                class="absolute top-3 right-3 z-30 w-10 h-10 rounded-full bg-black/50 backdrop-blur-sm flex items-center justify-center text-white active:bg-black/70">
            <svg x-show="!cameraActive" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            <svg x-show="cameraActive" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
        </button>
    </div>

    <div class="flex items-center justify-between px-4 py-2 bg-white border-b border-neutral-200">
        <div class="flex items-center gap-4 text-xs font-semibold">
            <span class="flex items-center gap-1 text-emerald-700">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                {{ $successCount }}
            </span>
            <span class="flex items-center gap-1 text-red-600">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                {{ $errorCount }}
            </span>
            <span class="text-neutral-500">Tổng: {{ count($scanLog) }}</span>
        </div>
        <div class="flex items-center gap-2">
            <button x-on:click="showManualInput = !showManualInput"
                    class="p-1.5 rounded-lg text-neutral-500 hover:bg-neutral-100 active:bg-neutral-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
            @if(count($scanLog) > 0)
                <button wire:click="clearLog"
                        class="p-1.5 rounded-lg text-red-500 hover:bg-red-50 active:bg-red-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            @endif
        </div>
    </div>

    <div x-show="showManualInput" x-cloak x-transition.opacity class="px-4 py-3 bg-neutral-50 border-b border-neutral-200">
        <div class="flex gap-2">
            <input type="text"
                   wire:model="barcodeInput"
                   x-on:keydown.enter.prevent="$wire.submitScan()"
                   placeholder="Nhập mã kiện..."
                   class="flex-1 h-11 rounded-xl border border-neutral-200 bg-white px-4 font-mono text-sm font-bold focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            <button wire:click="submitScan" class="h-11 px-4 rounded-xl bg-primary-600 text-white text-sm font-bold active:bg-primary-700">
                Quét
            </button>
        </div>
    </div>

    @if($scanResult['message'])
        <div class="px-4 py-3 border-b border-neutral-200 {{ $scanResult['type'] === 'error' ? 'bg-red-50' : 'bg-emerald-50' }}">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        @if($scanResult['type'] === 'error')
                            <svg class="w-5 h-5 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        @else
                            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        @endif
                        <span class="font-mono text-sm font-bold {{ $scanResult['type'] === 'error' ? 'text-red-800' : 'text-emerald-800' }}">
                            {{ $scanResult['package_code'] ?: $scanResult['pickup_code'] }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs {{ $scanResult['type'] === 'error' ? 'text-red-700' : 'text-emerald-700' }}">
                        {{ $scanResult['message'] }}
                    </p>
                </div>
                @if($scanResult['package_count'])
                    <span class="shrink-0 rounded-full bg-white px-2 py-1 text-xs font-bold text-neutral-700">{{ $scanResult['package_count'] }} kiện</span>
                @endif
            </div>

            @if($scanResult['pickup_code'])
                <div class="mt-3 rounded-xl bg-white/70 p-3 text-xs text-neutral-700">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <p class="text-neutral-500">Pickup</p>
                            <p class="font-mono font-bold text-primary-700">{{ $scanResult['pickup_code'] }}</p>
                        </div>
                        <div>
                            <p class="text-neutral-500">Đơn hàng</p>
                            <p class="font-mono font-bold text-neutral-900">{{ $scanResult['order_code'] }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-neutral-500">Khách hàng</p>
                            <p class="font-bold text-neutral-900">{{ $scanResult['customer'] }}</p>
                        </div>
                        @if($scanResult['address'])
                            <div class="col-span-2">
                                <p class="text-neutral-500">Địa chỉ</p>
                                <p class="font-medium text-neutral-800">{{ $scanResult['address'] }}</p>
                            </div>
                        @endif
                        @if($scanResult['phone'])
                            <div>
                                <p class="text-neutral-500">SĐT</p>
                                <a href="tel:{{ $scanResult['phone'] }}" class="font-bold text-primary-700">{{ $scanResult['phone'] }}</a>
                            </div>
                        @endif
                        <div>
                            <p class="text-neutral-500">Trạng thái</p>
                            <p class="font-bold text-neutral-900">{{ $scanResult['pickup_status'] }}</p>
                        </div>
                    </div>

                    <button type="button"
                            wire:click="receiveScannedPickup"
                            wire:loading.attr="disabled"
                            @disabled(! $scanResult['can_receive'])
                            class="mt-3 flex h-11 w-full items-center justify-center rounded-xl bg-emerald-600 text-sm font-bold text-white active:bg-emerald-700 disabled:bg-emerald-200">
                        Nhận hàng
                    </button>
                </div>
            @endif
        </div>
    @endif

    <div class="flex-1 overflow-y-auto">
        @if(count($scanLog) > 0)
            <div class="divide-y divide-neutral-100">
                @foreach($scanLog as $log)
                    <div class="px-4 py-2.5 flex items-center justify-between gap-3
                        {{ $log['type'] === 'success' ? 'bg-white' : ($log['type'] === 'error' ? 'bg-red-50/50' : 'bg-amber-50/50') }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-bold text-neutral-800 truncate">{{ $log['code'] }}</span>
                            </div>
                            <p class="mt-0.5 text-[11px] text-neutral-500 truncate">{{ $log['message'] }}</p>
                        </div>
                        <span class="shrink-0 text-[10px] text-neutral-400 font-medium">{{ $log['time'] }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-12 text-neutral-400">
                <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
                <p class="text-sm font-medium">Chưa có lần quét nào</p>
                <p class="text-xs mt-1">Đưa barcode kiện hàng vào khung hình</p>
            </div>
        @endif
    </div>
</div>

<style>
    @keyframes shipper-scan-line {
        0%, 100% { top: 18%; }
        50% { top: 80%; }
    }
    .shipper-scan-line {
        animation: shipper-scan-line 1.8s ease-in-out infinite;
    }
</style>
