<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class RoomApiControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_index_returns_rooms_ordered_by_sort_order_then_name(): void
    {
        $user = $this->userWithPermission('view rooms');
        Room::factory()->create(['name' => 'Zeta', 'sort_order' => 1]);
        Room::factory()->create(['name' => 'Alpha', 'sort_order' => 1]);
        Room::factory()->create(['name' => 'Kitchen', 'sort_order' => 0]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/rooms');

        $response->assertOk();
        $response->assertJsonCount(3);
        $response->assertJsonPath('0.name', 'Kitchen');
        $response->assertJsonPath('1.name', 'Alpha');
        $response->assertJsonPath('2.name', 'Zeta');
        $response->assertJsonStructure(['*' => ['id', 'name']]);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/rooms');

        $response->assertUnauthorized();
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/rooms');

        $response->assertForbidden();
    }
}
