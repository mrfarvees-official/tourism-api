<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContentSchemaResource;
use App\Models\ContentSchema;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContentSchemaController extends Controller
{

    public function index(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'tenantKey' => ['required', 'string'],
        ]);

        $tenant = Tenant::query()->where('key', $request->tenantKey)->first();

        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant'
            ], 404);
        }

        $tenantId = $tenant->id;

        $tenantUser = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$tenantUser) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant user'
            ], 404);
        }

        $contents = ContentSchema::query()
            ->where('tenant_id', $tenantId)
            ->get();

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => ContentSchemaResource::collection($contents),
        ], 200);
    }

    public function available(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'tenantKey' => ['required', 'string'],
        ]);

        $tenant = Tenant::query()->where('key', $request->tenantKey)->first();

        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant'
            ], 404);
        }

        $tenantUser = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$tenantUser) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant user'
            ], 404);
        }

        $contents = ContentSchema::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'enabled')
            ->get();

        // $contents = $contents->filter(fn (ContentSchema $schema) => $this->schemaSourceKey($schema->schema) !== null);

                logger()->info('data', [$contents]);

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => ContentSchemaResource::collection($contents),
        ], 200);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'tenantKey' => ['required', 'string'],
            'name'      => ['required', 'string'],
            'menu'      => ['required', 'string'],
            'schema'    => ['required', 'json'],
            'version'   => ['required', 'string'],
            'status'    => ['required', 'string', 'in:enabled,disabled'],
        ]);

        $tenant = Tenant::query()->where('key', $request->tenantKey)->first();

        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant'
            ], 404);
        }

        $tenantId = $tenant->id;

        $tenantUser = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$tenantUser) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant user'
            ], 404);
        }

        $foundLatestSchema = ContentSchema::query()
            ->where('tenant_id', $tenantId)
            ->where('menu', $request->menu)
            ->get()
            ->sort(function ($a, $b) {
                return version_compare(
                    ltrim($b->version, 'v'),
                    ltrim($a->version, 'v')
                );
            })
            ->first();

        $version = $request->version;

        if ($foundLatestSchema) {
            $version = $this->compareVersion($foundLatestSchema->version, $request->version);
        }

        $payload = $request->except('tenantKey');
        $sourceKey = $this->schemaSourceKey($payload['schema'] ?? null);
        if (!$sourceKey) {
            return response()->json([
                'ok' => false,
                'status' => 422,
                'error' => 'schema.meta.sourceKey is required',
            ], 422);
        }

        $content = ContentSchema::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->where('menu', $payload['menu'])
            ->first();

        if ($content) {
            $content->restore();
            $content->fill([
                'menu' => $payload['menu'],
                'schema' => $payload['schema'],
                'version' => $version,
                'status' => $payload['status'],
            ])->save();
        } else {
            $content = ContentSchema::create([
                'tenant_id' => $tenantId,
                'name' => $payload['name'],
                'menu' => $payload['menu'],
                'schema' => $payload['schema'],
                'version' => $version,
                'status' => $payload['status'],
            ]);
        }

        if ($payload['status'] === "enabled") {
            ContentSchema::query()
                ->where('tenant_id', $tenantId)
                ->where('menu', $payload['menu'])
                ->whereNot('version', $version)
                ->update([
                    'status' => 'disabled'
                ]);
        }

        $content = ContentSchema::query()
            ->where('tenant_id', $tenantId)
            ->where('menu', $payload['menu'])
            ->where('version', $version)
            ->first();

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Schema has created',
            'data' => ContentSchemaResource::make($content),
        ]);
    }

    public function show(Request $request, ContentSchema $content)
    {
        $user = $request->user();

        $request->validate([
            'tenantKey' => ['required', 'string'],
        ]);

        $tenant = Tenant::query()->where('key', $request->tenantKey)->first();

        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant'
            ], 404);
        }

        $tenantUser = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$tenantUser) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant user'
            ], 404);
        }

        if ($content->tenant_id !== $tenant->id) {
            return response()->json([
                'ok' => false,
                'status' => 401,
                'error' => 'Unauthorized access'
            ], 401);
        }

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => ContentSchemaResource::make($content),
        ], 200);
    }


    public function update(Request $request, ContentSchema $content)
    {
        $user = $request->user();
        $data = $request->validate([
            'tenantKey' => ['required', 'string'],
            'name' => ['sometimes', 'string'],
            'menu' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', 'in:enabled,disabled'],
            'schema' => ['sometimes', 'json']
        ]);

        $tenant = Tenant::query()->where('key', $request->tenantKey)->first();

        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant'
            ], 404);
        }

        $tenantUser = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$tenantUser) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant user'
            ], 404);
        }

        if ($content->tenant_id !== $tenant->id) {
            return response()->json([
                'ok' => false,
                'status' => 401,
                'error' => 'Unauthorized access'
            ]);
        }

        unset($data['tenantKey']);

        if (array_key_exists('schema', $data) && !$this->schemaSourceKey($data['schema'])) {
            return response()->json([
                'ok' => false,
                'status' => 422,
                'error' => 'schema.meta.sourceKey is required',
            ], 422);
        }

        DB::transaction(function () use ($data, $content, $tenant) {
            $newMenu = $data['menu'] ?? $content->menu;
            $newStatus = $data['status'] ?? $content->status;

            if ($newStatus === 'enabled') {
                ContentSchema::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('menu', $newMenu)
                    ->where('id', '!=', $content->id)
                    ->update([
                        'status' => 'disabled',
                    ]);
            }

            $content->fill($data);
            $content->save();
        });

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Schema updated',
            'data' => ContentSchemaResource::make($content->fresh()),
        ], 200);
    }


    public function destroy(Request $request, ContentSchema $content)
    {
        $user = $request->user();

        $request->validate([
            'tenantKey' => ['required', 'string']
        ]);

        $tenant = Tenant::query()->where('key', $request->tenantKey)->first();

        if (!$tenant) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant'
            ], 404);
        }

        $tenantUser = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$tenantUser) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Unknown tenant user'
            ], 404);
        }

        if ($content->tenant_id !== $tenant->id) {
            return response()->json([
                'ok' => false,
                'status' => 401,
                'error' => 'Unauthorized access'
            ]);
        }
        $content->fill([
            'status' => 'disabled'
        ])->save();
        $content->delete();
        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Schema deleted',
            'data' => ContentSchemaResource::make($content),
        ]);
    }

    public function compareVersion(string $old, string $new): string
    {
        if (!preg_match('/^v\d+(?:\.\d+)*$/', $new)) {
            throw new \InvalidArgumentException('Invalid version format');
        }

        $oldVersion = ltrim($old, 'v');
        $newVersion = ltrim($new, 'v');

        if (version_compare($newVersion, $oldVersion, '>')) {
            return $new;
        }

        $parts = explode('.', $oldVersion);
        $lastIndex = count($parts) - 1;
        $parts[$lastIndex] = (string) ((int) $parts[$lastIndex] + 1);

        return 'v' . implode('.', $parts);
    }

    private function schemaSourceKey(mixed $schema): ?string
    {
        if (is_array($schema)) {
            $menu = $schema['menu'] ?? null;
            if (is_string($menu)) {
                $menu = trim($menu);
                if ($menu !== '') {
                    return $menu;
                }
            }
        }

        if (is_string($schema)) {
            $schema = json_decode($schema, true);
        }

        if (!is_array($schema)) {
            return null;
        }

        $meta = $schema['meta'] ?? null;
        if (!is_array($meta)) {
            return null;
        }

        $sourceKey = $meta['sourceKey'] ?? null;
        if (!is_string($sourceKey)) {
            return null;
        }

        $sourceKey = trim($sourceKey);
        return $sourceKey !== '' ? $sourceKey : null;
    }
}
