<?php

namespace Tests\Feature\Public;

use App\Models\Tenant;
use App\Models\TenantPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTenantPageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_tenant_page_access_returns_the_published_page(): void
    {
        $tenant = Tenant::query()->create([
            'key' => 'lanka-trails',
            'name' => 'Lanka Trails',
            'status' => 'active',
        ]);

        TenantPages::query()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'home',
            'title' => 'Home',
            'schema' => [
                'schema' => [
                    'hero' => ['headline' => 'Welcome'],
                ],
                'components' => [],
            ],
            'seo' => [
                'title' => 'Home',
            ],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/live/lanka-trails/home');

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('data.slug', 'home');
        $response->assertJsonPath('data.title', 'Home');
    }
}
