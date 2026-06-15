<?php

use App\Actions\Order\RecordOrderEditHistoryAction;
use App\Enums\OrderStatusEnum;
use App\Models\OrderPhoto;
use App\Models\Order;
use App\Support\OrderAccess;
use Flux\Flux;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Order $order;

    public array $senderForm = [];
    public array $receiverForm = [];
    public array $serviceForm = [];
    public string $noteForm = '';
    public array $newPhotos = [];
    public array $deletedPhotoIds = [];
    public ?string $assignCsId = null;
    public ?string $assignOpsId = null;

    public function mount(): void
    {
        $this->order->loadMissing('photos');
        $this->fillForms();
    }

    #[On('order-lock-updated')]
    public function refreshOrderLock(): void
    {
        $this->order->refresh();
        $this->order->load([
            'photos',
            'dichvu:id,namevi',
            'chiTietDichVu:id,namevi',
            'chiNhanhNhanHang:id,namevi',
            'cs:id,fullname,username',
            'ops:id,fullname,username',
        ]);
        $this->fillForms();
    }

    public function fillForms(): void
    {
        $sender = $this->order->sender ?? [];
        $receiver = $this->order->receiver ?? [];
        $service = $this->order->service ?? [];
        $this->noteForm = (string) ($this->order->ghichu ?? '');

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

        $this->assignCsId = $this->order->id_cs ? (string) $this->order->id_cs : null;
        $this->assignOpsId = $this->order->id_ops ? (string) $this->order->id_ops : null;
    }

    public function value(array|string|null $data, string $key, mixed $fallback = '—'): mixed
    {
        return data_get($data, $key, $fallback) ?: $fallback;
    }

    public function serviceValue(string $key, mixed $fallback = '—'): mixed
    {
        return data_get($this->order->service ?? [], $key, $fallback) ?: $fallback;
    }

    public function canEditSenderReceiver(): bool
    {
        return OrderAccess::canEditOrder(auth()->user(), $this->order)
            && in_array($this->order->bill_status, [
            OrderStatusEnum::MOI_TAO,
            OrderStatusEnum::DA_XAC_NHAN,
            OrderStatusEnum::DA_NHAN_HANG,
        ], true);
    }

    public function canEditService(): bool
    {
        return OrderAccess::canEditOrder(auth()->user(), $this->order)
            && in_array($this->order->bill_status, [
            OrderStatusEnum::MOI_TAO,
            OrderStatusEnum::DA_XAC_NHAN,
            OrderStatusEnum::DA_NHAN_HANG,
            OrderStatusEnum::DUYET_XUAT_HANG,
        ], true);
    }

    public function canEditNotesAndPhotos(): bool
    {
        return OrderAccess::canEditOrder(auth()->user(), $this->order)
            && $this->order->bill_status instanceof OrderStatusEnum
            && $this->order->bill_status->sortOrder() < OrderStatusEnum::DANG_PHAT_HANG->sortOrder()
            && ! $this->order->bill_status->isSpecial();
    }

    public function senderLocation(): string
    {
        $sender = $this->order->sender ?? [];
        $provinceId = data_get($sender, 'id_city', data_get($sender, 'city_id'));
        $wardId = data_get($sender, 'id_ward', data_get($sender, 'ward_id'));

        $province = $provinceId
            ? \App\Models\Province::whereKey($provinceId)->value('name')
            : null;
        $ward = $wardId
            ? \App\Models\Ward::whereKey($wardId)->value('name')
            : null;

        $parts = array_filter([$province, $ward, 'VIETNAM']);

        return $parts ? implode(' / ', $parts) : '—';
    }

    public function receiverLocation(): string
    {
        $receiver = $this->order->receiver ?? [];
        $countryId = data_get($receiver, 'country_id', data_get($receiver, 'id_country'));
        $country = $countryId
            ? \App\Models\Country::whereKey($countryId)->value('name')
            : data_get($receiver, 'country');

        $parts = array_filter([
            data_get($receiver, 'city'),
            data_get($receiver, 'state'),
            $country,
        ]);

        return $parts ? implode(' / ', $parts) : '—';
    }

    public function saveSender(): void
    {
        if (! $this->canEditSenderReceiver()) {
            Flux::toast(duration: 2500, heading: 'Không thể chỉnh sửa', text: 'Chỉ được sửa người gửi ở trạng thái mới tạo, đã xác nhận hoặc đã nhận hàng.', variant: 'warning');
            return;
        }

        OrderAccess::assignCsOnEdit(auth()->user(), $this->order);

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

        $before = $this->order->sender ?? [];
        $sender = array_merge($before, $this->clean($this->senderForm));
        $this->order->forceFill(['sender' => $sender])->save();
        RecordOrderEditHistoryAction::execute($this->order, 'edit_sender', 'người gửi', $before, $sender, 'sửa người gửi');
        $this->order->refresh();
        $this->fillForms();
        $this->dispatch('order-history-updated');

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
        if (! $this->canEditSenderReceiver()) {
            Flux::toast(duration: 2500, heading: 'Không thể chỉnh sửa', text: 'Chỉ được sửa người nhận ở trạng thái mới tạo, đã xác nhận hoặc đã nhận hàng.', variant: 'warning');
            return;
        }

        OrderAccess::assignCsOnEdit(auth()->user(), $this->order);

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

        $before = $this->order->receiver ?? [];
        $receiver = array_merge($before, $this->clean($this->receiverForm));
        $receiver['tenlienhe'] = $receiver['fullname'] ?? '';

        $this->order->forceFill(['receiver' => $receiver])->save();
        RecordOrderEditHistoryAction::execute($this->order, 'edit_receiver', 'người nhận', $before, $receiver, 'sửa người nhận');
        $this->order->refresh();
        $this->fillForms();
        $this->dispatch('order-history-updated');

        Flux::modal('edit-receiver')->close();
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật thông tin người nhận.', variant: 'success');
    }

    public function saveService(): void
    {
        if (! $this->canEditService()) {
            Flux::toast(duration: 2500, heading: 'Không thể chỉnh sửa', text: 'Không được sửa dịch vụ ở trạng thái hiện tại.', variant: 'warning');
            return;
        }

        OrderAccess::assignCsOnEdit(auth()->user(), $this->order);

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

        $before = $this->order->service ?? [];
        $service = array_merge($before, $this->normalizeService($this->serviceForm));
        $this->order->forceFill(['service' => $service])->save();
        RecordOrderEditHistoryAction::execute($this->order, 'edit_service', 'dịch vụ', $before, $service, 'sửa dịch vụ');
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
        $this->dispatch('order-history-updated');

        Flux::modal('edit-service')->close();
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật thông tin dịch vụ.', variant: 'success');
    }

    public function csOptions()
    {
        return \App\Models\User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'cs'))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username']);
    }

    public function opsOptions()
    {
        return \App\Models\User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'ops'))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username']);
    }

    public function canAssignCs(): bool
    {
        return OrderAccess::canAssignCs(auth()->user(), $this->order);
    }

    public function canAssignOps(): bool
    {
        return OrderAccess::canAssignOps(auth()->user(), $this->order);
    }

    public function saveCs(): void
    {
        abort_unless($this->canAssignCs(), 403);

        $newId = filled($this->assignCsId) ? (int) $this->assignCsId : null;

        if ($newId !== null) {
            $valid = \App\Models\User::query()
                ->whereKey($newId)
                ->whereHas('roles', fn ($q) => $q->where('name', 'cs'))
                ->exists();
            abort_unless($valid, 422);
        }

        $before = ['id_cs' => $this->order->id_cs];
        $this->order->forceFill(['id_cs' => $newId])->save();
        $this->order->refresh();
        $this->order->load('cs:id,fullname,username,code');
        $this->fillForms();

        RecordOrderEditHistoryAction::execute($this->order, 'assign_cs', 'CS phụ trách', $before, [
            'id_cs' => $this->order->id_cs,
        ], 'cập nhật CS phụ trách');

        $this->dispatch('order-history-updated');
        Flux::modal('edit-cs')->close();
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật CS phụ trách.', variant: 'success');
    }

    public function saveOps(): void
    {
        abort_unless($this->canAssignOps(), 403);

        $newId = filled($this->assignOpsId) ? (int) $this->assignOpsId : null;

        // Sale/CS chỉ được chọn khi đơn chưa có OPS; không cho gỡ về trống.
        if (! auth()->user()->hasAnyRole(['admin', 'manager'])) {
            abort_unless($newId !== null, 422);
        }

        if ($newId !== null) {
            $valid = \App\Models\User::query()
                ->whereKey($newId)
                ->whereHas('roles', fn ($q) => $q->where('name', 'ops'))
                ->exists();
            abort_unless($valid, 422);
        }

        $before = ['id_ops' => $this->order->id_ops];
        $this->order->forceFill(['id_ops' => $newId])->save();
        $this->order->refresh();
        $this->order->load('ops:id,fullname,username,code');
        $this->fillForms();

        RecordOrderEditHistoryAction::execute($this->order, 'assign_ops', 'OPS phụ trách', $before, [
            'id_ops' => $this->order->id_ops,
        ], 'cập nhật OPS phụ trách');

        $this->dispatch('order-history-updated');
        Flux::modal('edit-ops')->close();
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật OPS phụ trách.', variant: 'success');
    }

    public function removeExistingPhoto(int $photoId): void
    {
        if (! in_array($photoId, $this->deletedPhotoIds, true)) {
            $this->deletedPhotoIds[] = $photoId;
        }
    }

    public function removeNewPhoto(int $index): void
    {
        unset($this->newPhotos[$index]);
        $this->newPhotos = array_values($this->newPhotos);
    }

    public function resetNotesPhotoForm(): void
    {
        $this->noteForm = (string) ($this->order->ghichu ?? '');
        $this->newPhotos = [];
        $this->deletedPhotoIds = [];
        $this->resetErrorBag();
    }

    public function saveNotesAndPhotos(): void
    {
        if (! $this->canEditNotesAndPhotos()) {
            Flux::toast(duration: 2500, heading: 'Không thể chỉnh sửa', text: 'Chỉ được sửa ghi chú và ảnh trước trạng thái đang phát hàng.', variant: 'warning');
            return;
        }

        OrderAccess::assignCsOnEdit(auth()->user(), $this->order);

        $this->validate([
            'noteForm' => 'nullable|string|max:5000',
            'newPhotos' => 'nullable|array',
            'newPhotos.*' => 'image|max:20480',
        ], [
            'newPhotos.*.image' => 'File đính kèm phải là hình ảnh.',
            'newPhotos.*.max' => 'Mỗi ảnh không được vượt quá 20MB.',
        ]);

        $currentPhotoCount = $this->order->photos()
            ->whereNotIn('id', $this->deletedPhotoIds ?: [0])
            ->count();

        if ($currentPhotoCount + count($this->newPhotos) > 5) {
            $this->addError('newPhotos', 'Mỗi đơn chỉ được lưu tối đa 5 ảnh đính kèm.');
            return;
        }

        $before = [
            'ghichu' => $this->order->ghichu,
            'photos' => $this->order->photos->pluck('photo')->values()->all(),
        ];

        $this->order->forceFill([
            'ghichu' => trim($this->noteForm),
        ])->save();

        if ($this->deletedPhotoIds !== []) {
            $this->order->photos()
                ->whereIn('id', $this->deletedPhotoIds)
                ->get()
                ->each
                ->delete();
        }

        $this->storeNewPhotos();

        $this->order->refresh();
        $this->order->load('photos');

        $after = [
            'ghichu' => $this->order->ghichu,
            'photos' => $this->order->photos->pluck('photo')->values()->all(),
        ];

        RecordOrderEditHistoryAction::execute($this->order, 'edit_notes_photos', 'ghi chú & ảnh đính kèm', $before, $after, 'sửa ghi chú và ảnh đính kèm');

        $this->fillForms();
        $this->newPhotos = [];
        $this->deletedPhotoIds = [];
        $this->dispatch('order-history-updated');

        Flux::modal('edit-notes-photos')->close();
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật ghi chú và ảnh đính kèm.', variant: 'success');
    }

    protected function storeNewPhotos(): void
    {
        if ($this->newPhotos === []) {
            return;
        }

        $uploadDir = public_path('uploads'.DIRECTORY_SEPARATOR.'order');
        File::ensureDirectoryExists($uploadDir);

        foreach ($this->newPhotos as $photo) {
            if (! is_object($photo) || ! method_exists($photo, 'getRealPath')) {
                continue;
            }

            $filename = $this->makePhotoFilename($photo);
            $targetPath = $uploadDir.DIRECTORY_SEPARATOR.$filename;

            if (! copy($photo->getRealPath(), $targetPath)) {
                continue;
            }

            OrderPhoto::create([
                'id_order' => $this->order->id,
                'photo' => $filename,
            ]);
        }
    }

    protected function makePhotoFilename(object $file): string
    {
        $originalName = method_exists($file, 'getClientOriginalName')
            ? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
            : 'order-photo';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $name = Str::slug($originalName) ?: 'order-photo';

        return $name.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(8)).'.'.$extension;
    }

    public function fileSize($photo): string
    {
        if (! is_object($photo) || ! method_exists($photo, 'getSize')) {
            return '';
        }

        $bytes = (int) $photo->getSize();

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format($bytes / 1024, 0).' KB';
    }

    public function imageDimensions($photo): string
    {
        if (! is_object($photo) || ! method_exists($photo, 'getRealPath')) {
            return '';
        }

        $size = @getimagesize($photo->getRealPath());

        return $size ? $size[0].'x'.$size[1].'px' : '';
    }

    public function storedPhotoFileSize(?string $filename): string
    {
        if (! $filename) {
            return '';
        }

        $path = public_path('uploads'.DIRECTORY_SEPARATOR.'order'.DIRECTORY_SEPARATOR.$filename);

        if (! File::isFile($path)) {
            return '';
        }

        $bytes = File::size($path);

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format($bytes / 1024, 0).' KB';
    }

    public function storedPhotoDimensions(?string $filename): string
    {
        if (! $filename) {
            return '';
        }

        $path = public_path('uploads'.DIRECTORY_SEPARATOR.'order'.DIRECTORY_SEPARATOR.$filename);
        $size = File::isFile($path) ? @getimagesize($path) : false;

        return $size ? $size[0].'x'.$size[1].'px' : '';
    }

    public function photoUrl(?string $filename): string
    {
        return $filename ? asset('uploads/order/'.$filename) : '';
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

    public function optionName(string $type, mixed $id, mixed $fallback = '—'): string
    {
        if (blank($id)) {
            return (string) $fallback;
        }

        $options = $this->optionsFor($type);

        return (string) ($options[$id] ?? $options[(string) $id] ?? $fallback);
    }

    public function optionNames(string $type, mixed $ids): array
    {
        return collect((array) $ids)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => $this->optionName($type, $id, null))
            ->filter()
            ->values()
            ->all();
    }

    public function render()
    {
        return $this->view();
    }
};

?>
<div>
    <div class="grid gap-5 xl:grid-cols-3">
        <section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
            <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900 uppercase">Người gửi</h2>
                    <p class="text-xs text-neutral-500">Thông tin người gửi</p>
                </div>
                @if($this->canEditSenderReceiver())
                <flux:modal.trigger name="edit-sender">
                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Sửa người gửi">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                        </svg>
                    </button>
                </flux:modal.trigger>
                @endif
            </div>
            <div class="space-y-4 p-5">
                <div>
                    <p class="text-base font-semibold text-neutral-900">{{ $this->value($order->sender, 'company') }}</p>
                    <p class="text-sm text-neutral-500">{{ $this->value($order->sender, 'fullname') }}</p>
                </div>
                <div class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                    <div><p class="text-xs text-neutral-400">Điện thoại</p><p class="font-medium text-neutral-700">{{ $this->value($order->sender, 'phone') }}</p></div>
                    <div><p class="text-xs text-neutral-400">Email</p><p class="font-medium text-neutral-700 break-words">{{ $this->value($order->sender, 'email') }}</p></div>
                    <div class="sm:col-span-2 xl:col-span-1"><p class="text-xs text-neutral-400">Tỉnh / phường / quốc gia</p><p class="font-medium text-neutral-700">{{ $this->senderLocation() }}</p></div>
                    <div class="sm:col-span-2 xl:col-span-1"><p class="text-xs text-neutral-400">Địa chỉ</p><p class="font-medium text-neutral-700">{{ $this->value($order->sender, 'address') }}</p></div>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
            <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900 uppercase">Người nhận</h2>
                    <p class="text-xs text-neutral-500">Thông tin người nhận</p>
                </div>
                @if($this->canEditSenderReceiver())
                <flux:modal.trigger name="edit-receiver">
                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Sửa người nhận">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                        </svg>
                    </button>
                </flux:modal.trigger>
                @endif
            </div>
            <div class="space-y-4 p-5">
                <div>
                    <p class="text-base font-semibold text-neutral-900">{{ $this->value($order->receiver, 'company') }}</p>
                    <p class="text-sm text-neutral-500">{{ $this->value($order->receiver, 'fullname', $this->value($order->receiver, 'tenlienhe')) }}</p>
                </div>
                <div class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                    <div><p class="text-xs text-neutral-400">Điện thoại</p><p class="font-medium text-neutral-700">{{ $this->value($order->receiver, 'phone') }}</p></div>
                    <div><p class="text-xs text-neutral-400">Postcode</p><p class="font-medium text-neutral-700">{{ $this->value($order->receiver, 'postcode') }}</p></div>
                    <div><p class="text-xs text-neutral-400">City / State / Quốc gia</p><p class="font-medium text-neutral-700">{{ $this->receiverLocation() }}</p></div>
                    <div class="sm:col-span-2 xl:col-span-1"><p class="text-xs text-neutral-400">Địa chỉ</p><p class="font-medium text-neutral-700">{{ $this->value($order->receiver, 'address') }}</p></div>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-neutral-200 bg-white shadow-xs xl:col-span-2">
            <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900 uppercase">Ghi chú đơn hàng & ảnh đính kèm</h2>
                    <p class="text-xs text-neutral-500">{{ $order->photos->count() }} ảnh đính kèm</p>
                </div>
                @if($this->canEditNotesAndPhotos())
                <flux:modal.trigger name="edit-notes-photos">
                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Sửa ghi chú và ảnh đính kèm">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                        </svg>
                    </button>
                </flux:modal.trigger>
                @endif
            </div>
            <div class="space-y-4 p-5">
                <div>
                    <p class="text-xs text-neutral-400">Ghi chú đơn hàng</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-700">{{ $order->ghichu ?: 'Chưa có ghi chú' }}</p>
                </div>
                <div>
                    <p class="text-xs text-neutral-400">Ảnh đính kèm</p>
                    @if($order->photos->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($order->photos as $photo)
                            <a href="{{ $this->photoUrl($photo->photo) }}" data-fancybox="order-notes-photos" data-caption="{{ $photo->photo }}" class="group h-14 w-14 overflow-hidden rounded-md border border-neutral-200 bg-neutral-50">
                                <img src="{{ $this->photoUrl($photo->photo) }}" alt="Ảnh đính kèm" class="h-full w-full object-cover transition group-hover:scale-105">
                            </a>
                        @endforeach
                        </div>
                    @else
                        <p class="mt-2 text-sm text-neutral-500">Chưa có ảnh đính kèm</p>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-neutral-200 bg-white shadow-xs xl:col-start-3 xl:row-span-2 xl:row-start-1">
            <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900 uppercase">Dịch vụ & phụ trách</h2>
                    <p class="text-xs text-neutral-500">Thông tin dịch vụ và người phụ trách</p>
                </div>
                @if($this->canEditService())
                <flux:modal.trigger name="edit-service">
                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Sửa dịch vụ">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                        </svg>
                    </button>
                </flux:modal.trigger>
                @endif
            </div>
            @php
                $service = $order->service ?? [];
                $addonServices = $this->optionNames('dichvudikem', data_get($service, 'dichvudikem', []));
            @endphp
            <div class="space-y-4 p-5">
                <div class="space-y-2">
                    <div>
                        <p class="text-xs text-neutral-400">Tên dịch vụ / Chi tiết dịch vụ</p>
                        <p class="font-medium text-neutral-800">
                            {{ $order->dichvu?->namevi ?: '—' }}
                            <span class="text-neutral-300">/</span>
                            {{ $order->chiTietDichVu?->namevi ?: '—' }}
                        </p>
                    </div>
                    @if($addonServices !== [])
                        <div>
                            <p class="text-xs text-neutral-400">Dịch vụ đi kèm</p>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                @foreach($addonServices as $addonService)
                                    <span class="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-700">{{ $addonService }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <div class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                    <div><p class="text-xs text-neutral-400">Sản phẩm</p><p class="font-medium text-neutral-700">{{ $this->serviceValue('tensanpham') }}</p></div>
                    <div><p class="text-xs text-neutral-400">Chi nhánh nhận</p><p class="font-medium text-neutral-700">{{ $order->chiNhanhNhanHang?->namevi ?: '—' }}</p></div>
                    <div><p class="text-xs text-neutral-400">Hình thức gửi hàng</p><p class="font-medium text-neutral-700">{{ $this->optionName('hinhthucgui', data_get($service, 'hinhthucguihang')) }}</p></div>
                    <div>
                        <p class="text-xs text-neutral-400">Loại bưu gửi / Lý do gửi hàng / Delivery Term / Tình trạng đơn</p>
                        <p class="font-medium text-neutral-700">
                            {{ $this->optionName('loaibuugui', data_get($service, 'loaibuugui')) }}
                            <span class="text-neutral-300">/</span>
                            {{ $this->optionName('lydoguihang', data_get($service, 'lydoguihang')) }}
                            <span class="text-neutral-300">/</span>
                            {{ $this->optionName('deliveryterm', data_get($service, 'deliveryterm')) }}
                            <span class="text-neutral-300">/</span>
                            {{ $this->optionName('tinhtrangdon', data_get($service, 'tinhtrangdon')) }}
                        </p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs text-neutral-400">CS</p>
                            @if($this->canAssignCs())
                                <flux:modal.trigger name="edit-cs">
                                    <button type="button" class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-neutral-200 text-neutral-400 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Sửa CS phụ trách">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" /></svg>
                                    </button>
                                </flux:modal.trigger>
                            @endif
                        </div>
                        <p class="font-medium text-neutral-700">{{ $order->cs?->fullname ?: $order->cs?->username ?: '—' }}</p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs text-neutral-400">OPS</p>
                            @if($this->canAssignOps())
                                <flux:modal.trigger name="edit-ops">
                                    <button type="button" class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-neutral-200 text-neutral-400 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Sửa OPS phụ trách">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" /></svg>
                                    </button>
                                </flux:modal.trigger>
                            @endif
                        </div>
                        <p class="font-medium text-neutral-700">{{ $order->ops?->fullname ?: $order->ops?->username ?: '—' }}</p>
                    </div>
                </div>
            </div>
        </section>

       
    </div>
    <flux:modal name="edit-notes-photos" class="w-full max-w-4xl">
        <form wire:submit="saveNotesAndPhotos" class="space-y-6">
                <div>
                    <flux:heading size="lg">Sửa ghi chú đơn hàng & ảnh đính kèm</flux:heading>
                    <flux:subheading>Cập nhật ghi chú và tối đa 5 ảnh đính kèm cho đơn hàng hiện tại.</flux:subheading>
                </div>

                <flux:field>
                    <flux:label>Ghi chú đơn hàng</flux:label>
                    <flux:textarea wire:model="noteForm" rows="5" />
                    @error('noteForm') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Thêm ảnh mới</flux:label>
                    <div class="space-y-3">
                        <label
                            for="edit-order-photos"
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

                        <input id="edit-order-photos" type="file" class="hidden" wire:model="newPhotos" accept="image/*" multiple>

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
                    </div>
                </flux:field>

                @if($newPhotos !== [])
                    <div class="divide-y divide-neutral-100 overflow-hidden rounded-lg border border-neutral-200 bg-white">
                        @foreach($newPhotos as $index => $photo)
                            @if(is_object($photo))
                                <div class="flex items-center gap-3 p-3">
                                    <div class="h-16 w-16 flex-none overflow-hidden rounded-md border border-neutral-200 bg-neutral-100">
                                        @if(method_exists($photo, 'temporaryUrl'))
                                            <img src="{{ $photo->temporaryUrl() }}" alt="Ảnh mới {{ $index + 1 }}" class="h-full w-full object-cover">
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-neutral-800">
                                            {{ method_exists($photo, 'getClientOriginalName') ? $photo->getClientOriginalName() : 'Ảnh đính kèm' }}
                                        </p>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-neutral-500">
                                            <span>{{ $this->fileSize($photo) }}</span>
                                            <span>{{ $this->imageDimensions($photo) }}</span>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="removeNewPhoto({{ $index }})"
                                        class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-600 hover:border-red-500 hover:bg-red-500 hover:text-white"
                                        aria-label="Xóa ảnh mới"
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

                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-neutral-800">Ảnh hiện có</p>
                        <p class="text-xs text-neutral-500">Tối đa 5 ảnh</p>
                    </div>

                    @php
                        $visiblePhotos = $order->photos->reject(fn ($photo) => in_array($photo->id, $deletedPhotoIds, true));
                    @endphp

                    @if($visiblePhotos->isNotEmpty())
                        <div class="divide-y divide-neutral-100 overflow-hidden rounded-lg border border-neutral-200 bg-white">
                            @foreach($visiblePhotos as $photo)
                                <div class="flex items-center gap-3 p-3">
                                    <div class="h-16 w-16 flex-none overflow-hidden rounded-md border border-neutral-200 bg-neutral-100">
                                        <img src="{{ $this->photoUrl($photo->photo) }}" alt="Ảnh đính kèm" class="h-full w-full object-cover">
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-neutral-800">
                                            {{ $photo->photo ?: 'Ảnh đính kèm' }}
                                        </p>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-neutral-500">
                                            <span>{{ $this->storedPhotoFileSize($photo->photo) }}</span>
                                            <span>{{ $this->storedPhotoDimensions($photo->photo) }}</span>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="removeExistingPhoto({{ $photo->id }})"
                                        class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-600 hover:border-red-500 hover:bg-red-500 hover:text-white"
                                        aria-label="Xóa ảnh"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="rounded-lg bg-neutral-50 px-3 py-2 text-sm text-neutral-500">Chưa có ảnh hiện có</p>
                    @endif
                </div>

                <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost" wire:click="resetNotesPhotoForm">Hủy</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Lưu</flux:button>
                </div>
        </form>
    </flux:modal>

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

        <flux:modal name="edit-cs" class="w-full max-w-md">
            <form wire:submit="saveCs" class="space-y-6">
                <div>
                    <flux:heading size="lg">CS phụ trách</flux:heading>
                    <flux:subheading>Chọn nhân viên CS phụ trách đơn hàng này.</flux:subheading>
                </div>

                <flux:field>
                    <flux:label>CS phụ trách</flux:label>
                    <flux:select wire:model="assignCsId">
                        <flux:select.option value="">— Chưa phân công —</flux:select.option>
                        @foreach($this->csOptions() as $cs)
                            <flux:select.option value="{{ $cs->id }}">{{ $cs->fullname ?: $cs->username }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Hủy</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Lưu</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="edit-ops" class="w-full max-w-md">
            <form wire:submit="saveOps" class="space-y-6">
                <div>
                    <flux:heading size="lg">OPS phụ trách</flux:heading>
                    <flux:subheading>Chọn nhân viên OPS phụ trách đơn hàng này.</flux:subheading>
                </div>

                <flux:field>
                    <flux:label>OPS phụ trách</flux:label>
                    <flux:select wire:model="assignOpsId">
                        @if(auth()->user()->hasAnyRole(['admin', 'manager']))
                            <flux:select.option value="">— Chưa phân công —</flux:select.option>
                        @else
                            <flux:select.option value="">— Chọn OPS phụ trách —</flux:select.option>
                        @endif
                        @foreach($this->opsOptions() as $ops)
                            <flux:select.option value="{{ $ops->id }}">{{ $ops->fullname ?: $ops->username }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @unless(auth()->user()->hasAnyRole(['admin', 'manager']))
                        <flux:description>Chỉ được chọn khi đơn chưa có OPS phụ trách.</flux:description>
                    @endunless
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Hủy</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Lưu</flux:button>
                </div>
            </form>
        </flux:modal>
</div>
