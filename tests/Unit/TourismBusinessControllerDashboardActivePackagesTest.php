<?php

namespace Tests\Unit;

use App\Http\Controllers\TourismBusinessController;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TourismBusinessControllerDashboardActivePackagesTest extends TestCase
{
    use RefreshDatabase;

    private function request(string $method, array $data = []): Request
    {
        return Request::create('/', $method, $data);
    }

    public function test_dashboard_reports_active_package_counts(): void
    {
        Tenant::query()->create([
            'key' => 'lanka-trails',
            'name' => 'Lanka Trails',
            'status' => 'active',
        ]);

        $controller = $this->app->make(TourismBusinessController::class);
        $response = $controller->dashboard($this->request('GET', [
            'tenantKey' => 'lanka-trails',
        ]));

        $this->assertSame(200, $response->status());
        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(10, $payload['data']['total_active_packages']);
    }
}
