<?php

namespace Tests\Unit;

use App\Services\TrackingMore\TrackingMore;
use App\Services\TrackingMore\TrackingMoreClient;
use App\Services\TrackingMore\TrackingMoreException;
use Tests\TestCase;

class TrackingMoreTest extends TestCase
{
    public function test_it_is_bound_in_container(): void
    {
        $this->assertInstanceOf(TrackingMore::class, app(TrackingMore::class));
    }

    public function test_it_requires_api_key(): void
    {
        config(['services.trackingmore.key' => null]);

        $this->expectException(TrackingMoreException::class);
        $this->expectExceptionMessage('TrackingMore API key is not configured.');

        (new TrackingMoreClient())->request('GET', 'couriers/all');
    }
}
