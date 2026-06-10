<?php

use App\Models\Country;
use App\Models\News;
use App\Models\ServicePriceDetail;
use App\Models\ServicePriceList;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;
use PhpOffice\PhpSpreadsheet\IOFactory;

new #[Layout('layouts.app')] #[Title('Chi tiết bảng giá dịch vụ')] class extends Component
{
    use WithFileUploads, WithPagination,WithoutUrlPagination;

    public ?int $itemId = null;

    public string $name = '';

    public ?int $serviceId = null;

    public array $countryIds = [];

    public $uploadFile = null;

    public int $importPreviewCount = 0;

    public array $importErrors = [];

    public bool $isSaving = false;

    public function mount(?int $id = null): void
    {
        abort_unless(\Gate::allows('service-prices.manage'), 403);

        $this->itemId = $id;

        if ($id) {
            $priceList = ServicePriceList::query()->with('countries')->findOrFail($id);
            $this->name = $priceList->name;
            $this->serviceId = $priceList->service_id;
            $this->countryIds = $priceList->countries->pluck('id')->map(fn ($id) => (string) $id)->all();
        }
    }

    public function updatedUploadFile(): void
    {
        $this->importPreviewCount = 0;
        $this->importErrors = [];

        if (! $this->uploadFile) {
            return;
        }

        try {
            $result = $this->parseExcelDetails($this->uploadFile->getRealPath());
            $this->importPreviewCount = count($result['rows']);
            $this->importErrors = $result['errors'];
        } catch (Throwable $exception) {
            $this->importErrors = ['Không đọc được file Excel. Vui lòng kiểm tra định dạng file.'];
        }
    }

    public function save(): mixed
    {
        abort_unless(\Gate::allows('service-prices.manage'), 403);

        $this->isSaving = true;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'serviceId' => ['required', 'integer', Rule::exists('news', 'id')->where('type', 'dichvuchinh')],
            'countryIds' => ['required', 'array', 'min:1'],
            'countryIds.*' => ['integer', 'exists:countries,id'],
            'uploadFile' => [$this->itemId ? 'nullable' : 'required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];

        try {
            $this->validate($rules, [
                'name.required' => 'Tên bảng giá không được để trống.',
                'serviceId.required' => 'Vui lòng chọn dịch vụ.',
                'countryIds.required' => 'Vui lòng chọn ít nhất một quốc gia.',
                'uploadFile.required' => 'Vui lòng upload file Excel chi tiết bảng giá.',
                'uploadFile.mimes' => 'File phải có định dạng xlsx, xls hoặc csv.',
            ]);
        } catch (ValidationException $exception) {
            $this->isSaving = false;

            throw $exception;
        }

        $parsedRows = null;

        if ($this->uploadFile) {
            try {
                $result = $this->parseExcelDetails($this->uploadFile->getRealPath());
            } catch (Throwable $exception) {
                $this->isSaving = false;
                $this->importErrors = ['Không đọc được file Excel. Vui lòng kiểm tra định dạng file.'];
                $this->addError('uploadFile', $this->importErrors[0]);

                return null;
            }

            $this->importPreviewCount = count($result['rows']);
            $this->importErrors = $result['errors'];

            if ($this->importErrors !== []) {
                $this->isSaving = false;
                $this->addError('uploadFile', implode(' ', array_slice($this->importErrors, 0, 3)));

                return null;
            }

            $parsedRows = $result['rows'];
        }

        $priceList = DB::transaction(function () use ($parsedRows) {
            $priceList = $this->itemId
                ? ServicePriceList::query()->findOrFail($this->itemId)
                : new ServicePriceList(['created_by' => auth()->id()]);

            $priceList->fill([
                'name' => trim($this->name),
                'service_id' => $this->serviceId,
                'updated_by' => auth()->id(),
            ])->save();

            $priceList->countries()->sync(array_map('intval', $this->countryIds));

            if (is_array($parsedRows)) {
                $priceList->details()->delete();
                $priceList->details()->createMany($parsedRows);
            }

            return $priceList;
        });

        $this->isSaving = false;
        Flux::toast(duration: 2200, heading: 'Thành công', text: 'Đã lưu bảng giá dịch vụ.', variant: 'success');

        return $this->redirect(route('phuphi.service-prices.edit', $priceList->id), navigate: true);
    }

    public function goBack(): mixed
    {
        return $this->redirect(route('phuphi.service-prices.index'), navigate: true);
    }

    #[Computed]
    public function services()
    {
        return News::query()
            ->where('type', 'dichvuchinh')
            ->orderBy('namevi')
            ->get(['id', 'namevi']);
    }

    #[Computed]
    public function countries()
    {
        return Country::query()->orderBy('name')->get(['id', 'name', 'iso2']);
    }

    #[Computed]
    public function selectedCountryIds(): array
    {
        return array_map('strval', $this->countryIds);
    }

    #[Computed]
    public function details()
    {
        if (! $this->itemId) {
            return ServicePriceDetail::query()->whereRaw('1 = 0')->paginate(20, pageName: 'detailsPage');
        }

        return ServicePriceDetail::query()
            ->where('service_price_list_id', $this->itemId)
            ->orderBy('weight_from')
            ->orderBy('weight_to')
            ->paginate(20, pageName: 'detailsPage');
    }

    private function parseExcelDetails(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $sheetRows = array_values(array_filter($sheetRows, fn ($row) => collect($row)->filter(fn ($cell) => filled($cell))->isNotEmpty()));

        if (count($sheetRows) < 2) {
            return ['rows' => [], 'errors' => ['File Excel cần có dòng tiêu đề và ít nhất một dòng dữ liệu.']];
        }

        $headers = array_shift($sheetRows);
        $headerMap = [];

        foreach ($headers as $column => $header) {
            $headerMap[$this->normalizeHeader((string) $header)] = $column;
        }

        $columns = [
            'quycach' => ['quy_cach', 'quycach'],
            'weight_from' => ['can_nang_tu', 'weight_from'],
            'weight_to' => ['can_nang_den', 'weight_to'],
            'sale_price' => ['cuoc_ban', 'sale_price'],
            'cost_price' => ['cuoc_von', 'cost_price'],
            'base_price' => ['cuoc_goc', 'base_price'],
        ];

        $resolvedColumns = [];
        $errors = [];

        foreach ($columns as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($headerMap[$alias])) {
                    $resolvedColumns[$key] = $headerMap[$alias];
                    break;
                }
            }
        }

        foreach (array_keys($columns) as $key) {
            if (! isset($resolvedColumns[$key])) {
                $errors[] = 'Thiếu cột: '.$this->columnLabel($key).'.';
            }
        }

        if ($errors !== []) {
            return ['rows' => [], 'errors' => $errors];
        }

        $rows = [];
        $rangeKeys = [];

        foreach ($sheetRows as $index => $row) {
            $line = $index + 2;
            $parsed = [];

            foreach ($resolvedColumns as $field => $column) {
                $parsed[$field] = $field === 'quycach'
                    ? $this->parseQuycach($row[$column] ?? null)
                    : $this->parseNumber($row[$column] ?? null);
            }

            if (collect($parsed)->filter(fn ($value) => $value !== null)->isEmpty()) {
                continue;
            }

            foreach ($parsed as $field => $value) {
                if ($value === null) {
                    $errors[] = "Dòng {$line}: {$this->columnLabel($field)} không hợp lệ.";
                }
            }

            if (($parsed['weight_to'] ?? 0) < ($parsed['weight_from'] ?? 0)) {
                $errors[] = "Dòng {$line}: Cân nặng đến phải lớn hơn hoặc bằng cân nặng từ.";
            }

            foreach (['weight_from', 'weight_to', 'sale_price', 'cost_price', 'base_price'] as $field) {
                if (($parsed[$field] ?? 0) < 0) {
                    $errors[] = "Dòng {$line}: {$this->columnLabel($field)} không được âm.";
                }
            }

            $rangeKey = null;

            if ($parsed['weight_from'] !== null && $parsed['weight_to'] !== null) {
                $rangeKey = ($parsed['quycach'] ?? '').'-'.number_format((float) $parsed['weight_from'], 2, '.', '').'-'.number_format((float) $parsed['weight_to'], 2, '.', '');

                if (isset($rangeKeys[$rangeKey])) {
                    $errors[] = "Dòng {$line}: Khoảng cân nặng bị trùng với dòng {$rangeKeys[$rangeKey]}.";
                }

                $rangeKeys[$rangeKey] = $line;
            }

            $rows[] = $parsed;
        }

        if ($rows === []) {
            $errors[] = 'File Excel chưa có dòng giá hợp lệ.';
        }

        return ['rows' => $rows, 'errors' => array_slice($errors, 0, 20)];
    }

    private function normalizeHeader(string $header): string
    {
        return trim((string) Str::of(Str::ascii($header))->lower()->replaceMatches('/[^a-z0-9]+/', '_'), '_');
    }

    private function parseNumber(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^\d,.\-]/', '', $value) ?? '';

        if (str_contains($value, '.') && str_contains($value, ',')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (preg_match('/^-?\d{1,3}(,\d{3})+$/', $value)) {
            $value = str_replace(',', '', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $value)) {
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function parseQuycach(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = (string) Str::of(Str::ascii($value))->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_');

        return in_array($value, ['CO_DINH', 'DON_GIA'], true) ? $value : null;
    }

    private function columnLabel(string $key): string
    {
        return [
            'weight_from' => 'Cân nặng từ',
            'weight_to' => 'Cân nặng đến',
            'sale_price' => 'Cước bán',
            'cost_price' => 'Cước vốn',
            'base_price' => 'Cước gốc',
            'quycach' => 'Quy cách',
        ][$key] ?? $key;
    }

    public function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }
};

?>

<div class="space-y-5">
    <div class="flex items-center gap-3">
        <button type="button" wire:click="goBack" class="rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-800">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <div>
            <p class="text-sm text-neutral-500">Phụ phí / Bảng giá dịch vụ</p>
            <h1 class="mt-1 text-2xl font-bold text-neutral-900">{{ $itemId ? 'Chi tiết bảng giá' : 'Thêm bảng giá dịch vụ' }}</h1>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,520px)_1fr]">
        <div class="space-y-5">
            <div class="rounded-xl border border-neutral-200 bg-white shadow-xs">
                <div class="border-b border-neutral-100 px-5 py-4">
                    <h2 class="font-semibold text-neutral-900">Thông tin bảng giá</h2>
                    <p class="mt-1 text-sm text-neutral-500">Mỗi bảng giá gắn với một dịch vụ và một hoặc nhiều quốc gia.</p>
                </div>

                <div class="space-y-4 p-5">
                    <flux:field>
                        <flux:label badge="Bắt buộc">Tên bảng giá</flux:label>
                        <flux:input wire:model.defer="name" placeholder="VD: Express US - 2026" />
                        @error('name') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label badge="Bắt buộc">Dịch vụ</flux:label>
                        <div wire:ignore>
                            <select
                                data-livewire-model="serviceId"
                                data-livewire-live="false"
                                data-placeholder="-- Chọn dịch vụ --"
                                class="tomselectEml"
                                autocomplete="off"
                            >
                                <option value="">-- Chọn dịch vụ --</option>
                                @foreach ($this->services as $service)
                                    <option value="{{ $service->id }}" @selected((string) $serviceId === (string) $service->id)>{{ $service->namevi }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('serviceId') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label badge="Bắt buộc">Quốc gia áp dụng</flux:label>
                        <div wire:ignore>
                            <select
                                data-livewire-model="countryIds"
                                data-livewire-live="false"
                                data-placeholder="Chọn một hoặc nhiều quốc gia"
                                multiple
                                class="tomselectEml"
                                autocomplete="off"
                            >
                                @foreach ($this->countries as $country)
                                    <option value="{{ $country->id }}" @selected(in_array((string) $country->id, $this->selectedCountryIds, true))>{{ $country->name }}{{ $country->iso2 ? ' ('.$country->iso2.')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('countryIds') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white shadow-xs">
                <div class="border-b border-neutral-100 px-5 py-4">
                    <h2 class="font-semibold text-neutral-900">Upload chi tiết Excel</h2>
                    <p class="mt-1 text-sm text-neutral-500">File cần có 6 cột: Quy cách, Cân nặng từ, Cân nặng đến, Cước bán, Cước vốn, Cước gốc.</p>
                </div>
                <div class="space-y-4 p-5">
                    <input type="file" wire:model="uploadFile" accept=".xlsx,.xls,.csv" class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-primary-700 hover:file:bg-primary-100">
                    @error('uploadFile') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    @if ($importPreviewCount > 0 && $importErrors === [])
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
                            Đã đọc được {{ number_format($importPreviewCount) }} dòng giá. Bấm lưu để cập nhật vào bảng giá.
                        </div>
                    @endif

                    @if ($importErrors !== [])
                        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                            @foreach ($importErrors as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <button type="button" wire:click="goBack" class="h-10 rounded-lg border border-neutral-300 px-4 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50">Hủy</button>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,uploadFile"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-primary-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60">
                    {{ $isSaving ? 'Đang lưu...' : 'Lưu bảng giá' }}
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white shadow-xs">
            <div class="flex flex-col gap-2 border-b border-neutral-100 px-5 py-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-semibold text-neutral-900">Danh sách giá</h2>
                    <p class="mt-1 text-sm text-neutral-500">Dữ liệu được lấy từ chi tiết bảng giá sau khi lưu Excel.</p>
                </div>
                <span class="text-sm font-medium text-neutral-500">{{ $this->details->total() }} dòng</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-neutral-100 bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <th class="px-4 py-3">Quy cách</th>
                            <th class="px-4 py-3">Cân nặng từ</th>
                            <th class="px-4 py-3">Cân nặng đến</th>
                            <th class="px-4 py-3 text-right">Cước bán</th>
                            <th class="px-4 py-3 text-right">Cước vốn</th>
                            <th class="px-4 py-3 text-right">Cước gốc</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse ($this->details as $detail)
                            <tr class="text-sm text-neutral-700">
                                <td class="px-4 py-3 font-medium">{{ $detail->quycach }}</td>
                                <td class="px-4 py-3 font-medium">{{ number_format((float) $detail->weight_from, 2, ',', '.') }} kg</td>
                                <td class="px-4 py-3 font-medium">{{ number_format((float) $detail->weight_to, 2, ',', '.') }} kg</td>
                                <td class="px-4 py-3 text-right">{{ $this->money($detail->sale_price) }}</td>
                                <td class="px-4 py-3 text-right">{{ $this->money($detail->cost_price) }}</td>
                                <td class="px-4 py-3 text-right">{{ $this->money($detail->base_price) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-14 text-center">
                                    <p class="font-medium text-neutral-600">Chưa có dòng giá</p>
                                    <p class="mt-1 text-sm text-neutral-400">Upload Excel và bấm lưu để tạo danh sách giá.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-neutral-100 px-5 py-4">
                {{ $this->details->links() }}
            </div>
        </div>
    </div>
</div>
