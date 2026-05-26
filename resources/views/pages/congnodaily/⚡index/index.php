<?php

use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
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
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;
use Flux\Flux;

new #[Layout('layouts.app')] #[Title('Công nợ đại lý')] class extends Component {
    use WithPagination, WithoutUrlPagination;

    public string $keyword = '';

    public string $status = '';

    public ?int $dailyId = null;

    public ?string $fromDate = null;

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
        $this->createDailyId ??= $this->dailyId;
        $this->createFromDate ??= now()->subDays(30)->format('Y-m-d');
        $this->createToDate ??= now()->format('Y-m-d');
        $this->paymentTermDays = (int) data_get(config('system', []), 'hanthanhtoan', 7);
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

    public function routes(): array
    {
        return [
            'datatable' => route('congno.daily.datatable'),
            'deleteSelected' => route('congno.daily.delete-selected'),
            'export' => route('congno.daily.export'),
        ];
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
                'hanthanhtoan' => now()->addDays((int) $data['paymentTermDays'])->startOfDay(),
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

            $debt->orders()->update(['agency_payment_status' => DebtStatusEnum::MOI_TAO->value]);

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

        $blockedCount = $debts->filter(fn (CongNoDaiLy $debt) => $debt->payments()
            ->whereIn('status', InvoicePaymentStatusEnum::pendingValues())
            ->exists())->count();

        if ($blockedCount > 0) {
            Flux::toast(heading: 'Không thể xóa', text: "{$blockedCount} công nợ đại lý còn hóa đơn đang xử lý.", variant: 'warning');
            return;
        }

        DB::transaction(function () use ($debts) {
            foreach ($debts as $debt) {
                $debt->orders()->update([
                    'agency_payment_status' => null,
                    'agency_paid_at' => null,
                ]);
                $debt->delete();
            }
        });

        $this->selectedIds = [];
        unset($this->items, $this->statusCounts, $this->summary);

        Flux::toast(heading: 'Đã hủy công nợ', text: "Đã hủy {$debts->count()} công nợ đại lý và giữ lại lịch sử hóa đơn.", variant: 'success');
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
        $counts = array_fill_keys($this->statusOptions(), 0);

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
        $total = (float) $items->sum('total_cuocvon');
        $paid = (float) $items->sum('paid_amount');
        $remaining = (float) $items->sum(fn (CongNoDaiLy $debt) => $debt->remaining_amount);
        $unpaidCount = $items->where('status', DebtStatusEnum::MOI_TAO)->count();
        $totalCount = $items->count();

        return [
            'total' => $total,
            'paid' => $paid,
            'remaining' => $remaining,
            'unpaid_count' => $unpaidCount,
            'total_percent' => $total > 0 ? 100 : 0,
            'paid_percent' => $this->percentOf($paid, $total),
            'remaining_percent' => $this->percentOf($remaining, $total),
            'unpaid_percent' => $this->percentOf($unpaidCount, $totalCount),
        ];
    }

    public function statusOptions(): array
    {
        return [
            DebtStatusEnum::MOI_TAO->value,
            DebtStatusEnum::DA_THANH_TOAN->value,
        ];
    }

    public function activeStatuses(): array
    {
        return [DebtStatusEnum::MOI_TAO, DebtStatusEnum::DA_THANH_TOAN];
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
                        DebtStatusEnum::MOI_TAO->value,
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

    public function percentOf(mixed $value, mixed $total): float
    {
        $total = (float) $total;

        if ($total <= 0) {
            return 0;
        }

        return round(min(100, max(0, ((float) $value / $total) * 100)), 2);
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
