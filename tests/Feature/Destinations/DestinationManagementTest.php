<?php

namespace Tests\Feature\Destinations;

use App\Http\Controllers\DestinationController;
use App\Models\Destination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Support\TourismFixtures;
use Tests\TestCase;

class DestinationManagementTest extends TestCase
{
    use RefreshDatabase;
    use TourismFixtures;

    public function test_destination_management_supports_create_update_and_delete(): void
    {
        [$tenant, $owner] = $this->createTenantOwner('lanka-trails', 'Lanka Trails');
        $controller = $this->app->make(DestinationController::class);

        $create = $controller->store($this->controllerRequest('POST', [
            'tenantKey' => $tenant->key,
            'destination_name' => 'Ella',
            'description' => 'Hill country town',
            'region' => 'Hill Country',
            'status' => 'active',
        ], user: $owner));

        $createPayload = json_decode($create->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $destinationId = $createPayload['data']['id'];

        $this->assertSame(201, $create->status());
        $this->assertSame('Ella', $createPayload['data']['title']);

        $model = Destination::query()->findOrFail($destinationId);

        $update = $controller->update($this->controllerRequest('PATCH', [
            'tenantKey' => $tenant->key,
            'destination_name' => 'Ella Updated',
            'description' => 'Updated hill country town',
            'region' => 'Hill Country',
            'imageUrl' => 'https://example.com/ella.jpg',
            'status' => 'active',
        ], user: $owner), $model);

        $updatePayload = json_decode($update->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(200, $update->status());
        $this->assertSame('Ella Updated', $updatePayload['data']['title']);

        $destroy = $controller->destroy($this->controllerRequest('DELETE', [
            'tenantKey' => $tenant->key,
        ], user: $owner), $model->fresh());

        $destroyPayload = json_decode($destroy->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(200, $destroy->status());
        $this->assertTrue($destroyPayload['data']['deleted']);
    }
}
