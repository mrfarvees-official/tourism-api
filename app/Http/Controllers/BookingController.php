<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
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

    private function resolveTenant(Request $request): Tenant
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string'],
        ]);

        $tenant = Tenant::query()->where('key', $validated['tenantKey'])->first();
        if (!$tenant) {
            abort(422, 'Tenant not found.');
        }

        return $tenant;
    }

    private function assertTenantUser(Request $request, Tenant $tenant): void
    {
        $tenantUser = TenantUser::query()
            ->where('user_id', $request->user()->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$tenantUser) {
            abort(403, 'Unauthorized.');
        }
    }

    private function seedTenantBookings(Tenant $tenant): void
    {
        if (Booking::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        foreach (Booking::defaultSeedRows() as $row) {
            $customer = $this->resolveCustomer($tenant, $row);
            Booking::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'reference' => $row['reference'],
                ],
                array_merge($row, [
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer?->id,
                ]),
            );
        }
    }

    private function resolveCustomer(Tenant $tenant, array $data, ?Booking $existing = null): ?Customer
    {
        $customerName = trim((string) ($data['customer_name'] ?? ''));
        $customerEmail = trim((string) ($data['customer_email'] ?? ''));
        $query = Customer::query()->where('tenant_id', $tenant->id);

        if ($customerEmail !== '') {
            $query->where('email', $customerEmail);
        } elseif ($customerName !== '') {
            $query->where('full_name', $customerName);
        }

        $customer = $query->first();
        if ($customer) {
            return $customer;
        }

        if ($existing?->customer_id) {
            return Customer::query()->whereKey($existing->customer_id)->first();
        }

        if ($customerName === '' && $customerEmail === '') {
            return null;
        }

        return Customer::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => $customerName !== '' ? $customerName : 'Customer',
            'email' => $customerEmail !== '' ? $customerEmail : null,
            'phone' => trim((string) ($data['customer_phone'] ?? '')) ?: null,
            'nationality' => trim((string) ($data['nationality'] ?? '')) ?: null,
            'passport_number' => trim((string) ($data['passport_number'] ?? '')) ?: null,
            'preferred_language' => trim((string) ($data['preferred_language'] ?? '')) ?: null,
            'loyalty_tier' => trim((string) ($data['loyalty_tier'] ?? 'Explorer')) ?: 'Explorer',
            'emergency_contact' => trim((string) ($data['emergency_contact'] ?? '')) ?: null,
            'address' => trim((string) ($data['address'] ?? '')) ?: null,
        ]);
    }

    private function generateBookingReference(Tenant $tenant): string
    {
        $year = now()->year;
        $prefix = sprintf('TBK-%d-', $year);
        $maxSequence = Booking::query()
            ->where('tenant_id', $tenant->id)
            ->where('reference', 'like', $prefix . '%')
            ->pluck('reference')
            ->reduce(function (int $carry, string $reference) use ($year) {
                if (!preg_match('/^TBK-' . $year . '-(\d{6})$/', $reference, $matches)) {
                    return $carry;
                }

                return max($carry, (int) $matches[1]);
            }, 0);

        return $prefix . str_pad((string) ($maxSequence + 1), 6, '0', STR_PAD_LEFT);
    }

    private function bookingReferenceExists(Tenant $tenant, string $reference, ?Booking $existing = null): bool
    {
        $query = Booking::query()
            ->where('tenant_id', $tenant->id)
            ->where('reference', $reference);

        if ($existing) {
            $query->whereKeyNot($existing->id);
        }

        return $query->exists();
    }

    private function resolveBookingReference(Tenant $tenant, ?Booking $existing, ?string $reference): string
    {
        $candidate = trim((string) $reference);

        if ($existing && $candidate === '') {
            return $existing->reference;
        }

        if ($candidate === '') {
            return $this->generateBookingReference($tenant);
        }

        if ($existing && $candidate === $existing->reference) {
            return $existing->reference;
        }

        if ($this->bookingReferenceExists($tenant, $candidate, $existing)) {
            return $this->generateBookingReference($tenant);
        }

        return $candidate;
    }

    private function bookingPayload(array $data, Tenant $tenant, ?Booking $existing = null): array
    {
        $reference = $this->resolveBookingReference($tenant, $existing, $data['reference'] ?? null);
        $customer = $this->resolveCustomer($tenant, $data, $existing);
        $tripHighlights = $data['trip_highlights'] ?? null;
        $addOns = $data['add_ons'] ?? $tripHighlights ?? null;
        $itinerary = $data['itinerary'] ?? null;
        $supportContact = trim((string) ($data['support_contact'] ?? ($existing?->support_contact ?? 'support@lankatrails.example')));

        return [
            'tenant_id' => $tenant->id,
            'customer_id' => $customer?->id ?? $existing?->customer_id,
            'reference' => $reference,
            'customer_name' => trim((string) ($data['customer_name'] ?? 'Customer')),
            'customer_email' => trim((string) ($data['customer_email'] ?? '')),
            'package_name' => trim((string) ($data['package_name'] ?? 'Custom Booking')),
            'package_slug' => trim((string) ($data['package_slug'] ?? '')),
            'destination' => trim((string) ($data['destination'] ?? 'Sri Lanka')),
            'destination_slug' => trim((string) ($data['destination_slug'] ?? '')),
            'service_name' => trim((string) ($data['service_name'] ?? '')),
            'service_slug' => trim((string) ($data['service_slug'] ?? '')),
            'activity_name' => trim((string) ($data['activity_name'] ?? '')),
            'activity_slug' => trim((string) ($data['activity_slug'] ?? '')),
            'travel_date' => $data['travel_date'] ?? null,
            'return_date' => $data['return_date'] ?? null,
            'adults' => (int) ($data['adults'] ?? 0),
            'children' => (int) ($data['children'] ?? 0),
            'infants' => (int) ($data['infants'] ?? 0),
            'travelers_count' => (int) ($data['travelers_count'] ?? 0),
            'total_amount' => (int) ($data['total_amount'] ?? 0),
            'paid_amount' => (int) ($data['paid_amount'] ?? 0),
            'currency' => trim((string) ($data['currency'] ?? 'LKR')) ?: 'LKR',
            'booking_status' => trim((string) ($data['booking_status'] ?? $data['status'] ?? 'pending')) ?: 'pending',
            'payment_status' => trim((string) ($data['payment_status'] ?? 'unpaid')) ?: 'unpaid',
            'payment_due_date' => $data['payment_due_date'] ?? null,
            'notes' => (string) ($data['notes'] ?? ''),
            'route_summary' => trim((string) ($data['route_summary'] ?? '')),
            'trip_story' => trim((string) ($data['trip_story'] ?? $data['journey_story'] ?? '')),
            'trip_highlights' => $tripHighlights,
            'add_ons' => is_array($addOns) ? $addOns : null,
            'itinerary' => is_array($itinerary) ? $itinerary : null,
            'support_contact' => $supportContact !== '' ? $supportContact : 'support@lankatrails.example',
            'destination_story' => (string) ($data['destination_story'] ?? ''),
            'package_story' => (string) ($data['package_story'] ?? ''),
            'service_story' => (string) ($data['service_story'] ?? ''),
            'activity_story' => (string) ($data['activity_story'] ?? ''),
        ];
    }

    private function paginatedResponse($paginator): array
    {
        return [
            'items' => collect($paginator->items())->map(fn (Booking $booking) => $booking->toTourismArray())->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);
        // $this->seedTenantBookings($tenant);

        $perPage = max(1, min((int) $request->integer('per_page', 10), 50));
        $search = trim((string) $request->input('search', ''));

        $query = Booking::query()->where('tenant_id', $tenant->id)->latest('id');
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('reference', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhere('customer_email', 'like', '%' . $search . '%')
                    ->orWhere('package_name', 'like', '%' . $search . '%')
                    ->orWhere('destination', 'like', '%' . $search . '%')
                    ->orWhere('booking_status', 'like', '%' . $search . '%')
                    ->orWhere('payment_status', 'like', '%' . $search . '%');
            });
        }

        $paginator = $query->paginate($perPage)->withQueryString();
        return $this->ok($this->paginatedResponse($paginator));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        $validated = $request->validate([
            'tenantKey' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'string', 'max:255'],
            'package_name' => ['nullable', 'string', 'max:255'],
            'package_slug' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'destination_slug' => ['nullable', 'string', 'max:255'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'service_slug' => ['nullable', 'string', 'max:255'],
            'activity_name' => ['nullable', 'string', 'max:255'],
            'activity_slug' => ['nullable', 'string', 'max:255'],
            'travel_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date'],
            'adults' => ['nullable', 'integer', 'min:0'],
            'children' => ['nullable', 'integer', 'min:0'],
            'infants' => ['nullable', 'integer', 'min:0'],
            'travelers_count' => ['nullable', 'integer', 'min:0'],
            'total_amount' => ['nullable', 'integer', 'min:0'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'booking_status' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'payment_due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'route_summary' => ['nullable', 'string', 'max:255'],
            'trip_story' => ['nullable', 'string'],
            'trip_highlights' => ['nullable'],
            'destination_story' => ['nullable', 'string'],
            'package_story' => ['nullable', 'string'],
            'service_story' => ['nullable', 'string'],
            'activity_story' => ['nullable', 'string'],
        ]);

        $record = Booking::query()->create($this->bookingPayload($validated, $tenant));
        return $this->ok($record->toTourismArray(), 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $booking->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        return $this->ok($booking->toTourismArray());
    }

    public function update(Request $request, Booking $booking): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $booking->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        $validated = $request->validate([
            'tenantKey' => ['required', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'string', 'max:255'],
            'package_name' => ['nullable', 'string', 'max:255'],
            'package_slug' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'destination_slug' => ['nullable', 'string', 'max:255'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'service_slug' => ['nullable', 'string', 'max:255'],
            'activity_name' => ['nullable', 'string', 'max:255'],
            'activity_slug' => ['nullable', 'string', 'max:255'],
            'travel_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date'],
            'adults' => ['nullable', 'integer', 'min:0'],
            'children' => ['nullable', 'integer', 'min:0'],
            'infants' => ['nullable', 'integer', 'min:0'],
            'travelers_count' => ['nullable', 'integer', 'min:0'],
            'total_amount' => ['nullable', 'integer', 'min:0'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'booking_status' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'payment_due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'route_summary' => ['nullable', 'string', 'max:255'],
            'trip_story' => ['nullable', 'string'],
            'trip_highlights' => ['nullable'],
            'destination_story' => ['nullable', 'string'],
            'package_story' => ['nullable', 'string'],
            'service_story' => ['nullable', 'string'],
            'activity_story' => ['nullable', 'string'],
        ]);

        $booking->update($this->bookingPayload($validated, $tenant, $booking));
        return $this->ok($booking->fresh()->toTourismArray());
    }

    public function destroy(Request $request, Booking $booking): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $booking->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        $booking->delete();
        return $this->ok(['deleted' => true]);
    }

    public function storePublic(Request $request, string $tenantKey): JsonResponse
    {
        $tenant = Tenant::query()->where('key', $tenantKey)->first();
        if (!$tenant) {
            return $this->error('Tenant not found.', 422);
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:100'],
            'booking_status' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'destination' => ['nullable', 'string', 'max:255'],
            'destination_slug' => ['nullable', 'string', 'max:255'],
            'package_name' => ['nullable', 'string', 'max:255'],
            'package_slug' => ['nullable', 'string', 'max:255'],
            'service_name' => ['nullable', 'string', 'max:255'],
            'service_slug' => ['nullable', 'string', 'max:255'],
            'activity_name' => ['nullable', 'string', 'max:255'],
            'activity_slug' => ['nullable', 'string', 'max:255'],
            'travel_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date'],
            'adults' => ['nullable', 'integer', 'min:0'],
            'children' => ['nullable', 'integer', 'min:0'],
            'infants' => ['nullable', 'integer', 'min:0'],
            'travelers_count' => ['nullable', 'integer', 'min:0'],
            'total_amount' => ['nullable', 'integer', 'min:0'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'route_summary' => ['nullable', 'string', 'max:255'],
            'trip_story' => ['nullable', 'string'],
            'journey_story' => ['nullable', 'string'],
            'trip_highlights' => ['nullable'],
            'destination_story' => ['nullable', 'string'],
            'package_story' => ['nullable', 'string'],
            'service_story' => ['nullable', 'string'],
            'activity_story' => ['nullable', 'string'],
            'add_ons' => ['nullable'],
            'itinerary' => ['nullable'],
            'support_contact' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = $this->bookingPayload(array_merge($validated, [
            'currency' => 'LKR',
            'booking_status' => $validated['booking_status'] ?? ((int) ($validated['paid_amount'] ?? 0) > 0 ? 'confirmed' : 'pending'),
            'payment_status' => $validated['payment_status'] ?? ((int) ($validated['paid_amount'] ?? 0) > 0 ? 'paid' : 'unpaid'),
        ]), $tenant);

        $record = Booking::query()->create($payload);

        return $this->ok($record->toCustomerSummaryArray(), 201);
    }

    public function settleCustomerPayment(Request $request, string $bookingReference): JsonResponse
    {
        $tenantKey = trim((string) $request->input('tenantKey', 'lanka-trails'));
        $tenant = Tenant::query()->where('key', $tenantKey)->first();
        if (!$tenant) {
            return $this->error('Tenant not found.', 422);
        }

        $booking = Booking::query()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($bookingReference) {
                $query->where('reference', $bookingReference)
                    ->orWhere('id', $bookingReference);
            })
            ->first();

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
            'paid_amount' => max((int) $booking->total_amount, (int) ($validated['amount'] ?? $booking->total_amount)),
            'payment_status' => 'paid',
            'booking_status' => $booking->booking_status === 'draft' ? 'confirmed' : $booking->booking_status,
            'payment_due_date' => $booking->payment_due_date ?? now()->toDateString(),
        ]);

        return $this->ok($booking->fresh()->toCustomerSummaryArray());
    }
}

