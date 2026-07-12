<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_registration_creates_a_tenant_and_attaches_the_new_user(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->postJson('/auth/register', [
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('user.email', 'owner@example.com');
        $response->assertJsonPath('user.tenants.0.role', 'tenant_owner');

        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.com',
        ]);
    }
}
