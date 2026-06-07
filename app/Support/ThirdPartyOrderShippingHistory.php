<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\ShipmentLoadHistory;
use App\Services\TrackingMore\TrackingMore;
use App\Services\TrackingMore\TrackingMoreException;
use Carbon\Carbon;
use Throwable;

class ThirdPartyOrderShippingHistory
{
    public function forOrder(Order $order): array
    {
        return collect()
            ->merge($this->trackingMoreHistory($order))
            ->merge($this->shipmentLoadHistory($order))
            ->merge($this->manualOrderHistory($order))
            ->sortByDesc(fn (array $item) => $item["sort_time"] ?? 0)
            ->map(function (array $item) {
                unset($item["sort_time"]);
                return $item;
            })
            ->values()
            ->all();
    }

    private function trackingMoreHistory(Order $order): array
    {
        $requests = collect();
        $commonTrackingNumber = trim((string) $order->mathamchieu);
        $commonCourierCode = trim((string) $order->id_thamchieu);

        if ($commonTrackingNumber !== "" && $commonCourierCode !== "") {
            $requests->push([
                "label" => "Tracking chung",
                "tracking_number" => $commonTrackingNumber,
                "courier_code" => $commonCourierCode,
            ]);
        }

        foreach ($order->packages as $package) {
            $trackingNumber = trim((string) $package->mathamchieu);
            $courierCode = trim((string) $package->id_thamchieu);

            if ($trackingNumber === "" || $courierCode === "") {
                continue;
            }

            $requests->push([
                "label" => $package->code ?: "Kien #".$package->id,
                "tracking_number" => $trackingNumber,
                "courier_code" => $courierCode,
            ]);
        }

        return $requests
            ->unique(fn (array $request) => $request["courier_code"]."|".$request["tracking_number"])
            ->flatMap(fn (array $request) => $this->trackingMoreEvents($request))
            ->values()
            ->all();
    }

    private function trackingMoreEvents(array $request): array
    {
        try {
            $response = app(TrackingMore::class)->tracking()->getTrackingResults([
                "tracking_numbers" => $request["tracking_number"],
                "courier_code" => $request["courier_code"],
            ]);
        } catch (TrackingMoreException|Throwable) {
            return [];
        }

        if ((int) data_get($response, "meta.code") !== 200) {
            return [];
        }

        return collect(data_get($response, "data.0.origin_info.trackinfo", []))
            ->map(function (array $event) use ($request) {
                $time = $this->parseTime(data_get($event, "checkpoint_date"));
                $status = (string) (data_get($event, "checkpoint_delivery_status") ?: data_get($event, "delivery_status") ?: "");
                $detail = (string) (data_get($event, "tracking_detail") ?: data_get($event, "checkpoint_delivery_substatus") ?: "");

                return $this->historyItem(
                    source: "tracking_more",
                    sourceLabel: "TrackingMore",
                    time: $time,
                    status: $status,
                    location: (string) (data_get($event, "location") ?: ""),
                    description: $detail,
                    extra: [
                        "tracking_number" => $request["tracking_number"],
                        "courier_code" => $request["courier_code"],
                        "package_label" => $request["label"],
                    ],
                );
            })
            ->all();
    }

    private function shipmentLoadHistory(Order $order): array
    {
        return $order->shipmentLoadHistories
            ->map(function (ShipmentLoadHistory $history) {
                $loadCode = $history->shipmentLoad?->code;
                return $this->historyItem(
                    source: "shipment_load",
                    sourceLabel: "Quan ly tai",
                    time: $history->thoigian ?: $history->created_at,
                    status: (string) ($history->trangthai ?: ""),
                    location: (string) ($history->diadiem ?: ""),
                    description: (string) ($history->ghichu ?: ($loadCode ? "Hanh trinh tu tai ".$loadCode : "Cap nhat tai hang")),
                    extra: [
                        "shipment_load_id" => $history->shipment_load_id,
                        "shipment_load_code" => $loadCode,
                    ],
                );
            })
            ->all();
    }

    private function manualOrderHistory(Order $order): array
    {
        return $order->histories
            ->filter(fn (OrderHistory $history) => filled($history->thoigian) || filled($history->trangthai) || filled($history->diadiem) || filled($history->ghichu) || in_array($history->action, ["tracking_history", "tracking_status_auto"], true))
            ->map(function (OrderHistory $history) {
                $payload = json_decode((string) $history->content, true);
                $summary = is_array($payload) ? (string) ($payload["summary"] ?? "") : (string) $history->content;
                return $this->historyItem(
                    source: "manual",
                    sourceLabel: "Lich su don hang",
                    time: $history->thoigian ?: $history->created_at,
                    status: (string) ($history->trangthai ?: ""),
                    location: (string) ($history->diadiem ?: ""),
                    description: (string) ($history->ghichu ?: $summary),
                    extra: ["action" => $history->action],
                );
            })
            ->all();
    }

    private function historyItem(string $source, string $sourceLabel, mixed $time, string $status, string $location, string $description, array $extra = []): array
    {
        $parsedTime = $this->parseTime($time);
        return array_merge([
            "time" => $parsedTime?->toIso8601String(),
            "source" => $source,
            "source_label" => $sourceLabel,
            "status" => $status !== "" ? $status : null,
            "location" => $location !== "" ? $location : null,
            "description" => $description !== "" ? $description : null,
            "sort_time" => $parsedTime?->timestamp ?? 0,
        ], $extra);
    }

    private function parseTime(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) { return $value; }
        if ($value instanceof \DateTimeInterface) { return Carbon::instance($value); }
        if (blank($value)) { return null; }
        try { return Carbon::parse($value); } catch (Throwable) { return null; }
    }
}