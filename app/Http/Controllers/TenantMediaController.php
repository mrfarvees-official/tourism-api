<?php

namespace App\Http\Controllers;

use App\Http\Resources\TenantAssetResource;
use App\Models\Tenant;
use App\Models\TenantAssets;
use App\Models\TenantUser;
use App\Services\CloudinaryMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantMediaController extends Controller
{
    public function __construct(private readonly CloudinaryMediaService $cloudinaryMediaService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        $assets = TenantAssets::query()
            ->where('tenant_id', $tenant->id)
            ->where('kind', 'image')
            ->latest('id')
            ->get();

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => TenantAssetResource::collection($assets),
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        $validated = $request->validate([
            'tenantKey' => ['required', 'string'],
            'label' => ['nullable', 'string', 'max:120'],
            'file' => ['required', 'file', 'image', 'max:10240'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $cloudinary = null;

        try {
            $cloudinary = $this->cloudinaryMediaService->uploadImage($file);

            $asset = DB::transaction(function () use ($tenant, $validated, $file, $cloudinary) {
                return TenantAssets::create([
                    'tenant_id' => $tenant->id,
                    'kind' => 'image',
                    'disk' => 'cloudinary',
                    'path' => (string) ($cloudinary['public_id'] ?? ''),
                    'public_id' => (string) ($cloudinary['public_id'] ?? ''),
                    'secure_url' => $cloudinary['secure_url'] ?? null,
                    'resource_type' => $cloudinary['resource_type'] ?? 'image',
                    'mime' => $cloudinary['format'] ? 'image/' . $cloudinary['format'] : $file->getMimeType(),
                    'size' => $cloudinary['bytes'] ?? $file->getSize(),
                    'label' => $validated['label'] ?? $file->getClientOriginalName(),
                    'original_name' => $file->getClientOriginalName(),
                    'cloudinary_version' => $cloudinary['version'] ?? null,
                ]);
            });

            return response()->json([
                'ok' => true,
                'status' => 201,
                'data' => new TenantAssetResource($asset),
            ], 201);
        } catch (\Throwable $throwable) {
            if (is_array($cloudinary) && isset($cloudinary['public_id'])) {
                try {
                    $this->cloudinaryMediaService->deleteImage((string) $cloudinary['public_id']);
                } catch (\Throwable) {
                    // Ignore cleanup errors after a failed save.
                }
            }

            throw $throwable instanceof RuntimeException
                ? $throwable
                : new RuntimeException($throwable->getMessage(), previous: $throwable);
        }
    }

    public function destroy(Request $request, TenantAssets $asset): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        $this->assertTenantUser($request, $tenant);

        if ($asset->tenant_id !== $tenant->id) {
            return response()->json([
                'ok' => false,
                'status' => 404,
                'error' => 'Media not found',
            ], 404);
        }

        $publicId = $asset->public_id ?: $asset->path;

        if (!$publicId) {
            return response()->json([
                'ok' => false,
                'status' => 422,
                'error' => 'Media is missing a Cloudinary public ID',
            ], 422);
        }

        $this->cloudinaryMediaService->deleteImage($publicId);
        $asset->forceDelete();

        return response()->json([
            'ok' => true,
            'status' => 200,
            'data' => [
                'deleted' => true,
                'id' => $asset->id,
            ],
        ], 200);
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
}
