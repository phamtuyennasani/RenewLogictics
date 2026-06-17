<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\News;
use App\Models\VSVX;
use Flux\Flux;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
new class extends Component {
    use WithFileUploads;

    public $type;
    public $itemId = null;
    public array $config = [];
    public $formData = [];
    // Status
    public bool $isSaving = false;
    public array $rules = [];
    public array $messages = [];

    // VSVX (chỉ dùng cho type dichvuchinh)
    public $vsvxFile = null;
    public int $vsvxPreviewCount = 0;
    public array $vsvxErrors = [];
    public ?string $pendingAction = null;
    public mixed $pendingId = null;

    public function mount($type = null, $id = null)
    {
        $gate = request()->routeIs('phuphi.*') ? 'phuphi.index' : 'dulieu.index';
        abort_unless(\Gate::allows($gate), 403);
        $this->type = $type;
        $this->itemId = $id;
        $this->config = config("dulieu.{$this->type}", []);
        // Build form fields và validation rules động
        foreach ($this->config['formFields'] ?? [] as $key => $field) {
            data_set($this->formData, $key, null);
            // Build validation rule động
            $rule = [];
            if (!empty($field['required'])) {
                $rule[] = 'required';
            } else {
                $rule[] = 'nullable';
            }
            // Các rule bổ sung
            if (!empty($field['type']) && $field['type'] === 'email') {
                $rule[] = 'email';
            }
            if (!empty($field['max'])) {
                $rule[] = 'max:' . $field['max'];
            }
            $this->rules["formData.{$key}"] = implode('|', $rule);
            // Build message động
            if (!empty($field['required'])) {
                $this->messages["formData.{$key}.required"] = "{$field['label']} không được để trống";
            }
        }
        foreach ($this->config['formOptions'] ?? [] as $key => $field) {
            $name = explode('.', $field['name']);
            $this->formData[$name[0]][$name[1]] = null;
            if($field['type'] === 'number') {
               $this->formData[$name[0]][$name[1]] = 1;
            }else {
                $this->formData[$name[0]][$name[1]] = null;
            }
           
        }
        
        // $this->formData['numb'] = 1;
        if($id){
            $item = News::find($id);
            foreach ($this->config['formFields'] ?? [] as $key => $field) {
                data_set($this->formData, $key, data_get($item, $key));
            }
            foreach ($this->config['formOptions'] ?? [] as $key => $field) {
                $name = explode('.', $field['name']);
                $this->formData[$name[0]][$name[1]] = $item->{$name[0]}[$name[1]] ?? null;
            }
        }
        
    }

    public function save()
    {
        $this->isSaving = true;
        try {
            $data = $this->validate($this->rules, $this->messages);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->isSaving = false;
            throw $e; 
        }
        $this->formData = $this->trimRecursive($this->formData);
        foreach ($this->formData as $key => $value) {
            if (is_array($value)) {
               foreach ($value as $subKey => $subValue) {
                    if($subKey=='price') {
                        $this->formData[$key][$subKey] = str_replace(['.', ','], '', $subValue);
                    }
               }
            }
        }
        $itemSaved = News::updateOrCreate(
            ['id' => $this->itemId],
            array_merge($this->formData, [
                'type' => $this->type,
                'id_user' => auth()->id(),
            ])
        );
        if($this->itemId) {
            Flux::toast(duration: 2000,heading: 'Thành công', text: 'Cập nhật dữ liệu thành công!', variant: 'success');
        } else {
            Flux::toast(duration: 2000,heading: 'Thành công', text: 'Thêm mới dữ liệu thành công!', variant: 'success');
        }
        return $this->redirect(route($this->config['route_group'] . '.index', ['type' => $this->type]),navigate:true);
    }
    private function trimRecursive(array $data): array
    {
        return array_map(function($value) {
            if (is_array($value)) {
                return $this->trimRecursive($value);
            }
            return is_string($value) ? trim($value) : $value;
        }, $data);
    }
    public function goBack()
    {
        return $this->redirect(route($this->config['route_group'] . '.index', ['type' => $this->type]),navigate:true);
    }

    // ==================== VSVX (dichvuchinh) ====================

    public function hasVsvxSection(): bool
    {
        return $this->itemId
            && $this->type === 'dichvuchinh'
            && ! empty($this->config['hasVsvx']);
    }

    #[Computed]
    public function vsvxList()
    {
        if (! $this->hasVsvxSection()) {
            return collect();
        }

        return VSVX::query()
            ->where('id_dichvu', $this->itemId)
            ->orderByDesc('id')
            ->get(['id', 'code', 'name']);
    }

    #[Computed]
    public function vsvxCount(): int
    {
        if (! $this->hasVsvxSection()) {
            return 0;
        }

        return VSVX::query()->where('id_dichvu', $this->itemId)->count();
    }

    public function updatedVsvxFile(): void
    {
        $this->vsvxPreviewCount = 0;
        $this->vsvxErrors = [];

        if (! $this->vsvxFile) {
            return;
        }

        try {
            $result = $this->parseVsvxExcel($this->vsvxFile->getRealPath());
            $this->vsvxPreviewCount = count($result['codes']);
            $this->vsvxErrors = $result['errors'];
        } catch (\Throwable $e) {
            $this->vsvxErrors = ['Không đọc được file Excel. Vui lòng kiểm tra định dạng file.'];
        }
    }

    public function importVsvx(): void
    {
        abort_unless($this->hasVsvxSection(), 403);

        $this->validate([
            'vsvxFile' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [
            'vsvxFile.required' => 'Vui lòng chọn file Excel VSVX.',
            'vsvxFile.mimes' => 'File phải có định dạng xlsx, xls hoặc csv.',
        ]);

        try {
            $result = $this->parseVsvxExcel($this->vsvxFile->getRealPath());
        } catch (\Throwable $e) {
            $this->addError('vsvxFile', 'Không đọc được file Excel. Vui lòng kiểm tra định dạng file.');
            return;
        }

        if ($result['errors'] !== []) {
            $this->vsvxErrors = $result['errors'];
            $this->addError('vsvxFile', implode(' ', array_slice($result['errors'], 0, 3)));
            return;
        }

        // Loại trùng trong chính file
        $codes = array_values(array_unique($result['codes']));

        // Bỏ qua các postcode đã tồn tại cho dịch vụ này
        $existing = VSVX::query()
            ->where('id_dichvu', $this->itemId)
            ->whereIn('code', $codes)
            ->pluck('code')
            ->map(fn ($c) => (string) $c)
            ->all();
        $existingSet = array_flip($existing);

        $now = now();
        $insert = [];
        foreach ($codes as $code) {
            if (isset($existingSet[$code])) {
                continue;
            }
            $insert[] = [
                'id_dichvu' => $this->itemId,
                'code' => $code,
                'name' => $code,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $added = 0;
        if ($insert !== []) {
            foreach (array_chunk($insert, 1000) as $chunk) {
                VSVX::query()->insert($chunk);
                $added += count($chunk);
            }
        }

        $skipped = count($codes) - $added;

        $this->vsvxFile = null;
        $this->vsvxPreviewCount = 0;
        $this->vsvxErrors = [];
        unset($this->vsvxList, $this->vsvxCount);

        Flux::toast(
            duration: 2500,
            heading: 'Đã import VSVX',
            text: "Thêm mới {$added} postcode" . ($skipped > 0 ? ", bỏ qua {$skipped} đã tồn tại." : '.'),
            variant: 'success'
        );
    }

    public function deleteVsvx($id): void
    {
        abort_unless($this->hasVsvxSection(), 403);

        $this->pendingAction = 'deleteVsvx';
        $this->pendingId = $id;
        $this->dispatch('open-confirm', [
            'title'   => 'Xác nhận xóa',
            'message' => 'Bạn có chắc chắn muốn xóa postcode này khỏi danh sách VSVX?',
            'variant' => 'danger',
        ]);
    }

    #[On('confirm-action')]
    public function handleConfirmAction(): void
    {
        if ($this->pendingAction === 'deleteVsvx' && $this->hasVsvxSection()) {
            VSVX::query()
                ->where('id_dichvu', $this->itemId)
                ->whereKey($this->pendingId)
                ->delete();
            unset($this->vsvxList, $this->vsvxCount);
            Flux::toast(duration: 2000, heading: 'Thành công', text: 'Đã xóa postcode VSVX.', variant: 'success');
        }

        $this->pendingAction = null;
        $this->pendingId = null;
    }

    private function parseVsvxExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $sheetRows = array_values(array_filter(
            $sheetRows,
            fn ($row) => collect($row)->filter(fn ($cell) => filled($cell))->isNotEmpty()
        ));

        if (count($sheetRows) < 2) {
            return ['codes' => [], 'errors' => ['File Excel cần có dòng tiêu đề và ít nhất một dòng dữ liệu.']];
        }

        $headers = array_shift($sheetRows);
        $headerMap = [];
        foreach ($headers as $column => $header) {
            $headerMap[$this->normalizeHeader((string) $header)] = $column;
        }

        // Cột PostCode (chấp nhận nhiều biến thể tiêu đề)
        $postcodeColumn = null;
        foreach (['postcode', 'post_code', 'zipcode', 'zip_code', 'zip', 'code', 'ma_buu_chinh', 'mapostcode'] as $alias) {
            if (isset($headerMap[$alias])) {
                $postcodeColumn = $headerMap[$alias];
                break;
            }
        }

        if ($postcodeColumn === null) {
            return ['codes' => [], 'errors' => ['Thiếu cột PostCode trong file Excel.']];
        }

        $codes = [];
        $errors = [];
        foreach ($sheetRows as $index => $row) {
            $line = $index + 2;
            $code = trim((string) ($row[$postcodeColumn] ?? ''));

            if ($code === '') {
                continue;
            }

            // Excel có thể đọc số dạng float (vd "1000.0") -> chuẩn hóa
            if (is_numeric($code) && str_ends_with($code, '.0')) {
                $code = (string) (int) $code;
            }

            if (mb_strlen($code) > 255) {
                $errors[] = "Dòng {$line}: PostCode quá dài (tối đa 255 ký tự).";
                continue;
            }

            $codes[] = $code;
        }

        if ($codes === []) {
            $errors[] = 'File Excel chưa có PostCode hợp lệ.';
        }

        return ['codes' => $codes, 'errors' => array_slice($errors, 0, 20)];
    }

    private function normalizeHeader(string $header): string
    {
        return trim((string) Str::of(Str::ascii($header))->lower()->replaceMatches('/[^a-z0-9]+/', '_'), '_');
    }

    public function render()
    {
        return $this->view();
    }
};

?>

<div class=" mx-auto space-y-6">

    {{-- ======================= PAGE HEADER ======================= --}}
    <div class="flex items-center gap-3">
        <button
            wire:click="goBack" 
        class="p-2 rounded-xl text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-all cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <div>
            <p class="text-sm text-neutral-500 capitalize">Dữ liệu / {{ $this->config['group']  }}</p>
            <h1 class="text-2xl font-bold text-neutral-900">
                {{ $itemId ? 'Chỉnh sửa' : 'Thêm mới' }} {{ $this->config['title'] ?? '' }}
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
            @foreach($this->config['formFields']??[] as $k => $v)
            <flux:field>
                <flux:label :badge="@$v['required'] ? 'Bắt buộc' : null">{{ $v['label'] }}</flux:label>
                
                @if((!empty($v['format'])) && $v['format']=='price')
                <flux:input.group>
                    <flux:input 
                        :type="$v['type'] ?? 'text'"
                        :required="@$v['required']"
                        wire:model.defer="formData.{{ $k }}"
                        :placeholder="$v['placeholder'] ?? ''"
                        @focus="$el.removeAttribute('data-invalid')"
                        mask:dynamic="$money($input)"
                        :class:input="[
                            $v['class'] ?? '',
                            'w-full px-4 py-2.5 text-sm border transition-all',
                            'placeholder:text-neutral-400',
                            'focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500',
                        ]"
                    />
                    <flux:input.group.suffix>VNĐ</flux:input.group.suffix>
                </flux:input.group>
                @else
                <flux:input 
                    :type="$v['type'] ?? 'text'"
                    :required="@$v['required']"
                    wire:model.defer="formData.{{ $k }}"
                    :invalid="$errors->has('formData.{{ $k }}')"
                    :placeholder="$v['placeholder'] ?? ''"
                    @focus="$el.removeAttribute('data-invalid')"
                    :class:input="[
                        'w-full px-4 py-2.5 text-sm border transition-all',
                        'placeholder:text-neutral-400',
                        'focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500',
                    ]"
                />
                @endif
            </flux:field>
            @endforeach
            @foreach($this->config['formOptions']??[] as $k => $v)
            <flux:field>
                <flux:label :badge="@$v['required'] ? 'Bắt buộc' : null">{{ $v['label'] }}</flux:label>
                <flux:input 
                    :type="$v['type'] ?? 'text'"
                    :required="@$v['required']"
                    wire:model.defer="formData.{{ $v['name'] }}"
                    :placeholder="$v['placeholder'] ?? ''"
                    :class:input="[
                        'w-full px-4 py-2.5 text-sm border transition-all',
                        'placeholder:text-neutral-400',
                        'focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500',
                    ]"
                />
            </flux:field>
            @endforeach
            {{-- <flux:field class="w-40">
                <flux:label>Số thứ tự</flux:label>
                <flux:input 
                    :type="'number'"
                    min="1"
                    wire:model.defer="formData.numb"
                    :class:input="[
                        'w-full px-4 py-2.5 text-sm border transition-all',
                        'placeholder:text-neutral-400',
                        'focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500',
                    ]"
                />
            </flux:field> --}}
        </div>

        {{-- ======================= ACTION BUTTONS ======================= --}}
        <div class="px-6 py-4 border-t border-neutral-100 flex items-center justify-end bg-neutral-50/50">
           
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    wire:click="goBack"
                    class="px-5 py-2.5 text-sm font-medium text-red-600 bg-red-100 border border-red-300
                           rounded-xl hover:bg-red-50 hover:text-red-800 cursor-pointer
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

    {{-- ======================= VSVX SECTION (dichvuchinh) ======================= --}}
    @if($this->hasVsvxSection())
    <div class="bg-white rounded-2xl border border-neutral-200 overflow-hidden shadow-sm">

        {{-- Section header --}}
        <div class="px-6 py-5 border-b border-neutral-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    Danh sách VSVX (vùng sâu vùng xa)
                </h2>
                <p class="mt-1 text-xs text-neutral-500">Các postcode thuộc vùng sâu vùng xa của dịch vụ này.</p>
            </div>
            <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full bg-neutral-100 text-xs font-semibold text-neutral-600">
                {{ number_format($this->vsvxCount) }} postcode
            </span>
        </div>

        {{-- Upload Excel --}}
        <div class="p-6 border-b border-neutral-100 space-y-4">
            <div>
                <label class="block text-sm font-medium text-neutral-700 mb-2">Upload Excel VSVX</label>
                <p class="text-xs text-neutral-500 mb-3">File Excel gồm 2 cột: <span class="font-medium">STT</span> và <span class="font-medium">PostCode</span>. Cột STT chỉ để tham khảo, hệ thống chỉ lưu PostCode. Postcode đã tồn tại sẽ được bỏ qua.
                    <a href="{{ route('dichvu.vsvx.template') }}"
                       class="inline-flex items-center gap-1 font-medium text-primary-600 hover:text-primary-700 hover:underline">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Tải file mẫu
                    </a>
                </p>
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <input type="file" wire:model="vsvxFile" accept=".xlsx,.xls,.csv"
                        class="block w-full sm:max-w-md rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-100">
                    <button
                        type="button"
                        wire:click="importVsvx"
                        wire:loading.attr="disabled"
                        wire:target="importVsvx,vsvxFile"
                        @disabled(! $vsvxFile)
                        class="shrink-0 inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-medium text-white rounded-xl transition-all shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed"
                        style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }});">
                        <span wire:loading.remove wire:target="importVsvx">Import</span>
                        <span wire:loading wire:target="importVsvx,vsvxFile">Đang xử lý...</span>
                    </button>
                </div>
                @error('vsvxFile') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @if ($vsvxPreviewCount > 0 && $vsvxErrors === [])
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
                    Đã đọc được {{ number_format($vsvxPreviewCount) }} postcode. Bấm Import để thêm vào danh sách.
                </div>
            @endif

            @if ($vsvxErrors !== [])
                <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 space-y-0.5">
                    @foreach ($vsvxErrors as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Danh sách VSVX --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-neutral-50 border-b border-neutral-100">
                        <th class="w-20 px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide text-center">STT</th>
                        <th class="px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">PostCode</th>
                        <th class="w-28 px-4 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($this->vsvxList as $i => $vsvx)
                        <tr class="hover:bg-neutral-50/60 transition-colors" wire:key="vsvx-{{ $vsvx->id }}">
                            <td class="px-4 py-3 text-center text-sm text-neutral-500">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-neutral-800">{{ $vsvx->code }}</td>
                            <td class="px-4 py-3 text-center">
                                <button
                                    type="button"
                                    wire:click="deleteVsvx({{ $vsvx->id }})"
                                    class="p-2 rounded-lg text-neutral-400 hover:text-red-600 hover:bg-red-50 transition-all cursor-pointer"
                                    title="Xóa">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-12 text-center">
                                <p class="text-sm font-medium text-neutral-600">Chưa có postcode VSVX</p>
                                <p class="text-xs text-neutral-400 mt-0.5">Upload file Excel để thêm danh sách postcode.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
