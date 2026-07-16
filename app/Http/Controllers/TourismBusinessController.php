<?php

namespace App\Http\Controllers;

use App\Models\ContentData;
use App\Models\ContentSchema;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerReview;
use App\Models\Destination;
use App\Models\Stay;
use App\Models\TenantAssets;
use App\Models\Tenant;
use App\Models\TenantInboxMessage;
use App\Models\TourismActivity;
use App\Models\TourismPackage;
use App\Models\TourismService;
use App\Models\TransportOption;
use Carbon\Carbon;
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

    private function defaultTenantKey(): string
    {
        return 'lanka-trails';
    }

    private function requestedVariant(Request $request): ?string
    {
        $variant = trim((string) $request->query('variant', ''));
        return $variant !== '' ? $variant : null;
    }

    private function customerTenant(Request $request): Tenant
    {
        $tenantKey = trim((string) $request->input('tenantKey', $request->query('tenantKey', $this->defaultTenantKey())));
        $tenant = $this->tenant($tenantKey);

        if (!$tenant) {
            abort(422, 'Tenant not found.');
        }

        return $tenant;
    }

    private function seedCustomerTenant(Tenant $tenant): Customer
    {
        $customer = Customer::query()->where('tenant_id', $tenant->id)->orderBy('id')->first();
        if ($customer) {
            return $customer;
        }

        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Ayesha Khan',
            'email' => 'ayesha.khan@example.com',
            'phone' => '+94 77 123 4567',
            'nationality' => 'Sri Lankan',
            'passport_number' => 'N1234567',
            'preferred_language' => 'English',
            'loyalty_tier' => 'Insider',
            'emergency_contact' => '+94 77 765 4321',
            'address' => '45 Marine Drive, Colombo 03',
        ]);

        return $customer;
    }

    private function customerRecord(Tenant $tenant): Customer
    {
        $latestBooking = Booking::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->first();

        if ($latestBooking) {
            if ($latestBooking->customer_id) {
                $customer = Customer::query()->whereKey($latestBooking->customer_id)->first();
                if ($customer) {
                    return $customer;
                }
            }

            $customerEmail = trim((string) ($latestBooking->customer_email ?? ''));
            if ($customerEmail !== '') {
                $customer = Customer::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('email', $customerEmail)
                    ->first();
                if ($customer) {
                    return $customer;
                }
            }

            $customerName = trim((string) ($latestBooking->customer_name ?? ''));
            if ($customerName !== '') {
                $customer = Customer::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('full_name', $customerName)
                    ->first();
                if ($customer) {
                    return $customer;
                }
            }
        }

        return $this->seedCustomerTenant($tenant);
    }

    private function customerBookingsQuery(Customer $customer)
    {
        return Booking::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where(function ($query) use ($customer) {
                $query->where('customer_id', $customer->id)
                    ->orWhere('customer_email', $customer->email)
                    ->orWhere('customer_name', $customer->full_name);
            });
    }

    private function customerReviewsQuery(Customer $customer)
    {
        return CustomerReview::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id);
    }

    private function customerBookingsList(Customer $customer): array
    {
        return $this->customerBookingsQuery($customer)
            ->latest('id')
            ->get()
            ->map(fn (Booking $booking) => $booking->toCustomerSummaryArray())
            ->values()
            ->all();
    }

    private function customerReviewsList(Customer $customer): array
    {
        return $this->customerReviewsQuery($customer)
            ->latest('id')
            ->get()
            ->map(fn (CustomerReview $review) => $review->toCustomerArray())
            ->values()
            ->all();
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

    private function customerBookingRecord(Customer $customer, string $bookingReference): ?Booking
    {
        return $this->customerBookingsQuery($customer)
            ->where(function ($query) use ($bookingReference) {
                $query->where('reference', $bookingReference)
                    ->orWhere('id', $bookingReference);
            })
            ->first();
    }

    private function customerDashboardData(Customer $customer, Tenant $tenant): array
    {
        $bookings = $this->customerBookingsList($customer);
        $reviews = $this->customerReviewsList($customer);
        $upcomingTrips = collect($bookings)->filter(function (array $booking) {
            $status = strtolower((string) ($booking['bookingStatus'] ?? $booking['status'] ?? ''));
            return in_array($status, ['confirmed', 'pending', 'checked_in'], true);
        })->count();
        $completedTrips = collect($bookings)->filter(fn (array $booking) => strtolower((string) ($booking['bookingStatus'] ?? $booking['status'] ?? '')) === 'completed')->count();
        $pendingPayments = collect($bookings)->filter(fn (array $booking) => strtolower((string) ($booking['paymentStatus'] ?? $booking['payment_status'] ?? 'unpaid')) !== 'paid')->count();
        $totalSpent = collect($bookings)->sum(fn (array $booking) => (int) ($booking['paidAmount'] ?? $booking['paid_amount'] ?? 0));
        $nextTrip = collect($bookings)
            ->filter(fn (array $booking) => strtolower((string) ($booking['bookingStatus'] ?? $booking['status'] ?? '')) !== 'cancelled')
            ->sortBy(fn (array $booking) => (string) ($booking['travelDate'] ?? $booking['travel_date'] ?? ''))
            ->first();

        $tasks = [];

        if ($nextTrip) {
            $tasks[] = [
                'id' => 'trip-review',
                'title' => 'Review your next trip details',
                'detail' => trim((string) ($nextTrip['packageName'] ?? $nextTrip['package_name'] ?? 'Trip')) . ' starts on ' . (string) ($nextTrip['travelDate'] ?? $nextTrip['travel_date'] ?? 'soon') . '.',
                'status' => 'open',
                'actionLabel' => 'Open booking',
                'href' => '/customer/bookings/' . (string) ($nextTrip['reference'] ?? $nextTrip['booking_reference'] ?? ''),
            ];
        } else {
            $tasks[] = [
                'id' => 'trip-review',
                'title' => 'No upcoming trips',
                'detail' => 'You do not have any confirmed trips yet. Start a new booking when you are ready.',
                'status' => 'done',
                'actionLabel' => 'Start booking',
                'href' => '/booking/start',
            ];
        }

        $tasks[] = $pendingPayments > 0
            ? [
                'id' => 'payment-followup',
                'title' => 'Settle pending payments',
                'detail' => $pendingPayments . ' booking' . ($pendingPayments === 1 ? '' : 's') . ' still need a payment action.',
                'status' => 'attention',
                'actionLabel' => 'View bookings',
                'href' => '/customer/bookings',
            ]
            : [
                'id' => 'payment-followup',
                'title' => 'All payments are up to date',
                'detail' => 'Payment tracking is clear across your active bookings.',
                'status' => 'done',
            ];

        $tasks[] = $reviews !== []
            ? [
                'id' => 'review-followup',
                'title' => 'Share trip feedback',
                'detail' => 'You already shared ' . count($reviews) . ' review' . (count($reviews) === 1 ? '' : 's') . '. Keep feedback coming after each trip.',
                'status' => 'in_progress',
                'actionLabel' => 'See reviews',
                'href' => '/customer/reviews',
            ]
            : [
                'id' => 'review-followup',
                'title' => 'Add your first review',
                'detail' => 'Completed trips can be reviewed from the customer review screen.',
                'status' => 'open',
                'actionLabel' => 'Write review',
                'href' => '/customer/reviews',
            ];

        $support = array_slice(array_map(static function (array $booking): array {
            $itinerary = is_array($booking['itinerary'] ?? null) ? $booking['itinerary'] : [];

            return [
                'id' => (string) ($booking['id'] ?? $booking['reference'] ?? ''),
                'title' => trim((string) ($booking['reference'] ?? 'Booking')) . ' - ' . trim((string) ($booking['packageName'] ?? $booking['package_name'] ?? 'Trip')),
                'detail' => trim((string) ($booking['notes'] ?? '')) ?: trim((string) ($itinerary[0] ?? $booking['route_summary'] ?? 'Support thread summary')),
                'updatedAt' => (string) ($booking['updatedAt'] ?? $booking['updated_at'] ?? now()->toIso8601String()),
            ];
        }, $bookings), 0, 3);

        return [
            'tenant' => [
                'key' => $tenant->key,
                'name' => $tenant->name,
                'supportEmail' => 'support@lankatrails.example',
            ],
            'profile' => $customer->toTourismArray(),
            'summary' => [
                'upcomingTrips' => $upcomingTrips,
                'completedTrips' => $completedTrips,
                'pendingPayments' => $pendingPayments,
                'totalSpent' => 'LKR ' . number_format($totalSpent),
                'reviewCount' => count($reviews),
                'loyaltyTier' => $customer->loyalty_tier,
            ],
            'nextTrip' => $nextTrip,
            'bookings' => $bookings,
            'reviews' => $reviews,
            'tasks' => $tasks,
            'support' => $support,
        ];
    }

    public function customerDashboard(Request $request): JsonResponse
    {
        $tenant = $this->customerTenant($request);
        $customer = $this->customerRecord($tenant);

        return $this->ok($this->customerDashboardData($customer, $tenant));
    }

    public function customerBookings(Request $request): JsonResponse
    {
        $tenant = $this->customerTenant($request);
        $customer = $this->customerRecord($tenant);

        return $this->ok($this->customerBookingsList($customer));
    }

    public function customerBookingShow(Request $request, string $bookingReference): JsonResponse
    {
        $tenant = $this->customerTenant($request);
        $customer = $this->customerRecord($tenant);
        $booking = $this->customerBookingRecord($customer, $bookingReference);

        return $booking
            ? $this->ok($booking->toCustomerSummaryArray())
            : $this->error('Record not found.');
    }

    public function customerProfile(Request $request): JsonResponse
    {
        $tenant = $this->customerTenant($request);
        $customer = $this->customerRecord($tenant);

        if (strtoupper($request->method()) === 'PATCH') {
            $validated = $request->validate([
                'tenantKey' => ['required', 'string'],
                'name' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:100'],
                'nationality' => ['nullable', 'string', 'max:150'],
                'passportNumber' => ['nullable', 'string', 'max:100'],
                'preferredLanguage' => ['nullable', 'string', 'max:100'],
                'emergencyContact' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string'],
            ]);

            $customer->fill([
                'full_name' => trim((string) ($validated['name'] ?? $customer->full_name)) ?: $customer->full_name,
                'email' => array_key_exists('email', $validated) ? (trim((string) $validated['email']) ?: null) : $customer->email,
                'phone' => array_key_exists('phone', $validated) ? (trim((string) $validated['phone']) ?: null) : $customer->phone,
                'nationality' => array_key_exists('nationality', $validated) ? (trim((string) $validated['nationality']) ?: null) : $customer->nationality,
                'passport_number' => array_key_exists('passportNumber', $validated) ? (trim((string) $validated['passportNumber']) ?: null) : $customer->passport_number,
                'preferred_language' => array_key_exists('preferredLanguage', $validated) ? (trim((string) $validated['preferredLanguage']) ?: null) : $customer->preferred_language,
                'emergency_contact' => array_key_exists('emergencyContact', $validated) ? (trim((string) $validated['emergencyContact']) ?: null) : $customer->emergency_contact,
                'address' => array_key_exists('address', $validated) ? (trim((string) $validated['address']) ?: null) : $customer->address,
            ]);
            $customer->save();
        }

        return $this->ok($customer->toTourismArray());
    }

    public function customerReviews(Request $request): JsonResponse
    {
        $tenant = $this->customerTenant($request);
        $customer = $this->customerRecord($tenant);

        if (strtoupper($request->method()) === 'POST') {
            $validated = $request->validate([
                'tenantKey' => ['required', 'string'],
                'bookingId' => ['required', 'string'],
                'title' => ['required', 'string', 'max:255'],
                'message' => ['required', 'string'],
                'rating' => ['required', 'integer', 'min:1', 'max:5'],
            ]);

            $booking = $this->customerBookingRecord($customer, (string) $validated['bookingId']);

            $review = CustomerReview::query()->create([
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'booking_id' => $booking?->id,
                'booking_reference' => $booking?->reference ?? (string) $validated['bookingId'],
                'title' => trim((string) $validated['title']),
                'message' => trim((string) $validated['message']),
                'rating' => (int) $validated['rating'],
                'status' => 'submitted',
            ]);

            return $this->ok($review->toCustomerArray(), 201);
        }

        return $this->ok($this->customerReviewsList($customer));
    }

    public function settleCustomerPayment(Request $request, string $bookingReference): JsonResponse
    {
        $tenant = $this->customerTenant($request);
        $customer = $this->customerRecord($tenant);
        $booking = $this->customerBookingRecord($customer, $bookingReference);

        if (!$booking) {
            return $this->error('Record not found.');
        }

        $validated = $request->validate([
            'tenantKey' => ['required', 'string'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_brand' => ['nullable', 'string', 'max:50'],
            'card_last4' => ['nullable', 'string', 'max:4'],
            'card_holder_name' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $booking->update([
            'paid_amount' => (int) $booking->total_amount,
            'payment_status' => 'paid',
            'booking_status' => in_array(strtolower((string) $booking->booking_status), ['pending', 'draft'], true)
                ? 'confirmed'
                : $booking->booking_status,
            'payment_due_date' => $booking->payment_due_date ?? now()->toDateString(),
        ]);

        return $this->ok($booking->fresh()->toCustomerSummaryArray());
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

    public function  dashboard(Request $request): JsonResponse
    {
        $tenantKey = (string) $request->input('tenantKey');
        if (!$tenantKey) {
            return $this->error('Tenant not found.', 422);
        }

        $tenant = $this->tenant($tenantKey);
        $period = strtolower((string) $request->input('period', 'yearly'));
        if (!in_array($period, ['yearly', 'monthly'], true)) {
            $period = 'yearly';
        }

        $year = (int) ($request->input('year') ?: now()->year);
        $month = $period === 'monthly'
            ? max(1, min(12, (int) ($request->input('month') ?: now()->month)))
            : null;
        $endDate = $this->analyticsEndDate($period, $year, $month);

        $labels = $this->analyticsLabels($period, $endDate);
        $resources = $this->analyticsResources($tenant, $period, $endDate, $labels);
        $packages = $this->catalogRows('packages', $tenant);
        $recentInquiries = TenantInboxMessage::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (TenantInboxMessage $message) => [
                'id' => $message->id,
                'name' => $message->name,
                'email' => $message->email,
                'message' => $message->message,
                'status' => $message->status,
                'createdAt' => $message->created_at,
                'updatedAt' => $message->updated_at,
            ])
            ->values()
            ->all();

        $summary = [];
        foreach ($resources as $resource) {
            $summary[$resource['key'] . '_total'] = $resource['total'];
            $summary[$resource['key'] . '_active'] = $resource['active'];
            $summary[$resource['key'] . '_draft'] = $resource['draft'];
        }

        $calculationNotes = [
            [
                'key' => 'total_count',
                'label' => 'Total resources',
                'formula' => 'COUNT(all records where tenant_id = current tenant)',
                'description' => 'Counts the full inventory for each resource model in the tenant scope.',
            ],
            [
                'key' => 'active_count',
                'label' => 'Active resources',
                'formula' => 'COUNT(records where status IN ("active", "published", "enabled"))',
                'description' => 'Measures the published or live subset for content-bearing resources.',
            ],
            [
                'key' => 'draft_count',
                'label' => 'Draft resources',
                'formula' => 'COUNT(records where status = "draft")',
                'description' => 'Measures unfinished or unpublished inventory.',
            ],
            [
                'key' => 'period_series',
                'label' => 'Period series',
                'formula' => 'COUNT(records grouped by created_at month/day within the selected rolling period)',
                'description' => 'Builds line-chart points from database timestamps for the trailing 12 months or selected month.',
            ],
            [
                'key' => 'resource_share',
                'label' => 'Resource share',
                'formula' => 'resource total / SUM(all resource totals) × 100',
                'description' => 'Shows how each resource contributes to the tenant inventory mix.',
            ],
            [
                'key' => 'period_growth',
                'label' => 'Period growth',
                'formula' => '(last bucket - first bucket) / MAX(first bucket, 1) × 100',
                'description' => 'Compares the beginning and end of the selected period to estimate direction of change.',
            ],
        ];

        $resourceTotals = collect($resources)->sum('total') ?: 1;
        $resourceCalculations = collect($resources)->map(function (array $resource) use ($resourceTotals) {
            $series = collect($resource['series'] ?? []);
            $firstValue = (int) ($series->first()['value'] ?? 0);
            $lastValue = (int) ($series->last()['value'] ?? 0);
            $growthBase = max($firstValue, 1);

            return [
                'key' => $resource['key'],
                'label' => $resource['label'],
                'total' => $resource['total'],
                'share_percent' => round(($resource['total'] / $resourceTotals) * 100, 2),
                'growth_percent' => round((($lastValue - $firstValue) / $growthBase) * 100, 2),
                'first_bucket' => $firstValue,
                'last_bucket' => $lastValue,
                'formula_share' => 'resource total / SUM(all resource totals) × 100',
                'formula_growth' => '(last bucket - first bucket) / MAX(first bucket, 1) × 100',
            ];
        })->values()->all();

        return $this->ok([
            'tenant' => [
                'id' => $tenant->id,
                'key' => $tenant->key,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'timezone' => $tenant->timezone,
                'locale' => $tenant->locale,
            ],
            'filters' => [
                'period' => $period,
                'year' => $year,
                'month' => $month,
                'label' => $this->analyticsPeriodLabel($period, $endDate),
                'labels' => $labels,
            ],
            'total_active_packages' => count($packages),
            'recent_inquiries' => $recentInquiries,
            'summary' => $summary,
            'resources' => $resources,
        ]);
    }

    private function analyticsResources(Tenant $tenant, string $period, Carbon $endDate, array $labels): array
    {
        $definitions = [
            ['key' => 'packages', 'label' => 'Packages', 'query' => TourismPackage::query()->where('tenant_id', $tenant->id), 'has_status' => true],
            ['key' => 'destinations', 'label' => 'Destinations', 'query' => Destination::query()->where('tenant_id', $tenant->id), 'has_status' => true],
            ['key' => 'services', 'label' => 'Services', 'query' => TourismService::query()->where('tenant_id', $tenant->id), 'has_status' => true],
            ['key' => 'activities', 'label' => 'Activities', 'query' => TourismActivity::query()->where('tenant_id', $tenant->id), 'has_status' => true],
            ['key' => 'stays', 'label' => 'Stays', 'query' => Stay::query()->where('tenant_id', $tenant->id), 'has_status' => true],
            ['key' => 'transport', 'label' => 'Transport', 'query' => TransportOption::query()->where('tenant_id', $tenant->id), 'has_status' => true],
            ['key' => 'customers', 'label' => 'Customers', 'query' => Customer::query()->where('tenant_id', $tenant->id), 'has_status' => false],
            ['key' => 'media', 'label' => 'Media assets', 'query' => TenantAssets::query()->where('tenant_id', $tenant->id), 'has_status' => false],
        ];

        return collect($definitions)->map(function (array $definition) use ($period, $endDate, $labels) {
            $query = $definition['query'];
            $total = (clone $query)->count();
            $active = $definition['has_status']
                ? (clone $query)->whereIn('status', ['active', 'published', 'enabled'])->count()
                : $total;
            $draft = $definition['has_status']
                ? (clone $query)->where('status', 'draft')->count()
                : 0;

            return [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'total' => $total,
                'active' => $active,
                'draft' => $draft,
                'series' => $this->analyticsSeries($query, $period, $endDate, $labels),
            ];
        })->values()->all();
    }

    private function analyticsSeries($query, string $period, Carbon $endDate, array $labels): array
    {
        if ($period === 'monthly') {
            $daysInMonth = $endDate->daysInMonth;
            $counts = array_fill(1, $daysInMonth, 0);

            $query->whereYear('created_at', $endDate->year)
                ->whereMonth('created_at', $endDate->month)
                ->get(['created_at'])
                ->each(function ($item) use (&$counts) {
                    $day = (int) Carbon::parse((string) $item->created_at)->day;
                    $counts[$day] = ($counts[$day] ?? 0) + 1;
                });

            $series = [];
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $series[] = [
                    'label' => (string) $day,
                    'value' => $counts[$day] ?? 0,
                ];
            }

            return $series;
        }

        $startDate = $endDate->copy()->subMonthsNoOverflow(11)->startOfMonth();
        $months = [];
        $cursor = $startDate->copy();
        for ($i = 0; $i < 12; $i++) {
            $months[$cursor->format('Y-m')] = 0;
            $cursor->addMonthNoOverflow();
        }

        $query->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->get(['created_at'])
            ->each(function ($item) use (&$months) {
                $bucket = Carbon::parse((string) $item->created_at)->format('Y-m');
                if (array_key_exists($bucket, $months)) {
                    $months[$bucket] = ($months[$bucket] ?? 0) + 1;
                }
            });

        $series = [];
        foreach ($labels as $bucket => $label) {
            $series[] = [
                'label' => $label,
                'value' => $months[$bucket] ?? 0,
            ];
        }

        return $series;
    }

    private function analyticsLabels(string $period, Carbon $endDate): array
    {
        if ($period === 'monthly') {
            $daysInMonth = $endDate->daysInMonth;
            return array_map(static fn (int $day): string => (string) $day, range(1, $daysInMonth));
        }

        $labels = [];
        $cursor = $endDate->copy()->subMonthsNoOverflow(11)->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $labels[$cursor->format('Y-m')] = $cursor->format('M y');
            $cursor->addMonthNoOverflow();
        }

        return $labels;
    }

    private function analyticsPeriodLabel(string $period, Carbon $endDate): string
    {
        if ($period === 'monthly') {
            return $endDate->format('F Y');
        }

        return sprintf(
            '%s - %s',
            $endDate->copy()->subMonthsNoOverflow(11)->format('M Y'),
            $endDate->format('M Y')
        );
    }

    private function analyticsEndDate(string $period, int $year, ?int $month): Carbon
    {
        if ($period === 'monthly') {
            return Carbon::create($year, $month ?? now()->month, 1)->endOfMonth();
        }

        $selectedMonth = $year === now()->year ? now()->month : 12;

        return Carbon::create($year, $selectedMonth, 1)->endOfMonth();
    }

}
