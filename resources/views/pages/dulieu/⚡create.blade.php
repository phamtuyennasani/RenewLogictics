<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\News;
use Flux\Flux;
new class extends Component {
    use WithFileUploads;

    public $type;
    public $itemId = null;
    public array $config = [];
    public $form;
    // Status
    public bool $isSaving = false;
    public array $rules = [];
    public array $messages = [];

    public function mount($type = null, $id = null)
    {
        $this->type = $type;
        $this->itemId = $id;
        $this->config = config("dulieu.{$this->type}", []);
        // Build form fields và validation rules động
        foreach ($this->config['formFields'] ?? [] as $key => $field) {
            $this->form[$key] = null;
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
            $this->rules["form.{$key}"] = implode('|', $rule);
            // Build message động
            if (!empty($field['required'])) {
                $this->messages["form.{$key}.required"] = "{$field['label']} không được để trống";
            }
        }
        foreach ($this->config['formOptions'] ?? [] as $key => $field) {
            $name = explode('.', $field['name']);
            $this->form[$name[0]][$name[1]] = null;
            if($field['type'] === 'number') {
               $this->form[$name[0]][$name[1]] = 1;
            }else {
                $this->form[$name[0]][$name[1]] = null;
            }
           
        }
        $this->form['numb'] = 1;
        if($id){
            $item = News::find($id);
            foreach ($this->config['formFields'] ?? [] as $key => $field) {
                $this->form[$key] = $item->{$key} ?? null;
            }
            foreach ($this->config['formOptions'] ?? [] as $key => $field) {
                $name = explode('.', $field['name']);
                $this->form[$name[0]][$name[1]] = $item->{$name[0]}[$name[1]] ?? null;
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
        $this->form = $this->trimRecursive($this->form);
        $itemSaved = News::updateOrCreate(
            ['id' => $this->itemId],
            array_merge($this->form, [
                'type' => $this->type,
                'id_user' => auth()->id(),
            ])
        );
        if($this->itemId) {
            Flux::toast(duration: 2000,heading: 'Thành công', text: 'Cập nhật dữ liệu thành công!', variant: 'success');
        } else {
            Flux::toast(duration: 2000,heading: 'Thành công', text: 'Thêm mới dữ liệu thành công!', variant: 'success');
        }
        return $this->redirect(route('dichvu.index', ['type' => $this->type]),navigate:true);
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
        return $this->redirect(route('dichvu.index', ['type' => $this->type]),navigate:true);
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
            class="p-2 rounded-xl text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-all">
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
                <flux:input 
                    :type="$v['type'] ?? 'text'"
                    :required="@$v['required']"
                    wire:model.defer="form.{{ $k }}"
                    :invalid="$errors->has('form.{{ $k }}')"
                    :placeholder="$v['placeholder'] ?? ''"
                    @focus="$el.removeAttribute('data-invalid')"
                    :class:input="[
                        'w-full px-4 py-2.5 text-sm border transition-all',
                        'placeholder:text-neutral-400',
                        'focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500',
                    ]"
                />
            </flux:field>
            @endforeach
            @foreach($this->config['formOptions']??[] as $k => $v)
            <flux:field>
                <flux:label :badge="@$v['required'] ? 'Bắt buộc' : null">{{ $v['label'] }}</flux:label>
                <flux:input 
                    :type="$v['type'] ?? 'text'"
                    :required="@$v['required']"
                    wire:model.defer="form.{{ $v['name'] }}"
                    :placeholder="$v['placeholder'] ?? ''"
                    :class:input="[
                        'w-full px-4 py-2.5 text-sm border transition-all',
                        'placeholder:text-neutral-400',
                        'focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500',
                    ]"
                />
            </flux:field>
            @endforeach
            <flux:field class="w-40">
                <flux:label>Số thứ tự</flux:label>
                <flux:input 
                    :type="'number'"
                    min="1"
                    wire:model.defer="form.numb"
                    :class:input="[
                        'w-full px-4 py-2.5 text-sm border transition-all',
                        'placeholder:text-neutral-400',
                        'focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500',
                    ]"
                />
            </flux:field>
        </div>

        {{-- ======================= ACTION BUTTONS ======================= --}}
        <div class="px-6 py-4 border-t border-neutral-100 flex items-center justify-end bg-neutral-50/50">
           
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
