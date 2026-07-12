<?php

namespace Tests\Unit;

use App\Http\Controllers\TourismBusinessController;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TourismBusinessControllerAdminPackageUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function request(string $method, array $data = []): Request
    {
        return Request::create('/', $method, $data);
    }

    public function test_admin_package_update_uses_tenant_catalog(): void
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

        $update = $controller->adminPackageUpdate($this->request('PATCH', [
            'tenantKey' => 'lanka-trails',
            'name' => 'Updated Package',
            'status' => 'active',
        ]), $id);

        $updatePayload = json_decode($update->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(200, $update->status());
        $this->assertSame('Updated Package', $updatePayload['data']['title']);
    }
}
