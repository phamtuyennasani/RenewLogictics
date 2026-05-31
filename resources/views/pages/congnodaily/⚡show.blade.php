<?php

use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Models\CongNoDaiLy;
use App\Models\CongNoDaiLyDetail;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Flux\Flux;

new #[Layout('layouts.app')] #[Title('Chi tiết công nợ đại lý')] class extends Component {
    use WithFileUploads;

    public CongNoDaiLy $debt;
    public string $referenceCode = '';
    public $paymentPhoto = null;

    public function mount(string $id): void
    {
        $this->debt = $this->loadDebt($id);

        abort_unless($this->canView(), 403);

        $this->referenceCode = (string) ($this->debt->sohoadon_thamchieu ?? '');
    }

    protected function loadDebt(string $id): CongNoDaiLy
    {
        return CongNoDaiLy::query()
            ->with([
                'daily:id,namevi,nameen',
                'creator:id,fullname,username,code',
                'ketoan:id,fullname,username,code',
                'success:id,fullname,username,code',
                'details.order.dichvu:id,namevi',
                'details.order.chiNhanhNhanHang:id,namevi',
                'details.order.packages',
            ])
            ->where(function ($query) use ($id) {
                $query->where('uuid', $id);

                if (ctype_digit($id)) {
                    $query->orWhere('id', (int) $id);
                }
            })
            ->firstOrFail();
    }

    /**
     * Lưu mã tham chiếu + chứng từ thanh toán mà không đổi trạng thái.
     * Chỉ áp dụng khi công nợ đã chốt cước và chưa thanh toán.
     */
    public function updatePaymentInfo(): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        if (! $this->canEditPaymentInfo()) {
            Flux::toast(heading: 'Không thể cập nhật', text: 'Chỉ công nợ đã chốt cước và chưa thanh toán mới có thể cập nhật.', variant: 'warning');
            return;
        }

        $data = $this->validate([
            'referenceCode' => ['nullable', 'string', 'max:255'],
            'paymentPhoto' => ['nullable', 'image', 'max:5120'],
        ], [], [
            'referenceCode' => 'Mã tham chiếu',
            'paymentPhoto' => 'Chứng từ thanh toán',
        ]);

        $oldReference = (string) ($this->debt->sohoadon_thamchieu ?? '');
        $oldPhoto = (string) ($this->debt->photo ?? '');

        $photoPath = $this->storePaymentPhoto();

        $this->debt->forceFill(array_filter([
            'sohoadon_thamchieu' => $data['referenceCode'] ?: null,
            'photo' => $photoPath,
        ], fn ($value, $key) => $key === 'sohoadon_thamchieu' || $value !== null, ARRAY_FILTER_USE_BOTH))->save();

        $this->debt->writeActivityLog(
            action: 'payment_info_updated',
            title: 'Cập nhật thông tin thanh toán',
            metadata: array_filter([
                'reference_from' => $oldReference !== '' ? $oldReference : null,
                'reference_to' => $data['referenceCode'] ?: null,
                'photo_from' => $oldPhoto !== '' ? $oldPhoto : null,
                'photo_to' => $photoPath,
            ], fn ($v) => $v !== null),
        );

        $this->paymentPhoto = null;
        $this->reloadDebt();
        $this->referenceCode = (string) ($this->debt->sohoadon_thamchieu ?? '');

        Flux::toast(heading: 'Đã cập nhật', text: 'Thông tin thanh toán đã được lưu.', variant: 'success');
    }

    /**
     * Lưu thông tin thanh toán và chuyển công nợ sang trạng thái Đã thanh toán.
     */
    public function confirmPaid(): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        if (! $this->canEditPaymentInfo()) {
            Flux::toast(heading: 'Không thể xác nhận', text: 'Chỉ công nợ đã chốt cước và chưa thanh toán mới có thể xác nhận thanh toán.', variant: 'warning');
            return;
        }

        $data = $this->validate([
            'referenceCode' => ['nullable', 'string', 'max:255'],
            'paymentPhoto' => ['nullable', 'image', 'max:5120'],
        ], [], [
            'referenceCode' => 'Mã tham chiếu',
            'paymentPhoto' => 'Chứng từ thanh toán',
        ]);

        $photoPath = $this->storePaymentPhoto();

        DB::transaction(function () use ($data, $photoPath) {
            $fromStatus = $this->debt->status;

            $this->debt->forceFill([
                'sohoadon_thamchieu' => $data['referenceCode'] ?: $this->debt->sohoadon_thamchieu,
                'photo' => $photoPath ?? $this->debt->photo,
                'status' => DebtStatusEnum::DA_THANH_TOAN->value,
                'paid_amount' => $this->debt->total_cuocvon,
                'ngaythanhtoan' => now(),
                'id_ketoan' => auth()->user()->hasRole('ketoan') ? auth()->id() : $this->debt->id_ketoan,
            ])->save();

            $this->debt->writeActivityLog(
                action: 'paid_confirmed',
                title: 'Xác nhận đã thanh toán',
                fromStatus: $fromStatus,
                toStatus: DebtStatusEnum::DA_THANH_TOAN,
                metadata: array_filter([
                    'amount' => (float) $this->debt->total_cuocvon,
                    'reference' => $data['referenceCode'] ?: $this->debt->sohoadon_thamchieu,
                    'photo' => $photoPath ?? $this->debt->photo,
                ], fn ($v) => $v !== null && $v !== ''),
            );

            $this->debt->orders()->update([
                'agency_payment_status' => DebtStatusEnum::DA_THANH_TOAN->value,
                'agency_paid_at' => now(),
            ]);
        });

        $this->paymentPhoto = null;
        $this->reloadDebt();
        $this->referenceCode = (string) ($this->debt->sohoadon_thamchieu ?? '');

        Flux::toast(heading: 'Đã thanh toán', text: 'Công nợ đại lý đã được xác nhận thanh toán.', variant: 'success');
        Flux::modal('confirm-paid')->close();
    }

    protected function storePaymentPhoto(): ?string
    {
        if (! $this->paymentPhoto) {
            return null;
        }

        return $this->paymentPhoto->store('congno-daily/chung-tu', 'public');
    }

    public function canEditPaymentInfo(): bool
    {
        return $this->canManage()
            && in_array($this->debt->status, [
                DebtStatusEnum::DA_CHOT_CUOC,
                DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN,
                DebtStatusEnum::QUA_HAN,
            ], true);
    }

    public function removeOrder(int $detailId): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        if ($this->debt->canCreatePaymentInvoice()) {
            Flux::toast(heading: 'Không thể xóa', text: 'Công nợ đã chốt cước, không thể gỡ order.', variant: 'warning');
            return;
        }

        $detail = $this->debt->details()->whereKey($detailId)->firstOrFail();
        $orderCode = $detail->order_code ?: $detail->order?->id_bill ?: data_get($detail->snapshot, 'order_code');
        $orderId = $detail->id_order;

        $detail->delete();
        $this->debt->syncTotalsFromDetails();
        $this->debt->writeActivityLog(
            action: 'order_removed',
            title: 'Gỡ order khỏi công nợ',
            metadata: array_filter([
                'detail_id' => $detailId,
                'order_id' => $orderId,
                'order_code' => $orderCode,
            ], fn ($v) => $v !== null && $v !== ''),
        );
        $this->reloadDebt();

        Flux::toast(heading: 'Đã xóa order', text: 'Order đã được gỡ khỏi công nợ đại lý.', variant: 'success');
    }

    public function confirmDebt(): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        if ($this->debt->status !== DebtStatusEnum::MOI_TAO) {
            Flux::toast(heading: 'Không hợp lệ', text: 'Chỉ công nợ mới tạo mới có thể chốt cước.', variant: 'warning');
            return;
        }

        DB::transaction(function () {
            $fromStatus = $this->debt->status;

            $this->debt->forceFill([
                'status' => DebtStatusEnum::DA_CHOT_CUOC,
                'id_success' => auth()->id(),
                'id_ketoan' => auth()->user()->hasRole('ketoan') ? auth()->id() : $this->debt->id_ketoan,
                'ngaychothoadon' => now(),
                'hanthanhtoan' => now()->addDays((int) ($this->debt->songaythanhtoan ?? 0))->startOfDay(),
            ])->save();

            $this->debt->writeActivityLog(
                action: 'confirmed',
                title: 'Chốt cước công nợ đại lý',
                fromStatus: $fromStatus,
                toStatus: DebtStatusEnum::DA_CHOT_CUOC,
                metadata: array_filter([
                    'total_orders' => (int) $this->debt->total_orders,
                    'total_amount' => (float) $this->debt->total_cuocvon,
                ], fn ($v) => $v !== null),
            );

            $this->debt->orders()->update(['agency_payment_status' => DebtStatusEnum::DA_CHOT_CUOC->value]);
        });

        $this->reloadDebt();
        Flux::toast(heading: 'Đã chốt cước', text: 'Công nợ đại lý đã chuyển sang trạng thái đã chốt cước.', variant: 'success');
    }

    /**
     * Quét lại các đơn hàng thỏa điều kiện (cùng đại lý, cùng khoảng ngày, chưa
     * thuộc công nợ đang mở) và bổ sung vào công nợ này. Chỉ áp dụng khi công
     * nợ chưa chốt cước.
     */
    public function refreshOrders(): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        if ($this->debt->status !== DebtStatusEnum::MOI_TAO) {
            Flux::toast(heading: 'Không thể làm mới', text: 'Chỉ công nợ chưa chốt cước mới có thể bổ sung order.', variant: 'warning');
            return;
        }

        $from = $this->debt->tungay ? Carbon::parse($this->debt->tungay)->startOfDay() : null;
        $to = $this->debt->denngay ? Carbon::parse($this->debt->denngay)->endOfDay() : null;
        $idDaily = (int) $this->debt->id_daily;

        if (! $from || ! $to || $idDaily <= 0) {
            Flux::toast(heading: 'Thiếu thông tin', text: 'Công nợ thiếu khoảng ngày hoặc đại lý để quét.', variant: 'warning');
            return;
        }

        $existingOrderIds = $this->debt->details()->pluck('id_order')->all();

        $newOrders = $this->eligibleOrdersQuery($from, $to, $idDaily)
            ->whereNotIn('id', $existingOrderIds)
            ->with(['packages'])
            ->get();

        if ($newOrders->isEmpty()) {
            Flux::toast(heading: 'Không có đơn mới', text: 'Không tìm thấy order mới thỏa điều kiện để bổ sung.', variant: 'info');
            return;
        }

        DB::transaction(function () use ($newOrders) {
            $rows = $newOrders->map(fn (Order $order) => [
                'id_congno_daily' => $this->debt->id,
                'id_order' => $order->id,
                ...$this->snapshotForOrder($order),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            CongNoDaiLyDetail::insert($rows);
            $this->debt->syncTotalsFromDetails();
            $this->debt->writeActivityLog(
                action: 'orders_refreshed',
                title: 'Bổ sung order vào công nợ',
                metadata: [
                    'added_count' => $newOrders->count(),
                    'order_ids' => $newOrders->pluck('id')->values()->all(),
                    'order_codes' => $newOrders->pluck('id_bill')->filter()->values()->all(),
                ],
            );

            Order::query()->whereIn('id', $newOrders->pluck('id'))
                ->update(['agency_payment_status' => DebtStatusEnum::MOI_TAO->value]);
        });

        $this->reloadDebt();
        Flux::toast(heading: 'Đã làm mới', text: "Đã bổ sung {$newOrders->count()} order vào công nợ.", variant: 'success');
        Flux::modal('refresh-orders')->close();
    }

    protected function eligibleOrdersQuery(Carbon $from, Carbon $to, int $idDaily)
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('service->id_daily', $idDaily)
            ->where('bill_status', '!=', OrderStatusEnum::HUY->value)
            ->where(function ($q) {
                $q->whereNull('agency_payment_status')
                    ->orWhereNotIn('agency_payment_status', [
                        DebtStatusEnum::MOI_TAO->value,
                        DebtStatusEnum::DA_THANH_TOAN->value,
                    ]);
            })
            ->whereDoesntHave(
                'congNoDaiLyDetails.congNoDaiLy',
                fn ($q) => $q->whereNotIn('status', [
                    DebtStatusEnum::DA_THANH_TOAN->value,
                    DebtStatusEnum::DA_HUY->value,
                ])
            );
    }

    protected function snapshotForOrder(Order $order): array
    {
        $weight = (float) $order->packages->sum(fn ($package) => (float) ($package->row_c_weight ?: $package->c_weight ?: 0));
        $snapshot = [
            'order_code' => $order->id_bill,
            'sale_total' => (float) data_get($order->payment_cuocban, 'total_tongcuoc', 0),
            'cost_total' => (float) data_get($order->payment_cuocvon, 'total_tongcuoc', 0),
            'base_total' => (float) data_get($order->payment_cuocgoc, 'total_tongcuoc', 0),
            'vat' => (float) data_get($order->payment_cuocvon, 'total_vat', 0),
            'ppxd' => (float) data_get($order->payment_cuocvon, 'ppxd_amount', 0),
            'fee' => (float) data_get($order->payment_cuocvon, 'total_phuphi', 0),
            'commission' => (float) data_get($order->payment_cuocvon, 'bonus_sale_amount', 0),
            'weight' => $weight,
        ];

        return [
            'weight' => $weight,
            'cuocban' => $snapshot['sale_total'],
            'cuocvon' => $snapshot['cost_total'],
            'cuocgoc' => $snapshot['base_total'],
            'vat' => $snapshot['vat'],
            'ppxd' => $snapshot['ppxd'],
            'phuphi' => $snapshot['fee'],
            'hoahong' => $snapshot['commission'],
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    public function cancelDebt(): void
    {
        abort_unless($this->canManage(), 403);
        $this->claimAccountantIfNeeded();

        if (in_array($this->debt->status, [DebtStatusEnum::DA_THANH_TOAN, DebtStatusEnum::DA_HUY], true)) {
            Flux::toast(heading: 'Không thể hủy', text: 'Công nợ đã thanh toán hoặc đã hủy không thể hủy.', variant: 'warning');
            return;
        }

        if ($this->debt->payments()
            ->whereIn('status', InvoicePaymentStatusEnum::pendingValues())
            ->exists()) {
            Flux::toast(heading: 'Không thể hủy', text: 'Công nợ còn hóa đơn đang xử lý.', variant: 'warning');
            return;
        }

        DB::transaction(function () {
            $this->debt->writeActivityLog(
                action: 'cancelled',
                title: 'Hủy công nợ đại lý',
                fromStatus: $this->debt->status,
                toStatus: DebtStatusEnum::DA_HUY,
                metadata: array_filter([
                    'total_orders' => (int) $this->debt->total_orders,
                    'total_amount' => (float) $this->debt->total_cuocvon,
                ], fn ($v) => $v !== null),
            );

            $this->debt->orders()->update([
                'agency_payment_status' => null,
                'agency_paid_at' => null,
            ]);

            $this->debt->forceFill([
                'status' => DebtStatusEnum::DA_HUY,
            ])->save();
        });

        Flux::toast(heading: 'Đã hủy công nợ', text: 'Công nợ đại lý đã chuyển sang trạng thái đã hủy và các order được giải phóng.', variant: 'success');
        $this->redirectRoute('congno.daily.index', navigate: true);
    }

    protected function reloadDebt(): void
    {
        $this->debt = $this->loadDebt((string) $this->debt->id);
        $this->dispatch('debt-activity-updated');
    }

    public function canView(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'ketoan']);
    }

    public function hasDebtAdminPower(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager']);
    }

    public function isAssignedAccountant(): bool
    {
        $user = auth()->user();

        return $user->hasRole('ketoan')
            && (int) $this->debt->id_ketoan === (int) $user->id;
    }

    public function isUnassignedAccountant(): bool
    {
        $user = auth()->user();

        return $user->hasRole('ketoan') && empty($this->debt->id_ketoan);
    }

    public function canManage(): bool
    {
        return $this->hasDebtAdminPower()
            || $this->isAssignedAccountant()
            || $this->isUnassignedAccountant();
    }

    public ?int $reassignAccountantId = null;

    public function reassignAccountant(): void
    {
        abort_unless($this->hasDebtAdminPower(), 403);

        $data = $this->validate([
            'reassignAccountantId' => ['required', 'integer', 'exists:user,id'],
        ], [], [
            'reassignAccountantId' => 'Kế toán phụ trách',
        ]);

        $accountant = \App\Models\User::role('ketoan')->whereKey((int) $data['reassignAccountantId'])->first();

        if (! $accountant) {
            Flux::toast(heading: 'Không hợp lệ', text: 'User được chọn không phải kế toán.', variant: 'warning');
            return;
        }

        $oldAccountantId = $this->debt->id_ketoan;

        $this->debt->forceFill(['id_ketoan' => $accountant->id])->save();
        $this->debt->writeActivityLog(
            action: 'accountant_reassigned',
            title: 'Đổi kế toán phụ trách',
            metadata: [
                'ketoan_from' => $oldAccountantId,
                'ketoan_to' => $accountant->id,
            ],
        );

        $this->reloadDebt();
        Flux::modal('reassign-accountant')->close();
        Flux::toast(heading: 'Đã cập nhật', text: 'Đã đổi kế toán phụ trách công nợ đại lý.', variant: 'success');
    }

    public function accountants()
    {
        return \App\Models\User::role('ketoan')->orderBy('fullname')->get(['id', 'fullname', 'username', 'code']);
    }

    protected function canClaimAsAccountant(): bool
    {
        $user = auth()->user();

        return $user->hasRole('ketoan')
            && ! $user->hasAnyRole(['admin', 'manager'])
            && empty($this->debt->id_ketoan);
    }

    /**
     * Gán kế toán đầu tiên thao tác cho công nợ đại lý chưa có người phụ trách.
     */
    protected function claimAccountantIfNeeded(): void
    {
        if (! $this->canClaimAsAccountant()) {
            return;
        }

        $assigned = DB::transaction(function () {
            $debt = CongNoDaiLy::query()->whereKey($this->debt->id)->lockForUpdate()->firstOrFail();

            if (! empty($debt->id_ketoan)) {
                return false;
            }

            $debt->forceFill(['id_ketoan' => auth()->id()])->save();

            $debt->writeActivityLog(
                action: 'accountant_assigned',
                title: 'Nhận phụ trách công nợ',
                metadata: ['ketoan_id' => auth()->id()],
            );

            return true;
        });

        if ($assigned) {
            $this->reloadDebt();
        }
    }

    protected function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^\d.,-]/', '', (string) ($value ?? 0));

        if ($normalized === '' || $normalized === '-') {
            return 0;
        }

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($normalized, ',');
            $lastDot = strrpos($normalized, '.');

            if ($lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            if (preg_match('/^-?\d{1,3}(,\d{3})+$/', $normalized)) {
                $normalized = str_replace(',', '', $normalized);
            } else {
                $normalized = str_replace(',', '.', $normalized);
            }
        } elseif ($hasDot && preg_match('/^-?\d{1,3}(\.\d{3})+$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
        }

        return (float) $normalized;
    }

    public function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' đ';
    }

    public function render()
    {
        return $this->view();
    }
};

?>

<div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route('congno.daily.index') }}" wire:navigate class="text-sm font-semibold text-primary-700 hover:text-primary-800">← Danh sách công nợ đại lý</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-neutral-950">{{ $debt->sohoadon }}</h1>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $debt->status->color() }}">{{ $debt->status->label() }}</span>
                <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Công nợ đại lý</span>
            </div>
            <p class="mt-1 text-sm text-neutral-500">{{ $debt->daily?->namevi ?: $debt->daily?->nameen ?: 'Chưa rõ đại lý' }}</p>
        </div>
        @if ($this->canManage())
            <div class="flex flex-wrap items-center gap-2">
                @if ($debt->status === DebtStatusEnum::MOI_TAO)
                    <flux:modal.trigger name="refresh-orders">
                        <flux:button type="button" variant="filled" icon="arrow-path">
                            Làm mới
                        </flux:button>
                    </flux:modal.trigger>
                    <flux:button type="button" wire:click="confirmDebt" wire:confirm="Chốt cước công nợ này? Sau khi chốt sẽ không thể sửa/xóa order." variant="primary" icon="check-circle">
                        Chốt cước
                    </flux:button>
                @endif
                @if (! in_array($debt->status, [DebtStatusEnum::DA_THANH_TOAN, DebtStatusEnum::DA_HUY], true))
                    <button type="button" wire:click="cancelDebt" wire:confirm="Hủy công nợ này? Các order sẽ được giải phóng để tạo công nợ mới." class="rounded-xl border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-100">Hủy công nợ</button>
                @endif
            </div>
        @endif
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-5">
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
                                <th class="px-4 py-3 text-right font-semibold">Cước vốn</th>
                                <th class="px-4 py-3 text-right font-semibold">Cước bán</th>
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
                                    <td class="px-4 py-4 text-right font-semibold text-neutral-950">{{ $this->money($detail->cuocvon) }}</td>
                                    <td class="px-4 py-4 text-right text-neutral-700">{{ $this->money($detail->cuocban) }}</td>
                                    <td class="px-4 py-4 text-right">
                                        @if ($this->canManage() && ! $debt->canCreatePaymentInvoice())
                                            <button type="button" wire:click="removeOrder({{ $detail->id }})" wire:confirm="Gỡ order này khỏi công nợ đại lý?" class="rounded-lg px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Gỡ</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-neutral-500">Công nợ chưa có order.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($debt->status === DebtStatusEnum::MOI_TAO)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    <p class="font-semibold">Chưa chốt cước.</p>
                    <p class="mt-1">Cần chốt cước công nợ trước khi cập nhật thông tin thanh toán.</p>
                </div>
            @elseif ($this->canEditPaymentInfo())
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-xs">
                    <h2 class="font-bold text-neutral-950">Thanh toán công nợ</h2>
                    <p class="mt-1 text-sm text-neutral-500">Cập nhật mã tham chiếu và chứng từ thanh toán cho đại lý.</p>

                    <div class="mt-4 space-y-4">
                        <label class="block">
                            <span class="text-sm font-semibold text-neutral-700">Mã tham chiếu</span>
                            <input type="text" wire:model="referenceCode" placeholder="Nhập mã tham chiếu thanh toán..." class="mt-1 h-10 w-full rounded-xl border border-neutral-200 px-3 text-sm outline-none focus:border-primary-500">
                            @error('referenceCode') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>

                        <div>
                            <span class="text-sm font-semibold text-neutral-700">Chứng từ thanh toán</span>
                            @if ($debt->photo)
                                <div class="mt-2 mb-2">
                                    <a href="{{ asset('storage/' . $debt->photo) }}" target="_blank" class="inline-flex items-center gap-1.5 text-sm text-primary-700 hover:text-primary-800">
                                        <flux:icon.document class="size-4" />
                                        Xem chứng từ hiện tại
                                    </a>
                                </div>
                            @endif
                            <input type="file" wire:model="paymentPhoto" accept="image/*" class="mt-1 w-full rounded-xl border border-neutral-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-neutral-100 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-neutral-700">
                            @error('paymentPhoto') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                            <p class="mt-1 text-xs text-neutral-400">Ảnh chứng từ (tối đa 5MB)</p>
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <flux:button type="button" wire:click="updatePaymentInfo" variant="outline" icon="arrow-up-tray">
                                Cập nhật
                            </flux:button>
                            <flux:modal.trigger name="confirm-paid">
                                <flux:button type="button" variant="primary" icon="check-circle">
                                    Xác nhận đã thanh toán
                                </flux:button>
                            </flux:modal.trigger>
                        </div>
                    </div>
                </div>
            @elseif ($debt->status === DebtStatusEnum::DA_THANH_TOAN)
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-xs">
                    <div class="flex items-center gap-2">
                        <flux:icon.check-circle class="size-5 text-emerald-600" />
                        <h2 class="font-bold text-emerald-800">Đã thanh toán</h2>
                    </div>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-emerald-600">Ngày thanh toán</dt><dd class="font-semibold text-emerald-900">{{ $debt->ngaythanhtoan?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-emerald-600">Mã tham chiếu</dt><dd class="font-semibold text-emerald-900">{{ $debt->sohoadon_thamchieu ?: '-' }}</dd></div>
                        @if ($debt->photo)
                            <div class="flex justify-between gap-3">
                                <dt class="text-emerald-600">Chứng từ</dt>
                                <dd><a href="{{ asset('storage/' . $debt->photo) }}" target="_blank" class="font-semibold text-primary-700 hover:text-primary-800">Xem chứng từ</a></dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif
        </div>

        <div class="space-y-5">
            <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-xs">
                <h2 class="font-bold text-neutral-950">Thông tin công nợ</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Đại lý</dt><dd class="font-semibold text-neutral-900">{{ $debt->daily?->namevi ?: $debt->daily?->nameen ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Người tạo</dt><dd class="font-semibold text-neutral-900">{{ $debt->creator?->fullname ?: $debt->creator?->username ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Kế toán</dt><dd class="text-right font-semibold text-neutral-900">{{ $debt->ketoan?->fullname ?: '-' }} @if ($this->hasDebtAdminPower()) <flux:modal.trigger name="reassign-accountant"><button type="button" class="ml-1 text-xs font-semibold text-primary-700 hover:text-primary-800">Đổi</button></flux:modal.trigger> @endif</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Tổng tiền</dt><dd class="font-semibold text-neutral-900">{{ $this->money($debt->total_cuocvon) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Ngày tạo</dt><dd class="font-semibold text-neutral-900">{{ $debt->ngaytaohoadon?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Ngày chốt cước</dt><dd class="font-semibold text-neutral-900">{{ $debt->ngaychothoadon?->format('d/m/Y H:i') ?: '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Tham chiếu</dt><dd class="font-semibold text-neutral-900">{{ $debt->sohoadon_thamchieu ?: '-' }}</dd></div>
                    @if ($debt->ngaythanhtoan)
                        <div class="flex justify-between gap-3"><dt class="text-neutral-500">Ngày thanh toán</dt><dd class="font-semibold text-emerald-700">{{ $debt->ngaythanhtoan->format('d/m/Y H:i') }}</dd></div>
                    @endif
                </dl>
            </div>

            <livewire:debt.activity-history :debt="$debt" wire:key="agency-debt-activity-{{ $debt->id }}" />
        </div>
    </div>

    @if ($this->hasDebtAdminPower())
        <flux:modal name="reassign-accountant" class="w-full max-w-lg">
            <form wire:submit="reassignAccountant" class="space-y-5">
                <div>
                    <h2 class="text-lg font-bold text-neutral-950">Đổi kế toán phụ trách</h2>
                    <p class="mt-1 text-sm text-neutral-500">Chọn kế toán mới cho công nợ đại lý {{ $debt->sohoadon }}.</p>
                </div>

                <label class="block">
                    <span class="text-sm font-semibold text-neutral-700">Kế toán phụ trách</span>
                    <select wire:model="reassignAccountantId" class="mt-1 h-10 w-full rounded-xl border border-neutral-200 bg-white px-3 text-sm outline-none focus:border-primary-500">
                        <option value="">-- Chọn kế toán --</option>
                        @foreach ($this->accountants() as $accountant)
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
                    <p class="mt-1 text-sm text-neutral-500">Quét và bổ sung các đơn hàng thỏa điều kiện (cùng đại lý, cùng khoảng ngày, chưa thuộc công nợ đang mở) vào công nợ này.</p>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-100 bg-neutral-50 p-4 text-sm text-neutral-600">
                <div class="flex justify-between gap-3"><span class="text-neutral-500">Đại lý</span><span class="font-semibold text-neutral-900">{{ $debt->daily?->namevi ?: $debt->daily?->nameen ?: '-' }}</span></div>
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

    <flux:modal name="confirm-paid" class="w-full max-w-lg">
        <div class="space-y-5">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                    <flux:icon.check-circle class="size-5" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-neutral-950">Xác nhận đã thanh toán</h2>
                    <p class="mt-1 text-sm text-neutral-500">Lưu thông tin đã nhập và chuyển công nợ sang trạng thái đã thanh toán. Sau khi xác nhận, công nợ không thể cập nhật thêm.</p>
                </div>
            </div>

            <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-800">
                <div class="flex justify-between gap-3"><span>Tổng thanh toán</span><span class="font-bold">{{ $this->money($debt->total_cuocvon) }}</span></div>
                <div class="mt-2 flex justify-between gap-3"><span>Mã tham chiếu</span><span class="font-semibold">{{ $referenceCode ?: '-' }}</span></div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-neutral-100 pt-4">
                <flux:modal.close>
                    <flux:button type="button" variant="outline">Đóng</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="primary" icon="check-circle" wire:click="confirmPaid">Xác nhận đã thanh toán</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
