<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_member_sees_main_sections_and_profile_plus_media_in_settings(): void
    {
        $this->seed(PermissionSeeder::class);
        $member = User::factory()->create();
        $member->assignRole('user');

        $this->withoutVite()->actingAs($member)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('navigation.0.key', 'dashboard')
                ->where('navigation.1.key', 'items.index')
                ->where('navigation.2.key', 'items.mine')
                ->where('navigation.3.key', 'expenses.index')
                ->where('navigation.4.key', 'rooms.index')
                ->where('navigation.5.key', 'group:settings')
                ->has('navigation.5.children', 2)
                ->where('navigation.5.children.0.key', 'profile.show')
                ->where('navigation.5.children.1.key', 'media.index'),
            );
    }

    public function test_admin_sees_skeleton_pages_under_settings(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->withoutVite()->actingAs($admin)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('navigation', 6)
                ->where('navigation.5.children.1.key', 'users.index')
                ->where('navigation.5.children.2.key', 'roles.index')
                ->where('navigation.5.children.3.key', 'audit-logs.index')
                ->where('navigation.5.children.4.key', 'media.index'),
            );
    }
}
