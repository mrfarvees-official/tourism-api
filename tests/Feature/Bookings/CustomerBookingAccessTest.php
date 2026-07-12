<?php

namespace Tests\Feature\Bookings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBookingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_booking_access_returns_seeded_booking_details(): void
    {
        $response = $this->getJson('/api/customer/bookings/TBK-2026-000101');

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('data.reference', 'TBK-2026-000101');
        $response->assertJsonPath('data.packageName', 'Sri Lanka Highlights');
    }
}
