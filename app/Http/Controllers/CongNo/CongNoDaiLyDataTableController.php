<?php

namespace App\Http\Controllers\CongNo;

use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\CongNoDaiLy;
use App\Models\News;
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

class CongNoDaiLyDataTableController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $response = DataTables::eloquent($this->query($request))
            ->addColumn('check', fn (CongNoDaiLy $debt) => '<label class="debt-checkbox relative mx-auto flex w-fit cursor-pointer select-none items-center justify-center"><input type="checkbox" class="daily-debt-check peer sr-only" value="'.$debt->id.'"><span class="flex h-[18px] w-[18px] items-center justify-center rounded-md border border-neutral-300 bg-white transition peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-hover:border-primary-400"></span><svg class="pointer-events-none absolute hidden h-3 w-3 text-white peer-checked:block" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.5l5 5 10-11" /></svg></label>')
            ->addColumn('debt_code', fn (CongNoDaiLy $debt) => '<a href="'.route('congno.daily.show', $debt->uuid).'" class="font-bold text-primary-700 hover:text-primary-800">'.$debt->sohoadon.'</a><div class="mt-0.5 text-xs text-neutral-500">Tạo '.$debt->created_at?->format('d/m/Y H:i').'</div>')
            ->addColumn('status_badge', fn (CongNoDaiLy $debt) => '<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold '.$debt->status->color().'">'.$debt->status->label().'</span>')
            ->addColumn('daily_info', fn (CongNoDaiLy $debt) => '<div class="max-w-[220px] truncate font-semibold text-neutral-900">'.e($debt->daily?->namevi ?: $debt->daily?->nameen ?: 'Chưa rõ đại lý').'</div><div class="mt-0.5 max-w-[220px] truncate text-xs text-neutral-500">Kế toán: '.e($debt->ketoan?->fullname ?: $debt->ketoan?->username ?: '-').'</div>')
            ->addColumn('creator_info', fn (CongNoDaiLy $debt) => '<div class="max-w-[180px] truncate font-semibold text-neutral-900">'.e($debt->creator?->fullname ?: $debt->creator?->username ?: '-').'</div>'.($debt->creator?->code ? '<div class="mt-0.5 max-w-[180px] truncate text-xs text-neutral-500">'.e($debt->creator->code).'</div>' : ''))
            ->addColumn('period_info', fn (CongNoDaiLy $debt) => '<div class="font-semibold text-neutral-900">'.$debt->tungay?->format('d/m/Y').' - '.$debt->denngay?->format('d/m/Y').'</div>')
            ->addColumn('volume_info', fn (CongNoDaiLy $debt) => '<div class="font-semibold text-neutral-900">'.number_format((int) $debt->total_orders).' đơn</div><div class="mt-0.5 text-xs text-neutral-500">'.number_format((float) $debt->total_weight, 1, ',', '.').' kg</div>')
            ->addColumn('total_amount', fn (CongNoDaiLy $debt) => '<p class="text-right mb-0"><span class="font-semibold text-neutral-950">'.$this->money($debt->total_cuocvon).'</span></p>')
            ->addColumn('actions', fn (CongNoDaiLy $debt) => '<p class="text-right mb-0"><a href="'.route('congno.daily.show', $debt->uuid).'" class="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50">Chi tiết</a></p>')
            ->setRowId(fn (CongNoDaiLy $debt) => 'daily-debt-'.$debt->id)
            ->rawColumns(['check', 'debt_code', 'status_badge', 'daily_info', 'creator_info', 'period_info', 'volume_info', 'total_amount', 'actions'])
            ->toJson();

        $payload = $response->getData(true);
        $payload['statusCounts'] = $this->statusCounts($request);
        $payload['summary'] = $this->summary($request);

        return response()->json($payload);
    }

    public function deleteSelected(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'manager', 'ketoan']), 403);

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $user = $request->user();
        $debts = $this->query($request)
            ->whereIn('congno_daily.id', $data['ids'])
            ->whereNotIn('status', [DebtStatusEnum::DA_THANH_TOAN->value, DebtStatusEnum::DA_HUY->value])
            ->when(
                $user->hasRole('ketoan') && ! $user->hasAnyRole(['admin', 'manager']),
                fn ($q) => $q->where(function ($w) use ($user) {
                    $w->whereNull('id_ketoan')->orWhere('id_ketoan', $user->id);
                })
            )
            ->get();

        if ($debts->isEmpty()) {
            return response()->json([
                'message' => 'Bạn chỉ được xóa công nợ đại lý chưa có kế toán phụ trách hoặc do bạn phụ trách.',
            ], 403);
        }

        $blockedCount = $debts->filter(fn (CongNoDaiLy $debt) => $debt->payments()
            ->whereIn('status', InvoicePaymentStatusEnum::pendingValues())
            ->exists())->count();

        if ($blockedCount > 0) {
            return response()->json([
                'message' => "Không thể xóa {$blockedCount} công nợ đại lý còn hóa đơn đang xử lý.",
            ], 422);
        }

        DB::transaction(function () use ($debts) {
            foreach ($debts as $debt) {
                $debt->orders()->update([
                    'agency_payment_status' => null,
                    'agency_paid_at' => null,
                ]);
                $debt->writeActivityLog(
                    action: 'cancelled',
                    title: 'Hủy công nợ đại lý',
                    fromStatus: $debt->status,
                    toStatus: DebtStatusEnum::DA_HUY,
                    metadata: array_filter([
                        'total_orders' => (int) $debt->total_orders,
                        'total_amount' => (float) $debt->total_cuocvon,
                    ], fn ($v) => $v !== null),
                );
                $debt->forceFill([
                    'status' => DebtStatusEnum::DA_HUY,
                ])->save();
            }
        });

        return response()->json(['message' => "Đã hủy {$debts->count()} công nợ đại lý và giữ lại lịch sử hóa đơn."]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = $this->query($request)->latest('congno_daily.id');
        $fileName = 'cong-no-dai-ly-'.now()->format('Ymd-His').'.xlsx';

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
                return ['Mã công nợ', 'Đại lý', 'Từ ngày', 'Đến ngày', 'Số order', 'Tổng cước vốn', 'Đã thanh toán', 'Còn lại', 'Trạng thái'];
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
                ];
            }
        }, $fileName);
    }

    protected function query(Request $request, bool $includeStatus = true): Builder
    {
        return CongNoDaiLy::query()
            ->with(['daily:id,namevi,nameen', 'creator:id,fullname,username,code', 'ketoan:id,fullname,username,code'])
            ->when($includeStatus && $request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('dailyId'), fn ($q) => $q->where('id_daily', $request->integer('dailyId')))
            ->when($request->filled('fromDate'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('fromDate')))
            ->when($request->filled('toDate'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('toDate')))
            ->when(filled($request->input('search.value')), function ($q) use ($request) {
                $keyword = '%'.trim((string) $request->input('search.value')).'%';
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('sohoadon', 'like', $keyword)
                        ->orWhere('sohoadon_thamchieu', 'like', $keyword)
                        ->orWhereHas('daily', fn ($daily) => $daily->where('namevi', 'like', $keyword)->orWhere('nameen', 'like', $keyword));
                });
            })
            ->latest('congno_daily.id');
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
        $items = $this->query($request, includeStatus: false)
            ->where('status', '!=', DebtStatusEnum::DA_HUY->value)
            ->withSum([
                'details as active_total_cuocvon' => fn ($query) => $query
                    ->whereHas('order', fn ($order) => $order->where('bill_status', '!=', OrderStatusEnum::HUY->value)),
            ], 'cuocvon')
            ->get();
        $total = (float) $items->sum('active_total_cuocvon');
        $paid = (float) $items->sum('paid_amount');
        $remaining = (float) $items->sum(fn (CongNoDaiLy $debt) => max(0, (float) $debt->active_total_cuocvon - (float) $debt->paid_amount));
        $unpaidCount = $items->where('status', '!=', DebtStatusEnum::DA_THANH_TOAN)->count();
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

    protected function percentOf(mixed $value, mixed $total): float
    {
        $total = (float) $total;
        if ($total <= 0) return 0;

        return round(min(100, max(0, ((float) $value / $total) * 100)), 2);
    }

    protected function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' đ';
    }

}
