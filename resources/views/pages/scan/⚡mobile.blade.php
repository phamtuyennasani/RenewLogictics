<?php

use App\Actions\Order\RecordTrackingHistoryAction;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderPackage;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.mobile')] #[Title('Quét kiện hàng')] class extends Component
{
    public string $barcodeInput = '';

    public array $scanResult = [
        'message' => '',
        'updated' => false,
        'package_code' => '',
        'order_code' => '',
        'from_status' => '',
        'current_status' => '',
        'customer' => '',
        'receiver' => '',
    ];

    public array $scanLog = [];
    public int $successCount = 0;
    public int $errorCount = 0;

    public function mount(): void
    {
        abort_unless(\Gate::allows('scan'), 403);
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
        $package = OrderPackage::query()
            ->with(['order.customerAccount:id,fullname,username,code', 'order.sale:id,fullname,username,code'])
            ->where('code', $code)
            ->first();

        if (! $package || ! $package->order) {
            $this->setScanResult($code, false, 'Không tìm thấy đơn hàng từ mã kiện này.');
            $this->errorCount++;
            return;
        }

        $result = DB::transaction(function () use ($package) {
            $order = Order::query()
                ->whereKey($package->order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $fromStatus = $order->bill_status;
            $toStatus = match ($fromStatus) {
                OrderStatusEnum::DA_XAC_NHAN => OrderStatusEnum::DA_NHAN_HANG,
                OrderStatusEnum::DUYET_XUAT_HANG => OrderStatusEnum::DANG_PHAT_HANG,
                default => null,
            };

            if (! $toStatus) {
                return [
                    'updated' => false,
                    'message' => 'Đơn hàng đã được xử lý.',
                    'order' => $order->fresh(['customerAccount:id,fullname,username,code', 'sale:id,fullname,username,code']),
                    'from_status' => $fromStatus,
                    'to_status' => null,
                ];
            }

            $payload = ['bill_status' => $toStatus];
            if ($toStatus === OrderStatusEnum::DA_NHAN_HANG && blank($order->ngaynhanhang)) {
                $payload['ngaynhanhang'] = now();
            }
            if ($toStatus === OrderStatusEnum::DANG_PHAT_HANG && blank($order->ngayxuathang)) {
                $payload['ngayxuathang'] = now();
            }

            $order->forceFill($payload)->save();

            return [
                'updated' => true,
                'message' => 'Đã cập nhật trạng thái.',
                'order' => $order->fresh(['customerAccount:id,fullname,username,code', 'sale:id,fullname,username,code']),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
            ];
        });

        if ($result['updated'] && $result['to_status']) {
            RecordTrackingHistoryAction::execute($result['order'], $result['to_status'], now());
            $this->successCount++;
        } else {
            $this->errorCount++;
        }

        $this->setScanResultFromPayload($package->code, $result);
    }

    protected function setScanResult(string $code, bool $updated, string $message): void
    {
        $this->scanResult = [
            'message' => $message,
            'updated' => $updated,
            'package_code' => $code,
            'order_code' => '',
            'from_status' => '',
            'current_status' => '',
            'customer' => '',
            'receiver' => '',
        ];

        $this->pushLog($code, $message, $updated ? 'success' : 'error');
    }

    protected function setScanResultFromPayload(string $packageCode, array $result): void
    {
        $order = $result['order'];
        $this->scanResult = [
            'message' => $result['message'],
            'updated' => $result['updated'],
            'package_code' => $packageCode,
            'order_code' => $order->id_bill ?? $order->tracking_code ?? '-',
            'from_status' => $result['from_status']?->label() ?? '-',
            'current_status' => $result['updated']
                ? ($result['to_status']?->label() ?? '-')
                : ($order->bill_status?->label() ?? '-'),
            'customer' => $order->customerAccount?->fullname ?: $order->customerAccount?->username ?: '-',
            'receiver' => collect([
                data_get($order->receiver, 'name'),
                data_get($order->receiver, 'phone'),
            ])->filter()->join(' / ') ?: '-',
        ];

        $this->pushLog(
            $packageCode,
            $result['message'],
            $result['updated'] ? 'success' : 'warning'
        );
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

    public function clearLog(): void
    {
        $this->scanLog = [];
        $this->successCount = 0;
        $this->errorCount = 0;
    }
}
?>

<div class="flex flex-col min-h-[calc(100vh-3.5rem)]" x-data="{
    cameraActive: false,
    scannerControls: null,
    zxingPromise: null,
    codeReader: null,
    lastCode: '',
    lastAt: 0,
    duplicateWindowMs: 5000,
    candidateCode: '',
    candidateAt: 0,
    candidateCount: 0,
    confirmationWindowMs: 2500,
    detectedPoints: '',
    detectedViewBox: '0 0 1 1',
    detectedLineTimer: null,
    statusMsg: 'Nhấn nút để bật camera',
    statusType: 'neutral',
    showManualInput: false,

    async loadScanner() {
        if (typeof window.loadZXingBrowser !== 'function') {
            throw new Error('Scanner assets not available.');
        }
        this.zxingPromise ??= window.loadZXingBrowser();
        return this.zxingPromise;
    },

    async ensureCameraDevices() {
        const { BrowserCodeReader } = await this.loadScanner();
        let devices = await BrowserCodeReader.listVideoInputDevices();
        if (devices.length > 0) return devices;
        if (!navigator.mediaDevices?.getUserMedia) throw new Error('Camera not supported.');
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        stream.getTracks().forEach(t => t.stop());
        return await BrowserCodeReader.listVideoInputDevices();
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

    showDetectedLine(result) {
        const video = this.$refs.cameraVideo;
        const points = result?.getResultPoints?.() || [];
        if (!video || points.length < 2 || !video.videoWidth) return;
        this.detectedViewBox = `0 0 ${video.videoWidth} ${video.videoHeight}`;
        this.detectedPoints = points.map(p => `${p.getX?.() ?? p.x},${p.getY?.() ?? p.y}`).join(' ');
        clearTimeout(this.detectedLineTimer);
        this.detectedLineTimer = setTimeout(() => { this.detectedPoints = ''; }, 700);
    },

    async enableContinuousFocus() {
        try {
            const caps = this.scannerControls?.streamVideoCapabilitiesGet;
            const apply = this.scannerControls?.streamVideoConstraintsApply;
            if (!caps || !apply) return;
            const c = caps(t => t.kind === 'video');
            if ((c?.focusMode || []).includes('continuous')) {
                await apply({ advanced: [{ focusMode: 'continuous' }] });
            }
        } catch {}
    },

    async startCamera() {
        this.stopCamera();
        this.statusMsg = 'Đang bật camera...';
        this.statusType = 'neutral';
        try {
            const { BarcodeFormat, BrowserMultiFormatOneDReader, DecodeHintType } = await this.loadScanner();
            await this.ensureCameraDevices();
            const hints = new Map([
                [DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.CODE_128]],
                [DecodeHintType.TRY_HARDER, true],
            ]);
            const videoConstraints = {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                facingMode: { ideal: 'environment' },
            };
            this.codeReader = new BrowserMultiFormatOneDReader(hints, {
                delayBetweenScanAttempts: 120,
                delayBetweenScanSuccess: 150,
            });
            this.scannerControls = await this.codeReader.decodeFromConstraints(
                { video: videoConstraints },
                this.$refs.cameraVideo,
                (result) => {
                    this.showDetectedLine(result);
                    const text = result?.getText?.()?.trim().toUpperCase();
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
                    this.statusMsg = '✓ ' + text;
                    this.statusType = 'success';
                    $wire.set('barcodeInput', text).then(() => $wire.submitScan());
                }
            );
            await this.enableContinuousFocus();
            this.cameraActive = true;
            this.statusMsg = 'Đưa barcode vào khung hình';
            this.statusType = 'success';
        } catch (e) {
            this.statusMsg = this.cameraErrorMessage(e);
            this.statusType = 'error';
        }
    },

    stopCamera() {
        if (this.scannerControls) { this.scannerControls.stop(); this.scannerControls = null; }
        const video = this.$refs.cameraVideo;
        if (video?.srcObject) {
            video.srcObject.getTracks().forEach(t => t.stop());
            video.srcObject = null;
        }
        this.cameraActive = false;
        this.candidateCode = '';
        this.candidateAt = 0;
        this.candidateCount = 0;
        clearTimeout(this.detectedLineTimer);
        this.detectedPoints = '';
        this.statusMsg = 'Camera đã tắt';
        this.statusType = 'neutral';
    },

    init() {
        this.startCamera();
        window.addEventListener('beforeunload', () => this.stopCamera());
    }
}">
    {{-- Camera Scanner Area --}}
    <div class="relative bg-black flex-shrink-0">
        <video x-ref="cameraVideo" class="w-full aspect-[4/3] object-cover" muted playsinline></video>

        {{-- Scan frame overlay --}}
        <div class="absolute inset-0 pointer-events-none">
            {{-- Dark overlay around scan area --}}
            <div class="absolute inset-0 bg-black/40"></div>
            {{-- Clear scan zone --}}
            <div class="absolute inset-x-[10%] top-[15%] bottom-[15%] bg-transparent border-2 border-white/80 rounded-xl"
                 style="box-shadow: 0 0 0 9999px rgba(0,0,0,0.4);"></div>
            {{-- Corner markers --}}
            <div class="absolute left-[10%] top-[15%] w-6 h-6 border-t-4 border-l-4 border-emerald-400 rounded-tl-lg"></div>
            <div class="absolute right-[10%] top-[15%] w-6 h-6 border-t-4 border-r-4 border-emerald-400 rounded-tr-lg"></div>
            <div class="absolute left-[10%] bottom-[15%] w-6 h-6 border-b-4 border-l-4 border-emerald-400 rounded-bl-lg"></div>
            <div class="absolute right-[10%] bottom-[15%] w-6 h-6 border-b-4 border-r-4 border-emerald-400 rounded-br-lg"></div>
        </div>

        {{-- Scan line animation --}}
        <div x-show="cameraActive" x-cloak class="scan-line-mobile pointer-events-none absolute inset-x-[10%] border-t-2 border-emerald-400 shadow-[0_0_8px_#34d399]"></div>

        {{-- Detected barcode line --}}
        <svg x-show="detectedPoints" x-cloak x-bind:viewBox="detectedViewBox"
             class="pointer-events-none absolute inset-0 h-full w-full" preserveAspectRatio="xMidYMid slice">
            <polyline x-bind:points="detectedPoints" fill="none" stroke="#22c55e" stroke-width="5"
                      stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
        </svg>

        {{-- Status bar at bottom of camera --}}
        <div class="absolute bottom-0 inset-x-0 px-4 py-2 bg-black/60 backdrop-blur-sm">
            <p class="text-center text-xs font-semibold"
               x-bind:class="statusType === 'success' ? 'text-emerald-300' : (statusType === 'error' ? 'text-red-300' : 'text-white/80')"
               x-text="statusMsg"></p>
        </div>

        {{-- Camera toggle button --}}
        <button x-on:click="cameraActive ? stopCamera() : startCamera()"
                class="absolute top-3 right-3 w-10 h-10 rounded-full bg-black/50 backdrop-blur-sm flex items-center justify-center text-white active:bg-black/70">
            <svg x-show="!cameraActive" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            <svg x-show="cameraActive" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
        </button>
    </div>

    {{-- Stats bar --}}
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

    {{-- Manual input (collapsible) --}}
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

    {{-- Last scan result --}}
    @if($scanResult['package_code'])
        <div class="px-4 py-3 border-b border-neutral-200 {{ $scanResult['updated'] ? 'bg-emerald-50' : 'bg-red-50' }}">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        @if($scanResult['updated'])
                            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        @else
                            <svg class="w-5 h-5 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        @endif
                        <span class="font-mono text-sm font-bold {{ $scanResult['updated'] ? 'text-emerald-800' : 'text-red-800' }}">
                            {{ $scanResult['package_code'] }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs {{ $scanResult['updated'] ? 'text-emerald-700' : 'text-red-700' }}">
                        {{ $scanResult['message'] }}
                    </p>
                </div>
                @if($scanResult['order_code'])
                    <span class="shrink-0 text-xs font-semibold text-neutral-600">{{ $scanResult['order_code'] }}</span>
                @endif
            </div>
            @if($scanResult['updated'] && $scanResult['from_status'])
                <div class="mt-2 flex items-center gap-2 text-xs">
                    <span class="px-2 py-0.5 rounded bg-neutral-200 text-neutral-700 font-medium">{{ $scanResult['from_status'] }}</span>
                    <svg class="w-3.5 h-3.5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    <span class="px-2 py-0.5 rounded bg-emerald-200 text-emerald-800 font-medium">{{ $scanResult['current_status'] }}</span>
                </div>
            @endif
            @if($scanResult['customer'] && $scanResult['customer'] !== '-')
                <p class="mt-1.5 text-[11px] text-neutral-600">KH: {{ $scanResult['customer'] }} @if($scanResult['receiver'] && $scanResult['receiver'] !== '-')· Nhận: {{ $scanResult['receiver'] }}@endif</p>
            @endif
        </div>
    @endif

    {{-- Scan log --}}
    <div class="flex-1 overflow-y-auto">
        @if(count($scanLog) > 0)
            <div class="divide-y divide-neutral-100">
                @foreach($scanLog as $index => $log)
                    @if($index > 0)
                        <div class="px-4 py-2.5 flex items-center justify-between gap-3
                            {{ $log['type'] === 'success' ? 'bg-white' : ($log['type'] === 'error' ? 'bg-red-50/50' : 'bg-amber-50/50') }}">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    @if($log['type'] === 'success')
                                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    @elseif($log['type'] === 'error')
                                        <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                    @else
                                        <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    @endif
                                    <span class="font-mono text-xs font-bold text-neutral-800 truncate">{{ $log['code'] }}</span>
                                </div>
                                <p class="mt-0.5 text-[11px] text-neutral-500 truncate">{{ $log['message'] }}</p>
                            </div>
                            <span class="shrink-0 text-[10px] text-neutral-400 font-medium">{{ $log['time'] }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-12 text-neutral-400">
                <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
                <p class="text-sm font-medium">Chưa có lần quét nào</p>
                <p class="text-xs mt-1">Đưa barcode vào khung hình camera</p>
            </div>
        @endif
    </div>
</div>

<style>
    @keyframes scan-line-mobile {
        0%, 100% { top: 18%; }
        50% { top: 80%; }
    }
    .scan-line-mobile {
        animation: scan-line-mobile 1.8s ease-in-out infinite;
    }
</style>
