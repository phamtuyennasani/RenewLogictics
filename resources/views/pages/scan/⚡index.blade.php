<?php

use App\Actions\Order\RecordTrackingHistoryAction;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderPackage;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Quét kiện hàng')] class extends Component
{
    public string $barcodeInput = '';

    public array $scanResult = [
        'message' => 'Chưa có dữ liệu quét',
        'updated' => false,
        'package_code' => '-',
        'order_code' => '-',
        'from_status' => '-',
        'current_status' => '-',
        'customer' => '-',
        'sale' => '-',
        'receiver' => '-',
    ];

    public array $scanLog = [];

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
                'message' => 'Đã cập nhật trạng thái đơn hàng.',
                'order' => $order->fresh(['customerAccount:id,fullname,username,code', 'sale:id,fullname,username,code']),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
            ];
        });

        if ($result['updated'] && $result['to_status']) {
            RecordTrackingHistoryAction::execute($result['order'], $result['to_status'], now());
        }

        $this->setScanResultFromPayload($package->code, $result);
    }

    protected function setScanResult(string $code, bool $updated, string $message): void
    {
        $this->scanResult = [
            'message' => $message,
            'updated' => $updated,
            'package_code' => $code,
            'order_code' => '-',
            'from_status' => '-',
            'current_status' => '-',
            'customer' => '-',
            'sale' => '-',
            'receiver' => '-',
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
            'sale' => $order->sale?->fullname ?: $order->sale?->username ?: '-',
            'receiver' => collect([
                data_get($order->receiver, 'name'),
                data_get($order->receiver, 'phone'),
            ])->filter()->join(' / ') ?: '-',
        ];

        $this->pushLog(
            $packageCode,
            $result['message'],
            $result['updated'] ? 'success' : ($result['to_status'] ? 'info' : 'warning')
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

        $this->scanLog = array_slice($this->scanLog, 0, 50);
    }

    public function clearLog(): void
    {
        $this->scanLog = [];
    }

    public function focusInput(): void
    {
        // Called from Alpine to refocus barcode input
    }
}
?>

<div class="space-y-5" x-data="{
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
    busy: false,

    get csrf() {
        return document.querySelector('meta[name=csrf-token]')?.content || '';
    },

    async loadScanner() {
        if (typeof window.loadZXingBrowser !== 'function') {
            throw new Error('Scanner assets are not available.');
        }
        this.zxingPromise ??= window.loadZXingBrowser();
        return this.zxingPromise;
    },

    async loadCameras() {
        try {
            const { BrowserCodeReader } = await this.loadScanner();
            const devices = await BrowserCodeReader.listVideoInputDevices();
            const select = document.querySelector('[data-camera-select]');
            if (!select) return;
            select.replaceChildren(new Option('Camera mặc định', ''));
            devices.forEach((d, i) => select.appendChild(new Option(d.label || `Camera ${i+1}`, d.deviceId)));
        } catch (e) {
            this.setCameraStatus('Không thể đọc danh sách camera. Kiểm tra quyền camera hoặc HTTPS.', 'error');
        }
    },

    async ensureCameraDevices() {
        const { BrowserCodeReader } = await this.loadScanner();
        let devices = await BrowserCodeReader.listVideoInputDevices();
        if (devices.length > 0) {
            return devices;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Camera API is not supported in this browser.');
        }

        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        stream.getTracks().forEach(track => track.stop());

        devices = await BrowserCodeReader.listVideoInputDevices();
        return devices;
    },

    async loadCamerasWithPermission() {
        try {
            const devices = await this.ensureCameraDevices();
            const select = document.querySelector('[data-camera-select]');
            if (!select) return devices;
            select.replaceChildren(new Option('Camera mặc định', ''));
            devices.forEach((d, i) => select.appendChild(new Option(d.label || `Camera ${i+1}`, d.deviceId)));
            return devices;
        } catch (e) {
            this.setCameraStatus(this.cameraErrorMessage(e, 'Không thể đọc danh sách camera. Kiểm tra quyền camera hoặc HTTPS.'), 'error');
            throw e;
        }
    },

    cameraErrorMessage(error, fallback) {
        const message = error?.message || '';
        const name = error?.name || '';

        if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
            return 'Bạn đã từ chối quyền camera. Hãy cấp quyền camera cho trình duyệt và thử lại.';
        }

        if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
            return 'Không tìm thấy camera trên thiết bị này.';
        }

        if (name === 'NotReadableError' || name === 'TrackStartError') {
            return 'Camera đang được ứng dụng khác sử dụng. Hãy đóng ứng dụng đó rồi thử lại.';
        }

        if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
            return 'Trình duyệt chỉ cho phép mở camera trên HTTPS hoặc localhost.';
        }

        if (message) {
            return `${fallback} (${message})`;
        }

        return fallback;
    },

    showDetectedLine(result) {
        const video = document.querySelector('[data-camera-video]');
        const points = result?.getResultPoints?.() || [];

        if (!video || points.length < 2 || !video.videoWidth || !video.videoHeight) {
            return;
        }

        this.detectedViewBox = `0 0 ${video.videoWidth} ${video.videoHeight}`;
        this.detectedPoints = points
            .map(point => `${point.getX?.() ?? point.x},${point.getY?.() ?? point.y}`)
            .join(' ');

        clearTimeout(this.detectedLineTimer);
        this.detectedLineTimer = setTimeout(() => {
            this.detectedPoints = '';
        }, 700);
    },

    async enableContinuousFocus() {
        const getCapabilities = this.scannerControls?.streamVideoCapabilitiesGet;
        const applyConstraints = this.scannerControls?.streamVideoConstraintsApply;

        if (!getCapabilities || !applyConstraints) {
            return;
        }

        try {
            const capabilities = getCapabilities(track => track.kind === 'video');
            if ((capabilities?.focusMode || []).includes('continuous')) {
                await applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
            }
        } catch {
            // Some mobile browsers expose camera controls but reject focus constraints.
        }
    },

    async startCamera() {
        const video = document.querySelector('[data-camera-video]');
        const select = document.querySelector('[data-camera-select]');
        if (!video) return;
        this.stopCamera();
        this.setCameraStatus('Đang bật camera...', 'neutral');
        try {
            const { BarcodeFormat, BrowserMultiFormatOneDReader, DecodeHintType } = await this.loadScanner();
            const devices = await this.loadCamerasWithPermission();
            const preferredDeviceId = select?.value || devices[0]?.deviceId;
            const hints = new Map([
                [DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.CODE_128]],
                [DecodeHintType.TRY_HARDER, true],
            ]);
            const videoConstraints = {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                ...(preferredDeviceId
                    ? { deviceId: { exact: preferredDeviceId } }
                    : { facingMode: { ideal: 'environment' } }),
            };
            this.codeReader = new BrowserMultiFormatOneDReader(hints, {
                delayBetweenScanAttempts: 120,
                delayBetweenScanSuccess: 150,
            });
            this.scannerControls = await this.codeReader.decodeFromConstraints(
                { video: videoConstraints },
                video,
                (result) => {
                    this.showDetectedLine(result);
                    const text = result?.getText?.()?.trim().toUpperCase();
                    if (!text) return;

                    if (!/^[A-Z0-9-]+-\d{2,}$/.test(text)) {
                        this.setCameraStatus('Barcode không đúng định dạng mã kiện: ' + text, 'neutral');
                        return;
                    }

                    const now = Date.now();

                    if (text !== this.candidateCode || now - this.candidateAt > this.confirmationWindowMs) {
                        this.candidateCode = text;
                        this.candidateAt = now;
                        this.candidateCount = 1;
                        this.setCameraStatus('Đang xác nhận barcode: ' + text, 'neutral');
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
                    this.setCameraStatus('Đã đọc: ' + text, 'success');
                    $wire.set('barcodeInput', text).then(() => $wire.submitScan());
                }
            );
            await this.enableContinuousFocus();
            this.cameraActive = true;
            this.setCameraStatus('Camera đang quét. Đưa barcode vào khung hình.', 'success');
        } catch (e) {
            this.setCameraStatus(this.cameraErrorMessage(e, 'Không thể bật camera.'), 'error');
        }
    },

    stopCamera() {
        if (this.scannerControls) { this.scannerControls.stop(); this.scannerControls = null; }
        const video = document.querySelector('[data-camera-video]');
        if (video?.srcObject) {
            video.srcObject.getTracks().forEach(t => t.stop());
            video.srcObject = null;
        }
        this.cameraActive = false;
        this.candidateCode = '';
        this.candidateAt = 0;
        this.candidateCount = 0;
        clearTimeout(this.detectedLineTimer);
        this.detectedLineTimer = null;
        this.detectedPoints = '';
        this.setCameraStatus('Camera chưa bật.', 'neutral');
    },

    setCameraStatus(msg, type) {
        const el = document.querySelector('[data-camera-status]');
        if (!el) return;
        el.textContent = msg;
        el.className = 'mt-3 text-sm font-semibold '
            + (type === 'success' ? 'text-emerald-700'
            : (type === 'error' ? 'text-red-700' : 'text-neutral-600'));
    },

    init() {
        const input = document.querySelector('[data-barcode-input]');
        if (input) {
            input.focus();
            input.select();
        }
        this.loadCameras();
        window.addEventListener('beforeunload', () => this.stopCamera());
    }
}">
    {{-- Header --}}
    <section class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase text-neutral-500">Quét label kiện hàng</p>
                <h1 class="mt-1 text-2xl font-black tracking-normal text-neutral-950">Cập nhật trạng thái đơn bằng barcode</h1>
                <p class="mt-2 max-w-3xl text-sm text-neutral-600">
                    Quét barcode từ mã kiện. Đơn ở trạng thái Đã xác nhận sẽ chuyển sang Đã nhận hàng; đơn ở Duyệt xuất hàng sẽ chuyển sang Đang phát hàng.
                </p>
            </div>
            <button type="button" x-on:click="document.querySelector('[data-barcode-input]')?.focus()" class="inline-flex h-10 items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 text-sm font-bold text-neutral-700 transition hover:bg-neutral-50">
                Focus ô quét
            </button>
        </div>

        <div class="mt-5 grid gap-4 xl:grid-cols-[0.95fr_1.05fr]">
            {{-- Barcode input (Livewire) --}}
            <div>
                <label class="block" x-data="{ autofocus() { this.$nextTick(() => this.$refs.barcodeInput?.focus()) } }" x-init="autofocus()">
                    <span class="text-sm font-bold text-neutral-800">Barcode kiện hàng</span>
                    <input
                        type="text"
                        autocomplete="off"
                        x-ref="barcodeInput"
                        data-barcode-input
                        wire:model="barcodeInput"
                        x-on:keydown.enter.prevent="$wire.submitScan()"
                        class="mt-2 h-14 w-full rounded-lg border border-neutral-200 bg-neutral-50 px-4 font-mono text-xl font-bold text-neutral-950 outline-none transition focus:border-primary-500 focus:bg-white focus:ring-4 focus:ring-primary-100"
                        placeholder="Quét hoặc nhập mã kiện rồi Enter"
                    >
                </label>
                <p class="mt-2 text-xs font-medium text-neutral-500">Dùng máy quét barcode dạng keyboard hoặc nhập thủ công.</p>
            </div>

            {{-- Camera section (Alpine) --}}
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <label class="block min-w-0 flex-1">
                        <span class="text-sm font-bold text-neutral-800">Camera</span>
                        <select data-camera-select class="mt-2 h-10 w-full rounded-lg border border-neutral-200 bg-white px-3 text-sm font-semibold text-neutral-700">
                            <option value="">Camera mặc định</option>
                        </select>
                    </label>
                    <div class="flex gap-2">
                        <button type="button" x-show="!cameraActive" x-on:click="startCamera()" class="inline-flex h-10 items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-bold text-white transition hover:bg-primary-700 disabled:opacity-60">
                            Bật camera
                        </button>
                        <button type="button" x-show="cameraActive" x-cloak x-on:click="stopCamera()" class="inline-flex h-10 items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 text-sm font-bold text-neutral-700 transition hover:bg-neutral-50">
                            Tắt
                        </button>
                    </div>
                </div>
                <div class="relative mt-4 overflow-hidden rounded-lg border border-neutral-200 bg-neutral-950">
                    <video data-camera-video class="aspect-video w-full object-cover" muted playsinline></video>
                    <div class="pointer-events-none absolute inset-[12%] rounded-lg border-2 border-white/70"></div>
                    <div x-show="cameraActive" x-cloak class="camera-scan-line pointer-events-none absolute inset-x-[12%] border-t-2 border-red-500 shadow-[0_0_8px_red]"></div>
                    <svg
                        x-show="detectedPoints"
                        x-cloak
                        x-bind:viewBox="detectedViewBox"
                        class="pointer-events-none absolute inset-0 h-full w-full"
                        preserveAspectRatio="xMidYMid slice"
                    >
                        <polyline
                            x-bind:points="detectedPoints"
                            fill="none"
                            stroke="#22c55e"
                            stroke-width="5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            vector-effect="non-scaling-stroke"
                        />
                    </svg>
                </div>
                <p data-camera-status class="mt-3 text-sm font-semibold text-neutral-600">Camera chưa bật.</p>
            </div>
        </div>
    </section>

    {{-- Results + Log --}}
    <section class="grid gap-5 xl:grid-cols-[1fr_0.9fr]">
        {{-- Latest scan result --}}
        <div class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase text-neutral-500">Kết quả quét gần nhất</p>
                    <h2 class="mt-1 text-xl font-black text-neutral-950">{{ $scanResult['message'] }}</h2>
                </div>
                <span class="rounded-md px-2.5 py-1 text-xs font-bold
                    @if($scanResult['updated']) bg-emerald-100 text-emerald-700
                    @elseif($scanResult['package_code'] !== '-' && !$scanResult['updated'] && $scanResult['message'] !== 'Chưa có dữ liệu quét') bg-red-100 text-red-700
                    @else bg-amber-100 text-amber-700
                    @endif
                ">
                    @if($scanResult['updated']) Đã cập nhật
                    @elseif($scanResult['package_code'] !== '-' && !$scanResult['updated'] && $scanResult['message'] !== 'Chưa có dữ liệu quét') Lỗi
                    @else Thông tin
                    @endif
                </span>
            </div>

            <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-lg bg-neutral-50 px-4 py-3">
                    <dt class="text-xs font-bold uppercase text-neutral-500">Mã kiện</dt>
                    <dd class="mt-1 break-all font-mono font-black text-neutral-950">{{ $scanResult['package_code'] }}</dd>
                </div>
                <div class="rounded-lg bg-neutral-50 px-4 py-3">
                    <dt class="text-xs font-bold uppercase text-neutral-500">Mã đơn</dt>
                    <dd class="mt-1 break-all font-mono font-black text-neutral-950">{{ $scanResult['order_code'] }}</dd>
                </div>
                <div class="rounded-lg bg-neutral-50 px-4 py-3">
                    <dt class="text-xs font-bold uppercase text-neutral-500">Trạng thái trước</dt>
                    <dd class="mt-1 font-bold text-neutral-950">{{ $scanResult['from_status'] }}</dd>
                </div>
                <div class="rounded-lg bg-neutral-50 px-4 py-3">
                    <dt class="text-xs font-bold uppercase text-neutral-500">Trạng thái hiện tại</dt>
                    <dd class="mt-1 font-bold text-neutral-950">{{ $scanResult['current_status'] }}</dd>
                </div>
                <div class="rounded-lg bg-neutral-50 px-4 py-3">
                    <dt class="text-xs font-bold uppercase text-neutral-500">Khách hàng</dt>
                    <dd class="mt-1 font-bold text-neutral-950">{{ $scanResult['customer'] }}</dd>
                </div>
                <div class="rounded-lg bg-neutral-50 px-4 py-3">
                    <dt class="text-xs font-bold uppercase text-neutral-500">Sale phụ trách</dt>
                    <dd class="mt-1 font-bold text-neutral-950">{{ $scanResult['sale'] }}</dd>
                </div>
                <div class="rounded-lg bg-neutral-50 px-4 py-3 sm:col-span-2">
                    <dt class="text-xs font-bold uppercase text-neutral-500">Người nhận</dt>
                    <dd class="mt-1 font-bold text-neutral-950">{{ $scanResult['receiver'] }}</dd>
                </div>
            </dl>
        </div>

        {{-- Scan log --}}
        <div class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold uppercase text-neutral-500">Lịch sử phiên quét</p>
                @if(count($scanLog) > 0)
                    <button type="button" wire:click="clearLog" class="text-xs font-semibold text-red-500 hover:text-red-600">Xóa lịch sử</button>
                @endif
            </div>
            <div class="mt-4 max-h-[28rem] space-y-3 overflow-y-auto">
                @forelse($scanLog as $log)
                    <div class="rounded-lg border px-4 py-3 text-sm
                        @if($log['type'] === 'success') border-emerald-200 bg-emerald-50 text-emerald-900
                        @elseif($log['type'] === 'error') border-red-200 bg-red-50 text-red-900
                        @else border-neutral-200 bg-neutral-50 text-neutral-800
                        @endif
                    ">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-black">{{ $log['code'] }}</div>
                                <div class="mt-1">{{ $log['message'] }}</div>
                            </div>
                            <div class="shrink-0 text-xs font-bold text-neutral-500">{{ $log['time'] }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-neutral-500">Các lần quét sẽ hiển thị tại đây.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>

<style>
    @keyframes camera-scan-line {
        0%, 100% { top: 16%; }
        50% { top: 84%; }
    }

    .camera-scan-line {
        animation: camera-scan-line 1.8s ease-in-out infinite;
    }
</style>
