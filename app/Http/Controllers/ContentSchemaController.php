<?php

namespace App\Http\Controllers;

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

        $tenant = Tenant::where('key', $request->tenantKey)->first();

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
            'data' => $contents,
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

        $tenant = Tenant::where('key', $request->tenantKey)->first();

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
            ->where('name', $request->name)
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

        unset($request['tenantKey']);

        ContentSchema::createOrRestore(
            [
                'tenant_id' => $tenantId,
                'name' => $request->name
            ],
            [
                'menu' => $request->menu,
                'schema' => $request->schema,
                'version' => $version,
                'status' => $request->status
            ]
        );

        if ($request->status === "enabled") {
            ContentSchema::query()
                ->where('tenant_id', $tenantId)
                ->where('name', $request->name)
                ->whereNot('version', $version)
                ->update([
                    'status' => 'disabled'
                ]);
        }

        return response()->json([
            'ok' => true,
            'status' => 200,
            'message' => 'Schema has created'
        ]);
    }

    public function show(Request $request, ContentSchema $content) {}


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

        $tenant = Tenant::where('key', $request->tenantKey)->first();

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

        DB::transaction(function () use ($data, $content, $tenant) {
            $newName = $data['name'] ?? $content->name;
            $newStatus = $data['status'] ?? $content->status;

            if ($newStatus === 'enabled') {
                ContentSchema::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('name', $newName)
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
            'message' => 'Schema updated'
        ], 200);
    }


    public function destroy(Request $request, ContentSchema $content)
    {
        $user = $request->user();

        $request->validate([
            'tenantKey' => ['required', 'string']
        ]);

        $tenant = Tenant::where('key', $request->tenantKey)->first();

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
            'message' => 'Schema deleted'
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
}
