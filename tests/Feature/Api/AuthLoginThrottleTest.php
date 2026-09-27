<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sixth_login_attempt_within_a_minute_returns_429(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);

            $response->assertUnprocessable();
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_login_throttle_is_keyed_per_email_and_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'first@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => 'second@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
    }

    public function test_spoofed_x_real_ip_header_cannot_bypass_the_throttle(): void
    {
        User::factory()->create(['email' => 'spoofed@example.com']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->withHeaders(['X-Real-IP' => "203.0.113.{$attempt}"])->postJson('/api/auth/login', [
                'email' => 'spoofed@example.com',
                'password' => 'wrong-password',
            ]);

            $response->assertUnprocessable();
        }

        $response = $this->withHeaders(['X-Real-IP' => '203.0.113.6'])->postJson('/api/auth/login', [
            'email' => 'spoofed@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }
}
