<?php

namespace Tests\Unit;

use App\Http\Controllers\TourismBusinessController;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TourismBusinessControllerAdminPackageStoreTest extends TestCase
{
    use RefreshDatabase;

    private function request(string $method, array $data = []): Request
    {
        return Request::create('/', $method, $data);
    }

    public function test_admin_package_store_uses_tenant_catalog(): void
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
        $this->assertSame(201, $store->status());
        $this->assertSame('test-package', $storePayload['data']['slug']);
        $this->assertSame('Test Package', $storePayload['data']['title']);
    }
}
