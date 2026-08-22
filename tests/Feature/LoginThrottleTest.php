<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_wrong_password_attempts_are_throttled(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
            $response->assertStatus(302)->assertSessionHasErrors('email');
        }

        // 6th attempt within the same minute, same email+IP key, is throttled.
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_different_emails_are_throttled_independently(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $userA->email, 'password' => 'wrong-password']);
        }

        // A different email is a different throttle key — not blocked by A's attempts.
        $response = $this->post('/login', [
            'email' => $userB->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
    }
}
