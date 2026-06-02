<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Setting;
use Flux\Flux;

new class extends Component {
    use WithFileUploads;

    public $favicon = null;
    public ?string $currentFavicon = null;
    public bool $isSaving = false;

    public function mount()
    {
        abort_unless(\Gate::allows('settings.admin'), 403);
        $setting = Setting::first();
        $options = $setting->options ?? [];
        $this->currentFavicon = $options['favicon_path'] ?? null;
    }

    public function updatedFavicon()
    {
        $this->validate([
            'favicon' => 'image|mimes:png,ico,svg|max:512',
        ]);
    }

    public function save()
    {
        $this->validate([
            'favicon' => 'required|image|mimes:png,ico,svg|max:512',
        ]);

        $this->isSaving = true;

        $path = $this->favicon->store('settings', 'public');

        $setting = Setting::firstOrCreate([], ['namevi' => 'Cấu hình hệ thống', 'options' => []]);
        $options = $setting->options ?? [];
        $options['favicon_path'] = '/storage/' . $path;
        $setting->update(['options' => $options]);

        $this->currentFavicon = '/storage/' . $path;
        $this->favicon = null;
        $this->isSaving = false;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: 'Cập nhật favicon thành công!',
            variant: 'success'
        );
    }

    public function removeFavicon()
    {
        $setting = Setting::first();
        if ($setting) {
            $options = $setting->options ?? [];
            $options['favicon_path'] = null;
            $setting->update(['options' => $options]);
        }

        $this->currentFavicon = null;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: 'Đã xóa favicon!',
            variant: 'success'
        );
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
$primaryHex = config('theme.primary.hex', '#3b82f6');
$accentHex  = config('theme.accent.hex', '#0ea5e9');
$gradientStyle = "background: linear-gradient(135deg, {$primaryHex}, {$accentHex});";
@endphp

<div class="mx-auto space-y-6">

    {{-- PAGE HEADER --}}
    <div>
        <p class="text-sm text-neutral-500">Cấu hình</p>
        <h1 class="text-2xl font-bold text-neutral-900">Favicon</h1>
    </div>

    {{-- FORM --}}
    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">

        <div class="px-6 py-5 border-b border-neutral-100">
            <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
                Upload Favicon
            </h2>
            <p class="text-xs text-neutral-500 mt-1">Icon hiển thị trên tab trình duyệt. Định dạng: PNG, ICO, SVG. Tối đa 512KB. Kích thước khuyến nghị: 32x32 hoặc 64x64 px.</p>
        </div>

        <div class="p-6 space-y-6">

            {{-- CURRENT FAVICON --}}
            @if ($currentFavicon)
                <div class="space-y-3">
                    <p class="text-sm font-medium text-neutral-700">Favicon hiện tại</p>
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-xl border border-neutral-200 bg-neutral-50 flex items-center justify-center p-3">
                            <img src="{{ $currentFavicon }}" alt="Favicon" class="max-w-full max-h-full object-contain">
                        </div>
                        <button
                            wire:click="removeFavicon"
                            wire:confirm="Bạn có chắc muốn xóa favicon hiện tại?"
                            class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-xl
                                   hover:bg-red-100 transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Xóa favicon
                        </button>
                    </div>
                </div>
            @endif

            {{-- UPLOAD --}}
            <div class="space-y-3">
                <p class="text-sm font-medium text-neutral-700">{{ $currentFavicon ? 'Thay đổi favicon' : 'Upload favicon mới' }}</p>

                <div x-data="{ isDragging: false }"
                     x-on:dragover.prevent="isDragging = true"
                     x-on:dragleave.prevent="isDragging = false"
                     x-on:drop.prevent="isDragging = false"
                     class="relative">
                    <label
                        class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-2xl cursor-pointer transition-all duration-200"
                        :class="isDragging ? 'border-primary-400 bg-primary-50' : 'border-neutral-300 bg-neutral-50 hover:border-primary-400 hover:bg-primary-50/50'">
                        <input type="file" wire:model="favicon" class="hidden" accept=".png,.ico,.svg">

                        @if ($favicon)
                            <div class="flex flex-col items-center gap-3">
                                <img src="{{ $favicon->temporaryUrl() }}" alt="Preview" class="max-h-16 rounded-lg">
                                <p class="text-sm text-primary-600 font-medium">{{ $favicon->getClientOriginalName() }}</p>
                            </div>
                        @else
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="text-sm text-neutral-600">Kéo thả hoặc <span class="text-primary-600 font-medium">chọn file</span></p>
                                <p class="text-xs text-neutral-400">PNG, ICO, SVG — Tối đa 512KB</p>
                            </div>
                        @endif
                    </label>
                </div>

                @error('favicon') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
            </div>

        </div>

        {{-- ACTION BUTTONS --}}
        @if ($favicon)
            <div class="px-6 py-4 border-t border-neutral-100 flex items-center justify-end bg-neutral-50/50">
                <button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    class="px-6 py-2.5 text-sm font-medium text-white rounded-xl
                           transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5
                           flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed"
                    style="{{ $gradientStyle }}">
                    @if ($isSaving)
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Đang lưu...
                    @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        Lưu favicon
                    @endif
                </button>
            </div>
        @endif
    </div>
</div>
