<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\News;

new class extends Component {
    use WithFileUploads;

    public $type;
    public $itemId;

    public string $namevi = '';
    public string $slug = '';
    public string $contentvi = '';
    public array $options2 = [];
    public array $files = [];
    public array $savedImages = [];
    public array $id_country = [];
    public array $id_dichvu = [];
    public bool $noibo = false;
    public bool $khachhang = false;

    // Status
    public bool $isSaving = false;

    public function mount($type = null, $id = null)
    {
        $this->type = $type;
        $this->itemId = $id;

        if ($id) {
            $item = News::findOrFail($id);
            $this->namevi = $item->namevi ?? '';
            $this->slug = $item->slug ?? '';
            $this->contentvi = $item->contentvi ?? '';
            $this->options2 = $item->options2 ?? [];
            $this->noibo = $item->noibo == 1;
            $this->khachhang = $item->khachhang == 1;

            // Load saved images
            if (!empty($config['images'])) {
                foreach (array_keys($config['images']) as $k) {
                    $this->savedImages[$k] = $item->{$k} ?? '';
                }
            }
        }
    }

    public function save()
    {
        $this->isSaving = true;

        $this->validate([
            'namevi' => 'required|string|max:255',
        ], [
            'namevi.required' => 'Tiêu đề là bắt buộc.',
        ]);

        $data = [
            'type' => $this->type,
            'namevi' => $this->namevi,
            'slug' => \NINACORE\Core\Support\Str::slug($this->namevi),
            'contentvi' => $this->contentvi,
            'options2' => $this->options2,
            'noibo' => $this->noibo ? 1 : 0,
            'khachhang' => $this->khachhang ? 1 : 0,
        ];

        if ($this->itemId) {
            $item = News::findOrFail($this->itemId);
            $item->update($data);
            $this->dispatch('toast', ['type' => 'success', 'message' => 'Cập nhật thành công!']);
        } else {
            $data['numb'] = (News::where('type', $this->type)->max('numb') ?? 0) + 1;
            News::create($data);
            $this->dispatch('toast', ['type' => 'success', 'message' => 'Thêm mới thành công!']);
        }

        $this->isSaving = false;
    }

    public function goBack()
    {
        return redirect()->route('dichvu.index', ['type' => $this->type]);
    }

    public function render()
    {
        return $this->view();
    }
};

?>

<div class="max-w-4xl mx-auto space-y-6">

    {{-- ======================= PAGE HEADER ======================= --}}
    <div class="flex items-center gap-3">
        <button
            wire:click="goBack"
            class="p-2 rounded-xl text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <div>
            <p class="text-sm text-neutral-500 capitalize">Dữ liệu / {{ $config['title_main'] ?? '' }}</p>
            <h1 class="text-2xl font-bold text-neutral-900">
                {{ $itemId ? 'Chỉnh sửa' : 'Thêm mới' }} {{ $config['title_main'] ?? '' }}
            </h1>
        </div>
    </div>

    {{-- ======================= MAIN FORM ======================= --}}
    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden shadow-sm">

        {{-- Section: Tiêu đề --}}
        <div class="px-6 py-5 border-b border-neutral-100">
            <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Thông tin cơ bản
            </h2>
        </div>
        <div class="p-6 space-y-5">

            {{-- Tiêu đề --}}
            <div>
                <label for="namevi" class="block text-sm font-medium text-neutral-700 mb-1.5">
                    Tiêu đề <span class="text-red-500">*</span>
                </label>
                <input
                    id="namevi"
                    type="text"
                    wire:model.live.debounce.300ms="namevi"
                    placeholder="Nhập tiêu đề..."
                    class="w-full px-4 py-2.5 text-sm border border-neutral-300 rounded-xl
                           focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                           placeholder:text-neutral-400 transition-all
                           {{ $errors->has('namevi') ? 'border-red-400 focus:ring-red-400 focus:border-red-400' : '' }}"
                >
                @if ($errors->has('namevi'))
                    <p class="mt-1.5 text-xs text-red-500 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $errors->first('namevi') }}
                    </p>
                @endif
            </div>

            {{-- Nội dung (nếu có config) --}}
            @if (!empty($config['content']))
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1.5">Nội dung</label>
                    <div class="relative">
                        <textarea
                            id="contentvi"
                            wire:model="contentvi"
                            rows="8"
                            placeholder="Nhập nội dung..."
                            class="w-full px-4 py-3 text-sm border border-neutral-300 rounded-xl
                                   focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                                   placeholder:text-neutral-400 transition-all resize-none"
                        ></textarea>
                    </div>
                </div>
            @endif

            {{-- Images (nếu có config) --}}
            @if (!empty($config['images']))
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-3">
                        Hình ảnh
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($config['images'] as $k => $v)
                            <div class="border border-neutral-200 rounded-xl p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-neutral-700">{{ $v['title'] }}</p>
                                    <span class="text-xs text-neutral-400">{{ $v['width'] }}×{{ $v['height'] }}px</span>
                                </div>

                                {{-- Current image preview --}}
                                @if (!empty($savedImages[$k]))
                                    <div class="relative group">
                                        <img
                                            src="{{ Storage::url($savedImages[$k]) }}"
                                            alt="{{ $v['title'] }}"
                                            class="w-full h-32 object-cover rounded-lg border border-neutral-100"
                                        >
                                        <button
                                            type="button"
                                            wire:click="removeImage('{{ $k }}')"
                                            class="absolute top-2 right-2 p-1 rounded-lg bg-red-500/80 text-white
                                                   opacity-0 group-hover:opacity-100 transition-opacity">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                @endif

                                {{-- Upload input --}}
                                <div
                                    x-data="{ uploading: false, progress: 0 }"
                                    x-on:livewire-upload-start="uploading = true"
                                    x-on:livewire-upload-finish="uploading = false"
                                    x-on:livewire-upload-error="uploading = false"
                                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                                    class="relative"
                                >
                                    <input
                                        type="file"
                                        id="file-{{ $k }}"
                                        wire:model="files.{{ $k }}"
                                        accept="image/*"
                                        class="hidden"
                                    >
                                    <label
                                        for="file-{{ $k }}"
                                        class="flex flex-col items-center justify-center gap-2 px-4 py-6 border-2 border-dashed
                                               border-neutral-300 rounded-xl cursor-pointer hover:border-primary-400
                                               hover:bg-primary-50/30 transition-all"
                                    >
                                        <svg class="w-8 h-8 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <div class="text-center">
                                            <p class="text-sm font-medium text-neutral-600">Tải lên hình ảnh</p>
                                            <p class="text-xs text-neutral-400 mt-0.5">PNG, JPG, WEBP tối đa 5MB</p>
                                        </div>
                                    </label>

                                    {{-- Upload progress --}}
                                    <div x-show="uploading" class="mt-2">
                                        <div class="w-full bg-neutral-200 rounded-full h-1.5 overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-300"
                                                 style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }});"
                                                 :style="`width: ${progress}%`"></div>
                                        </div>
                                    </div>

                                    {{-- Preview uploaded --}}
                                    @if ($files[$k] ?? null)
                                        <div class="mt-2">
                                            <img
                                                src="{{ $files[$k]->temporaryUrl() }}"
                                                alt="Preview"
                                                class="w-full h-32 object-cover rounded-lg border border-primary-200"
                                            >
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Options2 dynamic fields --}}
            @if (!empty($config['options2']))
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-3">
                        Thông tin bổ sung
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($config['options2'] as $k => $v)
                            <div>
                                <label for="opt2-{{ $k }}" class="block text-sm font-medium text-neutral-700 mb-1.5">
                                    {{ $v['title'] }}
                                    @if (!empty($v['required']))
                                        <span class="text-red-500">*</span>
                                    @endif
                                </label>
                                @if ($v['type-eml'] === 'text' || $v['type-eml'] === 'price')
                                    <input
                                        id="opt2-{{ $k }}"
                                        type="text"
                                        wire:model="options2.{{ $k }}"
                                        placeholder="{{ $v['title'] }}"
                                        class="w-full px-4 py-2.5 text-sm border border-neutral-300 rounded-xl
                                               focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                                               placeholder:text-neutral-400 transition-all"
                                        {{ $k === 'color' ? 'placeholder="#000000"' : '' }}
                                    >
                                @elseif($v['type-eml'] === 'number')
                                    <input
                                        id="opt2-{{ $k }}"
                                        type="number"
                                        wire:model="options2.{{ $k }}"
                                        placeholder="{{ $v['title'] }}"
                                        class="w-full px-4 py-2.5 text-sm border border-neutral-300 rounded-xl
                                               focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                                               placeholder:text-neutral-400 transition-all"
                                    >
                                @elseif($v['type-eml'] === 'textarea')
                                    <textarea
                                        id="opt2-{{ $k }}"
                                        wire:model="options2.{{ $k }}"
                                        rows="3"
                                        placeholder="{{ $v['title'] }}"
                                        class="w-full px-4 py-2.5 text-sm border border-neutral-300 rounded-xl
                                               focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                                               placeholder:text-neutral-400 transition-all resize-none"
                                    ></textarea>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Quốc gia (nếu có config) --}}
            @if (!empty($config['id_country']))
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1.5">Quốc gia</label>
                    <select
                        wire:model="id_country"
                        multiple
                        class="w-full px-4 py-2.5 text-sm border border-neutral-300 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        @foreach (\App\Models\News::where('type', 'country')->orderBy('namevi', 'asc')->get() as $country)
                            <option value="{{ $country->id }}">{{ $country->namevi }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Dịch vụ (nếu có config) --}}
            @if (!empty($config['id_dichvu']))
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1.5">Dịch vụ</label>
                    <select
                        wire:model="id_dichvu"
                        multiple
                        class="w-full px-4 py-2.5 text-sm border border-neutral-300 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        @foreach (\App\Models\News::where('type', 'dich-vu')->orderBy('namevi', 'asc')->get() as $dv)
                            <option value="{{ $dv->id }}">{{ $dv->namevi }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Checkboxes cho thông báo --}}
            @if ($type == 'thong-bao')
                <div class="space-y-3 pt-2">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            role="switch"
                            aria-checked="false"
                            wire:click="$toggle('noibo')"
                            class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors
                                   {{ $noibo ? 'shadow-sm' : 'bg-neutral-200' }}"
                            :class="{{ $noibo ? '' : '' }}"
                            style="background: {{ $noibo ? 'linear-gradient(135deg, ' . config('theme.primary.hex', '#3b82f6') . ', ' . config('theme.accent.hex', '#0ea5e9') . ')' : '#e5e7eb' }};">
                            <span
                                class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform
                                       {{ $noibo ? 'translate-x-4.5' : 'translate-x-0.5' }}"
                                style="transform: {{ $noibo ? 'translate-x-4.5px)' : 'translate-x-0.5px)' }};"></span>
                        </button>
                        <label class="text-sm font-medium text-neutral-700 cursor-pointer select-none">
                            Dành cho nội bộ
                        </label>
                    </div>
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            role="switch"
                            aria-checked="false"
                            wire:click="$toggle('khachhang')"
                            class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors
                                   {{ $khachhang ? 'shadow-sm' : 'bg-neutral-200' }}"
                            style="background: {{ $khachhang ? 'linear-gradient(135deg, ' . config('theme.primary.hex', '#3b82f6') . ', ' . config('theme.accent.hex', '#0ea5e9') . ')' : '#e5e7eb' }};">
                            <span
                                class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform
                                       {{ $khachhang ? 'translate-x-4.5' : 'translate-x-0.5' }}"
                                style="transform: {{ $khachhang ? 'translate-x-4.5px)' : 'translate-x-0.5px)' }};"></span>
                        </button>
                        <label class="text-sm font-medium text-neutral-700 cursor-pointer select-none">
                            Dành cho khách hàng
                        </label>
                    </div>
                </div>
            @endif

        </div>

        {{-- ======================= ACTION BUTTONS ======================= --}}
        <div class="px-6 py-4 border-t border-neutral-100 flex items-center justify-between bg-neutral-50/50">
            <div class="text-xs text-neutral-400">
                <span class="text-neutral-500">*</span> Trường bắt buộc
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    wire:click="goBack"
                    class="px-5 py-2.5 text-sm font-medium text-neutral-600 bg-white border border-neutral-300
                           rounded-xl hover:bg-neutral-50 hover:text-neutral-800
                           transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Hủy bỏ
                </button>
                <button
                    type="button"
                    wire:click="save"
                    wire:disabled="isSaving"
                    class="px-6 py-2.5 text-sm font-medium text-white rounded-xl
                           transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5
                           flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:translate-y-0"
                    style="background: linear-gradient(135deg,
                          {{ config('theme.primary.hex', '#3b82f6') }},
                          {{ config('theme.accent.hex', '#0ea5e9') }});">
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
                        Lưu {{ $itemId ? 'cập nhật' : 'mới' }}
                    @endif
                </button>
            </div>
        </div>
    </div>

</div>
