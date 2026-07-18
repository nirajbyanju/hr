<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('attacker@example.com|127.0.0.1');
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        // First five failed attempts are simply rejected as bad credentials.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'attacker@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        // The sixth attempt must be throttled, not just rejected.
        $this->post('/login', [
            'email' => 'attacker@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $messages = implode(' ', session('errors')->get('email'));
        $this->assertStringContainsString('Too many login attempts', $messages);
    }
}
