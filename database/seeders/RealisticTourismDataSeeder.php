<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerReview;
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

        $customerProfile = Customer::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => 'customer1@example.test',
            ],
            [
                'full_name' => 'Test Customer',
                'phone' => '+94 77 123 4567',
                'nationality' => 'Sri Lankan',
                'passport_number' => 'N1234567',
                'preferred_language' => 'English',
                'loyalty_tier' => 'Insider',
                'emergency_contact' => '+94 77 765 4321',
                'address' => '45 Marine Drive, Colombo 03',
            ],
        );

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
                'add_ons' => [
                    'Kandy Temple of the Tooth',
                    'Tea plantation visit',
                    'Ella Nine Arch Bridge',
                ],
                'itinerary' => [
                    'Colombo arrival',
                    'Kandy city stay',
                    'Nuwara Eliya tea country',
                    'Ella hill escape',
                ],
                'support_contact' => 'support@lankatrails.example',
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
                'add_ons' => [
                    'Galle Fort',
                    'Mirissa Beach',
                    'Whale-watching tour',
                ],
                'itinerary' => [
                    'Colombo pickup',
                    'Galle heritage stop',
                    'Mirissa beach stay',
                ],
                'support_contact' => 'bookings@lankatrails.example',
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
                'add_ons' => [
                    'Dambulla Cave Temple',
                    'Sigiriya Rock Fortress',
                    'Polonnaruwa Ancient City',
                ],
                'itinerary' => [
                    'Dambulla cave temple',
                    'Sigiriya fortress climb',
                    'Polonnaruwa ancient city',
                ],
                'support_contact' => 'operations@lankatrails.example',
                'notes' => 'English-speaking heritage guide requested.',
            ],
        ];

        foreach ($bookings as $bookingData) {
            $bookingCustomer = Customer::query()
                ->where('tenant_id', $tenant->id)
                ->where('email', $bookingData['customer_email'])
                ->first()
                ?? $customerProfile;

            Booking::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'reference' => $bookingData['reference'],
                ],
                array_merge($bookingData, [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $bookingCustomer->id,
                ]),
            );
        }

        $reviews = [
            [
                'booking_reference' => 'TBK-2026-900001',
                'title' => 'Excellent trip planning',
                'message' => 'The itinerary was practical, the driver was on time, and the support team responded fast.',
                'rating' => 5,
                'status' => 'published',
            ],
            [
                'booking_reference' => 'TBK-2026-900002',
                'title' => 'Smooth hill-country weekend',
                'message' => 'The child seat request was handled quickly and the schedule stayed relaxed.',
                'rating' => 5,
                'status' => 'published',
            ],
        ];

        foreach ($reviews as $reviewData) {
            $booking = Booking::query()
                ->where('tenant_id', $tenant->id)
                ->where('reference', $reviewData['booking_reference'])
                ->first();

            if (!$booking) {
                continue;
            }

            CustomerReview::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'booking_reference' => $reviewData['booking_reference'],
                ],
                array_merge($reviewData, [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customerProfile->id,
                    'booking_id' => $booking->id,
                ]),
            );
        }
    }
}
