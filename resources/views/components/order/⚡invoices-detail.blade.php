<?php

use App\Actions\Order\RecordOrderEditHistoryAction;
use App\Enums\OrderStatusEnum;
use App\Models\Invoice;
use App\Models\News;
use App\Models\Order;
use App\Support\OrderAccess;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Order $order;
    public array $invoiceForm = [];

    public function mount(): void
    {
        $this->fillInvoiceForm();
    }

    public function fillInvoiceForm(): void
    {
        $this->invoiceForm = $this->order->invoices
            ->sortBy('id')
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'tenhang' => $invoice->tenhang,
                'soluong' => $invoice->soluong ?: 1,
                'xuatxu' => $invoice->xuatxu,
                'loaihang' => $invoice->loaihang,
                'hscode' => $invoice->hscode,
                'price' => $invoice->price,
                'total' => $invoice->total,
            ])
            ->values()
            ->all();

        if ($this->invoiceForm === []) {
            $this->invoiceForm[] = $this->defaultInvoice();
        }

        $this->recalculateAll();
    }

    protected function defaultInvoice(): array
    {
        return [
            'id' => null,
            'tenhang' => '',
            'soluong' => 1,
            'xuatxu' => '',
            'loaihang' => '',
            'hscode' => '',
            'price' => '',
            'total' => 0,
        ];
    }

    public function addInvoice(): void
    {
        $this->invoiceForm[] = $this->defaultInvoice();
    }

    public function removeInvoice(int $index): void
    {
        if (count($this->invoiceForm) <= 1) {
            return;
        }

        unset($this->invoiceForm[$index]);
        $this->invoiceForm = array_values($this->invoiceForm);
        $this->recalculateAll();
    }

    public function updated(string $propertyName): void
    {
        if (! str_starts_with($propertyName, 'invoiceForm.')) {
            return;
        }

        $parts = explode('.', $propertyName);
        $index = isset($parts[1]) ? (int) $parts[1] : null;

        if ($index === null || ! isset($this->invoiceForm[$index])) {
            return;
        }

        if (in_array($parts[2] ?? '', ['soluong', 'price'], true)) {
            $this->calculateInvoiceRow($index);
        }
    }

    public function canEditInvoices(): bool
    {
        return OrderAccess::canEditOrder(auth()->user(), $this->order)
            && ! in_array($this->order->bill_status, [
            OrderStatusEnum::DA_GIAO,
            OrderStatusEnum::HUY,
        ], true);
    }

    public function saveInvoices(): void
    {
        if (! $this->canEditInvoices()) {
            Flux::toast(duration: 2500, heading: 'Không thể chỉnh sửa', text: 'Không được sửa invoice khi đơn đã giao hoặc đã hủy.', variant: 'warning');
            return;
        }

        OrderAccess::assignCsOnEdit(auth()->user(), $this->order);

        $this->validate([
            'invoiceForm' => 'required|array|min:1',
            'invoiceForm.*.tenhang' => 'required|string|max:255',
            'invoiceForm.*.soluong' => 'required|integer|min:1',
            'invoiceForm.*.xuatxu' => 'required|string|max:255',
            'invoiceForm.*.loaihang' => 'required|exists:news,id',
            'invoiceForm.*.hscode' => 'required|string|max:255',
            'invoiceForm.*.price' => 'required|numeric|min:0.01',
        ], [
            'invoiceForm.required' => 'Danh sách invoice là bắt buộc.',
            'invoiceForm.min' => 'Phải có ít nhất 1 dòng invoice.',
            'invoiceForm.*.tenhang.required' => 'Tên hàng hóa là bắt buộc.',
            'invoiceForm.*.soluong.required' => 'Số lượng là bắt buộc.',
            'invoiceForm.*.soluong.integer' => 'Số lượng phải là số nguyên.',
            'invoiceForm.*.soluong.min' => 'Số lượng phải lớn hơn 0.',
            'invoiceForm.*.xuatxu.required' => 'Xuất xứ là bắt buộc.',
            'invoiceForm.*.loaihang.required' => 'Loại hàng là bắt buộc.',
            'invoiceForm.*.loaihang.exists' => 'Loại hàng không hợp lệ.',
            'invoiceForm.*.hscode.required' => 'HS Code là bắt buộc.',
            'invoiceForm.*.price.required' => 'Đơn giá là bắt buộc.',
            'invoiceForm.*.price.numeric' => 'Đơn giá chỉ được nhập số.',
            'invoiceForm.*.price.min' => 'Đơn giá phải lớn hơn 0.',
        ]);

        $this->recalculateAll();
        $before = $this->invoicesSnapshot();

        DB::transaction(function () {
            $existingInvoices = $this->order->invoices()
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $keptInvoiceIds = [];

            foreach ($this->invoiceForm as $invoice) {
                $payload = $this->invoicePayload($invoice);
                $invoiceId = (int) ($invoice['id'] ?? 0);
                $existingInvoice = $invoiceId > 0 ? $existingInvoices->get($invoiceId) : null;

                if ($existingInvoice) {
                    $existingInvoice->update($payload);
                    $keptInvoiceIds[] = $existingInvoice->id;
                    continue;
                }

                $created = $this->order->invoices()->create($payload);
                $keptInvoiceIds[] = $created->id;
            }

            $deleteInvoiceIds = $existingInvoices->keys()
                ->diff($keptInvoiceIds)
                ->values();

            if ($deleteInvoiceIds->isNotEmpty()) {
                $this->order->invoices()
                    ->whereIn('id', $deleteInvoiceIds)
                    ->delete();
            }
        });

        $this->order->refresh();
        $this->order->load('invoices');
        RecordOrderEditHistoryAction::execute($this->order, 'edit_invoices', 'invoice', $before, $this->invoicesSnapshot(), 'sửa invoice');
        $this->fillInvoiceForm();
        $this->dispatch('order-history-updated');

        Flux::modal('edit-invoices')->close();
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật invoice hàng hóa.', variant: 'success');
    }

    protected function invoicePayload(array $invoice): array
    {
        $soluong = max(1, (int) ($invoice['soluong'] ?? 1));
        $price = $this->normalizeMoneyValue($invoice['price'] ?? 0);

        return [
            'tenhang' => trim((string) ($invoice['tenhang'] ?? '')),
            'soluong' => $soluong,
            'xuatxu' => trim((string) ($invoice['xuatxu'] ?? '')),
            'loaihang' => $invoice['loaihang'] ?: null,
            'hscode' => trim((string) ($invoice['hscode'] ?? '')),
            'price' => $price,
            'total' => round($soluong * $price, 2),
        ];
    }

    protected function invoicesSnapshot(): array
    {
        return [
            'invoices' => $this->order->invoices()
                ->orderBy('id')
                ->get(['id', 'tenhang', 'soluong', 'xuatxu', 'loaihang', 'hscode', 'price', 'total'])
                ->map(fn (Invoice $invoice) => $invoice->toArray())
                ->values()
                ->all(),
        ];
    }

    protected function normalizeMoneyValue(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return 0;
        }

        $normalized = preg_replace('/[^\d.]/', '', $value);

        if (substr_count($normalized, '.') > 1) {
            $parts = explode('.', $normalized);
            $normalized = array_shift($parts) . '.' . implode('', $parts);
        }

        return $normalized === '' ? 0 : (float) $normalized;
    }

    protected function calculateInvoiceRow(int $index): void
    {
        $invoice = $this->invoiceForm[$index] ?? $this->defaultInvoice();
        $soluong = max(1, (int) ($invoice['soluong'] ?? 1));
        $price = $this->normalizeMoneyValue($invoice['price'] ?? 0);

        $this->invoiceForm[$index]['soluong'] = $soluong;
        $this->invoiceForm[$index]['price'] = $price;
        $this->invoiceForm[$index]['total'] = round($soluong * $price, 2);
    }

    protected function recalculateAll(): void
    {
        foreach (array_keys($this->invoiceForm) as $index) {
            $this->calculateInvoiceRow((int) $index);
        }
    }

    public function money(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 2) : '—';
    }

    public function loaihangName(mixed $id): string
    {
        return $this->loaihang[(string) $id] ?? $this->loaihang[(int) $id] ?? (string) ($id ?: '—');
    }

    #[Computed]
    public function loaihang(): array
    {
        return Cache::remember('order_invoice_goods_types', now()->addDay(), fn () =>
            News::whereType('hanghoa')->orderBy('numb')->pluck('namevi', 'id')->toArray()
        );
    }

    public function render()
    {
        return $this->view();
    }
};

?>

<section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
    <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-neutral-900 uppercase">Invoice hàng hóa</h2>
            <p class="text-xs text-neutral-500">Danh sách khai báo hàng trong đơn</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-700">{{ $order->invoices->count() }} dòng</span>
            @if($this->canEditInvoices())
            <flux:modal.trigger name="edit-invoices">
                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Sửa invoice hàng hóa">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                    </svg>
                </button>
            </flux:modal.trigger>
            @endif
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-neutral-50 border-b border-neutral-100">
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Tên hàng</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Loại</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Xuất xứ</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">HS Code</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 text-right">SL</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 text-right">Đơn giá</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 text-right">Tổng</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($order->invoices as $invoice)
                    <tr class="hover:bg-neutral-50/60">
                        <td class="px-5 py-3 text-sm font-medium text-neutral-800">{!! nl2br(e($invoice->tenhang ?: '—')) !!}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600">{{ $this->loaihangName($invoice->loaihang) }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600">{{ $invoice->xuatxu ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600">{{ $invoice->hscode ?: '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm text-neutral-600">{{ $invoice->soluong ?: '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm text-neutral-600">{{ $this->money($invoice->price) }}</td>
                        <td class="px-5 py-3 text-right text-sm font-semibold text-neutral-800">{{ $this->money($invoice->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-neutral-500">Chưa có invoice hàng hóa</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <flux:modal name="edit-invoices" class="w-full max-w-6xl">
        <form wire:submit="saveInvoices" class="space-y-6">
            <div>
                <flux:heading size="lg">Sửa invoice hàng hóa</flux:heading>
                <flux:subheading>Điều chỉnh danh sách khai báo hàng hóa trong đơn.</flux:subheading>
            </div>

            <div class="space-y-3">
                @foreach($invoiceForm as $index => $invoice)
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-3">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-neutral-800">Dòng invoice {{ $index + 1 }}</p>
                            @if(count($invoiceForm) > 1)
                                <button type="button" wire:click="removeInvoice({{ $index }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 bg-white text-neutral-500 hover:border-red-200 hover:bg-red-50 hover:text-red-600" aria-label="Xóa invoice">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            @endif
                        </div>

                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-12">
                            <flux:field class="xl:col-span-4">
                                <flux:label>Tên hàng hóa</flux:label>
                                <flux:textarea rows="auto" wire:model="invoiceForm.{{ $index }}.tenhang" />
                                @error("invoiceForm.$index.tenhang") <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                            <flux:field class="xl:col-span-2">
                                <flux:label>Loại hàng</flux:label>
                                <flux:select wire:model="invoiceForm.{{ $index }}.loaihang">
                                    <flux:select.option value="">Chọn loại hàng</flux:select.option>
                                    @foreach($this->loaihang as $id => $name)
                                        @php
                                            $optionId = is_array($name) ? ($name['id'] ?? $id) : $id;
                                            $optionName = is_array($name) ? ($name['name'] ?? '') : $name;
                                        @endphp
                                        <flux:select.option value="{{ $optionId }}">{{ $optionName }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                @error("invoiceForm.$index.loaihang") <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                            <flux:field class="xl:col-span-2">
                                <flux:label>Xuất xứ</flux:label>
                                <flux:input type="text" wire:model="invoiceForm.{{ $index }}.xuatxu" />
                                @error("invoiceForm.$index.xuatxu") <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                            <flux:field class="xl:col-span-2">
                                <flux:label>HS Code</flux:label>
                                <flux:input type="text" wire:model="invoiceForm.{{ $index }}.hscode" />
                                @error("invoiceForm.$index.hscode") <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                            <flux:field>
                                <flux:label>SL</flux:label>
                                <flux:input type="number" min="1" step="1" wire:model.live.debounce.300ms="invoiceForm.{{ $index }}.soluong" />
                                @error("invoiceForm.$index.soluong") <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                            <flux:field>
                                <flux:label>Đơn giá</flux:label>
                                <flux:input type="number" min="0.01" step="0.01" wire:model.live.debounce.300ms="invoiceForm.{{ $index }}.price" />
                                @error("invoiceForm.$index.price") <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                        </div>

                        <div class="mt-3 grid gap-2 text-xs text-neutral-500 sm:grid-cols-3">
                            <div class="rounded-lg bg-white px-3 py-2">Tổng: <span class="font-semibold text-primary-700">{{ $this->money($invoice['total'] ?? 0) }}</span></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-3 border-t border-neutral-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <flux:button type="button" variant="filled" wire:click="addInvoice">+ Thêm invoice</flux:button>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Hủy</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Lưu</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</section>
