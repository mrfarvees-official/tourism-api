<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RealisticTourismDataSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->updateOrCreate(
            [
                'email' => 'customer1@example.test',
            ],
            [
                'name' => 'Test Customer',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
            ],
        );

        $tenant = Tenant::query()->updateOrCreate(
            [
                'key' => 'ocean-travel',
            ],
            [
                'name' => 'Ocean Travel Sri Lanka',
                'status' => 'active',
                'timezone' => 'Asia/Colombo',
                'locale' => 'en',
                'created_by_user_id' => $customer->id,
                'meta' => [
                    'description' => 'Synthetic tourism business used for testing.',
                    'test_data' => true,
                ],
            ],
        );

        $customer->tenants()->syncWithoutDetaching([
            $tenant->id => [
                'role' => 'customer',
                'status' => 'active',
                'joined_at' => now(),
            ],
        ]);

        $bookings = [
            [
                'reference' => 'TBK-2026-900001',
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
                'travel_date' => now()->addMonth()->toDateString(),
                'return_date' => now()->addMonth()->addDays(7)->toDateString(),
                'adults' => 2,
                'children' => 1,
                'infants' => 0,
                'travelers_count' => 3,
                'total_amount' => 185000,
                'paid_amount' => 50000,
                'currency' => 'LKR',
                'booking_status' => 'confirmed',
                'payment_status' => 'partial',
                'payment_due_date' => now()
                    ->addMonth()
                    ->subDays(7)
                    ->toDateString(),
                'route_summary' => 'Colombo · Kandy · Nuwara Eliya · Ella',
                'trip_story' => 'A seven-day cultural and hill-country journey.',
                'trip_highlights' => [
                    'Kandy Temple of the Tooth',
                    'Tea plantation visit',
                    'Ella Nine Arch Bridge',
                ],
                'notes' => 'Vegetarian meals and airport pickup requested.',
            ],
            [
                'reference' => 'TBK-2026-900002',
                'customer_name' => 'Nimal Perera',
                'customer_email' => 'nimal.perera@example.test',
                'package_name' => 'Southern Coast Adventure',
                'package_slug' => 'southern-coast-adventure',
                'destination' => 'Mirissa and Galle',
                'destination_slug' => 'mirissa-galle',
                'service_name' => 'Family Transport',
                'service_slug' => 'family-transport',
                'activity_name' => 'Whale Watching',
                'activity_slug' => 'whale-watching',
                'travel_date' => now()->addMonths(2)->toDateString(),
                'return_date' => now()->addMonths(2)->addDays(5)->toDateString(),
                'adults' => 2,
                'children' => 2,
                'infants' => 0,
                'travelers_count' => 4,
                'total_amount' => 240000,
                'paid_amount' => 0,
                'currency' => 'LKR',
                'booking_status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_due_date' => now()
                    ->addMonths(2)
                    ->subDays(10)
                    ->toDateString(),
                'route_summary' => 'Colombo · Galle · Mirissa',
                'trip_story' => 'A family-focused coastal holiday.',
                'trip_highlights' => [
                    'Galle Fort',
                    'Mirissa Beach',
                    'Whale-watching tour',
                ],
                'notes' => 'Two child seats are required.',
            ],
            [
                'reference' => 'TBK-2026-900003',
                'customer_name' => 'Maya Silva',
                'customer_email' => 'maya.silva@example.test',
                'package_name' => 'Cultural Triangle Experience',
                'package_slug' => 'cultural-triangle-experience',
                'destination' => 'Sigiriya and Polonnaruwa',
                'destination_slug' => 'sigiriya-polonnaruwa',
                'service_name' => 'Tour Guide',
                'service_slug' => 'tour-guide',
                'activity_name' => 'Sigiriya Rock Climb',
                'activity_slug' => 'sigiriya-rock-climb',
                'travel_date' => now()->addMonths(3)->toDateString(),
                'return_date' => now()->addMonths(3)->addDays(4)->toDateString(),
                'adults' => 2,
                'children' => 0,
                'infants' => 0,
                'travelers_count' => 2,
                'total_amount' => 160000,
                'paid_amount' => 160000,
                'currency' => 'LKR',
                'booking_status' => 'completed',
                'payment_status' => 'paid',
                'payment_due_date' => now()->addMonths(2)->toDateString(),
                'route_summary' => 'Dambulla · Sigiriya · Polonnaruwa',
                'trip_story' => 'A heritage-focused journey through ancient cities.',
                'trip_highlights' => [
                    'Dambulla Cave Temple',
                    'Sigiriya Rock Fortress',
                    'Polonnaruwa Ancient City',
                ],
                'notes' => 'English-speaking heritage guide requested.',
            ],
        ];

        foreach ($bookings as $bookingData) {
            Booking::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'reference' => $bookingData['reference'],
                ],
                array_merge($bookingData, [
                    'tenant_id' => $tenant->id,
                ]),
            );
        }
    }
}