<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Pdf\OrderPdfRenderer;
use App\Support\OrderAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Tải PDF vận đơn / tem kiện — tính năng MỚI, độc lập với nút in trình duyệt
 * sẵn có ở trang chi tiết đơn (không thay thế luồng đó).
 */
class OrderPdfController extends Controller
{
    public function __construct(protected OrderPdfRenderer $renderer)
    {
    }

    public function label(Request $request, string $uuid): Response
    {
        $order = $this->resolveOrder($request, $uuid);

        return $this->pdfResponse(
            $this->renderer->labelPdf($order),
            $this->renderer->labelFilename($order),
        );
    }

    public function bill(Request $request, string $uuid): Response
    {
        $order = $this->resolveOrder($request, $uuid);
        $withCvck = $request->boolean('cvck');

        return $this->pdfResponse(
            $this->renderer->billPdf($order, $withCvck),
            $this->renderer->billFilename($order),
        );
    }

    /** In hàng loạt tem: GET ?ids=1,2,3 — gộp mọi kiện của các đơn vào 1 PDF. */
    public function bulkLabel(Request $request): Response
    {
        $orders = $this->resolveBulkOrders($request);

        return $this->pdfResponse(
            $this->renderer->bulkLabelPdf($orders),
            'labels-'.now()->format('Ymd-His').'-'.$orders->count().'don.pdf',
        );
    }

    /** In hàng loạt bill: GET ?ids=1,2,3 — mỗi đơn 1 trang A4, không CVCK. */
    public function bulkBill(Request $request): Response
    {
        $orders = $this->resolveBulkOrders($request);

        return $this->pdfResponse(
            $this->renderer->bulkBillPdf($orders),
            'bills-'.now()->format('Ymd-His').'-'.$orders->count().'don.pdf',
        );
    }

    protected function resolveOrder(Request $request, string $uuid): Order
    {
        $order = Order::query()->where('uuid', $uuid)->firstOrFail();

        abort_unless(OrderAccess::canView($request->user(), $order), 403);

        return $order;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Order>
     */
    protected function resolveBulkOrders(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn (string $id) => (int) trim($id))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        abort_if($ids->isEmpty(), 422, 'Chưa chọn đơn hàng nào.');
        abort_if(
            $ids->count() > OrderPdfRenderer::BULK_MAX_ORDERS,
            422,
            'Tối đa '.OrderPdfRenderer::BULK_MAX_ORDERS.' đơn mỗi lần in.',
        );

        // Row-level filter: chỉ lấy đơn user được thấy — đơn ngoài phạm vi bị
        // bỏ qua lặng lẽ (không 403 cả lô vì 1 đơn lạ trong danh sách chọn).
        $orders = OrderAccess::scopeVisibleTo(Order::query(), $request->user())
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        abort_if($orders->isEmpty(), 404, 'Không có đơn hợp lệ để in.');

        return $orders;
    }

    protected function pdfResponse(string $content, string $filename): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            // inline: mở trong tab trình duyệt, user tự bấm lưu/in từ viewer.
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
