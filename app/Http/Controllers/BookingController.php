<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    private function bookingPayload(array $data, Tenant $tenant, ?Booking $existing = null): array
    {
        $reference = trim((string) ($data['reference'] ?? ''));

        return [
            'tenant_id' => $tenant->id,
            'reference' => Booking::normalizeReference($reference, $existing?->id),
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
            'trip_highlights' => $data['trip_highlights'] ?? null,
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
        $this->seedTenantBookings($tenant);

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
        ]);

        $payload = $this->bookingPayload(array_merge($validated, [
            'currency' => 'LKR',
            'booking_status' => 'pending',
            'payment_status' => 'unpaid',
        ]), $tenant);

        $record = Booking::query()->create($payload);

        return $this->ok($record->toCustomerSummaryArray(), 201);
    }
}
