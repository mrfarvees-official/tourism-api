<?php

namespace App\Http\Controllers;

use App\Models\ContentData;
use App\Models\ContentSchema;
use App\Models\Booking;
use App\Models\TourismActivity;
use App\Models\Destination;
use App\Models\TourismPackage;
use App\Models\TourismService;
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

    private function requestedVariant(Request $request): ?string
    {
        $variant = trim((string) $request->query('variant', ''));
        return $variant !== '' ? $variant : null;
    }

    private function normalizePublicResource(string $resource): string
    {
        return match (strtolower(trim($resource))) {
            'destination',
            'destinations',
            'destination_collection' => 'destinations',
            'tour_package',
            'tour-packages',
            'package',
            'packages',
            'featured-tour-packages',
            'featured-tour-package',
            'tour-highlight',
            'trip-hotspots',
            'recommended-tours' => 'packages',
            'service',
            'services',
            'tourism-service',
            'tourism-services',
            'service-categories',
            'accommodations',
            'transport-options' => 'services',
            default => strtolower(trim($resource)),
        };
    }

    private function resourceItems(string $tenantKey, string $resource, ?string $variant = null): array
    {
        $key = $this->normalizePublicResource($resource);
        $tenant = $this->tenant($tenantKey);
        $items = $this->catalogRows($key, $tenant, $variant);

        if ($items !== []) {
            return $items;
        }

        $data = $this->publicData();
        $items = $data[$key] ?? [];

        if ($variant !== 'featured') {
            return $items;
        }

        return array_values(array_filter($items, static fn (array $item) => (bool) ($item['featured'] ?? false)));
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

    private function catalogModelClass(string $resource): ?string
    {
        return match ($resource) {
            'packages' => TourismPackage::class,
            'services' => TourismService::class,
            'activities' => TourismActivity::class,
            default => null,
        };
    }

    private function seedCatalogTenant(Tenant $tenant, string $resource): void
    {
        $modelClass = $this->catalogModelClass($resource);
        if (!$modelClass) {
            return;
        }

        if ($modelClass::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        foreach ($modelClass::defaultSeedRows() as $row) {
            $modelClass::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'slug' => $row['slug'],
                ],
                array_merge($row, [
                    'tenant_id' => $tenant->id,
                ]),
            );
        }
    }

    private function catalogRows(string $resource, ?Tenant $tenant = null, ?string $variant = null): array
    {
        $tenant = $tenant ?? $this->tenant('lanka-trails');
        $modelClass = $this->catalogModelClass($resource);

        if (!$tenant || !$modelClass) {
            return [];
        }

        $this->seedCatalogTenant($tenant, $resource);

        $query = $modelClass::query()->where('tenant_id', $tenant->id)->where('status', 'active');
        if ($variant === 'featured') {
            $query->where('featured', true);
        }

        return $query->latest('id')->get()->map(fn ($record) => $record->toTourismArray())->values()->all();
    }

    private function catalogRequestMap(string $resource): array
    {
        return match ($resource) {
            'packages' => [
                'slug' => 'slug',
                'name' => 'package_name',
                'description' => 'description',
                'type' => 'duration',
                'coverage' => 'route_summary',
                'vehicle' => 'inclusions',
                'response_time' => 'best_for',
                'pricing_model' => 'pace',
                'story' => 'story',
                'season' => 'highlights',
            ],
            'services' => [
                'slug' => 'slug',
                'name' => 'service_name',
                'description' => 'description',
                'type' => 'service_type',
                'coverage' => 'coverage',
                'vehicle' => 'vehicle',
                'response_time' => 'response_time',
                'pricing_model' => 'pricing_model',
                'story' => 'story',
            ],
            'activities' => [
                'slug' => 'slug',
                'name' => 'activity_name',
                'description' => 'description',
                'type' => 'activity_type',
                'coverage' => 'duration',
                'vehicle' => 'best_for',
                'response_time' => 'pace',
                'pricing_model' => 'season',
                'story' => 'story',
            ],
            default => [],
        };
    }

    private function catalogValidationRules(string $resource): array
    {
        return match ($resource) {
            'packages' => [
                'slug' => ['nullable', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'type' => ['nullable', 'string', 'max:255'],
                'coverage' => ['nullable', 'string', 'max:255'],
                'vehicle' => ['nullable', 'string', 'max:255'],
                'response_time' => ['nullable', 'string', 'max:255'],
                'pricing_model' => ['nullable', 'string', 'max:255'],
                'story' => ['nullable', 'string'],
                'season' => ['nullable', 'string'],
                'price_label' => ['nullable', 'string', 'max:255'],
                'price_value' => ['nullable', 'integer'],
                'image_url' => ['nullable', 'string', 'max:2048'],
                'featured' => ['nullable', 'boolean'],
                'status' => ['required', 'string', 'max:32'],
            ],
            'services' => [
                'slug' => ['nullable', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'type' => ['nullable', 'string', 'max:255'],
                'coverage' => ['nullable', 'string', 'max:255'],
                'vehicle' => ['nullable', 'string', 'max:255'],
                'response_time' => ['nullable', 'string', 'max:255'],
                'pricing_model' => ['nullable', 'string', 'max:255'],
                'story' => ['nullable', 'string'],
                'price_label' => ['nullable', 'string', 'max:255'],
                'price_value' => ['nullable', 'integer'],
                'image_url' => ['nullable', 'string', 'max:2048'],
                'featured' => ['nullable', 'boolean'],
                'status' => ['required', 'string', 'max:32'],
            ],
            'activities' => [
                'slug' => ['nullable', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'type' => ['nullable', 'string', 'max:255'],
                'coverage' => ['nullable', 'string', 'max:255'],
                'vehicle' => ['nullable', 'string', 'max:255'],
                'response_time' => ['nullable', 'string', 'max:255'],
                'pricing_model' => ['nullable', 'string', 'max:255'],
                'story' => ['nullable', 'string'],
                'price_label' => ['nullable', 'string', 'max:255'],
                'price_value' => ['nullable', 'integer'],
                'image_url' => ['nullable', 'string', 'max:2048'],
                'featured' => ['nullable', 'boolean'],
                'status' => ['required', 'string', 'max:32'],
            ],
            default => [],
        };
    }

    private function catalogPayload(array $validated, string $resource, Tenant $tenant, ?int $id = null): array
    {
        $map = $this->catalogRequestMap($resource);
        $slug = trim((string) ($validated['slug'] ?? ''));
        $name = trim((string) ($validated['name'] ?? ''));
        $generatedSlug = \Illuminate\Support\Str::slug($name);

        $payload = [
            'tenant_id' => $tenant->id,
            'slug' => $slug !== ''
                ? $slug
                : ($generatedSlug !== '' ? $generatedSlug : ($resource . '-' . ($id ?? random_int(1000, 9999)))),
            'description' => (string) ($validated['description'] ?? ''),
            'price_label' => (string) ($validated['price_label'] ?? ''),
            'price_value' => (int) ($validated['price_value'] ?? 0),
            'image_url' => (string) ($validated['image_url'] ?? ''),
            'featured' => (bool) ($validated['featured'] ?? false),
            'status' => (string) ($validated['status'] ?? 'active'),
        ];

        foreach ($map as $input => $column) {
            if (!array_key_exists($input, $validated)) {
                continue;
            }

            if ($input === 'slug' || $input === 'status') {
                continue;
            }

            $payload[$column] = $validated[$input];
        }

        return $payload;
    }

    private function catalogEntry(string $resource, Tenant $tenant, int|string $id)
    {
        $modelClass = $this->catalogModelClass($resource);
        if (!$modelClass) {
            return null;
        }

        return $modelClass::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($id)
            ->first();
    }

    private function catalogPaginatedResponse(array $items, Request $request): array
    {
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = max(1, min((int) $request->integer('per_page', 10), 50));
        $total = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $pageItems = array_slice($items, $offset, $perPage);

        return [
            'items' => array_values($pageItems),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    private function publicData(): array
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

    private function customerData(): array
    {
        $data = $this->publicData();

        $data['inquiries'] = [
            [
                'id' => 'inq_001',
                'name' => 'Ayesha Khan',
                'email' => 'ayesha.khan@example.com',
                'message' => 'Need help planning a cultural triangle trip.',
                'status' => 'new',
                'createdAt' => '2026-07-09T08:15:00.000Z',
            ],
            [
                'id' => 'inq_002',
                'name' => 'Maya Silva',
                'email' => 'maya.silva@example.com',
                'message' => 'Looking for a coastal family package in August.',
                'status' => 'new',
                'createdAt' => '2026-07-10T10:40:00.000Z',
            ],
        ];

        return $data;
    }

    private function seedCustomerBookings(Tenant $tenant): void
    {
        if (Booking::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        foreach (Booking::defaultSeedRows() as $row) {
            Booking::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'reference' => $row['reference'],
                ],
                array_merge($row, [
                    'tenant_id' => $tenant->id,
                ]),
            );
        }
    }

    private function customerBookingsList(): array
    {
        $tenant = $this->tenant('lanka-trails');
        if (!$tenant) {
            return $this->customerData()['bookings'];
        }

        $this->seedCustomerBookings($tenant);

        return Booking::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->get()
            ->map(fn (Booking $booking) => $booking->toCustomerSummaryArray())
            ->values()
            ->all();
    }

    private function customerBookingRecord(string $bookingReference): ?Booking
    {
        $tenant = $this->tenant('lanka-trails');
        if (!$tenant) {
            return null;
        }

        $this->seedCustomerBookings($tenant);

        return Booking::query()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($bookingReference) {
                $query->where('reference', $bookingReference)
                    ->orWhere('id', $bookingReference);
            })
            ->first();
    }

    private function bookingSummary(array|Booking $booking): array
    {
        if ($booking instanceof Booking) {
            return $booking->toCustomerSummaryArray();
        }

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
        $bookings = $this->customerBookingsList();
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
        return $this->ok($this->customerBookingsList());
    }

    public function customerBookingShow(Request $request, string $bookingReference): JsonResponse
    {
        $booking = $this->customerBookingRecord($bookingReference);

        if (!$booking) {
            $fallbackBooking = collect($this->customerData()['bookings'])->firstWhere('reference', $bookingReference)
                ?? collect($this->customerData()['bookings'])->firstWhere('id', $bookingReference);

            if ($fallbackBooking) {
                return $this->ok($this->bookingSummary($fallbackBooking));
            }

            return $this->error('Record not found.');
        }

        return $this->ok($booking->toCustomerSummaryArray());
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

    private function publicCatalogIndex(Request $request, string $tenantKey, string $resource): JsonResponse
    {
        return $this->ok($this->resourceItems($tenantKey, $resource, $this->requestedVariant($request)));
    }

    private function publicCatalogShow(Request $request, string $tenantKey, string $resource, string $slug): JsonResponse
    {
        $items = $this->resourceItems($tenantKey, $resource, $this->requestedVariant($request));
        $item = collect($items)->firstWhere('slug', $slug);

        return $item ? $this->ok($item) : $this->error('Record not found.');
    }

    public function publicPackages(Request $request, string $tenantKey): JsonResponse
    {
        return $this->publicCatalogIndex($request, $tenantKey, 'packages');
    }

    public function publicPackageShow(Request $request, string $tenantKey, string $slug): JsonResponse
    {
        return $this->publicCatalogShow($request, $tenantKey, 'packages', $slug);
    }

    public function publicServices(Request $request, string $tenantKey): JsonResponse
    {
        return $this->publicCatalogIndex($request, $tenantKey, 'services');
    }

    public function publicServiceShow(Request $request, string $tenantKey, string $slug): JsonResponse
    {
        return $this->publicCatalogShow($request, $tenantKey, 'services', $slug);
    }

    public function publicActivities(Request $request, string $tenantKey): JsonResponse
    {
        return $this->publicCatalogIndex($request, $tenantKey, 'activities');
    }

    public function publicActivityShow(Request $request, string $tenantKey, string $slug): JsonResponse
    {
        return $this->publicCatalogShow($request, $tenantKey, 'activities', $slug);
    }

    public function publicReviews(Request $request, string $tenantKey): JsonResponse
    {
        return $this->ok($this->resourceItems($tenantKey, 'reviews', $this->requestedVariant($request)));
    }

    public function storeBooking(Request $request, string $tenantKey): JsonResponse
    {
        $reference = 'TBK-' . now()->format('Y') . '-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        $customerName = (string) $request->input('customer_name', $request->input('name', 'Customer'));
        $customerEmail = (string) $request->input('customer_email', $request->input('email', ''));
        $destination = (string) $request->input('destination', 'Sri Lanka');
        $destinationSlug = (string) $request->input('destination_slug', '');
        $packageName = (string) $request->input('package_name', $request->input('packageName', 'Custom Booking'));
        $packageSlug = (string) $request->input('package_slug', '');
        $serviceName = (string) $request->input('service_name', '');
        $serviceSlug = (string) $request->input('service_slug', '');
        $activityName = (string) $request->input('activity_name', '');
        $activitySlug = (string) $request->input('activity_slug', '');
        $travelersCount = (int) $request->input('travelers_count', (int) $request->input('adults', 0) + (int) $request->input('children', 0) + (int) $request->input('infants', 0));
        $routeSummary = trim((string) $request->input('route_summary', implode(' · ', array_filter([$destination, $packageName, $serviceName, $activityName]))));
        $tripStory = trim((string) $request->input('trip_story', $request->input('journey_story', '')));

        return $this->ok([
            'id' => $reference,
            'booking_reference' => $reference,
            'reference' => $reference,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'package_name' => $packageName,
            'package_slug' => $packageSlug,
            'destination' => $destination,
            'destination_slug' => $destinationSlug,
            'service_name' => $serviceName,
            'service_slug' => $serviceSlug,
            'activity_name' => $activityName,
            'activity_slug' => $activitySlug,
            'travel_date' => $request->input('travel_date', $request->input('travelDate')),
            'return_date' => $request->input('return_date', $request->input('returnDate')),
            'adults' => (int) $request->input('adults', 0),
            'children' => (int) $request->input('children', 0),
            'infants' => (int) $request->input('infants', 0),
            'travelers_count' => $travelersCount,
            'total_amount' => (int) $request->input('total_amount', 0),
            'paid_amount' => (int) $request->input('paid_amount', 0),
            'currency' => 'LKR',
            'notes' => $request->input('notes', ''),
            'route_summary' => $routeSummary,
            'trip_story' => $tripStory,
            'journey_story' => $tripStory,
            'trip_highlights' => $request->input('trip_highlights', []),
            'destination_story' => (string) $request->input('destination_story', ''),
            'package_story' => (string) $request->input('package_story', ''),
            'service_story' => (string) $request->input('service_story', ''),
            'activity_story' => (string) $request->input('activity_story', ''),
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
        return $this->adminCatalogIndex($request, $resource);
    }

    private function adminCatalogIndex(Request $request, string $resource): JsonResponse
    {
        $tenantKey = (string) $request->input('tenantKey');
        $tenant = $tenantKey ? $this->tenant($tenantKey) : null;
        if (!$tenant) {
            return $this->error('Tenant not found.', 422);
        }

        $map = [
            'services' => 'services',
            'tourism-services' => 'services',
            'transport-options' => 'services',
            'service-categories' => 'services',
            'accommodations' => 'services',
            'activities' => 'activities',
        ];
        $key = $map[$resource] ?? $resource;

        $items = $this->catalogRows($key, $tenant);
        if ($items !== []) {
            $search = trim((string) $request->input('search', ''));
            if ($search !== '') {
                $needle = mb_strtolower($search);
                $items = array_values(array_filter($items, static function (array $item) use ($needle) {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        (string) ($item['title'] ?? ''),
                        (string) ($item['subtitle'] ?? ''),
                        (string) ($item['description'] ?? ''),
                    ])));

                    return str_contains($haystack, $needle);
                }));
            }

            return $this->ok($this->catalogPaginatedResponse($items, $request));
        }

        $data = $this->publicData();
        $items = $data[$key] ?? [];
        if (!is_array($items)) {
            $items = [];
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $items = array_values(array_filter($items, static function (array $item) use ($needle) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    (string) ($item['title'] ?? ''),
                    (string) ($item['subtitle'] ?? ''),
                    (string) ($item['description'] ?? ''),
                ])));

                return str_contains($haystack, $needle);
            }));
        }

        return $this->ok($this->catalogPaginatedResponse($items, $request));
    }

    public function adminPackages(Request $request): JsonResponse
    {
        return $this->adminIndex($request, 'packages');
    }

    public function adminPackageStore(Request $request): JsonResponse
    {
        return $this->adminStore($request, 'packages');
    }

    public function adminPackageUpdate(Request $request, int $id): JsonResponse
    {
        return $this->adminUpdate($request, 'packages', $id);
    }

    public function adminPackageDestroy(Request $request, int $id): JsonResponse
    {
        return $this->adminDestroy($request, 'packages', $id);
    }

    public function adminServices(Request $request): JsonResponse
    {
        return $this->adminIndex($request, 'services');
    }

    public function adminServiceStore(Request $request): JsonResponse
    {
        return $this->adminStore($request, 'services');
    }

    public function adminServiceUpdate(Request $request, int $id): JsonResponse
    {
        return $this->adminUpdate($request, 'services', $id);
    }

    public function adminServiceDestroy(Request $request, int $id): JsonResponse
    {
        return $this->adminDestroy($request, 'services', $id);
    }

    public function adminActivities(Request $request): JsonResponse
    {
        return $this->adminIndex($request, 'activities');
    }

    public function adminActivityStore(Request $request): JsonResponse
    {
        return $this->adminStore($request, 'activities');
    }

    public function adminActivityUpdate(Request $request, int $id): JsonResponse
    {
        return $this->adminUpdate($request, 'activities', $id);
    }

    public function adminActivityDestroy(Request $request, int $id): JsonResponse
    {
        return $this->adminDestroy($request, 'activities', $id);
    }

    public function adminTransportStore(Request $request): JsonResponse
    {
        return $this->adminStore($request, 'services');
    }

    public function adminTransportUpdate(Request $request, int $id): JsonResponse
    {
        return $this->adminUpdate($request, 'services', $id);
    }

    public function adminTransportDestroy(Request $request, int $id): JsonResponse
    {
        return $this->adminDestroy($request, 'services', $id);
    }

    public function adminStore(Request $request, string $resource): JsonResponse
    {
        $tenantKey = (string) $request->input('tenantKey');
        $tenant = $tenantKey ? $this->tenant($tenantKey) : null;
        if (!$tenant) {
            return $this->error('Tenant not found.', 422);
        }

        $resource = $this->normalizePublicResource($resource);
        if (!$this->catalogModelClass($resource)) {
            return $this->error('Resource not found.', 404);
        }

        $validated = $request->validate($this->catalogValidationRules($resource));
        $modelClass = $this->catalogModelClass($resource);
        $payload = $this->catalogPayload($validated, $resource, $tenant);

        $record = $modelClass::query()->create($payload);

        return $this->ok($record->toTourismArray(), 201);
    }

    public function adminUpdate(Request $request, string $resource, int $id): JsonResponse
    {
        $tenantKey = (string) $request->input('tenantKey');
        $tenant = $tenantKey ? $this->tenant($tenantKey) : null;
        if (!$tenant) {
            return $this->error('Tenant not found.', 422);
        }

        $resource = $this->normalizePublicResource($resource);
        $modelClass = $this->catalogModelClass($resource);
        if (!$modelClass) {
            return $this->error('Resource not found.', 404);
        }

        $record = $this->catalogEntry($resource, $tenant, $id);
        if (!$record) {
            return $this->error('Record not found.');
        }

        $validated = $request->validate($this->catalogValidationRules($resource));
        $record->update($this->catalogPayload($validated, $resource, $tenant, $id));

        return $this->ok($record->fresh()->toTourismArray());
    }

    public function adminDestroy(Request $request, string $resource, int $id): JsonResponse
    {
        $tenantKey = (string) $request->input('tenantKey');
        $tenant = $tenantKey ? $this->tenant($tenantKey) : null;
        if (!$tenant) {
            return $this->error('Tenant not found.', 422);
        }

        $resource = $this->normalizePublicResource($resource);
        $modelClass = $this->catalogModelClass($resource);
        if (!$modelClass) {
            return $this->error('Resource not found.', 404);
        }

        $record = $this->catalogEntry($resource, $tenant, $id);
        if (!$record) {
            return $this->error('Record not found.');
        }

        $record->delete();

        return $this->ok(['deleted' => true]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $tenantKey = (string) $request->input('tenantKey');
        if (!$tenantKey) {
            return $this->error('Tenant not found.', 422);
        }

        $tenant = $this->tenant($tenantKey);
        $destinationCount = $tenant ? Destination::query()->where('tenant_id', $tenant->id)->count() : 0;
        $packageCount = $tenant ? count($this->catalogRows('packages', $tenant)) : 0;
        $serviceCount = $tenant ? count($this->catalogRows('services', $tenant)) : 0;
        $activityCount = $tenant ? count($this->catalogRows('activities', $tenant)) : 0;
        $bookingQuery = $tenant ? Booking::query()->where('tenant_id', $tenant->id) : Booking::query()->whereRaw('1 = 0');
        $totalBookings = (clone $bookingQuery)->count();
        $pendingBookings = (clone $bookingQuery)->where('booking_status', 'pending')->count();
        $confirmedBookings = (clone $bookingQuery)->where('booking_status', 'confirmed')->count();
        $completedBookings = (clone $bookingQuery)->where('booking_status', 'completed')->count();
        $recentBookings = (clone $bookingQuery)->latest('id')->limit(5)->get()->map(fn (Booking $booking) => $booking->toCustomerSummaryArray())->values()->all();
        $totalRevenue = (int) (clone $bookingQuery)->sum('paid_amount');

        return $this->ok([
            'total_destinations' => $destinationCount,
            'total_active_packages' => $packageCount,
            'total_services' => $serviceCount,
            'total_activities' => $activityCount,
            'total_bookings' => $totalBookings,
            'pending_bookings' => $pendingBookings,
            'confirmed_bookings' => $confirmedBookings,
            'completed_bookings' => $completedBookings,
            'total_inquiries' => 1,
            'new_inquiries' => 1,
            'total_revenue' => $totalRevenue,
            'recent_bookings' => $recentBookings ?: $this->publicData()['bookings'],
            'recent_inquiries' => $this->customerData()['inquiries'],
            'top_packages' => $this->catalogRows('packages', $tenant),
        ]);
    }
}
