<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvalidTenantKeyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rejects_an_invalid_tenant_key(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->postJson('/auth/register', [
            'name' => 'Broken Customer',
            'email' => 'broken@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tenantKey' => 'missing-key',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tenantKey');
    }
}
