<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_registration_with_tenant_key_attaches_the_user_to_that_tenant(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $tenant = Tenant::query()->create([
            'key' => 'lanka-trails',
            'name' => 'Lanka Trails',
            'status' => 'active',
        ]);

        $response = $this->postJson('/auth/register', [
            'name' => 'Customer User',
            'email' => 'customer@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tenantKey' => 'lanka-trails',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('user.tenants.0.key', 'lanka-trails');
        $response->assertJsonPath('user.tenants.0.role', 'customer');

        $user = User::query()->where('email', 'customer@example.com')->firstOrFail();
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'customer',
            'status' => 'active',
        ]);
    }
}
