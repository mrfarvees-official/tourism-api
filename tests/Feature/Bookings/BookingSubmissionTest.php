<?php

namespace Tests\Feature\Bookings;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_submission_creates_a_public_booking_reference(): void
    {
        Tenant::query()->create([
            'key' => 'lanka-trails',
            'name' => 'Lanka Trails',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/public/lanka-trails/bookings', [
            'customer_name' => 'Ayesha Khan',
            'customer_email' => 'ayesha.khan@example.com',
            'destination' => 'Sigiriya, Kandy, Ella',
            'package_name' => 'Sri Lanka Highlights',
            'travel_date' => '2026-08-14',
            'return_date' => '2026-08-20',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'travelers_count' => 2,
            'total_amount' => 370000,
            'paid_amount' => 185000,
        ]);

        $response->assertCreated();
        $reference = $response->json('data.reference');

        $this->assertIsString($reference);
        $this->assertTrue(Str::startsWith($reference, 'TBK-'));
    }
}
