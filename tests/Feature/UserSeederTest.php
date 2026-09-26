<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_seeded_as_family_member_and_can_log_in(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'gostikvladko9@gmail.com')->firstOrFail();
        $this->assertTrue($owner->hasRole('user'));
        $this->assertFalse($owner->hasRole('admin'));
        $this->assertTrue(User::where('email', 'admin@example.com')->firstOrFail()->hasRole('admin'));
        $this->assertTrue($owner->is_active);

        $this->post('/login', [
            'email' => 'gostikvladko9@gmail.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
    }
}
