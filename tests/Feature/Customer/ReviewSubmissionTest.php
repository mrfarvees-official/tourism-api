<?php

namespace Tests\Feature\Customer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_submission_accepts_a_new_review(): void
    {
        $response = $this->postJson('/api/customer/reviews', [
            'bookingId' => 'bk_001',
            'title' => 'Great trip',
            'message' => 'Everything went smoothly.',
            'rating' => 5,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('data.status', 'submitted');
        $response->assertJsonPath('data.rating', 5);
    }
}
