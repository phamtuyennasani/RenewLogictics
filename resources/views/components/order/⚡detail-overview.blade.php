<?php

use App\Models\Order;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Order $order;

    public array $senderForm = [];
    public array $receiverForm = [];
    public array $serviceForm = [];

    public function mount(): void
    {
        $this->fillForms();
    }

    public function fillForms(): void
    {
        $sender = $this->order->sender ?? [];
        $receiver = $this->order->receiver ?? [];
        $service = $this->order->service ?? [];

        $this->senderForm = [
            'company' => data_get($sender, 'company', ''),
            'fullname' => data_get($sender, 'fullname', ''),
            'phone' => data_get($sender, 'phone', ''),
            'email' => data_get($sender, 'email', ''),
            'country' => data_get($sender, 'country', ''),
            'address' => data_get($sender, 'address', ''),
            'id_city' => data_get($sender, 'id_city', data_get($sender, 'city_id', '')),
            'id_ward' => data_get($sender, 'id_ward', data_get($sender, 'ward_id', '')),
        ];

        $this->receiverForm = [
            'company' => data_get($receiver, 'company', ''),
            'fullname' => data_get($receiver, 'fullname', data_get($receiver, 'tenlienhe', '')),
            'phone' => data_get($receiver, 'phone', ''),
            'email' => data_get($receiver, 'email', ''),
            'country_id' => data_get($receiver, 'country_id', data_get($receiver, 'id_country', '')),
            'address' => data_get($receiver, 'address', ''),
            'state' => data_get($receiver, 'state', ''),
            'city' => data_get($receiver, 'city', ''),
            'postcode' => data_get($receiver, 'postcode', ''),
            'vsvx' => (bool) data_get($receiver, 'vsvx', false),
        ];

        $this->serviceForm = [
            'id_dichvu' => data_get($service, 'id_dichvu'),
            'id_chitiet_dichvu' => data_get($service, 'id_chitiet_dichvu'),
            'id_chinhanh_nhanhang' => data_get($service, 'id_chinhanh_nhanhang'),
            'tensanpham' => data_get($service, 'tensanpham', ''),
            'dichvudikem' => array_values((array) data_get($service, 'dichvudikem', [])),
            'loaibuugui' => data_get($service, 'loaibuugui'),
            'lydoguihang' => data_get($service, 'lydoguihang'),
            'hinhthucguihang' => data_get($service, 'hinhthucguihang'),
            'deliveryterm' => data_get($service, 'deliveryterm'),
            'tinhtrangdon' => data_get($service, 'tinhtrangdon'),
        ];
    }

    public function value(array|string|null $data, string $key, mixed $fallback = '—'): mixed
    {
        return data_get($data, $key, $fallback) ?: $fallback;
    }

    public function serviceValue(string $key, mixed $fallback = '—'): mixed
    {
        return data_get($this->order->service ?? [], $key, $fallback) ?: $fallback;
    }

    public function saveSender(): void
    {
        $this->validate([
            'senderForm.company' => 'nullable|string|max:255',
            'senderForm.fullname' => 'nullable|string|max:255',
            'senderForm.phone' => 'nullable|string|max:50',
            'senderForm.email' => 'nullable|email|max:255',
            'senderForm.country' => 'nullable|string|max:100',
            'senderForm.address' => 'nullable|string|max:500',
            'senderForm.id_city' => 'nullable|exists:province,id',
            'senderForm.id_ward' => 'nullable|exists:wards,id',
        ]);

        $sender = array_merge($this->order->sender ?? [], $this->clean($this->senderForm));
        $this->order->forceFill(['sender' => $sender])->save();
        $this->order->refresh();
        $this->fillForms();

        Flux::modal('edit-sender')->close();
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật thông tin người gửi.', variant: 'success');
    }

    public function updatedSenderFormIdCity(): void
    {
        $this->senderForm['id_ward'] = '';
    }

    public function updatedReceiverFormPostcode(): void
    {
        $this->checkReceiverVsvx();
    }

    public function checkReceiverVsvx(): void
    {
        $postcode = trim((string) ($this->receiverForm['postcode'] ?? ''));
        $serviceId = (int) data_get($this->order->service ?? [], 'id_dichvu', 0);

        if ($postcode === '' || $serviceId === 0) {
            $this->receiverForm['vsvx'] = false;
            return;
        }

        $this->receiverForm['vsvx'] = \App\Models\VSVX::query()
            ->where('code', $postcode)
            ->where('id_dichvu', $serviceId)
            ->exists();
    }

    public function saveReceiver(): void
    {
        $this->checkReceiverVsvx();

        $this->validate([
            'receiverForm.company' => 'nullable|string|max:255',
            'receiverForm.fullname' => 'nullable|string|max:255',
            'receiverForm.phone' => 'nullable|string|max:50',
            'receiverForm.email' => 'nullable|email|max:255',
            'receiverForm.country_id' => 'nullable|exists:countries,id',
            'receiverForm.address' => 'nullable|string|max:500',
            'receiverForm.state' => 'nullable|string|max:150',
            'receiverForm.city' => 'nullable|string|max:150',
            'receiverForm.postcode' => 'nullable|string|max:50',
            'receiverForm.vsvx' => 'boolean',
        ]);

        $receiver = array_merge($this->order->receiver ?? [], $this->clean($this->receiverForm));
        $receiver['tenlienhe'] = $receiver['fullname'] ?? '';

        $this->order->forceFill(['receiver' => $receiver])->save();
        $this->order->refresh();
        $this->fillForms();

        Flux::modal('edit-receiver')->close();
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật thông tin người nhận.', variant: 'success');
    }

    public function saveService(): void
    {
        $this->validate([
            'serviceForm.id_dichvu' => 'required|exists:news,id',
            'serviceForm.id_chitiet_dichvu' => 'nullable|exists:news,id',
            'serviceForm.id_chinhanh_nhanhang' => 'nullable|exists:news,id',
            'serviceForm.tensanpham' => 'nullable|string|max:255',
            'serviceForm.dichvudikem' => 'nullable|array',
            'serviceForm.dichvudikem.*' => 'exists:news,id',
            'serviceForm.loaibuugui' => 'nullable|exists:news,id',
            'serviceForm.lydoguihang' => 'nullable|exists:news,id',
            'serviceForm.hinhthucguihang' => 'nullable|exists:news,id',
            'serviceForm.deliveryterm' => 'nullable|exists:news,id',
            'serviceForm.tinhtrangdon' => 'nullable|exists:news,id',
        ]);

        $service = array_merge($this->order->service ?? [], $this->normalizeService($this->serviceForm));
        $this->order->forceFill(['service' => $service])->save();
        $this->order->refresh();
        $this->order->load([
            'dichvu:id,namevi',
            'chiTietDichVu:id,namevi',
            'chiNhanhNhanHang:id,namevi',
            'cs:id,fullname,username',
            'ops:id,fullname,username',
        ]);
        $this->fillForms();
        $this->checkReceiverVsvx();

        Flux::modal('edit-service')->close();
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật thông tin dịch vụ.', variant: 'success');
    }

    protected function normalizeService(array $service): array
    {
        foreach ($service as $key => $value) {
            if (is_array($value)) {
                $service[$key] = array_values(array_filter(array_map(
                    fn ($item) => is_numeric($item) ? (int) $item : $item,
                    $value
                )));
                continue;
            }

            if (is_numeric($value)) {
                $service[$key] = (int) $value;
            } elseif (is_string($value)) {
                $service[$key] = trim($value);
            }
        }

        return $service;
    }

    protected function clean(array $data): array
    {
        return collect($data)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();
    }

    #[Computed]
    public function countries()
    {
        return \App\Models\Country::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function provinces()
    {
        return \App\Models\Province::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function senderWards()
    {
        if (empty($this->senderForm['id_city'])) {
            return collect();
        }

        return \App\Models\Ward::query()
            ->where('parent_code', $this->senderForm['id_city'])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function serviceOptions()
    {
        return \App\Models\News::query()
            ->whereIn('type', ['dichvuchinh', 'dichvuchitiet', 'chinhanh', 'dichvudikem', 'loaibuugui', 'lydoguihang', 'hinhthucgui', 'deliveryterm', 'tinhtrangdon'])
            ->orderBy('numb')
            ->get(['id', 'namevi', 'type'])
            ->groupBy('type');
    }

    public function optionsFor(string $type): array
    {
        return ($this->serviceOptions[$type] ?? collect())
            ->pluck('namevi', 'id')
            ->toArray();
    }

    public function render()
    {
        return $this->view();
    }
};

?>

<div class="grid gap-5 xl:grid-cols-3">
    <section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-neutral-900">Người gửi</h2>
            <flux:modal.trigger name="edit-sender">
                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Sửa người gửi">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                    </svg>
                </button>
            </flux:modal.trigger>
        </div>
        <div class="space-y-4 p-5">
            <div>
                <p class="text-base font-semibold text-neutral-900">{{ $this->value($order->sender, 'company') }}</p>
                <p class="text-sm text-neutral-500">{{ $this->value($order->sender, 'fullname') }}</p>
            </div>
            <div class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                <div><p class="text-xs text-neutral-400">Điện thoại</p><p class="font-medium text-neutral-700">{{ $this->value($order->sender, 'phone') }}</p></div>
                <div><p class="text-xs text-neutral-400">Email</p><p class="font-medium text-neutral-700 break-words">{{ $this->value($order->sender, 'email') }}</p></div>
                <div class="sm:col-span-2 xl:col-span-1"><p class="text-xs text-neutral-400">Địa chỉ</p><p class="font-medium text-neutral-700">{{ $this->value($order->sender, 'address') }}</p></div>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-neutral-900">Người nhận</h2>
            <flux:modal.trigger name="edit-receiver">
                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Sửa người nhận">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                    </svg>
                </button>
            </flux:modal.trigger>
        </div>
        <div class="space-y-4 p-5">
            <div>
                <p class="text-base font-semibold text-neutral-900">{{ $this->value($order->receiver, 'company') }}</p>
                <p class="text-sm text-neutral-500">{{ $this->value($order->receiver, 'fullname', $this->value($order->receiver, 'tenlienhe')) }}</p>
            </div>
            <div class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                <div><p class="text-xs text-neutral-400">Điện thoại</p><p class="font-medium text-neutral-700">{{ $this->value($order->receiver, 'phone') }}</p></div>
                <div><p class="text-xs text-neutral-400">Postcode</p><p class="font-medium text-neutral-700">{{ $this->value($order->receiver, 'postcode') }}</p></div>
                <div><p class="text-xs text-neutral-400">City/State</p><p class="font-medium text-neutral-700">{{ $this->value($order->receiver, 'city') }} / {{ $this->value($order->receiver, 'state') }}</p></div>
                <div class="sm:col-span-2 xl:col-span-1"><p class="text-xs text-neutral-400">Địa chỉ</p><p class="font-medium text-neutral-700">{{ $this->value($order->receiver, 'address') }}</p></div>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-neutral-900">Dịch vụ & phụ trách</h2>
            <flux:modal.trigger name="edit-service">
                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Sửa dịch vụ">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                    </svg>
                </button>
            </flux:modal.trigger>
        </div>
        <div class="space-y-4 p-5">
            <div>
                <p class="text-base font-semibold text-neutral-900">{{ $order->dichvu?->namevi ?: '—' }}</p>
                <p class="text-sm text-neutral-500">{{ $order->chiTietDichVu?->namevi ?: 'Chưa có dịch vụ chi tiết' }}</p>
            </div>
            <div class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                <div><p class="text-xs text-neutral-400">Sản phẩm</p><p class="font-medium text-neutral-700">{{ $this->serviceValue('tensanpham') }}</p></div>
                <div><p class="text-xs text-neutral-400">Chi nhánh nhận</p><p class="font-medium text-neutral-700">{{ $order->chiNhanhNhanHang?->namevi ?: '—' }}</p></div>
                <div><p class="text-xs text-neutral-400">CS</p><p class="font-medium text-neutral-700">{{ $order->cs?->fullname ?: $order->cs?->username ?: '—' }}</p></div>
                <div><p class="text-xs text-neutral-400">OPS</p><p class="font-medium text-neutral-700">{{ $order->ops?->fullname ?: $order->ops?->username ?: '—' }}</p></div>
            </div>
        </div>
    </section>

    <flux:modal name="edit-sender" class="w-full max-w-2xl">
        <form wire:submit="saveSender" class="space-y-6">
            <div>
                <flux:heading size="lg">Sửa thông tin người gửi</flux:heading>
                <flux:subheading>Chỉ cập nhật dữ liệu lưu trong đơn hàng hiện tại.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Công ty / khách hàng gửi</flux:label>
                    <flux:input wire:model="senderForm.company" />
                    @error('senderForm.company') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>Người liên hệ</flux:label>
                    <flux:input wire:model="senderForm.fullname" />
                    @error('senderForm.fullname') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>Điện thoại</flux:label>
                    <flux:input wire:model="senderForm.phone" />
                    @error('senderForm.phone') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input type="email" wire:model="senderForm.email" />
                    @error('senderForm.email') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field class="sm:col-span-2">
                    <flux:label>Quốc gia</flux:label>
                    <flux:input wire:model="senderForm.country" />
                    @error('senderForm.country') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>Tỉnh / thành phố</flux:label>
                    <x-select-search
                        name="senderForm.id_city"
                        :options="$this->provinces->pluck('name', 'id')->toArray()"
                        :selected="$senderForm['id_city'] ?? null"
                        placeholder="-- Chọn tỉnh / thành phố --"
                    />
                    @error('senderForm.id_city') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field wire:key="sender-ward-{{ $senderForm['id_city'] ?? 'none' }}">
                    <flux:label>Phường / xã</flux:label>
                    <x-select-search
                        name="senderForm.id_ward"
                        :options="$this->senderWards->pluck('name', 'id')->toArray()"
                        :selected="$senderForm['id_ward'] ?? null"
                        placeholder="-- Chọn phường / xã --"
                        :disabled="empty($senderForm['id_city'])"
                    />
                    @error('senderForm.id_ward') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field class="sm:col-span-2">
                    <flux:label>Địa chỉ</flux:label>
                    <flux:textarea wire:model="senderForm.address" rows="3" />
                    @error('senderForm.address') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Hủy</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Lưu</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-receiver" class="w-full max-w-2xl">
        <form wire:submit="saveReceiver" class="space-y-6">
            <div>
                <flux:heading size="lg">Sửa thông tin người nhận</flux:heading>
                <flux:subheading>Chỉ cập nhật dữ liệu lưu trong đơn hàng hiện tại.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Công ty nhận</flux:label>
                    <flux:input wire:model="receiverForm.company" />
                    @error('receiverForm.company') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>Người liên hệ</flux:label>
                    <flux:input wire:model="receiverForm.fullname" />
                    @error('receiverForm.fullname') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>Điện thoại</flux:label>
                    <flux:input wire:model="receiverForm.phone" />
                    @error('receiverForm.phone') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input type="email" wire:model="receiverForm.email" />
                    @error('receiverForm.email') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>Quốc gia</flux:label>
                    <x-select-search
                        name="receiverForm.country_id"
                        :options="$this->countries->pluck('name', 'id')->toArray()"
                        :selected="$receiverForm['country_id'] ?? null"
                        placeholder="-- Chọn quốc gia --"
                    />
                    @error('receiverForm.country_id') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>Postcode</flux:label>
                    <flux:input wire:model.live.debounce.500ms="receiverForm.postcode">
                        @if(($receiverForm['vsvx'] ?? false) === true)
                            <x-slot name="iconTrailing">
                                <flux:tooltip position="left" content="Postcode thuộc VSVX">
                                    <flux:icon.exclamation-triangle class="text-red-800" />
                                </flux:tooltip>
                            </x-slot>
                        @endif
                    </flux:input>
                    @error('receiverForm.postcode') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>City</flux:label>
                    <flux:input wire:model="receiverForm.city" />
                    @error('receiverForm.city') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>State</flux:label>
                    <flux:input wire:model="receiverForm.state" />
                    @error('receiverForm.state') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
                <flux:field class="sm:col-span-2">
                    <flux:label>Địa chỉ</flux:label>
                    <flux:textarea wire:model="receiverForm.address" rows="3" />
                    @error('receiverForm.address') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Hủy</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Lưu</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-service" class="w-full max-w-4xl">
        <form wire:submit="saveService" class="space-y-6">
            <div>
                <flux:heading size="lg">Sửa thông tin dịch vụ</flux:heading>
                <flux:subheading>Chỉ cập nhật dữ liệu dịch vụ lưu trong đơn hàng hiện tại.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label badge="Bắt buộc">Dịch vụ chính</flux:label>
                    <x-select-search
                        name="serviceForm.id_dichvu"
                        :options="$this->optionsFor('dichvuchinh')"
                        :selected="$serviceForm['id_dichvu'] ?? null"
                        placeholder="-- Chọn dịch vụ --"
                        required
                    />
                    @error('serviceForm.id_dichvu') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Dịch vụ chi tiết</flux:label>
                    <x-select-search
                        name="serviceForm.id_chitiet_dichvu"
                        :options="$this->optionsFor('dichvuchitiet')"
                        :selected="$serviceForm['id_chitiet_dichvu'] ?? null"
                        placeholder="-- Chọn chi tiết dịch vụ --"
                    />
                    @error('serviceForm.id_chitiet_dichvu') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Chi nhánh nhận hàng</flux:label>
                    <x-select-search
                        name="serviceForm.id_chinhanh_nhanhang"
                        :options="$this->optionsFor('chinhanh')"
                        :selected="$serviceForm['id_chinhanh_nhanhang'] ?? null"
                        placeholder="-- Chọn chi nhánh --"
                    />
                    @error('serviceForm.id_chinhanh_nhanhang') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>

                <flux:field class="sm:col-span-3">
                    <flux:label>Tên sản phẩm</flux:label>
                    <flux:input wire:model="serviceForm.tensanpham" />
                    @error('serviceForm.tensanpham') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
            </div>

            @if(count($this->optionsFor('dichvudikem')) > 0)
                <flux:checkbox.group label="Dịch vụ đi kèm:" variant="buttons" class="flex flex-wrap gap-3" wire:model="serviceForm.dichvudikem">
                    @foreach($this->optionsFor('dichvudikem') as $id => $label)
                        <flux:checkbox value="{{ $id }}">
                            <div class="flex items-center gap-2">
                                <flux:checkbox.indicator />
                                <span class="text-sm">{{ $label }}</span>
                            </div>
                        </flux:checkbox>
                    @endforeach
                </flux:checkbox.group>
                @error('serviceForm.dichvudikem') <flux:error>{{ $message }}</flux:error> @enderror
            @endif

            <div class="space-y-5">
                <flux:radio.group label="Loại bưu gửi:" variant="buttons" class="flex flex-wrap gap-3" wire:model="serviceForm.loaibuugui">
                    @foreach($this->optionsFor('loaibuugui') as $id => $label)
                        <flux:radio value="{{ $id }}">
                            <div class="flex items-center gap-2">
                                <flux:radio.indicator />
                                <span class="text-sm">{{ $label }}</span>
                            </div>
                        </flux:radio>
                    @endforeach
                </flux:radio.group>

                <flux:radio.group label="Lý do gửi hàng:" variant="buttons" class="flex flex-wrap gap-3" wire:model="serviceForm.lydoguihang">
                    @foreach($this->optionsFor('lydoguihang') as $id => $label)
                        <flux:radio value="{{ $id }}">
                            <div class="flex items-center gap-2">
                                <flux:radio.indicator />
                                <span class="text-sm">{{ $label }}</span>
                            </div>
                        </flux:radio>
                    @endforeach
                </flux:radio.group>

                <flux:radio.group label="Hình thức gửi hàng:" variant="buttons" class="flex flex-wrap gap-3" wire:model="serviceForm.hinhthucguihang">
                    @foreach($this->optionsFor('hinhthucgui') as $id => $label)
                        <flux:radio value="{{ $id }}">
                            <div class="flex items-center gap-2">
                                <flux:radio.indicator />
                                <span class="text-sm">{{ $label }}</span>
                            </div>
                        </flux:radio>
                    @endforeach
                </flux:radio.group>

                <flux:radio.group label="Delivery Term:" variant="buttons" class="flex flex-wrap gap-3" wire:model="serviceForm.deliveryterm">
                    @foreach($this->optionsFor('deliveryterm') as $id => $label)
                        <flux:radio value="{{ $id }}">
                            <div class="flex items-center gap-2">
                                <flux:radio.indicator />
                                <span class="text-sm">{{ $label }}</span>
                            </div>
                        </flux:radio>
                    @endforeach
                </flux:radio.group>

                <flux:radio.group label="Tình trạng đơn:" variant="buttons" class="flex flex-wrap gap-3" wire:model="serviceForm.tinhtrangdon">
                    @foreach($this->optionsFor('tinhtrangdon') as $id => $label)
                        <flux:radio value="{{ $id }}">
                            <div class="flex items-center gap-2">
                                <flux:radio.indicator />
                                <span class="text-sm">{{ $label }}</span>
                            </div>
                        </flux:radio>
                    @endforeach
                </flux:radio.group>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Hủy</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Lưu</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
