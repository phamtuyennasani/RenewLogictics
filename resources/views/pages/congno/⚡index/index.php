<?php
use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Models\CongNo;
use App\Models\CongNoDetail;
use App\Models\Order;
use App\Models\User;
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

new #[Layout('layouts.app')] #[Title('Công nợ khách hàng')] class extends Component {
    use WithPagination, WithoutUrlPagination;

    public string $keyword = '';

    public string $status = '';

    public ?int $saleId = null;

    public ?int $customerId = null;

    public ?string $fromDate = null;

    public ?string $toDate = null;

    public bool $showCreateModal = false;
    public array $selectedIds = [];
    public ?int $createSaleId = null;
    public ?int $createCustomerId = null;
    public ?string $createFromDate = null;
    public ?string $createToDate = null;
    public int $paymentTermDays = 7;
    public ?string $note = null;

    public bool $isSaleUser = false;
    public bool $isCtvUser = false;

    public function mount(): void
    {
        abort_unless(\Gate::allows('congno.index'), 403);

        $user = auth()->user();

        $this->isSaleUser = $user->hasRole('sale');
        $this->isCtvUser = $user->hasRole('ctv');
        $this->fromDate ??= now()->subDays(30)->format('Y-m-d');
        $this->toDate ??= now()->format('Y-m-d');
        $this->saleId = $this->isSaleUser ? $user->id : null;
        $this->createSaleId ??= $this->isSaleUser ? $user->id : null;
        $this->createCustomerId ??= $user->hasRole('ctv') ? $user->id : null;
        $this->createFromDate ??= now()->subDays(30)->format('Y-m-d');
        $this->createToDate ??= now()->format('Y-m-d');
        $this->paymentTermDays = (int) data_get(config('system', []), 'hanthanhtoan', 7);
    }

    public function updating($property): void
    {
        if (in_array($property, ['keyword', 'status', 'saleId', 'customerId', 'fromDate', 'toDate'], true)) {
            $this->resetPage();
            $this->selectedIds = [];
        }
    }

    public function openCreateModal(): void
    {
        $user = auth()->user();
        $this->createSaleId = $user->hasRole('sale') ? $user->id : $this->saleId;
        $this->createCustomerId = $user->hasRole('ctv') ? $user->id : $this->customerId;
        $this->createFromDate = now()->subDays(30)->format('Y-m-d');
        $this->createToDate = now()->format('Y-m-d');
        $this->paymentTermDays = (int) data_get(config('system', []), 'hanthanhtoan', 7);
        $this->note = null;
        $this->showCreateModal = true;
    }

    public function routes(): array
    {
        return [
            'datatable' => route('congno.datatable'),
            'customers' => route('congno.customers'),
            'deleteSelected' => route('congno.delete-selected'),
            'export' => route('congno.export'),
        ];
    }

    public function createDebt(array $payload = []): void
    {
        abort_unless($this->canCreateDebt(), 403);

        if ($payload !== []) {
            $this->createSaleId = filled($payload['createSaleId'] ?? null) ? (int) $payload['createSaleId'] : null;
            $this->createCustomerId = filled($payload['createCustomerId'] ?? null) ? (int) $payload['createCustomerId'] : null;
            $this->createFromDate = filled($payload['createFromDate'] ?? null) ? (string) $payload['createFromDate'] : null;
            $this->createToDate = filled($payload['createToDate'] ?? null) ? (string) $payload['createToDate'] : null;
            $this->note = filled($payload['note'] ?? null) ? (string) $payload['note'] : null;
        }

        $user = auth()->user();
        if ($user->hasRole('sale') && ! $user->hasAnyRole(['admin', 'manager', 'ketoan'])) {
            // Sale chỉ được tạo công nợ với chính họ là người phụ trách.
            $this->createSaleId = (int) $user->id;
        }

        $data = $this->validate([
            'createSaleId' => ['required', 'integer', 'exists:user,id'],
            'createCustomerId' => ['required', 'integer', 'exists:user,id'],
            'createFromDate' => ['required', 'date'],
            'createToDate' => ['required', 'date', 'after_or_equal:createFromDate'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'createSaleId' => 'Sale phụ trách',
            'createCustomerId' => 'Khách hàng / CTV',
            'createFromDate' => 'Từ ngày',
            'createToDate' => 'Đến ngày',
        ]);

        $from = Carbon::parse($data['createFromDate'])->startOfDay();
        $to = Carbon::parse($data['createToDate'])->endOfDay();

        $orders = $this->eligibleOrdersQuery($from, $to, (int) $data['createSaleId'], (int) $data['createCustomerId'])
            ->with(['packages', 'sale:id,fullname,code', 'customerAccount:id,fullname,code'])
            ->get();

        if ($orders->isEmpty()) {
            Flux::toast(heading: 'Chưa có dữ liệu', text: 'Không tìm thấy order phù hợp để tạo công nợ.', variant: 'warning');
            return;
        }

        $debt = DB::transaction(function () use ($data, $from, $to, $orders) {
            $debt = CongNo::create([
                'sohoadon' => CongNo::generateSoHoaDon($from->format('dm'), $to->format('dm')),
                'id_sale' => $data['createSaleId'],
                'id_customer' => $data['createCustomerId'],
                'id_ctv' => $data['createCustomerId'],
                'id_user' => auth()->id(),
                'id_ketoan' => auth()->user()->hasRole('ketoan') ? auth()->id() : null,
                'tungay' => $from,
                'denngay' => $to,
                'ngaytaohoadon' => now(),
                'songaythanhtoan' => $this->paymentTermDays,
                'status' => DebtStatusEnum::MOI_TAO,
                'type' => 'customer',
                'ghichu' => $data['note'],
            ]);

            $rows = $orders->map(fn (Order $order) => [
                'id_congno' => $debt->id,
                'id_order' => $order->id,
                ...$this->snapshotForOrder($order),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            CongNoDetail::insert($rows);
            $debt->syncTotalsFromDetails();

            return $debt;
        });

        $this->showCreateModal = false;
        Flux::toast(heading: 'Đã tạo công nợ', text: "Mã {$debt->sohoadon} gồm {$debt->total_orders} order.", variant: 'success');
        $this->redirectRoute('congno.show', ['id' => $debt->uuid], navigate: true);
    }

    public function setStatus(string $status = ''): void
    {
        $this->status = $status;
        $this->selectedIds = [];
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $user = auth()->user();
        $this->keyword = '';
        $this->status = '';
        $this->saleId = $user->hasRole('sale') ? $user->id : null;
        $this->customerId = null;
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
        abort_unless($this->canDeleteDebt(), 403);

        $ids = collect($this->selectedIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            Flux::toast(heading: 'Chưa chọn công nợ', text: 'Vui lòng chọn ít nhất một dòng để xóa.', variant: 'warning');
            return;
        }

        $user = auth()->user();
        $debts = CongNo::query()
            ->whereIn('id', $ids)
            ->where('type', 'customer')
            ->where('status', '!=', DebtStatusEnum::DA_THANH_TOAN->value)
            ->when(
                $user->hasRole('ketoan') && ! $user->hasAnyRole(['admin', 'manager']),
                fn ($q) => $q->where(function ($w) use ($user) {
                    $w->whereNull('id_ketoan')->orWhere('id_ketoan', $user->id);
                })
            )
            ->get();

        if ($debts->isEmpty()) {
            Flux::toast(heading: 'Không thể xóa', text: 'Bạn chỉ được xóa công nợ chưa có kế toán phụ trách hoặc do bạn phụ trách.', variant: 'warning');
            return;
        }

        $blockedCount = $debts->filter(fn (CongNo $debt) => $debt->payments()
            ->whereIn('status', InvoicePaymentStatusEnum::pendingValues())
            ->exists())->count();

        if ($blockedCount > 0) {
            Flux::toast(heading: 'Không thể xóa', text: "{$blockedCount} công nợ còn hóa đơn đang xử lý.", variant: 'warning');
            return;
        }

        DB::transaction(function () use ($debts) {
            foreach ($debts as $debt) {
                $debt->orders()->update([
                    'customer_payment_status' => null,
                    'customer_paid_at' => null,
                ]);
                $debt->delete();
            }
        });

        $this->selectedIds = [];
        unset($this->items, $this->statusCounts, $this->summary);

        Flux::toast(heading: 'Đã hủy công nợ', text: "Đã hủy {$debts->count()} công nợ và giữ lại lịch sử hóa đơn.", variant: 'success');
    }

    public function exportExcel()
    {
        $query = $this->baseDebtQuery()->latest('id');
        $fileName = 'cong-no-khach-hang-'.now()->format('Ymd-His').'.xlsx';

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
                    'Khách hàng',
                    'Sale phụ trách',
                    'Từ ngày',
                    'Đến ngày',
                    'Số order',
                    'Tổng cước bán',
                    'Đã thanh toán',
                    'Còn lại',
                    'Trạng thái',
                ];
            }

            public function map($debt): array
            {
                return [
                    $debt->sohoadon,
                    $debt->customer?->fullname ?: $debt->customer?->username,
                    $debt->sale?->fullname ?: $debt->sale?->username,
                    $debt->tungay?->format('d/m/Y'),
                    $debt->denngay?->format('d/m/Y'),
                    (int) $debt->total_orders,
                    (float) $debt->total_cuocban,
                    (float) $debt->paid_amount,
                    (float) $debt->remaining_amount,
                    $debt->status?->label(),
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
    public function debtStatuses(): array
    {
        return array_values(array_filter(
            DebtStatusEnum::cases(),
            fn (DebtStatusEnum $status) => ! in_array($status, [
                DebtStatusEnum::QUA_HAN,
                DebtStatusEnum::DA_HUY,
            ], true)
        ));
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
        $total = (float) $items->sum('total_cuocban');
        $paid = (float) $items->sum('paid_amount');
        $remaining = (float) $items->sum(fn (CongNo $debt) => $debt->remaining_amount);

        return [
            'total' => $total,
            'paid' => $paid,
            'remaining' => $remaining,
            'paid_percent' => $this->percentOf($paid, $total),
            'remaining_percent' => $this->percentOf($remaining, $total),
        ];
    }

    #[Computed]
    public function sales()
    {
        return User::role('sale')->orderBy('fullname')->get(['id', 'fullname', 'username', 'code']);
    }

    #[Computed]
    public function customers()
    {
        return User::role('ctv')
            ->when($this->showCreateModal && $this->createSaleId, fn ($q) => $q->where('id_sale', $this->createSaleId))
            ->when(! $this->showCreateModal && $this->saleId, fn ($q) => $q->where('id_sale', $this->saleId))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username', 'code', 'id_sale']);
    }

    protected function baseDebtQuery(bool $includeStatus = true)
    {
        $user = auth()->user();

        return CongNo::query()
            ->with(['sale:id,fullname,username,code', 'customer:id,fullname,username,code', 'ketoan:id,fullname,username,code'])
            ->where('type', 'customer')
            ->when($user->hasRole('sale'), fn ($q) => $q->where('id_sale', $user->id))
            ->when($user->hasRole('ctv'), fn ($q) => $q->where('id_customer', $user->id))
            ->when($includeStatus && $this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->saleId, fn ($q) => $q->where('id_sale', $this->saleId))
            ->when($this->customerId, fn ($q) => $q->where('id_customer', $this->customerId))
            ->when($this->fromDate, fn ($q) => $q->whereDate('created_at', '>=', Carbon::parse($this->fromDate)))
            ->when($this->toDate, fn ($q) => $q->whereDate('created_at', '<=', Carbon::parse($this->toDate)))
            ->when($this->keyword !== '', function ($q) {
                $keyword = '%'.trim($this->keyword).'%';
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('sohoadon', 'like', $keyword)
                        ->orWhere('sohoadon_thamchieu', 'like', $keyword)
                        ->orWhereHas('sale', fn ($sale) => $sale->where('fullname', 'like', $keyword)->orWhere('code', 'like', $keyword))
                        ->orWhereHas('customer', fn ($customer) => $customer->where('fullname', 'like', $keyword)->orWhere('code', 'like', $keyword));
                });
            });
    }

    protected function eligibleOrdersQuery(Carbon $from, Carbon $to, int $saleId, int $customerId)
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('id_sale', $saleId)
            ->where('id_customer', $customerId)
            ->where('bill_status', '!=', OrderStatusEnum::HUY->value)
            ->whereNotNull('sale_price_locked_at')
            ->where(function ($q) {
                $q->whereNull('customer_payment_status')
                    ->orWhereNotIn('customer_payment_status', [
                        DebtStatusEnum::DA_CHOT_CUOC->value,
                        DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN->value,
                        DebtStatusEnum::DA_THANH_TOAN->value,
                    ]);
            })
            ->whereDoesntHave('congNoDetails.congNo', fn ($q) => $q->where('status', '!=', DebtStatusEnum::DA_THANH_TOAN->value));
    }

    protected function snapshotForOrder(Order $order): array
    {
        $weight = (float) $order->packages->sum(fn ($package) => (float) ($package->row_c_weight ?: $package->c_weight ?: 0));
        $snapshot = [
            'order_code' => $order->id_bill,
            'sale_total' => $this->number(data_get($order->payment_cuocban, 'total_tongcuoc', 0)),
            'cost_total' => $this->number(data_get($order->payment_cuocvon, 'total_tongcuoc', 0)),
            'base_total' => $this->number(data_get($order->payment_cuocgoc, 'total_tongcuoc', 0)),
            'vat' => $this->number(data_get($order->payment_cuocban, 'total_vat', 0)),
            'ppxd' => $this->number(data_get($order->payment_cuocban, 'ppxd_amount', 0)),
            'fee' => $this->number(data_get($order->payment_cuocban, 'total_phuphi', 0)),
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

    public function canCreateDebt(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'ketoan', 'sale']);
    }

    public function canDeleteDebt(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'ketoan']);
    }

    public function canManage(): bool
    {
        return $this->canCreateDebt();
    }

    public function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' đ';
    }

    public function percentOf(mixed $value, mixed $total): float
    {
        $total = (float) $total;

        if ($total <= 0) {
            return 0;
        }

        return round(min(100, max(0, ((float) $value / $total) * 100)), 2);
    }

    public function render()
    {
        return $this->view();
    }
};
