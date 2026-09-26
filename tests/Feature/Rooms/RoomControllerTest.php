<?php

declare(strict_types=1);

namespace Tests\Feature\Rooms;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class RoomControllerTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_index_is_accessible_with_view_rooms_permission(): void
    {
        $user = $this->userWithPermission('view rooms');
        Room::factory()->count(3)->create();

        $response = $this->withoutVite()->actingAs($user)->get('/rooms');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Rooms/Index')
            ->has('rooms.data', 3)
            ->has('filters'),
        );
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/rooms');

        $response->assertForbidden();
    }

    public function test_create_is_accessible_with_create_rooms_permission(): void
    {
        $user = $this->userWithPermission('create rooms');

        $response = $this->withoutVite()->actingAs($user)->get('/rooms/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Rooms/Form'));
    }

    public function test_create_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/rooms/create');

        $response->assertForbidden();
    }

    public function test_store_creates_room_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('create rooms');

        $response = $this->actingAs($user)->post('/rooms', [
            'name' => 'Kitchen',
            'description' => 'Main kitchen',
            'sort_order' => 5,
        ]);

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('rooms', ['name' => 'Kitchen', 'sort_order' => 5]);
    }

    public function test_store_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/rooms', ['name' => 'Kitchen']);

        $response->assertForbidden();
    }

    public function test_store_with_missing_name_returns_validation_error(): void
    {
        $user = $this->userWithPermission('create rooms');

        $response = $this->actingAs($user)->post('/rooms', ['name' => '']);

        $response->assertInvalid(['name']);
    }

    public function test_edit_is_accessible_with_edit_rooms_permission(): void
    {
        $user = $this->userWithPermission('edit rooms');
        $room = Room::factory()->create();

        $response = $this->withoutVite()->actingAs($user)->get("/rooms/{$room->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Rooms/Form')
            ->has('room'),
        );
    }

    public function test_edit_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($user)->get("/rooms/{$room->id}/edit");

        $response->assertForbidden();
    }

    public function test_update_modifies_room_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('edit rooms');
        $room = Room::factory()->create(['name' => 'Old name']);

        $response = $this->actingAs($user)->put("/rooms/{$room->id}", [
            'name' => 'New name',
            'description' => $room->description,
            'sort_order' => $room->sort_order,
        ]);

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'name' => 'New name']);
    }

    public function test_update_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($user)->put("/rooms/{$room->id}", [
            'name' => 'New name',
        ]);

        $response->assertForbidden();
    }

    public function test_destroy_deletes_room_and_redirects_with_success(): void
    {
        $user = $this->userWithPermission('delete rooms');
        $room = Room::factory()->create();

        $response = $this->actingAs($user)->delete("/rooms/{$room->id}");

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    public function test_destroy_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($user)->delete("/rooms/{$room->id}");

        $response->assertForbidden();
    }
}
