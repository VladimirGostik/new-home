<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class LoginDurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_lasts_two_weeks(): void
    {
        $this->assertSame(20160, config('session.lifetime'));
    }

    public function test_remember_me_cookie_lasts_one_year(): void
    {
        $user = User::factory()->create(['email' => 'mama@example.com']);

        $response = $this->post('/login', [
            'email' => 'mama@example.com',
            'password' => 'password',
            'remember' => true,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), Auth::guard('web')->getRecallerName()));

        $this->assertNotNull($cookie, 'remember-me cookie missing');
        $days = ($cookie->getExpiresTime() - time()) / 86400;
        $this->assertEqualsWithDelta(365, $days, 1);
    }
}
