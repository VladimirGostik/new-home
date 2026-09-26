<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AppInstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_syncs_permissions_without_touching_data(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:install')->assertSuccessful();

        $this->assertSame(count(PermissionSeeder::PERMISSIONS), Permission::count());
        $this->assertModelExists($user);
    }

    public function test_install_is_idempotent(): void
    {
        $this->artisan('app:install')->assertSuccessful();
        $this->artisan('app:install')->assertSuccessful();

        $this->assertSame(count(PermissionSeeder::PERMISSIONS), Permission::count());
    }
}
