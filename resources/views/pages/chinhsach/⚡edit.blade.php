<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Flux\Flux;

new class extends Component {
    public string $slug;
    public array $policyConfig = [];
    public bool $canEdit = false;
    public bool $isSaving = false;

    public string $namevi = '';
    public string $contentvi = '';

    public function mount($slug)
    {
        $this->slug = $slug;

        // Load policy config
        $this->policyConfig = config("policy.{$slug}");

        if (empty($this->policyConfig)) {
            abort(404, 'Chính sách không tồn tại');
        }

        // Check edit permission
        $userRole = auth()->user()->roles->first()?->name ?? '';
        $this->canEdit = in_array($userRole, $this->policyConfig['canEdit'] ?? []);

        // Load existing data from static table
        $record = DB::table('static')
            ->where('type', $slug)
            ->first();

        if ($record) {
            $this->namevi = $record->namevi ?? '';
            $this->contentvi = $record->contentvi ?? '';
        } else {
            // Initialize with title from config
            $this->namevi = $this->policyConfig['title'] ?? '';
        }
    }

    public function save()
    {
        if (!$this->canEdit) {
            Flux::toast(
                duration: 2000,
                heading: 'Lỗi',
                text: 'Bạn không có quyền chỉnh sửa chính sách này',
                variant: 'danger'
            );
            return;
        }

        $this->isSaving = true;

        $data = [
            'namevi' => $this->policyConfig['title'] ?? '',
            'contentvi' => $this->contentvi,
            'type' => $this->slug,
            'updated_at' => now(),
        ];

        $existing = DB::table('static')
            ->where('type', $this->slug)
            ->first();

        if ($existing) {
            DB::table('static')
                ->where('type', $this->slug)
                ->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('static')->insert($data);
        }

        $this->isSaving = false;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: 'Lưu chính sách thành công!',
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
    <div class="flex items-center gap-3">
        <div>
            <p class="text-sm text-neutral-500 capitalize">Chính sách</p>
            <h1 class="text-2xl font-bold text-neutral-900">
                {{ $policyConfig['title'] ?? 'Chính sách' }}
            </h1>
        </div>
    </div>

    {{-- MAIN CONTENT --}}
    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">

        <div class="px-6 py-5 border-b border-neutral-100">
            <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                {{ $canEdit ? 'Chỉnh sửa nội dung' : 'Nội dung chính sách' }}
            </h2>
        </div>

        <div class="p-6 space-y-5">

            @if($canEdit)
                {{-- EDIT MODE --}}
                @if($policyConfig['content_editor'] ?? false)
                    <flux:field>
                        <flux:label badge="Bắt buộc">Nội dung chính sách</flux:label>
                        <div wire:ignore>
                            <textarea id="ckeditor-{{ $slug }}" class="w-full">{{ $contentvi }}</textarea>
                        </div>
                    </flux:field>
                @endif

            @else
                {{-- VIEW MODE --}}
                <div class="prose prose-neutral max-w-none">
                    <h2 class="text-2xl font-bold text-neutral-900 mb-6">{{ $policyConfig['title'] }}</h2>

                    @if($contentvi)
                        <div class="text-neutral-700 leading-relaxed">
                            {!! $contentvi !!}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 mx-auto text-neutral-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-neutral-500">Chưa có nội dung</p>
                        </div>
                    @endif
                </div>
            @endif

        </div>

        @if($canEdit)
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
                        Lưu chính sách
                    @endif
                </button>
            </div>
        @endif
    </div>
</div>

@if($canEdit && ($policyConfig['content_editor'] ?? false))
    @push('scripts')
    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
    <script>
        document.addEventListener('livewire:navigated', function () {
            initCKEditor();
        });

        function initCKEditor() {
            if (typeof CKEDITOR !== 'undefined') {
                const editorId = 'ckeditor-{{ $slug }}';

                // Destroy existing instance if any
                if (CKEDITOR.instances[editorId]) {
                    CKEDITOR.instances[editorId].destroy(true);
                }

                // Initialize CKEditor
                CKEDITOR.replace(editorId, {
                    height: 400,
                    language: 'vi',
                    removeButtons: 'Save,NewPage,Preview,Print,Templates,Cut,Copy,Paste,PasteText,PasteFromWord,Undo,Redo,Find,Replace,SelectAll,Scayt,Form,Checkbox,Radio,TextField,Textarea,Select,Button,ImageButton,HiddenField,Strike,Subscript,Superscript,CopyFormatting,RemoveFormat,Outdent,Indent,CreateDiv,Blockquote,BidiLtr,BidiRtl,Language,Anchor,Flash,Smiley,SpecialChar,PageBreak,Iframe,Styles,Font,ShowBlocks,About',
                    toolbar: [
                        { name: 'document', items: ['Source'] },
                        { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline'] },
                        { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'] },
                        { name: 'links', items: ['Link', 'Unlink'] },
                        { name: 'insert', items: ['Image', 'Table', 'HorizontalRule'] },
                        { name: 'styles', items: ['Format', 'FontSize'] },
                        { name: 'colors', items: ['TextColor', 'BGColor'] },
                        { name: 'tools', items: ['Maximize'] }
                    ],
                    versionCheck: false,
                });

                // Sync with Livewire on change
                CKEDITOR.instances[editorId].on('change', function() {
                    @this.set('contentvi', CKEDITOR.instances[editorId].getData());
                });
            }
        }

        // Initialize on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCKEditor);
        } else {
            initCKEditor();
        }
    </script>
    @endpush
@endif
