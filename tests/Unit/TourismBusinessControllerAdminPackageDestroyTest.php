<?php

namespace Tests\Unit;

use App\Http\Controllers\TourismBusinessController;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TourismBusinessControllerAdminPackageDestroyTest extends TestCase
{
    use RefreshDatabase;

    private function request(string $method, array $data = []): Request
    {
        return Request::create('/', $method, $data);
    }

    public function test_admin_package_destroy_uses_tenant_catalog(): void
    {
        Tenant::query()->create([
            'key' => 'lanka-trails',
            'name' => 'Lanka Trails',
            'status' => 'active',
        ]);

        $controller = $this->app->make(TourismBusinessController::class);

        $store = $controller->adminPackageStore($this->request('POST', [
            'tenantKey' => 'lanka-trails',
            'name' => 'Test Package',
            'status' => 'active',
            'description' => 'A test package',
        ]));

        $storePayload = json_decode($store->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $id = $storePayload['data']['id'];

        $destroy = $controller->adminPackageDestroy($this->request('DELETE', [
            'tenantKey' => 'lanka-trails',
        ]), $id);

        $destroyPayload = json_decode($destroy->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(200, $destroy->status());
        $this->assertTrue($destroyPayload['data']['deleted']);
    }
}
