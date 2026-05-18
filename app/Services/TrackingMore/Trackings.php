<?php

namespace App\Services\TrackingMore;

class Trackings
{
    public function __construct(protected TrackingMoreClient $client) {}

    public function createTracking(array $params): array
    {
        return $this->client->request('POST', 'trackings/create', [
            'json' => $params,
        ]);
    }

    public function getTrackingResults(array $params): array
    {
        return $this->client->request('GET', 'trackings/get', [
            'query' => $params,
        ]);
    }

    public function batchCreateTrackings(array $params): array
    {
        return $this->client->request('POST', 'trackings/batch', [
            'json' => $params,
        ]);
    }

    public function updateTrackingByID(string $id, array $params): array
    {
        return $this->client->request('PUT', "trackings/update/{$id}", [
            'json' => $params,
        ]);
    }

    public function deleteTrackingByID(string $id): array
    {
        return $this->client->request('DELETE', "trackings/delete/{$id}");
    }

    public function retrackTrackingByID(string $id): array
    {
        return $this->client->request('POST', "trackings/retrack/{$id}");
    }
}
