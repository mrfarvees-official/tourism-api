<?php

namespace Tests\Feature\Public;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPackagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_packages_are_seeded_for_known_tenant(): void
    {
        Tenant::query()->create([
            'key' => 'lanka-trails',
            'name' => 'Lanka Trails',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/public/lanka-trails/packages');

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonCount(10, 'data');
        $response->assertJsonFragment(['slug' => 'sri-lanka-highlights']);
    }
}
