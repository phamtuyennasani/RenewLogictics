<?php

namespace App\Services\TrackingMore;

class TrackingMore
{
    protected TrackingMoreClient $client;

    public function __construct(?TrackingMoreClient $client = null)
    {
        $this->client = $client ?: new TrackingMoreClient();
    }

    public function courier(): Couriers
    {
        return new Couriers($this->client);
    }

    public function tracking(): Trackings
    {
        return new Trackings($this->client);
    }

    public function airWaybill(): AirWaybills
    {
        return new AirWaybills($this->client);
    }
}
