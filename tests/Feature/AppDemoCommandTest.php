<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AppDemoCommandTest extends TestCase
{
    // No RefreshDatabase: app:demo runs migrate:fresh itself, which SQLite refuses inside a transaction.

    public function test_app_demo_seeds_admin_user(): void
    {
        $this->artisan('app:demo')
            ->assertSuccessful();

        $admin = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->is_active);
    }

    public function test_admin_credentials_work_after_demo(): void
    {
        $this->artisan('app:demo')->assertSuccessful();

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    public function test_refuses_to_wipe_production_without_force(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('app:demo')
            ->expectsConfirmation('Are you sure you want to run this command?', 'no')
            ->assertFailed();

        // Nothing was migrated: the in-memory DB still has no tables.
        $this->assertFalse(Schema::hasTable('users'));
    }
}
