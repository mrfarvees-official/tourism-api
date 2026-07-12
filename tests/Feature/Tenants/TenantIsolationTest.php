<?php

namespace Tests\Feature\Tenants;

use App\Http\Controllers\DestinationController;
use App\Models\Destination;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Support\TourismFixtures;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use TourismFixtures;

    public function test_tenant_separation_blocks_cross_tenant_destination_access(): void
    {
        [$tenantA, $ownerA] = $this->createTenantOwner('lanka-trails', 'Lanka Trails');
        [$tenantB, $ownerB] = $this->createTenantOwner('island-routes', 'Island Routes');

        $controller = $this->app->make(DestinationController::class);

        $createResponse = $controller->store($this->controllerRequest('POST', [
            'tenantKey' => $tenantA->key,
            'destination_name' => 'Sigiriya',
            'description' => 'Heritage site',
            'region' => 'Cultural Triangle',
            'status' => 'active',
        ], user: $ownerA));

        $payload = json_decode($createResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $destinationId = $payload['data']['id'];

        $otherTenantLookup = $controller->show(
            $this->controllerRequest('GET', ['tenantKey' => $tenantB->key], user: $ownerB),
            Destination::query()->findOrFail($destinationId)
        );

        $otherPayload = json_decode($otherTenantLookup->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(404, $otherTenantLookup->status());
        $this->assertSame('Record not found.', $otherPayload['error']);
    }
}
