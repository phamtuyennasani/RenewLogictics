<?php

namespace App\Services\TrackingMore;

class AirWaybills
{
    public function __construct(protected TrackingMoreClient $client) {}

    public function createAnAirWayBill(array $params): array
    {
        return $this->client->request('POST', 'awb', [
            'json' => $params,
        ]);
    }
}
