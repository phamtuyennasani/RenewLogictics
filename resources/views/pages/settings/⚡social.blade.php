<?php

use Livewire\Component;
use App\Models\Setting;
use Flux\Flux;

new class extends Component {
    public string $facebook = '';
    public string $zalo = '';
    public string $hotline = '';
    public string $instagram = '';
    public string $youtube = '';
    public string $tiktok = '';
    public string $telegram = '';
    public string $twitter = '';
    public bool $isSaving = false;

    public function mount()
    {
        $setting = Setting::first();
        $options = $setting->options ?? [];

        $this->facebook = $options['social_facebook'] ?? '';
        $this->zalo = $options['social_zalo'] ?? '';
        $this->hotline = $options['social_hotline'] ?? '';
        $this->instagram = $options['social_instagram'] ?? '';
        $this->youtube = $options['social_youtube'] ?? '';
        $this->tiktok = $options['social_tiktok'] ?? '';
        $this->telegram = $options['social_telegram'] ?? '';
        $this->twitter = $options['social_twitter'] ?? '';
    }

    public function save()
    {
        $this->validate([
            'facebook' => 'nullable|url|max:500',
            'zalo' => 'nullable|string|max:500',
            'hotline' => 'nullable|string|max:20',
            'instagram' => 'nullable|url|max:500',
            'youtube' => 'nullable|url|max:500',
            'tiktok' => 'nullable|url|max:500',
            'telegram' => 'nullable|string|max:500',
            'twitter' => 'nullable|url|max:500',
        ]);

        $this->isSaving = true;

        $setting = Setting::firstOrCreate([], ['namevi' => 'Cấu hình hệ thống', 'options' => []]);
        $options = $setting->options ?? [];

        $options['social_facebook'] = $this->facebook;
        $options['social_zalo'] = $this->zalo;
        $options['social_hotline'] = $this->hotline;
        $options['social_instagram'] = $this->instagram;
        $options['social_youtube'] = $this->youtube;
        $options['social_tiktok'] = $this->tiktok;
        $options['social_telegram'] = $this->telegram;
        $options['social_twitter'] = $this->twitter;

        $setting->update(['options' => $options]);

        $this->isSaving = false;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: 'Cập nhật mạng xã hội thành công!',
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
        <h1 class="text-2xl font-bold text-neutral-900">Mạng xã hội</h1>
    </div>

    {{-- FORM --}}
    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">

        <div class="px-6 py-5 border-b border-neutral-100">
            <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
                Liên kết mạng xã hội
            </h2>
            <p class="text-xs text-neutral-500 mt-1">Cấu hình các liên kết mạng xã hội hiển thị trên hệ thống.</p>
        </div>

        <div class="p-6 space-y-5">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <flux:field>
                    <flux:label>
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            Facebook
                        </span>
                    </flux:label>
                    <flux:input wire:model="facebook" placeholder="https://facebook.com/company" />
                    @error('facebook') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>

                <flux:field>
                    <flux:label>
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.692-1.653-1.123-2.678-1.799-1.185-.781-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.139-5.062 3.345-.479.329-.913.489-1.302.481-.428-.009-1.252-.242-1.865-.442-.751-.244-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.015 3.333-1.386 4.025-1.627 4.477-1.635.099-.002.321.023.465.141.12.098.153.229.168.326.016.097.036.317.02.489z"/></svg>
                            Telegram
                        </span>
                    </flux:label>
                    <flux:input wire:model="telegram" placeholder="https://t.me/company" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <flux:field>
                    <flux:label>
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="10" font-weight="bold">Z</text></svg>
                            Zalo
                        </span>
                    </flux:label>
                    <flux:input wire:model="zalo" placeholder="https://zalo.me/0901234567" />
                </flux:field>

                <flux:field>
                    <flux:label>
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            Hotline
                        </span>
                    </flux:label>
                    <flux:input wire:model="hotline" placeholder="0901234567" />
                </flux:field>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <flux:field>
                    <flux:label>
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-pink-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                            Instagram
                        </span>
                    </flux:label>
                    <flux:input wire:model="instagram" placeholder="https://instagram.com/company" />
                    @error('instagram') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>

                <flux:field>
                    <flux:label>
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            YouTube
                        </span>
                    </flux:label>
                    <flux:input wire:model="youtube" placeholder="https://youtube.com/@company" />
                    @error('youtube') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <flux:field>
                    <flux:label>
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-neutral-900" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61.04 3.91.18.59.07 1.17.18 1.74.33.57.15 1.12.35 1.64.6.52.25 1.01.55 1.46.9.45.35.86.74 1.22 1.17.36.43.67.9.92 1.4.25.5.44 1.03.57 1.58.13.55.2 1.12.22 1.69.02.57-.01 1.14-.08 1.7-.07.56-.19 1.11-.35 1.64-.16.53-.37 1.04-.62 1.52-.25.48-.55.93-.89 1.34-.34.41-.72.78-1.14 1.11-.42.33-.87.62-1.35.86-.48.24-.99.43-1.51.57-.52.14-1.06.23-1.6.27-.54.04-1.09.03-1.63-.02-.54-.05-1.07-.15-1.58-.3-.51-.15-1-.35-1.46-.6-.46-.25-.89-.55-1.29-.89-.4-.34-.76-.72-1.08-1.13-.32-.41-.6-.86-.83-1.33-.23-.47-.41-.97-.54-1.48-.13-.51-.21-1.04-.24-1.57-.03-.53-.01-1.07.05-1.6.06-.53.17-1.05.33-1.55.16-.5.36-.98.61-1.43.25-.45.54-.87.87-1.26.33-.39.7-.74 1.1-1.05.4-.31.83-.58 1.29-.8.46-.22.94-.39 1.44-.51.5-.12 1.01-.19 1.53-.21zm-.13 2.03c-.96.03-1.9.18-2.79.46-.89.28-1.73.69-2.49 1.22-.76.53-1.43 1.17-1.99 1.9-.56.73-1.01 1.55-1.33 2.42-.32.87-.51 1.79-.56 2.72-.05.93.04 1.87.26 2.78.22.91.57 1.78 1.04 2.58.47.8 1.06 1.53 1.74 2.16.68.63 1.46 1.16 2.3 1.56.84.4 1.74.67 2.67.8.93.13 1.88.12 2.81-.02.93-.14 1.83-.42 2.67-.82.84-.4 1.61-.93 2.28-1.56.67-.63 1.24-1.36 1.7-2.16.46-.8.8-1.67 1.02-2.58.22-.91.3-1.85.25-2.78-.05-.93-.24-1.85-.56-2.72-.32-.87-.77-1.69-1.33-2.42-.56-.73-1.23-1.37-1.99-1.9-.76-.53-1.6-.94-2.49-1.22-.89-.28-1.83-.43-2.79-.46z"/><path d="M9.04 16.87V7.13l8.43 4.87-8.43 4.87z"/></svg>
                            TikTok
                        </span>
                    </flux:label>
                    <flux:input wire:model="tiktok" placeholder="https://tiktok.com/@company" />
                    @error('tiktok') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>

                <flux:field>
                    <flux:label>
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-neutral-900" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            X (Twitter)
                        </span>
                    </flux:label>
                    <flux:input wire:model="twitter" placeholder="https://x.com/company" />
                    @error('twitter') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
            </div>

        </div>

        {{-- ACTION BUTTONS --}}
        <div class="px-6 py-4 border-t border-neutral-100 flex items-center justify-end bg-neutral-50/50">
            <button
                type="button"
                wire:click="save"
                wire:loading.attr="disabled"
                class="px-6 py-2.5 text-sm font-medium text-white rounded-xl
                       transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5
                       flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed
                       disabled:hover:shadow-none disabled:hover:translate-y-0"
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
                    Lưu cấu hình
                @endif
            </button>
        </div>
    </div>
</div>
