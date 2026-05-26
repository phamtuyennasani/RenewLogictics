<?php

namespace App\Http\Controllers\CongNo;

use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CongNo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\DataTables\Facades\DataTables;

class CongNoDataTableController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $response = DataTables::eloquent($this->query($request))
            ->addColumn('check', fn (CongNo $debt) => '<label class="debt-checkbox relative mx-auto flex w-fit cursor-pointer select-none items-center justify-center"><input type="checkbox" class="debt-check peer sr-only" value="'.$debt->id.'"><span class="flex h-[18px] w-[18px] items-center justify-center rounded-md border border-neutral-300 bg-white transition peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-hover:border-primary-400"></span><svg class="pointer-events-none absolute hidden h-3 w-3 text-white peer-checked:block" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.5l5 5 10-11" /></svg></label>')
            ->addColumn('debt_code', fn (CongNo $debt) => '<a wire:navigate href="'.route('congno.show', $debt->uuid).'" class="font-bold text-primary-700 hover:text-primary-800">'.$debt->sohoadon.'</a><div class="mt-0.5 text-xs text-neutral-500">Tạo '.$debt->created_at?->format('d/m/Y H:i').'</div>')
            ->addColumn('status_badge', fn (CongNo $debt) => '<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold '.$debt->status->color().'">'.$debt->status->label().'</span>')
            ->addColumn('customer_info', fn (CongNo $debt) => '<div class="max-w-[300px] truncate font-semibold text-neutral-900 whitespace-pre-line">'.e($this->customerCompanyLabel($debt)).'</div>')
            ->addColumn('sale_info', fn (CongNo $debt) => '<div class="max-w-[200px] truncate font-semibold text-neutral-800">'.e($this->saleLabel($debt)).'</div>')
            ->addColumn('total_amount', fn (CongNo $debt) => '<span class="font-semibold text-neutral-950 text-center">'.$this->money($debt->total_cuocban).'</span>')
            ->addColumn('paid_amount_html', fn (CongNo $debt) => '<span class="font-semibold text-emerald-700 text-center">'.$this->money($debt->paid_amount).'</span>')
            ->addColumn('remaining_amount_html', fn (CongNo $debt) => '<span class="font-semibold text-amber-700 text-center">'.$this->money($debt->remaining_amount).'</span>')
            ->addColumn('due_date', fn (CongNo $debt) => $debt->hanthanhtoan?->format('d/m/Y') ?: '-')
            ->addColumn('actions', fn (CongNo $debt) => '<p class="text-right"><a wire:navigate href="'.route('congno.show', $debt->uuid).'" class="inline-flex h-8 items-center justify-end gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50">Chi tiết</a></p>')
            ->setRowId(fn (CongNo $debt) => 'debt-'.$debt->id)
            ->rawColumns(['check', 'debt_code', 'status_badge', 'customer_info', 'sale_info', 'total_amount', 'paid_amount_html', 'remaining_amount_html', 'actions'])
            ->toJson();

        $payload = $response->getData(true);
        $payload['statusCounts'] = $this->statusCounts($request);
        $payload['summary'] = $this->summary($request);

        return response()->json($payload);
    }

    public function customers(Request $request): JsonResponse
    {
        $user = $request->user();
        $saleId = $request->integer('saleId') ?: null;

        $customers = User::role('ctv')
            ->when($user->hasRole('sale'), fn ($q) => $q->where('id_sale', $user->id))
            ->when($user->hasRole('ctv'), fn ($q) => $q->whereKey($user->id))
            ->when(! $user->hasAnyRole(['sale', 'ctv']) && $saleId, fn ($q) => $q->where('id_sale', $saleId))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username', 'code'])
            ->map(fn (User $customer) => [
                'id' => $customer->id,
                'label' => trim(($customer->fullname ?: $customer->username).' '.($customer->code ? "({$customer->code})" : '')),
            ])
            ->values();

        return response()->json(['customers' => $customers]);
    }

    public function deleteSelected(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'manager', 'ketoan']), 403);

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $debts = $this->query($request)
            ->whereIn('congno.id', $data['ids'])
            ->where('status', '!=', DebtStatusEnum::DA_THANH_TOAN->value)
            ->get();

        $blockedCount = $debts->filter(fn (CongNo $debt) => $debt->payments()
            ->whereIn('status', InvoicePaymentStatusEnum::pendingValues())
            ->exists())->count();

        if ($blockedCount > 0) {
            return response()->json([
                'message' => "Không thể xóa {$blockedCount} công nợ còn hóa đơn đang xử lý.",
            ], 422);
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

        return response()->json(['message' => "Đã hủy {$debts->count()} công nợ chưa thanh toán và giữ lại lịch sử hóa đơn."]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = $this->query($request)->latest('congno.id');
        $fileName = 'cong-no-khach-hang-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new class($query) implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize {
            public function __construct(private Builder $query)
            {
            }

            public function query(): Builder
            {
                return $this->query;
            }

            public function headings(): array
            {
                return ['Mã công nợ', 'Khách hàng', 'Sale phụ trách', 'Từ ngày', 'Đến ngày', 'Số order', 'Tổng cước bán', 'Đã thanh toán', 'Còn lại', 'Trạng thái', 'Hạn thanh toán'];
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
                    $debt->hanthanhtoan?->format('d/m/Y'),
                ];
            }
        }, $fileName);
    }

    protected function query(Request $request, bool $includeStatus = true): Builder
    {
        $user = $request->user();

        return CongNo::query()
            ->with(['sale:id,fullname,username,code', 'customer:id,fullname,username,code,options', 'ketoan:id,fullname,username,code'])
            ->where('type', 'customer')
            ->when($user->hasRole('sale'), fn ($q) => $q->where('id_sale', $user->id))
            ->when($user->hasRole('ctv'), fn ($q) => $q->where('id_customer', $user->id))
            ->when($includeStatus && $request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('saleId'), fn ($q) => $q->where('id_sale', $request->integer('saleId')))
            ->when($request->filled('customerId'), fn ($q) => $q->where('id_customer', $request->integer('customerId')))
            ->when($request->filled('fromDate'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('fromDate')))
            ->when($request->filled('toDate'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('toDate')))
            ->when(filled($request->input('search.value')), function ($q) use ($request) {
                $keyword = '%'.trim((string) $request->input('search.value')).'%';
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('sohoadon', 'like', $keyword)
                        ->orWhere('sohoadon_thamchieu', 'like', $keyword)
                        ->orWhereHas('sale', fn ($sale) => $sale->where('fullname', 'like', $keyword)->orWhere('username', 'like', $keyword)->orWhere('code', 'like', $keyword))
                        ->orWhereHas('customer', fn ($customer) => $customer->where('fullname', 'like', $keyword)->orWhere('username', 'like', $keyword)->orWhere('code', 'like', $keyword));
                });
            })
            ->latest('congno.id');
    }

    protected function customerCompanyLabel(CongNo $debt): string
    {
        $customer = $debt->customer;

        return data_get($customer?->options, 'company.company_short_name')
            ?: data_get($customer?->options, 'company.company_name')
            ?: $customer?->fullname
            ?: $customer?->username
            ?: 'Chưa rõ khách hàng';
    }

    protected function saleLabel(CongNo $debt): string
    {
        $sale = $debt->sale;
        if (! $sale) {
            return '-';
        }

        return trim(($sale->fullname ?: $sale->username ?: '-').($sale->code ? ' - '.$sale->code : ''));
    }

    protected function statusCounts(Request $request): array
    {
        $counts = array_fill_keys(DebtStatusEnum::values(), 0);

        $this->query($request, includeStatus: false)
            ->reorder()
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

    protected function summary(Request $request): array
    {
        $items = $this->query($request, includeStatus: false)->get();
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

    protected function percentOf(mixed $value, mixed $total): float
    {
        $total = (float) $total;

        if ($total <= 0) {
            return 0;
        }

        return round(min(100, max(0, ((float) $value / $total) * 100)), 2);
    }

    protected function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' đ';
    }
}
