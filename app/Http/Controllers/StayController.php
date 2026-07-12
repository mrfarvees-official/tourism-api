<?php

namespace App\Http\Controllers;

use App\Models\Stay;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StayController extends Controller
{
    private function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['ok' => true, 'status' => $status, 'data' => $data], $status);
    }

    private function error(string $message, int $status = 404): JsonResponse
    {
        return response()->json(['ok' => false, 'status' => $status, 'error' => $message], $status);
    }

    private function resolveTenant(Request $request): Tenant
    {
        $validated = $request->validate(['tenantKey' => ['required', 'string']]);
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

    private function seedTenantStays(Tenant $tenant): void
    {
        if (Stay::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        foreach (Stay::defaultSeedRows() as $row) {
            Stay::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $row['slug']],
                array_merge($row, ['tenant_id' => $tenant->id]),
            );
        }
    }

    private function payload(array $data, Tenant $tenant, ?Stay $existing = null): array
    {
        $slug = trim((string) ($data['slug'] ?? ''));
        $stayName = trim((string) ($data['stay_name'] ?? 'Stay'));

        return [
            'tenant_id' => $tenant->id,
            'slug' => $slug !== '' ? $slug : Stay::normalizeSlug($stayName, $existing?->id),
            'stay_name' => $stayName,
            'description' => (string) ($data['description'] ?? ''),
            'stay_type' => (string) ($data['stay_type'] ?? ''),
            'location' => (string) ($data['location'] ?? ''),
            'room_type' => (string) ($data['room_type'] ?? ''),
            'amenities' => (string) ($data['amenities'] ?? ''),
            'price_label' => (string) ($data['price_label'] ?? ''),
            'price_value' => (int) ($data['price_value'] ?? 0),
            'image_url' => (string) ($data['image_url'] ?? ''),
            'featured' => (bool) ($data['featured'] ?? false),
            'status' => (string) ($data['status'] ?? 'active'),
            'story' => (string) ($data['story'] ?? ''),
        ];
    }

    private function paginatedResponse($paginator): array
    {
        return [
            'items' => collect($paginator->items())->map(fn (Stay $stay) => $stay->toTourismArray())->values()->all(),
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
        $this->seedTenantStays($tenant);

        $perPage = max(1, min((int) $request->integer('per_page', 10), 50));
        $search = trim((string) $request->input('search', ''));
        $query = Stay::query()->where('tenant_id', $tenant->id)->latest('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('slug', 'like', '%' . $search . '%')
                    ->orWhere('stay_name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('stay_type', 'like', '%' . $search . '%')
                    ->orWhere('location', 'like', '%' . $search . '%')
                    ->orWhere('room_type', 'like', '%' . $search . '%')
                    ->orWhere('amenities', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%');
            });
        }

        return $this->ok($this->paginatedResponse($query->paginate($perPage)->withQueryString()));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        $validated = $request->validate([
            'tenantKey' => ['required', 'string'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('stays', 'slug')->where(fn ($query) => $query->where('tenant_id', $tenant->id)->whereNull('deleted_at'))],
            'stay_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'stay_type' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'room_type' => ['nullable', 'string', 'max:255'],
            'amenities' => ['nullable', 'string'],
            'price_label' => ['nullable', 'string', 'max:255'],
            'price_value' => ['nullable', 'integer'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['required', 'string', 'max:50'],
            'story' => ['nullable', 'string'],
        ]);

        $stay = Stay::query()->create($this->payload($validated, $tenant));
        return $this->ok($stay->toTourismArray(), 201);
    }

    public function show(Request $request, Stay $stay): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $stay->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        return $this->ok($stay->toTourismArray());
    }

    public function update(Request $request, Stay $stay): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $stay->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        $validated = $request->validate([
            'tenantKey' => ['required', 'string'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('stays', 'slug')->ignore($stay->id)->where(fn ($query) => $query->where('tenant_id', $tenant->id)->whereNull('deleted_at'))],
            'stay_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'stay_type' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'room_type' => ['nullable', 'string', 'max:255'],
            'amenities' => ['nullable', 'string'],
            'price_label' => ['nullable', 'string', 'max:255'],
            'price_value' => ['nullable', 'integer'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['required', 'string', 'max:50'],
            'story' => ['nullable', 'string'],
        ]);

        $stay->update($this->payload($validated, $tenant, $stay));
        return $this->ok($stay->fresh()->toTourismArray());
    }

    public function destroy(Request $request, Stay $stay): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $stay->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        $stay->delete();
        return $this->ok(['deleted' => true]);
    }
}
