<?php

namespace Tests\Feature\Media;

use App\Http\Controllers\TenantMediaController;
use App\Models\TenantAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Support\TourismFixtures;
use Tests\TestCase;

class TenantMediaOwnershipTest extends TestCase
{
    use RefreshDatabase;
    use TourismFixtures;

    public function test_media_ownership_is_scoped_to_the_tenant(): void
    {
        [$tenantA, $ownerA] = $this->createTenantOwner('lanka-trails', 'Lanka Trails');
        [$tenantB, $ownerB] = $this->createTenantOwner('island-routes', 'Island Routes');

        $this->bindFakeCloudinaryService();

        $controller = $this->app->make(TenantMediaController::class);

        $store = $controller->store($this->controllerRequest('POST', [
            'tenantKey' => $tenantA->key,
            'label' => 'Homepage hero',
        ], [
            'file' => UploadedFile::fake()->image('hero.jpg'),
        ], $ownerA));

        $storePayload = json_decode($store->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $asset = TenantAssets::query()->findOrFail($storePayload['data']['id']);

        $unauthorizedDelete = $controller->destroy(
            $this->controllerRequest('DELETE', [
                'tenantKey' => $tenantB->key,
            ], user: $ownerB),
            $asset
        );

        $unauthorizedPayload = json_decode($unauthorizedDelete->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(404, $unauthorizedDelete->status());
        $this->assertSame('Media not found', $unauthorizedPayload['error']);

        $authorizedDelete = $controller->destroy(
            $this->controllerRequest('DELETE', [
                'tenantKey' => $tenantA->key,
            ], user: $ownerA),
            $asset
        );

        $authorizedPayload = json_decode($authorizedDelete->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(200, $authorizedDelete->status());
        $this->assertTrue($authorizedPayload['data']['deleted']);
    }
}
