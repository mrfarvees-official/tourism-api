<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Models\Tenant;
use App\Models\TenantAssets;
use App\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DestinationController extends Controller
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

    private function resolveTenantFromKey(string $tenantKey): ?Tenant
    {
        return Tenant::query()->where('key', $tenantKey)->first();
    }

    private function resolveTenant(Request $request): Tenant
    {
        $validated = $request->validate([
            'tenantKey' => ['required', 'string'],
        ]);

        $tenant = $this->resolveTenantFromKey($validated['tenantKey']);

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

    private function resolveImageUrl(Request $request, Tenant $tenant): ?string
    {
        $imageUrl = trim((string) $request->input('imageUrl', $request->input('imageUrl', '')));
        return $imageUrl !== '' ? $imageUrl : null;
    }

    private function destinationQuery(Tenant $tenant): Builder
    {
        return Destination::query()->where('tenant_id', $tenant->id);
    }

    private function requestedVariant(Request $request): ?string
    {
        $variant = trim((string) $request->query('variant', ''));
        return $variant !== '' ? $variant : null;
    }

    private function seedTenantDestinations(Tenant $tenant): void
    {
        if ($this->destinationQuery($tenant)->exists()) {
            return;
        }

        foreach (Destination::defaultSeedRows() as $row) {
            Destination::query()->updateOrCreate(
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

    private function destinationPayload(Destination $destination): array
    {
        return $destination->toTourismArray();
    }

    private function paginatedResponse($paginator): array
    {
        return [
            'items' => collect($paginator->items())->map(fn (Destination $destination) => $this->destinationPayload($destination))->values()->all(),
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
        $this->seedTenantDestinations($tenant);

        $perPage = max(1, min((int) $request->integer('per_page', 10), 50));
        $search = trim((string) $request->input('search', ''));

        $query = $this->destinationQuery($tenant)->latest('id');

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('destination_name', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%')
                    ->orWhere('region', 'like', '%' . $search . '%')
                    ->orWhere('province', 'like', '%' . $search . '%')
                    ->orWhere('district', 'like', '%' . $search . '%');
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
            'destination_name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('destinations', 'slug')->where(fn ($query) => $query->where('tenant_id', $tenant->id)->whereNull('deleted_at'))],
            'description' => ['nullable', 'string'],
            'region' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'best_time_to_visit' => ['nullable', 'string', 'max:255'],
            'nearby_attractions' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'media_id' => ['nullable'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $imageUrl = $this->resolveImageUrl($request, $tenant);
        $slug = Destination::normalizeSlug($validated['slug'] ?? $validated['destination_name']);

        $destination = DB::transaction(function () use ($tenant, $validated, $slug, $imageUrl) {
            return Destination::query()->create([
                'tenant_id' => $tenant->id,
                'slug' => $slug,
                'destination_name' => $validated['destination_name'],
                'description' => $validated['description'] ?? null,
                'region' => $validated['region'] ?? null,
                'province' => $validated['province'] ?? null,
                'district' => $validated['district'] ?? null,
                'best_time_to_visit' => $validated['best_time_to_visit'] ?? null,
                'nearby_attractions' => $validated['nearby_attractions'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'image_url' => $imageUrl,
                'featured' => (bool) ($validated['featured'] ?? false),
                'status' => $validated['status'] ?? 'active',
            ]);
        });

        return $this->ok($this->destinationPayload($destination), 201);
    }

    public function show(Request $request, Destination $destination): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $destination->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        return $this->ok($this->destinationPayload($destination));
    }

    public function update(Request $request, Destination $destination): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $destination->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        $validated = $request->validate([
            'tenantKey' => ['required', 'string'],
            'destination_name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('destinations', 'slug')
                    ->ignore($destination->id)
                    ->where(fn ($query) => $query->where('tenant_id', $tenant->id)->whereNull('deleted_at')),
            ],
            'description' => ['nullable', 'string'],
            'region' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'best_time_to_visit' => ['nullable', 'string', 'max:255'],
            'nearby_attractions' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'imageUrl' => ['nullable', 'string', 'max:2048'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $imageUrl = array_key_exists('imageUrl', $validated)
            ? $this->resolveImageUrl($request, $tenant)
            : $destination->image_url;

        logger()->info("image-url: ", [$validated['imageUrl'], $imageUrl]);

        $destination->fill([
            'destination_name' => $validated['destination_name'] ?? $destination->destination_name,
            'slug' => Destination::normalizeSlug($validated['slug'] ?? $destination->slug, $destination->id),
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $destination->description,
            'region' => array_key_exists('region', $validated) ? $validated['region'] : $destination->region,
            'province' => array_key_exists('province', $validated) ? $validated['province'] : $destination->province,
            'district' => array_key_exists('district', $validated) ? $validated['district'] : $destination->district,
            'best_time_to_visit' => array_key_exists('best_time_to_visit', $validated) ? $validated['best_time_to_visit'] : $destination->best_time_to_visit,
            'nearby_attractions' => array_key_exists('nearby_attractions', $validated) ? $validated['nearby_attractions'] : $destination->nearby_attractions,
            'latitude' => array_key_exists('latitude', $validated) ? $validated['latitude'] : $destination->latitude,
            'longitude' => array_key_exists('longitude', $validated) ? $validated['longitude'] : $destination->longitude,
            'image_url' => $imageUrl,
            'featured' => array_key_exists('featured', $validated) ? (bool) $validated['featured'] : $destination->featured,
            'status' => $validated['status'] ?? $destination->status,
        ]);

        $destination->save();

        return $this->ok($this->destinationPayload($destination));
    }

    public function destroy(Request $request, Destination $destination): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ((int) $destination->tenant_id !== (int) $tenant->id) {
            return $this->error('Record not found.');
        }

        $destination->delete();

        return $this->ok([
            'deleted' => true,
            'id' => $destination->id,
        ]);
    }

    public function publicIndex(Request $request, string $tenantKey): JsonResponse
    {
        $tenant = $this->resolveTenantFromKey($tenantKey);

        if (!$tenant) {
            return $this->error('Tenant not found.', 404);
        }

        $this->seedTenantDestinations($tenant);

        $query = $this->destinationQuery($tenant)
            ->where('status', 'active');

        if ($this->requestedVariant($request) === 'featured') {
            $query->where('featured', true);
        }

        $items = $query
            ->latest('id')
            ->get()
            ->map(fn (Destination $destination) => $this->destinationPayload($destination))
            ->values()
            ->all();

        return $this->ok($items);
    }

    public function publicShow(Request $request, string $tenantKey, string $slug): JsonResponse
    {
        $tenant = $this->resolveTenantFromKey($tenantKey);

        if (!$tenant) {
            return $this->error('Tenant not found.', 404);
        }

        $this->seedTenantDestinations($tenant);

        $query = $this->destinationQuery($tenant)
            ->where('status', 'active')
            ->where('slug', $slug)
            ->when($this->requestedVariant($request) === 'featured', fn (Builder $builder) => $builder->where('featured', true));

        $destination = $query->first();

        return $destination
            ? $this->ok($this->destinationPayload($destination))
            : $this->error('Record not found.');
    }
}
