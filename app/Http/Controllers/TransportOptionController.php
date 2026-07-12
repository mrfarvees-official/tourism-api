<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\TransportOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransportOptionController extends Controller
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

    private function seedTenantTransport(Tenant $tenant): void
    {
        if (TransportOption::query()->where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        foreach (TransportOption::defaultSeedRows() as $row) {
            TransportOption::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $row['slug']],
                array_merge($row, ['tenant_id' => $tenant->id]),
            );
        }
    }

    private function payload(array $data, Tenant $tenant, ?TransportOption $existing = null): array
    {
        $slug = trim((string) ($data['slug'] ?? ''));
        $name = trim((string) ($data['transport_name'] ?? 'Transport'));

        return [
            'tenant_id' => $tenant->id,
            'slug' => $slug !== '' ? $slug : TransportOption::normalizeSlug($name, $existing?->id),
            'transport_name' => $name,
            'description' => (string) ($data['description'] ?? ''),
            'transport_type' => (string) ($data['transport_type'] ?? ''),
            'capacity' => (string) ($data['capacity'] ?? ''),
            'coverage' => (string) ($data['coverage'] ?? ''),
            'vehicle' => (string) ($data['vehicle'] ?? ''),
            'pricing_model' => (string) ($data['pricing_model'] ?? ''),
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
            'items' => collect($paginator->items())->map(fn (TransportOption $transportOption) => $transportOption->toTourismArray())->values()->all(),
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
        $this->seedTenantTransport($tenant);

        $perPage = max(1, min((int) $request->integer('per_page', 10), 50));
        $search = trim((string) $request->input('search', ''));
        $query = TransportOption::query()->where('tenant_id', $tenant->id)->latest('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('slug', 'like', '%' . $search . '%')
                    ->orWhere('transport_name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('transport_type', 'like', '%' . $search . '%')
                    ->orWhere('capacity', 'like', '%' . $search . '%')
                    ->orWhere('coverage', 'like', '%' . $search . '%')
                    ->orWhere('vehicle', 'like', '%' . $search . '%')
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
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('transport_options', 'slug')->where(fn ($query) => $query->where('tenant_id', $tenant->id)->whereNull('deleted_at'))],
            'transport_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'transport_type' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'string', 'max:255'],
            'coverage' => ['nullable', 'string', 'max:255'],
            'vehicle' => ['nullable', 'string', 'max:255'],
            'pricing_model' => ['nullable', 'string', 'max:255'],
            'price_label' => ['nullable', 'string', 'max:255'],
            'price_value' => ['nullable', 'integer'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['required', 'string', 'max:50'],
            'story' => ['nullable', 'string'],
        ]);

        $transportOption = TransportOption::query()->create($this->payload($validated, $tenant));
        return $this->ok($transportOption->toTourismArray(), 201);
    }

    public function show(Request $request, TransportOption $transportOption): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $transportOption->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        return $this->ok($transportOption->toTourismArray());
    }

    public function update(Request $request, TransportOption $transportOption): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $transportOption->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        $validated = $request->validate([
            'tenantKey' => ['required', 'string'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('transport_options', 'slug')->ignore($transportOption->id)->where(fn ($query) => $query->where('tenant_id', $tenant->id)->whereNull('deleted_at'))],
            'transport_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'transport_type' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'string', 'max:255'],
            'coverage' => ['nullable', 'string', 'max:255'],
            'vehicle' => ['nullable', 'string', 'max:255'],
            'pricing_model' => ['nullable', 'string', 'max:255'],
            'price_label' => ['nullable', 'string', 'max:255'],
            'price_value' => ['nullable', 'integer'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['required', 'string', 'max:50'],
            'story' => ['nullable', 'string'],
        ]);

        $transportOption->update($this->payload($validated, $tenant, $transportOption));
        return $this->ok($transportOption->fresh()->toTourismArray());
    }

    public function destroy(Request $request, TransportOption $transportOption): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $transportOption->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        $transportOption->delete();
        return $this->ok(['deleted' => true]);
    }
}
