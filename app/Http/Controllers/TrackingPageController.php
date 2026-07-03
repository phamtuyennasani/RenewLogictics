<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang tra cứu đơn công khai /theo-doi/{idbill} — khách cuối (người nhận)
 * xem hành trình đơn KHÔNG cần đăng nhập.
 *
 * Nguyên tắc dữ liệu (trang public):
 * - Chỉ hiện: mã đơn, trạng thái, timeline (thời gian/trạng thái/địa điểm),
 *   người nhận đã che bớt (tên viết tắt, SĐT giữ 3 số cuối), tuyến đến.
 * - KHÔNG hiện: giá/cước, thông tin người gửi, thông tin nội bộ.
 * - KHÔNG gọi TrackingMore tại đây (tốn quota API + chậm trang public);
 *   timeline lấy từ order_history do hệ thống ghi.
 */
class TrackingPageController extends Controller
{
    public function __invoke(Request $request, ?string $idbill = null): View
    {
        $keyword = trim((string) ($idbill ?? $request->query('code', '')));

        // Mã đơn/tracking hợp lệ chỉ gồm chữ-số và -_. — chặn input rác sớm.
        if ($keyword !== '' && (mb_strlen($keyword) > 64 || ! preg_match('/^[A-Za-z0-9._-]+$/', $keyword))) {
            $keyword = '';
        }

        $order = $keyword === '' ? null : Order::query()
            ->where('id_bill', $keyword)
            ->orWhere('tracking_code', $keyword)
            ->first();

        return view('tracking.index', [
            'keyword' => $keyword,
            'order' => $order,
            'statusLabel' => $order?->bill_status?->label(),
            'statusColor' => $order?->bill_status?->color(),
            'isDelivered' => $order?->bill_status === OrderStatusEnum::DA_GIAO,
            'timeline' => $order ? $this->timeline($order) : [],
            'receiverMasked' => $order ? $this->maskedReceiver($order) : null,
        ]);
    }

    /**
     * @return array<int, array{time: ?string, status: ?string, location: ?string}>
     */
    protected function timeline(Order $order): array
    {
        return $order->histories()
            ->where(function ($query) {
                $query->whereNotNull('thoigian')
                    ->orWhereNotNull('trangthai')
                    ->orWhereIn('action', ['tracking_history', 'tracking_status_auto']);
            })
            ->orderByRaw('COALESCE(thoigian, created_at) desc')
            ->get()
            ->map(fn (OrderHistory $history) => [
                'time' => ($history->thoigian ?: $history->created_at)?->format('d/m/Y H:i'),
                'status' => $history->trangthai ?: null,
                'location' => $history->diadiem ?: null,
            ])
            ->filter(fn (array $row) => $row['status'] !== null || $row['location'] !== null)
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, phone: string, destination: string}
     */
    protected function maskedReceiver(Order $order): array
    {
        $receiver = $order->receiver ?? [];

        $destination = implode(', ', array_filter([
            data_get($receiver, 'city'),
            data_get($receiver, 'state'),
            data_get($receiver, 'country', data_get($receiver, 'postcode')),
        ]));

        return [
            'name' => $this->maskName((string) data_get($receiver, 'fullname', data_get($receiver, 'tenlienhe', ''))),
            'phone' => $this->maskPhone((string) data_get($receiver, 'phone', '')),
            'destination' => $destination !== '' ? $destination : '-',
        ];
    }

    /** "Nguyễn Văn Đức" → "N*** V*** Đ***" */
    protected function maskName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '-';
        }

        return collect(preg_split('/\s+/u', $name))
            ->map(fn (string $word) => mb_substr($word, 0, 1).'***')
            ->implode(' ');
    }

    /** "0909123456" → "*******456" */
    protected function maskPhone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '-';
        }
        if (mb_strlen($phone) <= 3) {
            return str_repeat('*', mb_strlen($phone));
        }

        return str_repeat('*', mb_strlen($phone) - 3).mb_substr($phone, -3);
    }
}
