<?php

namespace Tests\Feature\Support;

use App\Models\Tenant;
use App\Models\User;
use App\Services\CloudinaryMediaService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait TourismFixtures
{
    protected function controllerRequest(string $method, array $data = [], array $files = [], ?User $user = null): Request
    {
        $request = Request::create('/test', $method, $data, [], $files);

        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        return $request;
    }

    protected function createTenantOwner(string $tenantKey, string $tenantName): array
    {
        $tenant = Tenant::query()->create([
            'key' => $tenantKey,
            'name' => $tenantName,
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'name' => $tenantName . ' Owner',
            'email' => Str::slug($tenantName) . '@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $tenant->users()->attach($user->id, [
            'role' => 'tenant_owner',
            'status' => 'active',
            'joined_at' => now(),
            'invited_by_user_id' => $user->id,
        ]);

        return [$tenant, $user];
    }

    protected function bindFakeCloudinaryService(): void
    {
        $this->app->instance(CloudinaryMediaService::class, new class extends CloudinaryMediaService {
            public function uploadImage(UploadedFile $file): array
            {
                return [
                    'public_id' => 'media/test-image',
                    'secure_url' => 'https://example.com/test-image.jpg',
                    'resource_type' => 'image',
                    'format' => 'jpg',
                    'bytes' => 2048,
                    'version' => 1,
                ];
            }

            public function deleteImage(string $publicId): array
            {
                return ['result' => 'ok', 'public_id' => $publicId];
            }
        });
    }
}
