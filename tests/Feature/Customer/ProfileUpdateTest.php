<?php

namespace Tests\Feature\Customer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_updates_return_the_updated_values(): void
    {
        $response = $this->patchJson('/api/customer/profile', [
            'name' => 'Updated Profile',
            'email' => 'updated@example.com',
            'phone' => '+94 77 000 0000',
            'preferredLanguage' => 'Sinhala',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('data.name', 'Updated Profile');
        $response->assertJsonPath('data.preferredLanguage', 'Sinhala');
    }
}
