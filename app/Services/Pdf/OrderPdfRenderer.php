<?php

namespace App\Services\Pdf;

use App\Models\Country;
use App\Models\News;
use App\Models\Order;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Picqer\Barcode\Renderers\PngRenderer;
use Picqer\Barcode\Types\TypeCode128;

/**
 * Xuất PDF vận đơn (bill) và tem kiện (label) server-side bằng Dompdf.
 *
 * Tính năng MỚI, độc lập hoàn toàn với luồng in trình duyệt sẵn có trong
 * pages/order/⚡show.blade.php (window.print + print CSS) — không thay thế,
 * không đụng tới luồng đó. PDF phục vụ: tải file, in hàng loạt về sau,
 * đính kèm email / trả qua API đối tác.
 *
 * Lưu ý kỹ thuật:
 * - Dùng class Dompdf trực tiếp (không qua facade barryvdh — facade lỗi
 *   "Cannot resolve public path" khi chạy CLI với public_html tùy biến).
 * - Template pdf/* chỉ dùng table/block — Dompdf KHÔNG hỗ trợ flex/grid.
 * - Font DejaVu Sans có sẵn trong Dompdf, hiển thị đủ dấu tiếng Việt.
 * - Barcode render PNG (Picqer) nhúng base64 — SVG trong Dompdf không ổn định.
 */
class OrderPdfRenderer
{
    /** Khổ giấy khớp print CSS hiện hành (app.css @page label/bill). */
    private const A6_POINTS = [0, 0, 298, 470];

    /**
     * Chặn PDF hàng loạt quá lớn: mỗi đơn có thể sinh nhiều trang tem
     * (mỗi kiện 1 trang), render đồng bộ trong request.
     */
    public const BULK_MAX_ORDERS = 100;

    public function labelPdf(Order $order): string
    {
        $order->loadMissing(['packages', 'dichvu:id,namevi']);

        $html = view('pdf.order-label', $this->labelData($order))->render();

        return $this->renderPdf($html, self::A6_POINTS);
    }

    public function billPdf(Order $order, bool $withCvck = false): string
    {
        $order->loadMissing(['packages', 'invoices', 'dichvu:id,namevi']);

        $html = view('pdf.order-bill', $this->billData($order, $withCvck))->render();

        return $this->renderPdf($html, 'A4');
    }

    /**
     * Gộp tem của nhiều đơn vào MỘT file PDF (mỗi kiện 1 trang, nối tiếp nhau).
     *
     * @param  \Illuminate\Support\Collection<int, Order>  $orders
     */
    public function bulkLabelPdf($orders): string
    {
        $items = $orders->map(function (Order $order): array {
            $order->loadMissing(['packages', 'dichvu:id,namevi']);

            return $this->labelData($order);
        })->all();

        return $this->renderPdf(view('pdf.bulk-labels', ['items' => $items])->render(), self::A6_POINTS);
    }

    /**
     * Gộp bill của nhiều đơn vào MỘT file PDF (mỗi đơn 1 trang A4, không CVCK —
     * CVCK là công văn ký riêng, không thuộc ngữ cảnh in hàng loạt).
     *
     * @param  \Illuminate\Support\Collection<int, Order>  $orders
     */
    public function bulkBillPdf($orders): string
    {
        $items = $orders->map(function (Order $order): array {
            $order->loadMissing(['packages', 'invoices', 'dichvu:id,namevi']);

            return $this->billData($order, false);
        })->all();

        return $this->renderPdf(view('pdf.bulk-bills', ['items' => $items])->render(), 'A4');
    }

    public function labelFilename(Order $order): string
    {
        return 'label-'.($order->id_bill ?: 'ORDER-'.$order->id).'.pdf';
    }

    public function billFilename(Order $order): string
    {
        return 'bill-'.($order->id_bill ?: 'ORDER-'.$order->id).'.pdf';
    }

    /**
     * @return array<string, mixed>
     */
    protected function labelData(Order $order): array
    {
        $orderCode = $order->id_bill ?: 'ORDER-'.$order->id;
        $sender = $order->sender ?? [];
        $receiver = $order->receiver ?? [];

        // Mỗi kiện 1 trang tem — cùng quy ước với phần in trình duyệt:
        // package.code có sẵn thì dùng, không thì fallback <mã đơn>-K<n>.
        $labelPages = [];
        foreach ($order->packages as $packageIndex => $package) {
            $pieces = max(1, (int) ($package->number_of_package ?? 1));

            for ($piece = 1; $piece <= $pieces; $piece++) {
                $code = trim((string) ($package->code ?? ''));

                $labelPages[] = [
                    'package' => $package,
                    'package_index' => $packageIndex + 1,
                    'piece' => $piece,
                    'pieces_in_row' => $pieces,
                    'package_code' => $code !== '' ? $code : $orderCode.'-K'.($packageIndex + 1),
                ];
            }
        }

        if ($labelPages === []) {
            $labelPages[] = [
                'package' => null,
                'package_index' => 1,
                'piece' => 1,
                'pieces_in_row' => 1,
                'package_code' => $orderCode.'-K1',
            ];
        }

        return [
            'order' => $order,
            'orderCode' => $orderCode,
            'sender' => $sender,
            'receiver' => $receiver,
            'labelPages' => $labelPages,
            'totalLabelPages' => count($labelPages),
            'barcodes' => collect($labelPages)
                ->mapWithKeys(fn (array $page) => [$page['package_code'] => $this->barcodePngDataUri($page['package_code'])])
                ->all(),
            'serviceName' => $this->text($order->dichvu?->namevi),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function billData(Order $order, bool $withCvck): array
    {
        $orderCode = $order->id_bill ?: 'ORDER-'.$order->id;
        $sender = $order->sender ?? [];
        $receiver = $order->receiver ?? [];

        $receiverCountryId = data_get($receiver, 'country_id', data_get($receiver, 'id_country'));
        $receiverCountry = $receiverCountryId
            ? Country::query()->whereKey($receiverCountryId)->first(['id', 'name'])
            : null;
        $receiverCountryName = $receiverCountry?->name ?: data_get($receiver, 'country', '-');
        $receiverAddress = trim(implode(', ', array_filter([
            data_get($receiver, 'address'),
            data_get($receiver, 'city'),
            data_get($receiver, 'state'),
            $receiverCountryName !== '-' ? $receiverCountryName : null,
            data_get($receiver, 'postcode'),
        ])));

        $customerForCvck = $order->id_customer
            ? User::query()->whereKey($order->id_customer)->first(['id', 'fullname', 'phone', 'email', 'options'])
            : null;

        return [
            'order' => $order,
            'orderCode' => $orderCode,
            'sender' => $sender,
            'receiver' => $receiver,
            'receiverAddress' => $receiverAddress,
            'receiverCountryName' => $receiverCountryName,
            'serviceName' => $this->text($order->dichvu?->namevi),
            'orderBarcode' => $this->barcodePngDataUri($orderCode),
            'packageCount' => $order->packages->count(),
            'grossWeight' => $order->packages->sum(fn ($p) => (float) ($p->g_weight ?? 0)),
            'volumeWeight' => $order->packages->sum(fn ($p) => (float) ($p->v_weight ?? 0)),
            'chargeableWeight' => $order->packages->sum(fn ($p) => (float) ($p->c_weight ?? 0)),
            'invoiceValue' => $order->invoices->sum(fn ($i) => (float) ($i->total ?? 0)),
            'invoiceQty' => $order->invoices->sum(fn ($i) => (int) ($i->soluong ?? 0)),
            'withCvck' => $withCvck,
            'cvckName' => $this->text($customerForCvck?->fullname ?: data_get($sender, 'company', data_get($sender, 'fullname'))),
            'cvckId' => $this->text(data_get($customerForCvck?->options ?? [], 'cccd', data_get($customerForCvck?->options ?? [], 'tax_code'))),
            'cvckAddress' => $this->text(data_get($customerForCvck?->options ?? [], 'address', data_get($sender, 'address'))),
        ];
    }

    protected function barcodePngDataUri(string $value): string
    {
        $barcode = (new TypeCode128())->getBarcode($value !== '' ? $value : '-');
        $png = (new PngRenderer())->render($barcode, 420, 64);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    protected function text(mixed $value, string $fallback = '-'): string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : $fallback;
    }

    /**
     * @param  string|array{0: float, 1: float, 2: float, 3: float}  $paper
     */
    protected function renderPdf(string $html, string|array $paper): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        // Chroot về base_path: chặn Dompdf đọc file ngoài project qua đường dẫn local.
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($paper);
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
