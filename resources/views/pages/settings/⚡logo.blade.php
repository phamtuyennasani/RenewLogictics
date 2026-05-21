<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Setting;
use Flux\Flux;

new class extends Component {
    use WithFileUploads;

    public $logo = null;
    public ?string $currentLogo = null;
    public bool $isSaving = false;

    public function mount()
    {
        $setting = Setting::first();
        $options = $setting->options ?? [];
        $this->currentLogo = $options['logo_path'] ?? null;
    }

    public function updatedLogo()
    {
        $this->validate([
            'logo' => 'image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);
    }

    public function save()
    {
        $this->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        $this->isSaving = true;

        $path = $this->logo->store('settings', 'public');

        $setting = Setting::firstOrCreate([], ['namevi' => 'Cấu hình hệ thống', 'options' => []]);
        $options = $setting->options ?? [];
        $options['logo_path'] = '/storage/' . $path;
        $setting->update(['options' => $options]);

        $this->currentLogo = '/storage/' . $path;
        $this->logo = null;
        $this->isSaving = false;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: 'Cập nhật logo thành công!',
            variant: 'success'
        );
    }

    public function removeLogo()
    {
        $setting = Setting::first();
        if ($setting) {
            $options = $setting->options ?? [];
            $options['logo_path'] = null;
            $setting->update(['options' => $options]);
        }

        $this->currentLogo = null;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: 'Đã xóa logo!',
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
        <h1 class="text-2xl font-bold text-neutral-900">Logo hệ thống</h1>
    </div>

    {{-- FORM --}}
    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">

        <div class="px-6 py-5 border-b border-neutral-100">
            <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Upload Logo
            </h2>
            <p class="text-xs text-neutral-500 mt-1">Định dạng: PNG, JPG, SVG, WebP. Tối đa 2MB.</p>
        </div>

        <div class="p-6 space-y-6">

            {{-- CURRENT LOGO --}}
            @if ($currentLogo)
                <div class="space-y-3">
                    <p class="text-sm font-medium text-neutral-700">Logo hiện tại</p>
                    <div class="flex items-center gap-4">
                        <div class="w-32 h-32 rounded-xl border border-neutral-200 bg-neutral-50 flex items-center justify-center p-3">
                            <img src="{{ $currentLogo }}" alt="Logo" class="max-w-full max-h-full object-contain">
                        </div>
                        <button
                            wire:click="removeLogo"
                            wire:confirm="Bạn có chắc muốn xóa logo hiện tại?"
                            class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-xl
                                   hover:bg-red-100 transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Xóa logo
                        </button>
                    </div>
                </div>
            @endif

            {{-- UPLOAD --}}
            <div class="space-y-3">
                <p class="text-sm font-medium text-neutral-700">{{ $currentLogo ? 'Thay đổi logo' : 'Upload logo mới' }}</p>

                <div x-data="{ isDragging: false }"
                     x-on:dragover.prevent="isDragging = true"
                     x-on:dragleave.prevent="isDragging = false"
                     x-on:drop.prevent="isDragging = false"
                     class="relative">
                    <label
                        class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed rounded-2xl cursor-pointer transition-all duration-200"
                        :class="isDragging ? 'border-primary-400 bg-primary-50' : 'border-neutral-300 bg-neutral-50 hover:border-primary-400 hover:bg-primary-50/50'">
                        <input type="file" wire:model="logo" class="hidden" accept="image/*">

                        @if ($logo)
                            <div class="flex flex-col items-center gap-3">
                                <img src="{{ $logo->temporaryUrl() }}" alt="Preview" class="max-h-24 rounded-lg">
                                <p class="text-sm text-primary-600 font-medium">{{ $logo->getClientOriginalName() }}</p>
                            </div>
                        @else
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-10 h-10 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="text-sm text-neutral-600">Kéo thả hoặc <span class="text-primary-600 font-medium">chọn file</span></p>
                                <p class="text-xs text-neutral-400">PNG, JPG, SVG, WebP — Tối đa 2MB</p>
                            </div>
                        @endif
                    </label>
                </div>

                @error('logo') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
            </div>

        </div>

        {{-- ACTION BUTTONS --}}
        @if ($logo)
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
                        Lưu logo
                    @endif
                </button>
            </div>
        @endif
    </div>
</div>
