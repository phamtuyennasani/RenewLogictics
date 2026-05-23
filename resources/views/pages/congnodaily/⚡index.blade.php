<?php

use App\Enums\DebtStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Models\CongNoDaiLy;
use App\Models\CongNoDaiLyDetail;
use App\Models\News;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;
use Flux\Flux;

new #[Layout('layouts.app')] #[Title('Công nợ đại lý')] class extends Component {
    use WithPagination;

    #[Url(history: true)]
    public string $keyword = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public ?int $dailyId = null;

    #[Url(history: true)]
    public ?string $fromDate = null;

    #[Url(history: true)]
    public ?string $toDate = null;

    public bool $showCreateModal = false;
    public array $selectedIds = [];
    public ?int $createDailyId = null;
    public ?string $createFromDate = null;
    public ?string $createToDate = null;
    public int $paymentTermDays = 7;
    public ?string $note = null;

    public function mount(): void
    {
        $this->fromDate ??= now()->subDays(30)->format('Y-m-d');
        $this->toDate ??= now()->format('Y-m-d');
        $this->createFromDate ??= now()->subDays(30)->format('Y-m-d');
        $this->createToDate ??= now()->format('Y-m-d');
    }

    public function updating($property): void
    {
        if (in_array($property, ['keyword', 'status', 'dailyId', 'fromDate', 'toDate'], true)) {
            $this->resetPage();
            $this->selectedIds = [];
        }
    }

    public function openCreateModal(): void
    {
        $this->createDailyId = $this->dailyId;
        $this->createFromDate = now()->subDays(30)->format('Y-m-d');
        $this->createToDate = now()->format('Y-m-d');
        $this->paymentTermDays = (int) data_get(config('system', []), 'hanthanhtoan', 7);
        $this->note = null;
        $this->showCreateModal = true;
    }

    public function createDebt(): void
    {
        abort_unless($this->canManage(), 403);

        $data = $this->validate([
            'createDailyId' => ['required', 'integer', 'exists:news,id'],
            'createFromDate' => ['required', 'date'],
            'createToDate' => ['required', 'date', 'after_or_equal:createFromDate'],
            'paymentTermDays' => ['required', 'integer', 'min:0', 'max:365'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'createDailyId' => 'Đại lý',
            'createFromDate' => 'Từ ngày',
            'createToDate' => 'Đến ngày',
            'paymentTermDays' => 'Hạn thanh toán',
        ]);

        $from = Carbon::parse($data['createFromDate'])->startOfDay();
        $to = Carbon::parse($data['createToDate'])->endOfDay();

        $orders = $this->eligibleOrdersQuery($from, $to, (int) $data['createDailyId'])
            ->with(['packages'])
            ->get();

        if ($orders->isEmpty()) {
            Flux::toast(heading: 'Chưa có dữ liệu', text: 'Không tìm thấy order phù hợp để tạo công nợ đại lý.', variant: 'warning');
            return;
        }

        $debt = DB::transaction(function () use ($data, $from, $to, $orders) {
            $debt = CongNoDaiLy::create([
                'sohoadon' => CongNoDaiLy::generateSoHoaDon($from->format('dm'), $to->format('dm')),
                'id_daily' => $data['createDailyId'],
                'id_user' => auth()->id(),
                'id_ketoan' => auth()->user()->hasRole('ketoan') ? auth()->id() : null,
                'tungay' => $from,
                'denngay' => $to,
                'ngaytaohoadon' => now(),
                'songaythanhtoan' => $data['paymentTermDays'],
                'status' => DebtStatusEnum::MOI_TAO,
                'ghichu' => $data['note'],
            ]);

            $rows = $orders->map(fn (Order $order) => [
                'id_congno_daily' => $debt->id,
                'id_order' => $order->id,
                ...$this->snapshotForOrder($order),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            CongNoDaiLyDetail::insert($rows);
            $debt->syncTotalsFromDetails();

            return $debt;
        });

        $this->showCreateModal = false;
        Flux::toast(heading: 'Đã tạo công nợ đại lý', text: "Mã {$debt->sohoadon} gồm {$debt->total_orders} order.", variant: 'success');
        $this->redirectRoute('congno.daily.show', ['id' => $debt->uuid], navigate: true);
    }

    public function setStatus(string $status = ''): void
    {
        $this->status = $status;
        $this->selectedIds = [];
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->keyword = '';
        $this->status = '';
        $this->dailyId = null;
        $this->fromDate = now()->subDays(30)->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
        $this->selectedIds = [];
        $this->resetPage();
    }

    public function applyDatePreset(string $preset): void
    {
        $this->toDate = now()->format('Y-m-d');

        $this->fromDate = match ($preset) {
            'today' => now()->format('Y-m-d'),
            '7' => now()->subDays(7)->format('Y-m-d'),
            default => now()->subDays(30)->format('Y-m-d'),
        };

        $this->selectedIds = [];
        $this->resetPage();
    }

    public function toggleCurrentPageSelection(): void
    {
        $pageIds = $this->items->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $selected = array_map('strval', $this->selectedIds);

        if (count(array_intersect($pageIds, $selected)) === count($pageIds)) {
            $this->selectedIds = array_values(array_diff($selected, $pageIds));
            return;
        }

        $this->selectedIds = array_values(array_unique([...$selected, ...$pageIds]));
    }

    public function deleteSelected(): void
    {
        abort_unless($this->canManage(), 403);

        $ids = collect($this->selectedIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            Flux::toast(heading: 'Chưa chọn công nợ', text: 'Vui lòng chọn ít nhất một dòng để xóa.', variant: 'warning');
            return;
        }

        $debts = CongNoDaiLy::query()
            ->whereIn('id', $ids)
            ->where('status', '!=', DebtStatusEnum::DA_THANH_TOAN->value)
            ->get();

        DB::transaction(function () use ($debts) {
            foreach ($debts as $debt) {
                $debt->details()->delete();
                $debt->payments()->delete();
                $debt->delete();
            }
        });

        $this->selectedIds = [];
        unset($this->items, $this->statusCounts, $this->summary);

        Flux::toast(heading: 'Đã xóa công nợ', text: "Đã xóa {$debts->count()} công nợ chưa thanh toán.", variant: 'success');
    }

    public function exportExcel()
    {
        $query = $this->baseDebtQuery()->latest('id');
        $fileName = 'cong-no-dai-ly-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new class($query) implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize {
            public function __construct(private $query)
            {
            }

            public function query()
            {
                return $this->query;
            }

            public function headings(): array
            {
                return [
                    'Mã công nợ',
                    'Đại lý',
                    'Từ ngày',
                    'Đến ngày',
                    'Số order',
                    'Tổng cước vốn',
                    'Đã thanh toán',
                    'Còn lại',
                    'Trạng thái',
                    'Hạn thanh toán',
                ];
            }

            public function map($debt): array
            {
                return [
                    $debt->sohoadon,
                    $debt->daily?->namevi ?: $debt->daily?->nameen,
                    $debt->tungay?->format('d/m/Y'),
                    $debt->denngay?->format('d/m/Y'),
                    (int) $debt->total_orders,
                    (float) $debt->total_cuocvon,
                    (float) $debt->paid_amount,
                    (float) $debt->remaining_amount,
                    $debt->status?->label(),
                    $debt->hanthanhtoan?->format('d/m/Y'),
                ];
            }
        }, $fileName);
    }

    #[Computed]
    public function items()
    {
        return $this->baseDebtQuery()
            ->latest('id')
            ->paginate(12);
    }

    #[Computed]
    public function currentPageSelected(): bool
    {
        $pageIds = $this->items->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all();

        return $pageIds !== [] && count(array_intersect($pageIds, array_map('strval', $this->selectedIds))) === count($pageIds);
    }

    #[Computed]
    public function statusCounts(): array
    {
        $counts = array_fill_keys(DebtStatusEnum::values(), 0);

        $this->baseDebtQuery(includeStatus: false)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get()
            ->each(function ($row) use (&$counts) {
                $status = (string) $row->getRawOriginal('status');
                if (isset($counts[$status])) {
                    $counts[$status] = (int) $row->total;
                }
            });

        return ['all' => array_sum($counts), ...$counts];
    }

    #[Computed]
    public function summary(): array
    {
        $items = $this->baseDebtQuery(includeStatus: false)->get();

        return [
            'total' => $items->sum('total_cuocvon'),
            'paid' => $items->sum('paid_amount'),
            'remaining' => $items->sum(fn (CongNoDaiLy $debt) => $debt->remaining_amount),
            'overdue' => $items->where('status', DebtStatusEnum::QUA_HAN)->sum(fn (CongNoDaiLy $debt) => $debt->remaining_amount),
        ];
    }

    #[Computed]
    public function dailies()
    {
        return News::query()
            ->where('type', 'daily')
            ->orderBy('namevi')
            ->get(['id', 'namevi', 'nameen']);
    }

    protected function baseDebtQuery(bool $includeStatus = true)
    {
        return CongNoDaiLy::query()
            ->with(['daily:id,namevi,nameen', 'creator:id,fullname,username,code', 'ketoan:id,fullname,username,code'])
            ->when($includeStatus && $this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->dailyId, fn ($q) => $q->where('id_daily', $this->dailyId))
            ->when($this->fromDate, fn ($q) => $q->whereDate('created_at', '>=', Carbon::parse($this->fromDate)))
            ->when($this->toDate, fn ($q) => $q->whereDate('created_at', '<=', Carbon::parse($this->toDate)))
            ->when($this->keyword !== '', function ($q) {
                $keyword = '%'.trim($this->keyword).'%';
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('sohoadon', 'like', $keyword)
                        ->orWhere('sohoadon_thamchieu', 'like', $keyword)
                        ->orWhereHas('daily', fn ($daily) => $daily->where('namevi', 'like', $keyword)->orWhere('nameen', 'like', $keyword));
                });
            });
    }

    protected function eligibleOrdersQuery(Carbon $from, Carbon $to, int $idDaily)
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('service->id_daily', $idDaily)
            ->where('bill_status', '!=', OrderStatusEnum::HUY->value)
            ->where(function ($q) {
                $q->whereNull('agency_payment_status')
                    ->orWhereNotIn('agency_payment_status', [
                        DebtStatusEnum::DA_CHOT_CUOC->value,
                        DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN->value,
                        DebtStatusEnum::DA_THANH_TOAN->value,
                    ]);
            })
            ->whereDoesntHave(
                'congNoDaiLyDetails.congNoDaiLy',
                fn ($q) => $q->where('status', '!=', DebtStatusEnum::DA_THANH_TOAN->value)
            );
    }

    protected function snapshotForOrder(Order $order): array
    {
        $weight = (float) $order->packages->sum(fn ($package) => (float) ($package->row_c_weight ?: $package->c_weight ?: 0));
        $snapshot = [
            'order_code' => $order->id_bill,
            'sale_total' => $this->number(data_get($order->payment_cuocban, 'total_tongcuoc', 0)),
            'cost_total' => $this->number(data_get($order->payment_cuocvon, 'total_tongcuoc', 0)),
            'base_total' => $this->number(data_get($order->payment_cuocgoc, 'total_tongcuoc', 0)),
            'vat' => $this->number(data_get($order->payment_cuocvon, 'total_vat', 0)),
            'ppxd' => $this->number(data_get($order->payment_cuocvon, 'ppxd_amount', 0)),
            'fee' => $this->number(data_get($order->payment_cuocvon, 'total_phuphi', 0)),
            'commission' => $this->number(data_get($order->payment_cuocvon, 'bonus_sale_amount', 0)),
            'weight' => $weight,
        ];

        return [
            'weight' => $weight,
            'cuocban' => $snapshot['sale_total'],
            'cuocvon' => $snapshot['cost_total'],
            'cuocgoc' => $snapshot['base_total'],
            'vat' => $snapshot['vat'],
            'ppxd' => $snapshot['ppxd'],
            'phuphi' => $snapshot['fee'],
            'hoahong' => $snapshot['commission'],
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    protected function number(mixed $value): float
    {
        return (float) preg_replace('/[^\d.-]/', '', (string) ($value ?? 0));
    }

    public function canManage(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'ketoan']);
    }

    public function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' đ';
    }

    public function render()
    {
        return $this->view();
    }
};

?>

<div id="congnodaily-index-page" class="space-y-4">
    <style>
        #congnodaily-index-page .debt-status-nav {
            overflow: hidden;
            border-radius: 0.5rem;
            border: 1px solid rgb(229 229 229);
            background: linear-gradient(180deg, #fff 0%, #fafafa 100%);
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.05);
        }

        #congnodaily-index-page .debt-status-nav-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem;
            padding-bottom: 0.25rem;
        }

        #congnodaily-index-page .debt-status-nav-header h3 {
            margin: 0;
            font-size: 0.875rem;
            font-weight: 750;
            line-height: 1.25rem;
            color: rgb(23 23 23);
        }

        #congnodaily-index-page .debt-status-nav-header p {
            margin: 0.125rem 0 0;
            font-size: 0.75rem;
            line-height: 1rem;
            color: rgb(115 115 115);
        }

        #congnodaily-index-page .debt-status-tabs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr));
            gap: 0.625rem;
            padding: 0.75rem 1rem 1rem;
        }

        #congnodaily-index-page .debt-status-tab {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            min-height: 4rem;
            min-width: 0;
            align-items: center;
            gap: 0.625rem;
            border-radius: 0.875rem;
            border: 1px solid rgb(229 229 229);
            background: rgb(255 255 255 / 0.92);
            padding: 0.625rem 0.75rem;
            text-align: left;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
            transition: transform 150ms ease, border-color 150ms ease, box-shadow 150ms ease, background-color 150ms ease;
        }

        #congnodaily-index-page .debt-status-tab:hover {
            transform: translateY(-1px);
            border-color: rgb(212 212 212);
            box-shadow: 0 10px 24px rgb(15 23 42 / 0.08);
        }

        #congnodaily-index-page .debt-status-tab[data-active="true"] {
            border-color: currentColor;
            background: #fff;
            box-shadow: 0 12px 30px rgb(15 23 42 / 0.12);
        }

        #congnodaily-index-page .debt-status-tab-all[data-active="true"] {
            border-color: rgb(115 115 115);
            background: rgb(245 245 245);
        }

        #congnodaily-index-page .debt-status-dot {
            width: 0.625rem;
            height: 0.625rem;
            flex-shrink: 0;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.75;
            box-shadow: 0 0 0 4px rgb(0 0 0 / 0.04);
        }

        #congnodaily-index-page .debt-status-text {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }

        #congnodaily-index-page .debt-status-label {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: rgb(23 23 23);
            font-size: 0.8125rem;
            font-weight: 700;
            line-height: 1.125rem;
        }

        #congnodaily-index-page .debt-status-meta {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.6875rem;
            font-weight: 500;
            line-height: 0.875rem;
            color: rgb(115 115 115);
        }

        #congnodaily-index-page .debt-status-count {
            display: inline-flex;
            min-width: 2rem;
            height: 1.625rem;
            align-items: center;
            justify-content: center;
            border: 1px solid rgb(229 229 229);
            border-radius: 999px;
            background: #fff;
            padding: 0 0.5rem;
            font-size: 0.75rem;
            font-weight: 750;
            line-height: 1;
            color: rgb(64 64 64);
            box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.8);
        }

        #congnodaily-index-page .debt-table-frame {
            border-radius: 0.5rem;
            border: 1px solid rgb(229 229 229);
        }

        #congnodaily-index-page .debt-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 3rem 1rem;
            text-align: center;
        }

        #congnodaily-index-page .debt-empty-state-icon {
            display: flex;
            height: 3rem;
            width: 3rem;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgb(245 245 245);
            color: rgb(82 82 82);
        }

        #congnodaily-index-page .debt-filter-panel {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        #congnodaily-index-page .debt-filter-section {
            border-radius: 1rem;
            border: 1px solid rgb(229 229 229);
            background: linear-gradient(180deg, #fff, rgb(250 250 250));
            padding: 1rem;
        }

        #congnodaily-index-page .debt-filter-section-heading {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        #congnodaily-index-page .debt-filter-section-heading h3 {
            font-size: 0.9375rem;
            font-weight: 800;
            color: rgb(23 23 23);
        }

        #congnodaily-index-page .debt-filter-section-heading p {
            margin-top: 0.125rem;
            font-size: 0.8125rem;
            color: rgb(115 115 115);
        }

        #congnodaily-index-page .debt-filter-presets {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.375rem;
        }

        #congnodaily-index-page .debt-filter-presets button {
            min-height: 2rem;
            border-radius: 999px;
            border: 1px solid rgb(229 229 229);
            background: #fff;
            padding: 0.375rem 0.75rem;
            color: rgb(64 64 64);
            font-size: 0.8125rem;
            font-weight: 700;
            transition: border-color 150ms ease, background-color 150ms ease, color 150ms ease;
        }

        #congnodaily-index-page .debt-filter-presets button:hover {
            border-color: rgb(147 197 253);
            background: rgb(239 246 255);
            color: rgb(29 78 216);
        }

        #congnodaily-index-page .debt-filter-field {
            display: flex;
            min-width: 0;
            flex-direction: column;
            gap: 0.375rem;
        }

        #congnodaily-index-page .debt-filter-label {
            color: rgb(64 64 64);
            font-size: 0.875rem;
            font-weight: 700;
            line-height: 1.25rem;
        }

        #congnodaily-index-page .debt-filter-control,
        #congnodaily-index-page .debt-filter-field .ts-control {
            width: 100%;
            height: 2.875rem;
            min-height: 2.875rem;
            border: 1px solid rgb(212 212 212);
            border-radius: 0.75rem;
            background-color: #fff;
            padding: 0.625rem 1rem;
            color: rgb(23 23 23);
            font-size: 0.9375rem;
            line-height: 1.375rem;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        #congnodaily-index-page .debt-filter-control:focus,
        #congnodaily-index-page .debt-filter-field .ts-wrapper.focus .ts-control {
            border-color: rgb(59 130 246);
            box-shadow: 0 0 0 3px rgb(59 130 246 / 0.14);
            outline: none;
        }

        #congnodaily-index-page .debt-filter-field .ts-wrapper {
            width: 100%;
            min-width: 0;
        }

        #congnodaily-index-page .debt-filter-field .ts-wrapper.single .ts-control {
            display: flex;
            align-items: center;
            padding-right: 2.5rem;
        }

        #congnodaily-index-page .debt-filter-field .ts-control > input {
            min-width: 0;
            font-size: 0.9375rem;
        }

        #congnodaily-index-page .debt-filter-field .ts-control .item,
        #congnodaily-index-page .debt-filter-field .ts-control .items-placeholder {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #congnodaily-index-page .debt-filter-field .ts-dropdown {
            z-index: 999999;
            overflow: hidden;
            border-radius: 0.75rem;
        }

        #congnodaily-index-page .debt-filter-date-field {
            position: relative;
            width: 100%;
        }

        #congnodaily-index-page .debt-filter-date-field .flatpickr-wrapper {
            display: block;
            width: 100%;
        }

        #congnodaily-index-page .debt-filter-date-field .flatpickr-input {
            width: 100%;
        }

        #congnodaily-index-page .debt-create-field {
            display: flex;
            min-width: 0;
            flex-direction: column;
            gap: 0.375rem;
        }

        #congnodaily-index-page .debt-create-label {
            color: rgb(64 64 64);
            font-size: 0.875rem;
            font-weight: 700;
            line-height: 1.25rem;
        }

        #congnodaily-index-page .debt-create-control,
        #congnodaily-index-page .debt-create-field .ts-control {
            width: 100%;
            height: 2.875rem;
            min-height: 2.875rem;
            border: 1px solid rgb(212 212 212);
            border-radius: 0.75rem;
            background-color: #fff;
            padding: 0.625rem 1rem;
            color: rgb(23 23 23);
            font-size: 0.9375rem;
            line-height: 1.375rem;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        #congnodaily-index-page .debt-create-control:focus,
        #congnodaily-index-page .debt-create-field .ts-wrapper.focus .ts-control {
            border-color: rgb(59 130 246);
            box-shadow: 0 0 0 3px rgb(59 130 246 / 0.14);
            outline: none;
        }

        #congnodaily-index-page .debt-create-field .ts-wrapper {
            width: 100%;
            min-width: 0;
        }

        #congnodaily-index-page .debt-create-field .ts-wrapper.single .ts-control {
            display: flex;
            align-items: center;
            padding-right: 2.5rem;
        }

        #congnodaily-index-page .debt-create-field .ts-control > input {
            min-width: 0;
            font-size: 0.9375rem;
        }

        #congnodaily-index-page .debt-create-field .ts-control .item,
        #congnodaily-index-page .debt-create-field .ts-control .items-placeholder {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #congnodaily-index-page .debt-create-field .ts-dropdown {
            z-index: 999999;
            overflow: hidden;
            border-radius: 0.75rem;
        }

        #congnodaily-index-page textarea.debt-create-control {
            height: auto;
            min-height: 5.5rem;
            resize: vertical;
        }

        #congnodaily-index-page .debt-date-picker-field .flatpickr-wrapper {
            width: 100%;
        }

        #congnodaily-index-page .debt-date-picker-field .flatpickr-input {
            width: 100%;
        }

        .debt-create-calendar-daily,
        .debt-filter-calendar-daily {
            z-index: 90 !important;
            border-radius: 0.875rem !important;
            box-shadow: 0 18px 40px rgb(15 23 42 / 0.18) !important;
        }

        #congnodaily-index-page .debt-filter-grid {
            display: grid;
            gap: 0.875rem;
        }

        @media (min-width: 768px) {
            #congnodaily-index-page .debt-filter-grid-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            #congnodaily-index-page .debt-filter-grid-3 {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
    </style>

    <section class="space-y-4">
        <div class="flex flex-col gap-4 border-b border-neutral-200 pb-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm text-neutral-500">Công nợ / Đại lý</p>
                <h1 class="mt-1 text-2xl font-bold text-neutral-900">Quản lý công nợ đại lý</h1>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:modal.trigger name="congnodaily-filter">
                    <flux:button type="button" variant="outline" icon="funnel">
                        Bộ lọc
                    </flux:button>
                </flux:modal.trigger>

                <flux:button type="button" wire:click="exportExcel" variant="outline" icon="arrow-down-tray">
                    Xuất Excel
                </flux:button>

                @if ($this->canManage())
                    <flux:button type="button" wire:click="deleteSelected" wire:confirm="Xóa các công nợ đã chọn? Công nợ đã thanh toán sẽ được bỏ qua." variant="danger" icon="trash">
                        Xóa
                    </flux:button>

                    <flux:button type="button" wire:click="openCreateModal" variant="primary" icon="plus">
                        Tạo công nợ
                    </flux:button>
                @endif
            </div>
        </div>
    </section>

    <div class="grid gap-3 lg:grid-cols-4">
        <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 pl-2">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-neutral-700">Tổng công nợ vốn</p>
                    </div>
                    <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950">{{ $this->money($this->summary['total']) }}</p>
                    <p class="mt-2 text-xs font-medium text-neutral-500">{{ $this->statusCounts['all'] }} phiếu theo bộ lọc</p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <flux:icon.document-currency-dollar class="size-5" />
                </div>
            </div>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-blue-100">
                <div class="h-full w-full rounded-full bg-blue-500"></div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="absolute inset-y-4 left-0 w-1 rounded-r-full bg-emerald-500"></div>
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 pl-2">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Đã thanh toán</p>
                    </div>
                    <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950">{{ $this->money($this->summary['paid']) }}</p>
                    <p class="mt-2 text-xs font-medium text-neutral-500">Đã trả cho đại lý</p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <flux:icon.check-circle class="size-5" />
                </div>
            </div>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-emerald-100">
                <div class="h-full w-2/3 rounded-full bg-emerald-500"></div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="absolute inset-y-4 left-0 w-1 rounded-r-full bg-amber-500"></div>
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 pl-2">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Còn lại</p>
                    </div>
                    <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950">{{ $this->money($this->summary['remaining']) }}</p>
                    <p class="mt-2 text-xs font-medium text-neutral-500">Còn phải trả đại lý</p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <flux:icon.clock class="size-5" />
                </div>
            </div>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-amber-100">
                <div class="h-full w-2/3 rounded-full bg-amber-500"></div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="absolute inset-y-4 left-0 w-1 rounded-r-full bg-red-500"></div>
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 pl-2">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-red-500"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-red-700">Quá hạn</p>
                    </div>
                    <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950">{{ $this->money($this->summary['overdue']) }}</p>
                    <p class="mt-2 text-xs font-medium text-neutral-500">Cần ưu tiên xử lý</p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600">
                    <flux:icon.exclamation-triangle class="size-5" />
                </div>
            </div>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-red-100">
                <div class="h-full w-2/3 rounded-full bg-red-500"></div>
            </div>
        </div>
    </div>

    <section class="debt-status-nav">
        <div class="debt-status-nav-header">
            <div>
                <h3>Trạng thái công nợ đại lý</h3>
                <p>Lọc nhanh theo tiến trình thanh toán cho đại lý/NCC</p>
            </div>
        </div>
        <div class="debt-status-tabs">
            <button type="button" wire:click="setStatus('')" data-active="{{ $status === '' ? 'true' : 'false' }}" class="debt-status-tab debt-status-tab-all text-neutral-700">
                <span class="debt-status-dot"></span>
                <span class="debt-status-text">
                    <span class="debt-status-label">Tất cả</span>
                    <span class="debt-status-meta">Toàn bộ công nợ</span>
                </span>
                <span class="debt-status-count">{{ $this->statusCounts['all'] }}</span>
            </button>

            @foreach (DebtStatusEnum::cases() as $debtStatus)
                <button type="button"
                        wire:click="setStatus('{{ $debtStatus->value }}')"
                        data-active="{{ $status === $debtStatus->value ? 'true' : 'false' }}"
                        class="{{ $debtStatus->color() }} debt-status-tab">
                    <span class="debt-status-dot"></span>
                    <span class="debt-status-text">
                        <span class="debt-status-label">{{ $debtStatus->label() }}</span>
                        <span class="debt-status-meta">Nhấn để lọc</span>
                    </span>
                    <span class="debt-status-count">{{ $this->statusCounts[$debtStatus->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>
    </section>

    <div class="space-y-3 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <flux:input type="search" wire:model.live.debounce.300ms="keyword" icon="magnifying-glass" placeholder="Tìm mã công nợ, đại lý..." class="lg:max-w-md" />
            <div class="flex items-center gap-2 text-sm text-neutral-600">
                <span>{{ count($selectedIds) }} công nợ đã chọn</span>
                <span class="h-1 w-1 rounded-full bg-neutral-300"></span>
                <span>Hiển thị {{ $this->items->count() }} / {{ $this->items->total() }}</span>
            </div>
        </div>

        <div class="debt-table-frame overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1180px] text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="w-12 px-4 py-3 text-center">
                                <label class="relative mx-auto flex w-fit cursor-pointer select-none items-center justify-center">
                                    <input type="checkbox" class="peer sr-only" wire:click="toggleCurrentPageSelection" @checked($this->currentPageSelected)>
                                    <span class="flex h-[18px] w-[18px] items-center justify-center rounded-md border border-neutral-300 bg-white transition peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-hover:border-primary-400"></span>
                                    <svg class="pointer-events-none absolute hidden h-3 w-3 text-white peer-checked:block" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.5l5 5 10-11" />
                                    </svg>
                                </label>
                            </th>
                            <th class="px-3 py-3">Mã công nợ</th>
                            <th class="px-3 py-3">Trạng thái</th>
                            <th class="px-3 py-3">Đại lý</th>
                            <th class="px-3 py-3 text-right">Tổng cước vốn</th>
                            <th class="px-3 py-3 text-right">Đã trả</th>
                            <th class="px-3 py-3 text-right">Còn lại</th>
                            <th class="px-3 py-3">Hạn thanh toán</th>
                            <th class="px-3 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 bg-white">
                        @forelse ($this->items as $item)
                            <tr class="transition hover:bg-neutral-50/80">
                                <td class="px-4 py-4 text-center">
                                    <label class="relative mx-auto flex w-fit cursor-pointer select-none items-center justify-center">
                                        <input type="checkbox" value="{{ $item->id }}" wire:model.live="selectedIds" class="peer sr-only">
                                        <span class="flex h-[18px] w-[18px] items-center justify-center rounded-md border border-neutral-300 bg-white transition peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-hover:border-primary-400"></span>
                                        <svg class="pointer-events-none absolute hidden h-3 w-3 text-white peer-checked:block" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.5l5 5 10-11" />
                                        </svg>
                                    </label>
                                </td>
                                <td class="px-3 py-4">
                                    <a href="{{ route('congno.daily.show', $item->uuid) }}" wire:navigate class="font-bold text-primary-700 hover:text-primary-800">{{ $item->sohoadon }}</a>
                                    <div class="mt-0.5 text-xs text-neutral-500">Tạo {{ $item->created_at?->format('d/m/Y H:i') }}</div>
                                </td>
                                <td class="px-3 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $item->status->color() }}">{{ $item->status->label() }}</span>
                                </td>
                                <td class="px-3 py-4">
                                    <div class="max-w-[220px] truncate font-semibold text-neutral-900">{{ $item->daily?->namevi ?: $item->daily?->nameen ?: 'Chưa rõ đại lý' }}</div>
                                    <div class="mt-0.5 max-w-[220px] truncate text-xs text-neutral-500">Kế toán: {{ $item->ketoan?->fullname ?: $item->ketoan?->username ?: '-' }}</div>
                                </td>
                                <td class="px-3 py-4 text-right font-semibold text-neutral-950">{{ $this->money($item->total_cuocvon) }}</td>
                                <td class="px-3 py-4 text-right font-semibold text-emerald-700">{{ $this->money($item->paid_amount) }}</td>
                                <td class="px-3 py-4 text-right font-semibold text-amber-700">{{ $this->money($item->remaining_amount) }}</td>
                                <td class="px-3 py-4 text-neutral-700">{{ $item->hanthanhtoan?->format('d/m/Y') ?: '-' }}</td>
                                <td class="px-3 py-4 text-right">
                                    <flux:button href="{{ route('congno.daily.show', $item->uuid) }}" wire:navigate size="sm" variant="outline" icon="eye">
                                        Chi tiết
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="debt-empty-state">
                                        <div class="debt-empty-state-icon">
                                            <flux:icon.document-magnifying-glass class="size-6" />
                                        </div>
                                        <div>
                                            <p class="font-bold text-neutral-900">Không có công nợ đại lý phù hợp</p>
                                            <p class="mt-1 text-sm text-neutral-500">Thử đổi từ khóa, nới rộng bộ lọc hoặc tạo công nợ mới từ các order chưa chốt.</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="border-t border-neutral-100 pt-3">
            {{ $this->items->links() }}
        </div>
    </div>

    <flux:modal name="congnodaily-filter" class="w-full max-w-5xl !overflow-visible">
        <div class="debt-filter-panel">
            <div class="flex items-start gap-3 border-b border-neutral-100 pb-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                    <flux:icon.funnel class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg">Bộ lọc công nợ đại lý</flux:heading>
                    <flux:subheading>Lọc theo thời gian tạo, trạng thái và đại lý cần thanh toán.</flux:subheading>
                </div>
            </div>

            <section class="debt-filter-section">
                <div class="debt-filter-section-heading">
                    <div>
                        <h3>Thời gian</h3>
                        <p>Khoảng ngày tạo phiếu công nợ</p>
                    </div>
                    <div class="debt-filter-presets">
                        <button type="button" wire:click="applyDatePreset('today')">Hôm nay</button>
                        <button type="button" wire:click="applyDatePreset('7')">7 ngày</button>
                        <button type="button" wire:click="applyDatePreset('30')">30 ngày</button>
                    </div>
                </div>
                <div class="debt-filter-grid debt-filter-grid-2">
                    <div class="debt-filter-field debt-filter-date-field">
                        <label class="debt-filter-label">Từ ngày</label>
                        <input type="text" value="{{ $fromDate }}" data-congnodaily-filter-date data-livewire-model="fromDate" autocomplete="off" class="debt-filter-control">
                    </div>
                    <div class="debt-filter-field debt-filter-date-field">
                        <label class="debt-filter-label">Đến ngày</label>
                        <input type="text" value="{{ $toDate }}" data-congnodaily-filter-date data-livewire-model="toDate" autocomplete="off" class="debt-filter-control">
                    </div>
                </div>
            </section>

            <section class="debt-filter-section">
                <div class="debt-filter-section-heading">
                    <div>
                        <h3>Đối tượng</h3>
                        <p>Đại lý/NCC cần thanh toán</p>
                    </div>
                </div>
                <div class="debt-filter-grid debt-filter-grid-2">
                    <div class="debt-filter-field">
                        <label class="debt-filter-label">Đại lý</label>
                        <select
                            wire:key="filter-daily-{{ $dailyId ?: 'empty' }}"
                            class="tomselectEml debt-filter-tomselect-daily"
                            data-placeholder="Tất cả đại lý"
                            data-livewire-model="dailyId"
                            data-livewire-live="true"
                        >
                            <option value="">Tất cả đại lý</option>
                            @foreach ($this->dailies as $daily)
                                <option value="{{ $daily->id }}" @selected((int) $dailyId === (int) $daily->id)>{{ $daily->namevi ?: $daily->nameen }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            <section class="debt-filter-section">
                <div class="debt-filter-section-heading">
                    <div>
                        <h3>Trạng thái</h3>
                        <p>Tiến trình chốt cước và thanh toán</p>
                    </div>
                </div>
                <div class="debt-filter-grid debt-filter-grid-3">
                    <div class="debt-filter-field">
                        <label class="debt-filter-label">Trạng thái công nợ</label>
                        <select
                            wire:key="filter-status-{{ $status ?: 'all' }}"
                            class="tomselectEml debt-filter-tomselect-daily"
                            data-placeholder="Tất cả trạng thái"
                            data-livewire-model="status"
                            data-livewire-live="true"
                        >
                            <option value="">Tất cả trạng thái</option>
                            @foreach (DebtStatusEnum::cases() as $debtStatus)
                                <option value="{{ $debtStatus->value }}" @selected($status === $debtStatus->value)>{{ $debtStatus->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                <flux:button type="button" wire:click="resetFilters" variant="ghost">Làm mới</flux:button>
                <flux:modal.close>
                    <flux:button type="button" variant="primary">Áp dụng</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    @if ($showCreateModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-neutral-950/45 backdrop-blur-sm" wire:click="$set('showCreateModal', false)"></div>
            <div class="relative w-full max-w-4xl overflow-visible rounded-2xl bg-white shadow-2xl">
                <div class="border-b border-neutral-100 px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                                <flux:icon.plus class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-neutral-950">Tạo công nợ đại lý</h2>
                                <p class="mt-1 text-sm text-neutral-500">Hệ thống gom các order theo đại lý chưa nằm trong công nợ đang mở.</p>
                            </div>
                        </div>
                        <button type="button" wire:click="$set('showCreateModal', false)" class="rounded-lg p-2 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700">
                            <flux:icon.x-mark class="size-5" />
                        </button>
                    </div>
                </div>
                <div class="grid gap-4 p-6 md:grid-cols-2">
                    <div class="debt-create-field md:col-span-2">
                        <label class="debt-create-label">Đại lý</label>
                        <select
                            wire:key="create-daily-{{ $createDailyId ?: 'empty' }}"
                            class="tomselectEml debt-create-tomselect-daily"
                            data-placeholder="Chọn đại lý"
                            data-livewire-model="createDailyId"
                            data-livewire-live="true"
                        >
                            <option value="">Chọn đại lý</option>
                            @foreach ($this->dailies as $daily)
                                <option value="{{ $daily->id }}" @selected((int) $createDailyId === (int) $daily->id)>{{ $daily->namevi ?: $daily->nameen }}</option>
                            @endforeach
                        </select>
                        @error('createDailyId') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="debt-create-field debt-date-picker-field">
                        <label class="debt-create-label">Từ ngày</label>
                        <input type="text" value="{{ $createFromDate }}" data-congnodaily-create-date data-livewire-model="createFromDate" autocomplete="off" class="debt-create-control">
                        @error('createFromDate') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="debt-create-field debt-date-picker-field">
                        <label class="debt-create-label">Đến ngày</label>
                        <input type="text" value="{{ $createToDate }}" data-congnodaily-create-date data-livewire-model="createToDate" autocomplete="off" class="debt-create-control">
                        @error('createToDate') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="debt-create-field md:col-span-2">
                        <label class="debt-create-label">Hạn thanh toán sau khi chốt (ngày)</label>
                        <input type="number" min="0" wire:model="paymentTermDays" class="debt-create-control">
                        @error('paymentTermDays') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <div class="debt-create-field">
                            <label class="debt-create-label">Ghi chú</label>
                            <textarea wire:model="note" rows="3" class="debt-create-control"></textarea>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-neutral-100 px-6 py-4">
                    <flux:button type="button" wire:click="$set('showCreateModal', false)" variant="ghost">Hủy</flux:button>
                    <flux:button type="button" wire:click="createDebt" wire:loading.attr="disabled" variant="primary" icon="plus">Tạo công nợ</flux:button>
                </div>
            </div>
        </div>
    @endif

    @script
    <script>
        const initCongNoDailyEnhancedControls = () => {
            const root = document.getElementById('congnodaily-index-page');
            if (!root) return;

            window.TomSelectHelper?.init(root);

            if (!window.flatpickr) return;

            root.querySelectorAll('input[data-congnodaily-create-date], input[data-congnodaily-filter-date]').forEach((input) => {
                if (input._flatpickr) return;
                const isFilterDate = input.hasAttribute('data-congnodaily-filter-date');

                window.flatpickr(input, {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd/m/Y',
                    allowInput: true,
                    defaultDate: input.value || null,
                    disableMobile: true,
                    clickOpens: true,
                    position: 'below left',
                    onReady: (_selectedDates, _dateStr, instance) => {
                        instance.calendarContainer.classList.add(isFilterDate ? 'debt-filter-calendar-daily' : 'debt-create-calendar-daily');
                    },
                    onChange: (_selectedDates, dateStr) => {
                        const model = input.dataset.livewireModel;
                        if (!model) return;

                        $wire.set(model, dateStr || null, true);
                    },
                });
            });
        };

        const destroyCongNoDailyEnhancedControls = () => {
            const root = document.getElementById('congnodaily-index-page');
            if (!root) return;

            root.querySelectorAll('select.debt-create-tomselect-daily, select.debt-filter-tomselect-daily').forEach((select) => {
                select.tomselect?.destroy();
                delete select.dataset.tomselectInit;
            });

            root.querySelectorAll('input[data-congnodaily-create-date], input[data-congnodaily-filter-date]').forEach((input) => {
                input._flatpickr?.destroy();
            });
        };

        initCongNoDailyEnhancedControls();

        Livewire.hook('morph.updating', ({ el }) => {
            if (
                el?.id === 'congnodaily-index-page'
                || el?.matches?.('select.debt-create-tomselect-daily, select.debt-filter-tomselect-daily, input[data-congnodaily-create-date], input[data-congnodaily-filter-date]')
                || el?.querySelector?.('select.debt-create-tomselect-daily, select.debt-filter-tomselect-daily, input[data-congnodaily-create-date], input[data-congnodaily-filter-date]')
            ) {
                destroyCongNoDailyEnhancedControls();
            }
        });

        Livewire.hook('morph.updated', () => {
            setTimeout(initCongNoDailyEnhancedControls, 0);
        });
    </script>
    @endscript
</div>
