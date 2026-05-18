<?php

namespace App\Services\TrackingMore;

class Couriers
{
    public function __construct(protected TrackingMoreClient $client) {}

    public function getAllCouriers(): array
    {
        return $this->client->request('GET', 'couriers/all');
    }

    public function detect(array $params): array
    {
        return $this->client->request('POST', 'couriers/detect', [
            'json' => $params,
        ]);
    }
}
