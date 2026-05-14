<?php

use Livewire\Attributes\Modelable;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Modelable]
    public array $ghichu = [
        'note' => '',
        'photos' => [],
    ];

    public array $newPhotos = [];

    public function updatedNewPhotos(): void
    {
        $this->validate([
            'newPhotos' => 'nullable|array|max:5',
            'newPhotos.*' => 'image|max:20480',
        ]);

        $currentPhotos = $this->ghichu['photos'] ?? [];
        $remainingSlots = max(0, 5 - count($currentPhotos));
        $this->ghichu['photos'] = array_merge($currentPhotos, array_slice($this->newPhotos, 0, $remainingSlots));
        $this->newPhotos = [];
    }

    public function removePhoto(int $index): void
    {
        unset($this->ghichu['photos'][$index]);
        $this->ghichu['photos'] = array_values($this->ghichu['photos'] ?? []);
    }

    public function fileSize($photo): string
    {
        if (! is_object($photo) || ! method_exists($photo, 'getSize')) {
            return '';
        }

        $bytes = (int) $photo->getSize();

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        return number_format($bytes / 1024, 1).' KB';
    }

    public function imageDimensions($photo): string
    {
        if (! is_object($photo) || ! method_exists($photo, 'getRealPath')) {
            return '';
        }

        $size = @getimagesize($photo->getRealPath());

        if (! $size) {
            return '';
        }

        return $size[0].' x '.$size[1].' px';
    }
};
?>

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
                                        wire:click="removePhoto({{ $index }})"
                                        class="flex h-8 w-8 flex-none items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-600 hover:border-blue-500 hover:bg-blue-500 hover:text-white"
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
