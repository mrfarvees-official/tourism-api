<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_dashboard_returns_profile_and_bookings(): void
    {
        $response = $this->getJson('/api/customer/dashboard');

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('data.tenant.key', 'lanka-trails');
        $response->assertJsonPath('data.profile.name', 'Ayesha Khan');
        $this->assertNotEmpty($response->json('data.bookings'));
    }
}
