<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
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
            abort(response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant',
            ], 404));
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
            abort(response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant user',
            ], 404));
        }
    }

    private function customerQuery(Tenant $tenant): Builder
    {
        return Customer::query()->where('tenant_id', $tenant->id);
    }

    private function customerPayload(Customer $customer): array
    {
        return $customer->toTourismArray();
    }

    private function paginatedResponse($paginator): array
    {
        return [
            'items' => collect($paginator->items())->map(fn (Customer $customer) => $this->customerPayload($customer))->values()->all(),
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

        $perPage = max(1, min((int) $request->integer('per_page', 10), 50));
        $search = trim((string) $request->input('search', ''));

        $query = $this->customerQuery($tenant)->latest('id');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('full_name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('nationality', 'like', '%' . $search . '%')
                    ->orWhere('passport_number', 'like', '%' . $search . '%')
                    ->orWhere('preferred_language', 'like', '%' . $search . '%')
                    ->orWhere('loyalty_tier', 'like', '%' . $search . '%')
                    ->orWhere('emergency_contact', 'like', '%' . $search . '%')
                    ->orWhere('address', 'like', '%' . $search . '%');
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
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:150'],
            'passport_number' => ['nullable', 'string', 'max:100'],
            'preferred_language' => ['nullable', 'string', 'max:100'],
            'loyalty_tier' => ['nullable', 'string', 'max:50', Rule::in(['Explorer', 'Insider', 'VIP'])],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => $tenant->id,
            'full_name' => trim((string) $validated['full_name']),
            'email' => trim((string) ($validated['email'] ?? '')) ?: null,
            'phone' => trim((string) ($validated['phone'] ?? '')) ?: null,
            'nationality' => trim((string) ($validated['nationality'] ?? '')) ?: null,
            'passport_number' => trim((string) ($validated['passport_number'] ?? '')) ?: null,
            'preferred_language' => trim((string) ($validated['preferred_language'] ?? '')) ?: null,
            'loyalty_tier' => $validated['loyalty_tier'] ?? 'Explorer',
            'emergency_contact' => trim((string) ($validated['emergency_contact'] ?? '')) ?: null,
            'address' => trim((string) ($validated['address'] ?? '')) ?: null,
        ]);

        return $this->ok($this->customerPayload($customer), 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $customer->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        return $this->ok($this->customerPayload($customer));
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $customer->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        $validated = $request->validate([
            'tenantKey' => ['required', 'string'],
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:150'],
            'passport_number' => ['nullable', 'string', 'max:100'],
            'preferred_language' => ['nullable', 'string', 'max:100'],
            'loyalty_tier' => ['nullable', 'string', 'max:50', Rule::in(['Explorer', 'Insider', 'VIP'])],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
        ]);

        $customer->fill([
            'full_name' => array_key_exists('full_name', $validated) ? trim((string) $validated['full_name']) : $customer->full_name,
            'email' => array_key_exists('email', $validated) ? (trim((string) $validated['email']) ?: null) : $customer->email,
            'phone' => array_key_exists('phone', $validated) ? (trim((string) $validated['phone']) ?: null) : $customer->phone,
            'nationality' => array_key_exists('nationality', $validated) ? (trim((string) $validated['nationality']) ?: null) : $customer->nationality,
            'passport_number' => array_key_exists('passport_number', $validated) ? (trim((string) $validated['passport_number']) ?: null) : $customer->passport_number,
            'preferred_language' => array_key_exists('preferred_language', $validated) ? (trim((string) $validated['preferred_language']) ?: null) : $customer->preferred_language,
            'loyalty_tier' => $validated['loyalty_tier'] ?? $customer->loyalty_tier,
            'emergency_contact' => array_key_exists('emergency_contact', $validated) ? (trim((string) $validated['emergency_contact']) ?: null) : $customer->emergency_contact,
            'address' => array_key_exists('address', $validated) ? (trim((string) $validated['address']) ?: null) : $customer->address,
        ]);

        $customer->save();

        return $this->ok($this->customerPayload($customer));
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $customer->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        $customer->delete();

        return $this->ok([
            'deleted' => true,
            'id' => $customer->id,
        ]);
    }
}
