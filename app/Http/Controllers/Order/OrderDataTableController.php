<?php

namespace App\Http\Controllers\Order;

use App\Actions\Order\RecordTrackingHistoryAction;
use App\Enums\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\OrderAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class OrderDataTableController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return DataTables::eloquent($this->query($request))
            ->addColumn('check', fn (Order $order) => '<input type="checkbox" class="order-check h-4 w-4 rounded border-neutral-300" value="'.$order->id.'">')
            ->addColumn('order_code', fn (Order $order) => view('pages.order.partials.index.order-code', compact('order'))->render())
            ->addColumn('status_badge', fn (Order $order) => '<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold '.($order->bill_status?->color() ?? 'bg-neutral-100 text-neutral-700').'">'.e($order->bill_status?->label() ?? 'Chưa rõ').'</span>')
            ->addColumn('dates', fn (Order $order) => view('pages.order.partials.index.dates', compact('order'))->render())
            ->addColumn('assignee', fn (Order $order) => view('pages.order.partials.index.assignee', compact('order'))->render())
            ->addColumn('sender_info', fn (Order $order) => view('pages.order.partials.index.contact', ['data' => $order->sender])->render())
            ->addColumn('receiver_info', fn (Order $order) => view('pages.order.partials.index.contact', ['data' => $order->receiver, 'receiver' => true])->render())
            ->addColumn('service_info', fn (Order $order) => view('pages.order.partials.index.service', compact('order'))->render())
            ->addColumn('package_info', fn (Order $order) => view('pages.order.partials.index.packages', compact('order'))->render())
            ->addColumn('sale_total', fn (Order $order) => '<span class="font-semibold text-primary-700">'.$this->money(data_get($order->payment_cuocban, 'total_tongcuoc', data_get($order->payment_cuocban, 'tongcuoc', 0))).'</span>')
            ->addColumn('cost_total', fn (Order $order) => auth()->user()->hasAnyRole(['admin', 'manager', 'ketoan']) ? $this->money(data_get($order->payment_cuocvon, 'total_tongcuoc', data_get($order->payment_cuocvon, 'tongcuoc', 0))) : '—')
            ->addColumn('profit_total', fn (Order $order) => $this->profitHtml($order))
            ->addColumn('payment_state', fn (Order $order) => view('pages.order.partials.index.payment-state', compact('order'))->render())
            ->addColumn('actions', fn (Order $order) => view('pages.order.partials.index.actions', compact('order'))->render())
            ->rawColumns([
                'check',
                'order_code',
                'status_badge',
                'dates',
                'assignee',
                'sender_info',
                'receiver_info',
                'service_info',
                'package_info',
                'sale_total',
                'cost_total',
                'profit_total',
                'payment_state',
                'actions',
            ])
            ->toJson();
    }

    public function bulkStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'status' => ['required', 'string'],
        ]);

        $status = OrderStatusEnum::tryFrom($data['status']);
        abort_if(! $status, 422, 'Trạng thái không hợp lệ.');

        $count = 0;
        foreach ($this->query($request)->whereIn('orders.id', $data['ids'])->get() as $order) {
            if (! OrderAccess::canEditOrder($request->user(), $order)) {
                continue;
            }

            $payload = ['bill_status' => $status];
            if ($status === OrderStatusEnum::DA_NHAN_HANG && blank($order->ngaynhanhang)) {
                $payload['ngaynhanhang'] = now();
            }
            if (in_array($status, [OrderStatusEnum::DUYET_XUAT_HANG, OrderStatusEnum::DANG_PHAT_HANG], true) && blank($order->ngayxuathang)) {
                $payload['ngayxuathang'] = now();
            }
            if ($status === OrderStatusEnum::DA_GIAO && blank($order->ngaygiaohang)) {
                $payload['ngaygiaohang'] = now();
            }

            $order->forceFill($payload)->save();
            RecordTrackingHistoryAction::execute($order, $status, now());
            $count++;
        }

        return response()->json(['message' => "Đã cập nhật {$count} đơn hàng."]);
    }

    public function deleteCancelled(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'manager']), 403);

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $orders = $this->query($request)
            ->whereIn('orders.id', $data['ids'])
            ->where('bill_status', OrderStatusEnum::HUY->value)
            ->get();

        $count = $orders->count();
        $orders->each->delete();

        return response()->json(['message' => "Đã xóa {$count} đơn hàng đã hủy."]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'orders-'.now()->format('Ymd-His').'.csv';
        $orders = $this->query($request)->limit(3000)->get();

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Ma don', 'Ngay tao', 'Trang thai', 'Sale', 'Khach hang', 'Nguoi gui', 'Nguoi nhan', 'Cuoc ban', 'Cuoc von', 'Loi nhuan']);

            foreach ($orders as $order) {
                fputcsv($out, [
                    $order->id_bill,
                    $order->created_at?->format('d/m/Y H:i'),
                    $order->bill_status?->label(),
                    $order->sale?->fullname ?: $order->sale?->username,
                    $order->customer?->fullname ?: $order->customer?->company_name,
                    data_get($order->sender, 'company') ?: data_get($order->sender, 'fullname'),
                    data_get($order->receiver, 'company') ?: data_get($order->receiver, 'fullname'),
                    data_get($order->payment_cuocban, 'total_tongcuoc'),
                    data_get($order->payment_cuocvon, 'total_tongcuoc'),
                    data_get($order->payment_loinhuan, 'loinhuan'),
                ]);
            }

            fclose($out);
        }, $filename);
    }

    protected function query(Request $request): Builder
    {
        $user = $request->user();

        return Order::query()
            ->with([
                'sale:id,fullname,username,code',
                'customer:id,fullname,company_name,phone,code',
                'creator:id,fullname,username,code',
                'dichvu:id,namevi',
                'chiNhanhNhanHang:id,namevi',
                'packages:id_order,g_weight,v_weight,c_weight,row_g_weight,row_v_weight,row_c_weight',
            ])
            ->when($user->hasRole('sale'), fn ($q) => $q->where('id_sale', $user->id))
            ->when($user->hasRole('ctv'), fn ($q) => $q->where('id_customer', $user->id))
            ->when($user->hasRole('cs'), fn ($q) => $q->where(fn ($sub) => $sub->whereNull('id_cs')->orWhere('id_cs', $user->id)))
            ->when($request->filled('status'), fn ($q) => $q->where('bill_status', $request->string('status')))
            ->when($request->filled('fromDate'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('fromDate')))
            ->when($request->filled('toDate'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('toDate')))
            ->when($request->filled('saleId'), fn ($q) => $q->where('id_sale', $request->integer('saleId')))
            ->when($request->filled('customerId'), fn ($q) => $q->where('id_customer', $request->integer('customerId')))
            ->when($request->filled('serviceId'), fn ($q) => $q->where('service->id_dichvu', $request->integer('serviceId')))
            ->when($request->filled('branchId'), fn ($q) => $q->where('service->id_chinhanh_nhanhang', $request->integer('branchId')))
            ->when(filled($request->input('search.value')), function ($q) use ($request) {
                $keyword = '%'.trim((string) $request->input('search.value')).'%';
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('id_bill', 'like', $keyword)
                        ->orWhere('tracking_code', 'like', $keyword)
                        ->orWhere('mathamchieu', 'like', $keyword)
                        ->orWhere('sender', 'like', $keyword)
                        ->orWhere('receiver', 'like', $keyword)
                        ->orWhereHas('sale', fn ($sale) => $sale->where('fullname', 'like', $keyword)->orWhere('code', 'like', $keyword))
                        ->orWhereHas('customer', fn ($customer) => $customer->where('fullname', 'like', $keyword)->orWhere('company_name', 'like', $keyword)->orWhere('code', 'like', $keyword));
                });
            })
            ->latest('orders.id');
    }

    protected function money(mixed $value): string
    {
        return number_format((float) preg_replace('/[^\d.-]/', '', (string) ($value ?? 0)), 0).' đ';
    }

    protected function profitHtml(Order $order): string
    {
        if (! auth()->user()->hasAnyRole(['admin', 'manager', 'ketoan'])) {
            return '—';
        }

        $profit = (float) data_get($order->payment_loinhuan, 'loinhuan', 0);
        $class = $profit >= 0 ? 'text-emerald-700' : 'text-red-700';

        return '<span class="font-semibold '.$class.'">'.$this->money($profit).'</span>';
    }
}
