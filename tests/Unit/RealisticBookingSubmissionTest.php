<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Tenant;
use Database\Seeders\RealisticTourismDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RealisticBookingSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_booking_using_realistic_seeded_data(): void
    {
        $this->seed(RealisticTourismDataSeeder::class);

        $tenant = Tenant::query()
            ->where('key', 'ocean-travel')
            ->firstOrFail();

        $travelDate = now()->addMonths(4);
        $returnDate = $travelDate->copy()->addDays(7);

        $response = $this->postJson(
            "/api/public/{$tenant->key}/bookings",
            [
                'customer_name' => 'Test Customer',
                'customer_email' => 'customer1@example.test',
                'package_name' => 'Seven-Day Sri Lanka Tour',
                'package_slug' => 'seven-day-sri-lanka-tour',
                'destination' => 'Ella',
                'destination_slug' => 'ella',
                'service_name' => 'Private Transport',
                'service_slug' => 'private-transport',
                'activity_name' => 'Ella Hiking Experience',
                'activity_slug' => 'ella-hiking-experience',
                'travel_date' => $travelDate->toDateString(),
                'return_date' => $returnDate->toDateString(),
                'adults' => 2,
                'children' => 1,
                'infants' => 0,
                'travelers_count' => 3,
                'total_amount' => 185000,
                'paid_amount' => 0,
                'currency' => 'LKR',
                'notes' => 'Airport pickup and vegetarian meals requested.',
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.customer_name', 'Test Customer')
            ->assertJsonPath('data.customer_email', 'customer1@example.test')
            ->assertJsonPath('data.destination', 'Ella')
            ->assertJsonPath('data.total_amount', 185000);

        $reference = $response->json('data.reference');

        $this->assertIsString($reference);
        $this->assertTrue(Str::startsWith($reference, 'TBK-'));

        $this->assertDatabaseHas('bookings', [
            'tenant_id' => $tenant->id,
            'reference' => $reference,
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer1@example.test',
            'package_name' => 'Seven-Day Sri Lanka Tour',
            'destination' => 'Ella',
            'adults' => 2,
            'children' => 1,
            'travelers_count' => 3,
            'total_amount' => 185000,
            'currency' => 'LKR',
        ]);

        $booking = Booking::query()
            ->where('reference', $reference)
            ->firstOrFail();

        $this->assertSame($tenant->id, $booking->tenant_id);
        $this->assertSame('pending', $booking->booking_status);
        $this->assertSame('unpaid', $booking->payment_status);
        $this->assertSame(
            $travelDate->toDateString(),
            $booking->travel_date->toDateString(),
        );
        $this->assertSame(
            $returnDate->toDateString(),
            $booking->return_date->toDateString(),
        );
    }
}