<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_succeeds_with_correct_credentials_and_fails_with_wrong_password(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        User::query()->create([
            'name' => 'Login User',
            'email' => 'login@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $success = $this->postJson('/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $success->assertOk();
        $success->assertJsonPath('ok', true);
        $success->assertJsonPath('user.email', 'login@example.com');

        $failure = $this->postJson('/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ]);

        $failure->assertStatus(422);
        $failure->assertJsonPath('ok', false);
        $failure->assertJsonPath('errors.email.0', 'Invalid credentials.');
    }
}
