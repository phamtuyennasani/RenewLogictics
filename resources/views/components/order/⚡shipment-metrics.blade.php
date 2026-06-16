<?php

use App\Actions\Order\CalculateChargeableWeightAction;
use App\Actions\Order\RecordOrderEditHistoryAction;
use App\Enums\OrderStatusEnum;
use App\Models\News;
use App\Models\Order;
use App\Models\OrderPackage;
use App\Support\OrderAccess;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Order $order;
    public array $packageForm = [];
    public $dim = 6000;

    public function mount(): void
    {
        $this->dim = $this->order->dim ?: 6000;
        $this->fillPackageForm();
    }

    #[On('order-lock-updated')]
    public function refreshOrderLock(): void
    {
        $this->order->refresh();
        $this->order->load('packages');
        $this->dim = $this->order->dim ?: 6000;
        $this->fillPackageForm();
    }

    public function fillPackageForm(): void
    {
        $this->packageForm = $this->order->packages
            ->sortBy('id')
            ->map(fn (OrderPackage $package) => [
                'id' => $package->id,
                'code' => $package->code,
                'package_type' => $package->package_type,
                'number_of_package' => $package->number_of_package ?: 1,
                'length' => $package->length,
                'width' => $package->width,
                'height' => $package->height,
                'g_weight' => $package->g_weight,
                'v_weight' => $package->v_weight,
                'c_weight' => $package->c_weight,
                'row_g_weight' => $package->row_g_weight,
                'row_v_weight' => $package->row_v_weight,
                'row_c_weight' => $package->row_c_weight,
            ])
            ->values()
            ->all();

        if ($this->packageForm === []) {
            $this->packageForm[] = $this->defaultPackage();
        }

        $this->recalculateAll();
    }

    protected function defaultPackage(): array
    {
        return [
            'id' => null,
            'code' => null,
            'package_type' => null,
            'number_of_package' => 1,
            'length' => '',
            'width' => '',
            'height' => '',
            'g_weight' => '',
            'v_weight' => 0,
            'c_weight' => 0,
            'row_g_weight' => 0,
            'row_v_weight' => 0,
            'row_c_weight' => 0,
        ];
    }

    public function addPackage(): void
    {
        $this->packageForm[] = $this->defaultPackage();
    }

    public function removePackage(int $index): void
    {
        if (count($this->packageForm) <= 1) {
            return;
        }

        unset($this->packageForm[$index]);
        $this->packageForm = array_values($this->packageForm);
        $this->recalculateAll();
    }

    public function updated(string $propertyName): void
    {
        if (! str_starts_with($propertyName, 'packageForm.')) {
            return;
        }

        $parts = explode('.', $propertyName);
        $index = isset($parts[1]) ? (int) $parts[1] : null;

        if ($index === null || ! isset($this->packageForm[$index])) {
            return;
        }

        $this->calculatePackageRow($index);
    }

    public function updatedDim(): void
    {
        $this->dim = preg_replace('/\D+/', '', (string) $this->dim);
        $this->recalculateAll();
    }

    public function canEditPackages(): bool
    {
        if (! OrderAccess::canEditOrder(auth()->user(), $this->order)) {
            return false;
        }

        // CTV được sửa kiện hàng khi đơn còn mới tạo.
        if (auth()->user()->hasRole('ctv')) {
            return $this->order->bill_status === OrderStatusEnum::MOI_TAO;
        }

        return $this->order->bill_status === OrderStatusEnum::DA_NHAN_HANG;
    }

    public function savePackages(): void
    {
        if (! $this->canEditPackages()) {
            Flux::toast(duration: 2500, heading: 'Không thể chỉnh sửa', text: 'Chỉ được sửa kiện hàng khi đơn đã nhận hàng.', variant: 'warning');
            return;
        }

        OrderAccess::assignCsOnEdit(auth()->user(), $this->order);
        OrderAccess::assignOpsOnAction(auth()->user(), $this->order);

        $this->recalculateAll();
        $before = $this->packagesSnapshot();

        $this->validate([
            'dim' => 'required|numeric|min:1',
            'packageForm' => 'required|array|min:1',
            'packageForm.*.package_type' => 'required|exists:news,id',
            'packageForm.*.number_of_package' => 'required|integer|min:1',
            'packageForm.*.length' => 'required|numeric|min:0.1',
            'packageForm.*.width' => 'required|numeric|min:0.1',
            'packageForm.*.height' => 'required|numeric|min:0.1',
            'packageForm.*.g_weight' => 'required|numeric|min:0.1',
        ], [
            'dim.required' => 'DIM là bắt buộc.',
            'dim.numeric' => 'DIM chỉ được nhập số.',
            'dim.min' => 'DIM phải lớn hơn 0.',
            'packageForm.required' => 'Danh sách kiện là bắt buộc.',
            'packageForm.min' => 'Phải có ít nhất 1 kiện.',
            'packageForm.*.package_type.required' => 'Loại kiện là bắt buộc.',
            'packageForm.*.package_type.exists' => 'Loại kiện không hợp lệ.',
            'packageForm.*.number_of_package.required' => 'Số kiện là bắt buộc.',
            'packageForm.*.number_of_package.integer' => 'Số kiện phải là số nguyên.',
            'packageForm.*.number_of_package.min' => 'Số kiện phải lớn hơn 0.',
            'packageForm.*.length.required' => 'Chiều dài là bắt buộc.',
            'packageForm.*.length.numeric' => 'Chiều dài chỉ được nhập số.',
            'packageForm.*.length.min' => 'Chiều dài phải lớn hơn 0.',
            'packageForm.*.width.required' => 'Chiều rộng là bắt buộc.',
            'packageForm.*.width.numeric' => 'Chiều rộng chỉ được nhập số.',
            'packageForm.*.width.min' => 'Chiều rộng phải lớn hơn 0.',
            'packageForm.*.height.required' => 'Chiều cao là bắt buộc.',
            'packageForm.*.height.numeric' => 'Chiều cao chỉ được nhập số.',
            'packageForm.*.height.min' => 'Chiều cao phải lớn hơn 0.',
            'packageForm.*.g_weight.required' => 'Gross weight là bắt buộc.',
            'packageForm.*.g_weight.numeric' => 'Gross weight chỉ được nhập số.',
            'packageForm.*.g_weight.min' => 'Gross weight phải lớn hơn 0.',
        ]);

        DB::transaction(function () {
            $dim = $this->normalizedDim();

            $this->order->update([
                'dim' => $dim,
            ]);

            $existingPackages = $this->order->packages()
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $keptPackageIds = [];
            $packageIndex = $this->nextPackageIndex($existingPackages);

            foreach ($this->packageForm as $index => $package) {
                $this->calculatePackageRow($index);
                $package = $this->packageForm[$index];
                $qty = ! empty($package['id'])
                    ? 1
                    : max(1, (int) ($package['number_of_package'] ?? 1));

                for ($i = 0; $i < $qty; $i++) {
                    $payload = $this->packagePayload($package);
                    $packageId = (int) ($package['id'] ?? 0);
                    $existingPackage = $i === 0 && $packageId > 0
                        ? $existingPackages->get($packageId)
                        : null;

                    if ($existingPackage) {
                        $existingPackage->update([
                            ...$payload,
                            'code' => $existingPackage->code ?: $this->generatePackageCode($packageIndex++),
                        ]);

                        $keptPackageIds[] = $existingPackage->id;
                        continue;
                    }

                    $this->order->packages()->create([
                        ...$payload,
                        'code' => $this->generatePackageCode($packageIndex++),
                    ]);
                }
            }

            $deletePackageIds = $existingPackages->keys()
                ->diff($keptPackageIds)
                ->values();

            if ($deletePackageIds->isNotEmpty()) {
                $this->order->packages()
                    ->whereIn('id', $deletePackageIds)
                    ->delete();
            }
        });

        $this->order->refresh();
        $this->order->load('packages');
        RecordOrderEditHistoryAction::execute($this->order, 'edit_packages', 'kiện hàng', $before, $this->packagesSnapshot(), 'sửa kiện hàng');
        $this->fillPackageForm();
        $this->dispatch('order-history-updated');

        Flux::modal('edit-packages')->close();
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật cân nặng & kiện hàng.', variant: 'success');
    }

    protected function packagePayload(array $package): array
    {
        return [
            'package_type' => $package['package_type'] ?: null,
            'number_of_package' => 1,
            'length' => (float) ($package['length'] ?? 0),
            'width' => (float) ($package['width'] ?? 0),
            'height' => (float) ($package['height'] ?? 0),
            'g_weight' => (float) ($package['g_weight'] ?? 0),
            'v_weight' => (float) ($package['v_weight'] ?? 0),
            'c_weight' => (float) ($package['c_weight'] ?? 0),
            'row_g_weight' => (float) ($package['g_weight'] ?? 0),
            'row_v_weight' => (float) ($package['v_weight'] ?? 0),
            'row_c_weight' => (float) ($package['c_weight'] ?? 0),
        ];
    }

    protected function packagesSnapshot(): array
    {
        return [
            'dim' => $this->order->dim,
            'packages' => $this->order->packages()
                ->orderBy('id')
                ->get(['id', 'code', 'package_type', 'number_of_package', 'length', 'width', 'height', 'g_weight', 'v_weight', 'c_weight', 'row_g_weight', 'row_v_weight', 'row_c_weight'])
                ->map(fn (OrderPackage $package) => $package->toArray())
                ->values()
                ->all(),
        ];
    }

    protected function calculatePackageRow(int $index): void
    {
        $package = $this->packageForm[$index] ?? $this->defaultPackage();
        $qty = ! empty($package['id'])
            ? 1
            : max(1, (int) ($package['number_of_package'] ?? 1));

        $weights = CalculateChargeableWeightAction::execute(
            length: (float) ($package['length'] ?? 0),
            width: (float) ($package['width'] ?? 0),
            height: (float) ($package['height'] ?? 0),
            gWeight: (float) ($package['g_weight'] ?? 0),
            dim: $this->normalizedDim(),
        );

        $this->packageForm[$index]['number_of_package'] = $qty;
        $this->packageForm[$index]['v_weight'] = $weights['v_weight'];
        $this->packageForm[$index]['c_weight'] = $weights['c_weight'];
        $this->packageForm[$index]['row_g_weight'] = round((float) ($package['g_weight'] ?? 0) * $qty, 2);
        $this->packageForm[$index]['row_v_weight'] = round($weights['v_weight'] * $qty, 2);
        $this->packageForm[$index]['row_c_weight'] = round($weights['c_weight'] * $qty, 2);
    }

    protected function recalculateAll(): void
    {
        foreach (array_keys($this->packageForm) as $index) {
            $this->calculatePackageRow((int) $index);
        }
    }

    protected function generatePackageCode(int $index): string
    {
        return ($this->order->id_bill ?: 'ORDER-'.$this->order->id).'-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    }

    protected function normalizedDim(): float
    {
        return is_numeric($this->dim) && (float) $this->dim > 0
            ? (float) $this->dim
            : 6000;
    }

    protected function nextPackageIndex($packages): int
    {
        $baseCode = $this->order->id_bill ?: 'ORDER-'.$this->order->id;
        $pattern = '/^'.preg_quote($baseCode, '/').'-(\d+)$/';

        $maxIndex = $packages
            ->map(function (OrderPackage $package) use ($pattern) {
                if (! is_string($package->code) || ! preg_match($pattern, $package->code, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() ?: 0;

        return $maxIndex + 1;
    }

    public function number(mixed $value, int $decimals = 2): string
    {
        return is_numeric($value) ? number_format((float) $value, $decimals) : '—';
    }

    #[Computed]
    public function packageTypes(): array
    {
        return Cache::remember('order_package_types', now()->addDay(), fn () =>
            News::whereType('loaikien')->orderBy('numb')->pluck('namevi', 'id')->toArray()
        );
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
    $packages = $order->packages;
    $totalGross = $packages->sum('row_g_weight');
    $totalVolume = $packages->sum('row_v_weight');
    $totalCharge = $packages->sum('row_c_weight');
    $totalQty = $packages->sum('number_of_package');
@endphp

<section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-neutral-900 uppercase">Cân nặng & kiện hàng</h2>
            <p class="text-xs text-neutral-500">Tổng hợp theo cân nặng gross, volume và chargeable</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-700">{{ $packages->count() }} dòng kiện</span>
            @if($this->canEditPackages())
            <flux:modal.trigger name="edit-packages">
                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Sửa cân nặng & kiện hàng">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                    </svg>
                </button>
            </flux:modal.trigger>
            @endif
        </div>
    </div>

    <div class="grid gap-3 border-b border-neutral-100 p-5 sm:grid-cols-4">
        <div class="rounded-lg bg-neutral-50 p-4"><p class="text-xs text-neutral-500">Số kiện</p><p class="mt-1 text-lg font-bold text-neutral-900">{{ $this->number($totalQty, 0) }}</p></div>
        <div class="rounded-lg bg-neutral-50 p-4"><p class="text-xs text-neutral-500">Gross weight</p><p class="mt-1 text-lg font-bold text-neutral-900">{{ $this->number($totalGross) }} kg</p></div>
        <div class="rounded-lg bg-neutral-50 p-4"><p class="text-xs text-neutral-500">Volume weight</p><p class="mt-1 text-lg font-bold text-neutral-900">{{ $this->number($totalVolume) }} kg</p></div>
        <div class="rounded-lg bg-primary-50 p-4"><p class="text-xs text-primary-600">Chargeable</p><p class="mt-1 text-lg font-bold text-primary-700">{{ $this->number($totalCharge) }} kg</p></div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-neutral-50 border-b border-neutral-100">
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Mã kiện</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Kích thước</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 text-right">Gross weight</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 text-right">Volume weight</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 text-right">Chargeable weight</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($packages as $package)
                    <tr class="hover:bg-neutral-50/60">
                        <td class="px-5 py-3 text-sm font-medium text-neutral-800">{{ $package->code ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600">{{ $this->number($package->length, 0) }} x {{ $this->number($package->width, 0) }} x {{ $this->number($package->height, 0) }}</td>
                        <td class="px-4 py-3 text-right text-sm text-neutral-600">{{ $this->number($package->row_g_weight) }}</td>
                        <td class="px-4 py-3 text-right text-sm text-neutral-600">{{ $this->number($package->row_v_weight) }}</td>
                        <td class="px-5 py-3 text-right text-sm font-semibold text-neutral-800">{{ $this->number($package->row_c_weight) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-neutral-500">Chưa có dữ liệu kiện hàng</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal name="edit-packages" class="w-full max-w-6xl">
        <form wire:submit="savePackages" class="space-y-6">
            <div>
                <div>
                    <flux:heading size="lg">Sửa cân nặng & kiện hàng</flux:heading>
                    <flux:subheading>Thông tin cân quy đổi và tính phí được tự tính lại theo DIM đang nhập.</flux:subheading>
                </div>
            </div>

            <div class="grid gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-3 md:grid-cols-4">
                <flux:field>
                    <flux:label>DIM</flux:label>
                    <flux:input
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        x-on:input="$event.target.value = $event.target.value.replace(/\D/g, '')"
                        wire:model.live.debounce.300ms="dim"
                    />
                    @error('dim') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
            </div>

            <div class="space-y-3">
                @foreach($packageForm as $index => $package)
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-3">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-neutral-800">Dòng kiện {{ $index + 1 }}</p>
                                <p class="text-xs text-neutral-500">{{ $package['code'] ?: 'Mã kiện sẽ được tạo khi lưu' }}</p>
                            </div>
                            @if(count($packageForm) > 1)
                                <button type="button" wire:click="removePackage({{ $index }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 bg-white text-neutral-500 hover:border-red-200 hover:bg-red-50 hover:text-red-600" aria-label="Xóa kiện">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            @endif
                        </div>

                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-7">
                            <flux:field class="xl:col-span-2">
                                <flux:label>Loại kiện</flux:label>
                                <flux:select wire:model="packageForm.{{ $index }}.package_type">
                                    <flux:select.option value="">Chọn loại kiện</flux:select.option>
                                    @foreach($this->packageTypes as $id => $name)
                                        <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                @error("packageForm.$index.package_type") <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                            <flux:field>
                                <flux:label>Số kiện</flux:label>
                                @if(empty($package['id']))
                                    <flux:input type="number" min="1" step="1" wire:model.live.debounce.300ms="packageForm.{{ $index }}.number_of_package" />
                                @else
                                    <div class="flex h-10 items-center rounded-lg border border-neutral-200 bg-neutral-100 px-3 text-sm font-medium text-neutral-700">1</div>
                                @endif
                                @error("packageForm.$index.number_of_package") <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                            <flux:field>
                                <flux:label>Dài</flux:label>
                                <flux:input type="number" min="0.1" step="0.1" wire:model.live.debounce.300ms="packageForm.{{ $index }}.length" />
                                @error("packageForm.$index.length") <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                            <flux:field>
                                <flux:label>Rộng</flux:label>
                                <flux:input type="number" min="0.1" step="0.1" wire:model.live.debounce.300ms="packageForm.{{ $index }}.width" />
                                @error("packageForm.$index.width") <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                            <flux:field>
                                <flux:label>Cao</flux:label>
                                <flux:input type="number" min="0.1" step="0.1" wire:model.live.debounce.300ms="packageForm.{{ $index }}.height" />
                                @error("packageForm.$index.height") <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                            <flux:field>
                                <flux:label>Gross weight / kiện</flux:label>
                                <flux:input type="number" min="0.1" step="0.1" wire:model.live.debounce.300ms="packageForm.{{ $index }}.g_weight" />
                                @error("packageForm.$index.g_weight") <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                        </div>

                        <div class="mt-3 grid gap-2 text-xs text-neutral-500 sm:grid-cols-3">
                            <div class="rounded-lg bg-white px-3 py-2">Gross weight: <span class="font-semibold text-neutral-800">{{ $this->number($package['row_g_weight'] ?? 0) }} kg</span></div>
                            <div class="rounded-lg bg-white px-3 py-2">Volume weight: <span class="font-semibold text-neutral-800">{{ $this->number($package['row_v_weight'] ?? 0) }} kg</span></div>
                            <div class="rounded-lg bg-white px-3 py-2">Chargeable weight: <span class="font-semibold text-primary-700">{{ $this->number($package['row_c_weight'] ?? 0) }} kg</span></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-3 border-t border-neutral-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <flux:button type="button" variant="filled" wire:click="addPackage">+ Thêm kiện</flux:button>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Hủy</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Lưu</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</section>
