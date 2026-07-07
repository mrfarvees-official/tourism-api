<?php

namespace App\Http\Controllers;

use App\Models\ContentData;
use App\Models\ContentSchema;
use App\Models\Destination;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourismBusinessController extends Controller
{
    private function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'status' => $status,
            'data' => $data,
        ], $status);
    }

    private function error(string $message, int $status = 404): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'status' => $status,
            'error' => $message,
        ], $status);
    }

    private function tenant(string $tenantKey): ?Tenant
    {
        return Tenant::query()->where('key', $tenantKey)->first();
    }

    private function schemaBlueprint(?string $schema): array
    {
        if (!$schema) {
            return [];
        }

        $decoded = json_decode($schema, true);
        if (!is_array($decoded)) {
            return [];
        }

        $columns = $decoded['columns'] ?? [];
        if (!is_array($columns)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($column) {
            if (!is_array($column)) {
                return null;
            }

            $name = trim((string) ($column['name'] ?? ''));
            if ($name === '') {
                return null;
            }

            return [
                'name' => $name,
                'label' => trim((string) ($column['label'] ?? '')) ?: ucfirst(str_replace(['_', '-'], ' ', $name)),
                'type' => (string) ($column['type'] ?? 'string'),
                'visible' => (bool) ($column['visible'] ?? true),
                'required' => (bool) ($column['required'] ?? false),
            ];
        }, $columns)));
    }

    private function normalizeDestinationRecord(ContentData $contentData): array
    {
        $data = is_array($contentData->data) ? $contentData->data : [];
        $slug = trim((string) ($data['slug'] ?? ''));
        $title = trim((string) ($data['title'] ?? $data['name'] ?? 'Destination'));
        $subtitle = trim((string) ($data['subtitle'] ?? $data['category'] ?? $data['region'] ?? ''));
        $description = trim((string) ($data['description'] ?? $data['summary'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'active')) ?: 'active';
        $meta = trim((string) ($data['meta'] ?? $data['badge'] ?? ''));
        $amount = trim((string) ($data['amount'] ?? $data['price'] ?? ''));
        $image = trim((string) (
            $data['image']
            ?? $data['image_url']
            ?? $data['cover_image']
            ?? $data['thumbnail']
            ?? $data['photo']
            ?? $data['hero_image']
            ?? ''
        ));

        $fields = array_filter($data, static fn ($value) => $value !== null && $value !== '');

        return [
            'id' => $contentData->id,
            'slug' => $slug !== '' ? $slug : 'destination-' . $contentData->id,
            'title' => $title !== '' ? $title : 'Destination',
            'subtitle' => $subtitle,
            'description' => $description,
            'status' => $status,
            'meta' => $meta,
            'amount' => $amount ?: null,
            'image' => $image ?: '/no-image.jpg',
            'href' => '/destinations/' . ($slug !== '' ? $slug : 'destination-' . $contentData->id),
            'fields' => $fields,
            'allowed_fields' => $this->schemaBlueprint($contentData->contentSchema?->schema),
            'schema_blueprint' => $contentData->contentSchema?->schema,
            'updated_at' => $contentData->updated_at,
        ];
    }

    private function destinationRecordsForTenant(string $tenantKey): ?array
    {
        return null;
    }

    private function publicData(): array
    {
        return [
            'destinations' => [
                ['id' => 1, 'slug' => 'sigiriya', 'title' => 'Sigiriya', 'subtitle' => 'Cultural triangle', 'description' => 'Guided heritage visits, village lunches, and nearby nature stops.', 'status' => 'active'],
                ['id' => 2, 'slug' => 'ella', 'title' => 'Ella', 'subtitle' => 'Hill country', 'description' => 'Rail journeys, tea trails, waterfalls, and relaxed boutique stays.', 'status' => 'active'],
                ['id' => 3, 'slug' => 'mirissa', 'title' => 'Mirissa', 'subtitle' => 'South coast', 'description' => 'Beach breaks, whale watching, seafood dining, and coastal transfers.', 'status' => 'active'],
            ],
            'packages' => [
                ['id' => 1, 'slug' => 'sri-lanka-highlights', 'title' => 'Sri Lanka Highlights', 'subtitle' => '7 days / 6 nights', 'description' => 'Culture, tea country, wildlife, and coast in one practical itinerary.', 'status' => 'active', 'amount' => 'LKR 185,000'],
                ['id' => 2, 'slug' => 'hill-country-weekend', 'title' => 'Hill Country Weekend', 'subtitle' => '3 days / 2 nights', 'description' => 'A compact private trip with train scenery, hikes, and tea estates.', 'status' => 'active', 'amount' => 'LKR 72,000'],
            ],
            'services' => [
                ['id' => 1, 'slug' => 'airport-transfer', 'title' => 'Airport Transfer', 'subtitle' => 'Fixed price', 'description' => 'Private airport pickup and drop-off with vehicle size options.', 'status' => 'active', 'amount' => 'LKR 14,500'],
                ['id' => 2, 'slug' => 'private-chauffeur', 'title' => 'Private Chauffeur', 'subtitle' => 'Per day', 'description' => 'Full-day driver service for multi-stop routes and custom itineraries.', 'status' => 'active', 'amount' => 'LKR 22,000'],
            ],
            'activities' => [
                ['id' => 1, 'slug' => 'tea-estate-walk', 'title' => 'Tea Estate Walk', 'subtitle' => '2 hours', 'description' => 'Guided estate walk with tea tasting and local host storytelling.', 'status' => 'active', 'amount' => 'LKR 8,500'],
                ['id' => 2, 'slug' => 'whale-watching', 'title' => 'Whale Watching', 'subtitle' => 'Half day', 'description' => 'Seasonal ocean experience with pickup support from south coast stays.', 'status' => 'active', 'amount' => 'LKR 18,000'],
            ],
            'bookings' => [
                ['id' => 101, 'booking_reference' => 'TBK-2026-000101', 'title' => 'TBK-2026-000101', 'subtitle' => 'Sri Lanka Highlights', 'description' => 'Ayesha Khan, 2 adults, travel date 2026-08-14.', 'status' => 'confirmed', 'payment_status' => 'unpaid', 'amount' => 'LKR 370,000'],
                ['id' => 102, 'booking_reference' => 'TBK-2026-000102', 'title' => 'TBK-2026-000102', 'subtitle' => 'Hill Country Weekend', 'description' => 'Daniel Perera, 1 adult and 1 child, travel date 2026-08-22.', 'status' => 'pending', 'payment_status' => 'unpaid', 'amount' => 'LKR 116,000'],
            ],
            'inquiries' => [
                ['id' => 201, 'slug' => 'inquiry-201', 'title' => 'Custom honeymoon request', 'subtitle' => 'New inquiry', 'description' => 'Looking for a 9 day private trip with hill country and south coast.', 'status' => 'pending'],
            ],
            'reviews' => [
                ['id' => 301, 'slug' => 'review-301', 'title' => 'Excellent planning', 'subtitle' => '5 stars', 'description' => 'The team handled the route, transport, and hotel changes clearly.', 'status' => 'approved'],
            ],
        ];
    }

    private function customerData(): array
    {
        return [
            'tenant' => [
                'key' => 'lanka-trails',
                'name' => 'Lanka Trails',
                'supportEmail' => 'support@lankatrails.example',
            ],
            'profile' => [
                'name' => 'Ayesha Khan',
                'email' => 'ayesha.khan@example.com',
                'phone' => '+94 77 123 4567',
                'loyaltyTier' => 'Insider',
                'updatedAt' => '2026-07-03T09:30:00.000Z',
            ],
            'bookings' => [
                [
                    'id' => 'bk_001',
                    'reference' => 'TBK-2026-000101',
                    'packageName' => 'Sri Lanka Highlights',
                    'destination' => 'Sigiriya, Kandy, Ella, Mirissa',
                    'travelDate' => '2026-08-14',
                    'returnDate' => '2026-08-20',
                    'travelersCount' => 2,
                    'totalAmount' => 370000,
                    'paidAmount' => 185000,
                    'currency' => 'LKR',
                    'bookingStatus' => 'confirmed',
                    'paymentStatus' => 'partial',
                    'paymentDueDate' => '2026-08-07',
                    'notes' => 'Pickup requested from Bandaranaike International Airport.',
                    'addOns' => ['Airport transfer', 'Private driver', 'Cultural guide'],
                    'supportContact' => 'operations@lankatrails.example',
                    'itinerary' => ['Arrival in Colombo', 'Sigiriya day trip', 'Kandy temple trail', 'Ella tea country', 'South coast'],
                ],
                [
                    'id' => 'bk_002',
                    'reference' => 'TBK-2026-000102',
                    'packageName' => 'Hill Country Weekend',
                    'destination' => 'Nuwara Eliya, Ella',
                    'travelDate' => '2026-08-22',
                    'returnDate' => '2026-08-24',
                    'travelersCount' => 2,
                    'totalAmount' => 116000,
                    'paidAmount' => 116000,
                    'currency' => 'LKR',
                    'bookingStatus' => 'confirmed',
                    'paymentStatus' => 'paid',
                    'paymentDueDate' => '2026-08-20',
                    'notes' => 'Child seat requested for the transfer.',
                    'addOns' => ['Child seat', 'Train tickets'],
                    'supportContact' => 'support@lankatrails.example',
                    'itinerary' => ['Nanu Oya pickup', 'Tea estate walk', 'Ella day excursion'],
                ],
                [
                    'id' => 'bk_003',
                    'reference' => 'TBK-2026-000103',
                    'packageName' => 'Southern Coast Escape',
                    'destination' => 'Mirissa, Galle',
                    'travelDate' => '2026-09-04',
                    'returnDate' => '2026-09-08',
                    'travelersCount' => 2,
                    'totalAmount' => 248000,
                    'paidAmount' => 0,
                    'currency' => 'LKR',
                    'bookingStatus' => 'pending',
                    'paymentStatus' => 'unpaid',
                    'paymentDueDate' => '2026-08-30',
                    'notes' => 'Sea-view room preference.',
                    'addOns' => ['Whale watching', 'Airport transfer'],
                    'supportContact' => 'bookings@lankatrails.example',
                    'itinerary' => ['Galle Fort', 'Mirissa beach time', 'Whale watching', 'Spa afternoon'],
                ],
                [
                    'id' => 'bk_004',
                    'reference' => 'TBK-2026-000104',
                    'packageName' => 'Cultural Triangle Tour',
                    'destination' => 'Anuradhapura, Sigiriya, Polonnaruwa',
                    'travelDate' => '2026-09-14',
                    'returnDate' => '2026-09-19',
                    'travelersCount' => 3,
                    'totalAmount' => 312000,
                    'paidAmount' => 156000,
                    'currency' => 'LKR',
                    'bookingStatus' => 'confirmed',
                    'paymentStatus' => 'partial',
                    'paymentDueDate' => '2026-09-04',
                    'notes' => 'Need early morning departures for temple visits.',
                    'addOns' => ['Private guide', 'Lunch stops'],
                    'supportContact' => 'operations@lankatrails.example',
                    'itinerary' => ['Anuradhapura', 'Sigiriya climb', 'Polonnaruwa bike tour'],
                ],
            ],
            'reviews' => [
                [
                    'id' => 'rv_001',
                    'bookingId' => 'bk_001',
                    'title' => 'Excellent trip planning',
                    'message' => 'The itinerary was practical, the driver was on time, and the support team responded fast.',
                    'rating' => 5,
                    'status' => 'published',
                    'createdAt' => '2026-07-06T08:30:00.000Z',
                ],
                [
                    'id' => 'rv_002',
                    'bookingId' => 'bk_002',
                    'title' => 'Smooth hill-country weekend',
                    'message' => 'The child seat request was handled quickly and the schedule stayed relaxed.',
                    'rating' => 5,
                    'status' => 'published',
                    'createdAt' => '2026-07-06T09:45:00.000Z',
                ],
            ],
        ];
    }

    private function bookingSummary(array $booking): array
    {
        return [
            'id' => $booking['id'],
            'reference' => $booking['reference'],
            'booking_reference' => $booking['reference'],
            'packageName' => $booking['packageName'],
            'package_name' => $booking['packageName'],
            'destination' => $booking['destination'],
            'travelDate' => $booking['travelDate'],
            'travel_date' => $booking['travelDate'],
            'returnDate' => $booking['returnDate'],
            'return_date' => $booking['returnDate'],
            'travelersCount' => $booking['travelersCount'],
            'totalAmount' => $booking['totalAmount'],
            'total_amount' => $booking['totalAmount'],
            'paidAmount' => $booking['paidAmount'],
            'paid_amount' => $booking['paidAmount'],
            'currency' => $booking['currency'],
            'bookingStatus' => $booking['bookingStatus'],
            'status' => $booking['bookingStatus'],
            'paymentStatus' => $booking['paymentStatus'],
            'payment_status' => $booking['paymentStatus'],
            'paymentDueDate' => $booking['paymentDueDate'],
            'notes' => $booking['notes'],
            'addOns' => $booking['addOns'],
            'supportContact' => $booking['supportContact'],
            'itinerary' => $booking['itinerary'],
        ];
    }

    public function customerDashboard(Request $request): JsonResponse
    {
        $data = $this->customerData();
        $bookings = array_map(fn (array $booking) => $this->bookingSummary($booking), $data['bookings']);
        $reviews = $data['reviews'];
        $nextTrip = $bookings[0] ?? null;

        return $this->ok([
            'tenant' => $data['tenant'],
            'profile' => $data['profile'],
            'summary' => [
                'upcomingTrips' => count($bookings),
                'completedTrips' => 1,
                'pendingPayments' => count(array_filter($bookings, fn (array $booking) => ($booking['paymentStatus'] ?? 'unpaid') !== 'paid')),
                'totalSpent' => 'LKR 809,000',
                'reviewCount' => count($reviews),
                'loyaltyTier' => $data['profile']['loyaltyTier'],
            ],
            'nextTrip' => $nextTrip,
            'bookings' => $bookings,
            'reviews' => $reviews,
            'tasks' => [
                [
                    'id' => 'trip-review',
                    'title' => 'Review your next trip details',
                    'detail' => 'Check passengers, pickup notes, and add-ons before departure.',
                    'status' => 'open',
                    'actionLabel' => 'Open booking',
                    'href' => '/customer/bookings/TBK-2026-000101',
                ],
                [
                    'id' => 'payment-followup',
                    'title' => 'Settle pending payments',
                    'detail' => '2 bookings still need a payment action.',
                    'status' => 'attention',
                    'actionLabel' => 'View bookings',
                    'href' => '/customer/bookings',
                ],
                [
                    'id' => 'review-followup',
                    'title' => 'Share trip feedback',
                    'detail' => 'You already shared 2 reviews. Keep feedback coming after each trip.',
                    'status' => 'in_progress',
                    'actionLabel' => 'See reviews',
                    'href' => '/customer/reviews',
                ],
            ],
            'support' => [
                [
                    'id' => 'bk_001',
                    'title' => 'TBK-2026-000101 - Sri Lanka Highlights',
                    'detail' => 'Pickup requested from Bandaranaike International Airport.',
                    'updatedAt' => '2026-07-01T13:05:00.000Z',
                ],
                [
                    'id' => 'bk_002',
                    'title' => 'TBK-2026-000102 - Hill Country Weekend',
                    'detail' => 'Child seat requested for the transfer.',
                    'updatedAt' => '2026-07-02T11:40:00.000Z',
                ],
                [
                    'id' => 'bk_003',
                    'title' => 'TBK-2026-000103 - Southern Coast Escape',
                    'detail' => 'Sea-view room preference.',
                    'updatedAt' => '2026-07-04T14:10:00.000Z',
                ],
            ],
        ]);
    }

    public function customerBookings(Request $request): JsonResponse
    {
        $bookings = array_map(fn (array $booking) => $this->bookingSummary($booking), $this->customerData()['bookings']);

        return $this->ok($bookings);
    }

    public function customerBookingShow(Request $request, string $bookingReference): JsonResponse
    {
        $booking = collect($this->customerData()['bookings'])
            ->first(fn (array $item) => $item['reference'] === $bookingReference || $item['id'] === $bookingReference);

        if (!$booking) {
            return $this->error('Record not found.');
        }

        return $this->ok($this->bookingSummary($booking));
    }

    public function customerProfile(Request $request): JsonResponse
    {
        if (strtoupper($request->method()) === 'PATCH') {
            $profile = $this->customerData()['profile'];

            return $this->ok([
                'name' => (string) $request->input('name', $profile['name']),
                'email' => (string) $request->input('email', $profile['email']),
                'phone' => (string) $request->input('phone', $profile['phone']),
                'nationality' => (string) $request->input('nationality', 'Sri Lankan'),
                'passportNumber' => (string) $request->input('passportNumber', 'N1234567'),
                'preferredLanguage' => (string) $request->input('preferredLanguage', 'English'),
                'emergencyContact' => (string) $request->input('emergencyContact', '+94 77 765 4321'),
                'address' => (string) $request->input('address', '45 Marine Drive, Colombo 03'),
                'updatedAt' => now()->toIso8601String(),
            ]);
        }

        return $this->ok($this->customerData()['profile']);
    }

    public function customerReviews(Request $request): JsonResponse
    {
        if (strtoupper($request->method()) === 'POST') {
            return $this->ok([
                'id' => (string) random_int(1000, 9999),
                'bookingId' => $request->input('bookingId'),
                'title' => $request->input('title'),
                'message' => $request->input('message'),
                'rating' => (int) $request->input('rating', 5),
                'status' => 'submitted',
                'createdAt' => now()->toISOString(),
            ], 201);
        }

        return $this->ok($this->customerData()['reviews']);
    }

    public function publicIndex(string $tenantKey, string $resource): JsonResponse
    {
        $data = $this->publicData();
        if (!array_key_exists($resource, $data)) {
            return $this->error('Resource not found.');
        }

        return $this->ok($data[$resource]);
    }

    public function publicShow(string $tenantKey, string $resource, string $slug): JsonResponse
    {
        $response = $this->publicIndex($tenantKey, $resource);
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $payload = $response->getData(true);
        $item = collect($payload['data'])->firstWhere('slug', $slug);

        return $item ? $this->ok($item) : $this->error('Record not found.');
    }

    public function storeBooking(Request $request, string $tenantKey): JsonResponse
    {
        $reference = 'TBK-' . now()->format('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);

        return $this->ok([
            'id' => $reference,
            'booking_reference' => $reference,
            'reference' => $reference,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'customer_name' => $request->input('customer_name', $request->input('name', 'Customer')),
            'customer_email' => $request->input('customer_email', $request->input('email')),
            'package_name' => $request->input('package_name', $request->input('packageName', 'Custom Booking')),
            'destination' => $request->input('destination', 'Sri Lanka'),
            'travel_date' => $request->input('travel_date', $request->input('travelDate')),
            'return_date' => $request->input('return_date', $request->input('returnDate')),
            'adults' => (int) $request->input('adults', 0),
            'children' => (int) $request->input('children', 0),
            'infants' => (int) $request->input('infants', 0),
            'travelers_count' => (int) $request->input('adults', 0) + (int) $request->input('children', 0) + (int) $request->input('infants', 0),
            'total_amount' => 0,
            'paid_amount' => 0,
            'currency' => 'LKR',
            'notes' => $request->input('notes', ''),
        ], 201);
    }

    public function storeInquiry(Request $request, string $tenantKey): JsonResponse
    {
        return $this->ok([
            'id' => random_int(1000, 9999),
            'status' => 'new',
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'message' => $request->input('message'),
        ], 201);
    }

    public function adminIndex(Request $request, string $resource): JsonResponse
    {
        $tenantKey = (string) $request->input('tenantKey');
        if (!$tenantKey || !$this->tenant($tenantKey)) {
            return $this->error('Tenant not found.', 422);
        }

        $map = [
            'tourism-services' => 'services',
            'transport-options' => 'services',
            'service-categories' => 'services',
            'accommodations' => 'services',
        ];
        $key = $map[$resource] ?? $resource;
        $data = $this->publicData();

        return $this->ok($data[$key] ?? []);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $tenantKey = (string) $request->input('tenantKey');
        if (!$tenantKey) {
            return $this->error('Tenant not found.', 422);
        }

        $tenant = $this->tenant($tenantKey);
        $destinationCount = $tenant
            ? Destination::query()->where('tenant_id', $tenant->id)->count()
            : 0;

        return $this->ok([
            'total_destinations' => $destinationCount,
            'total_active_packages' => 2,
            'total_services' => 2,
            'total_bookings' => 2,
            'pending_bookings' => 1,
            'confirmed_bookings' => 1,
            'completed_bookings' => 0,
            'total_inquiries' => 1,
            'new_inquiries' => 1,
            'total_revenue' => 0,
            'recent_bookings' => $this->publicData()['bookings'],
            'recent_inquiries' => $this->publicData()['inquiries'],
            'top_packages' => $this->publicData()['packages'],
        ]);
    }
}
