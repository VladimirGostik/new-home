<?php

declare(strict_types=1);

namespace Tests\Feature\Rooms;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class RoomDetailAndOrderTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    public function test_show_lists_room_items_with_room_total(): void
    {
        $user = $this->userWithPermission('view rooms');
        $room = Room::factory()->create();
        Item::factory()->create(['room_id' => $room->id, 'unit_price' => 12.5, 'quantity' => 4, 'status' => ItemStatus::Bought]);
        Item::factory()->create(['room_id' => $room->id, 'unit_price' => null]);
        Item::factory()->create(['room_id' => null, 'unit_price' => 500]);

        $this->withoutVite()->actingAs($user)->get("/rooms/{$room->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Rooms/Show')
                ->where('room.id', $room->id)
                ->where('room.summary.items_count', 2)
                ->where('room.summary.total', 50)
                ->where('room.summary.bought_total', 50)
                ->has('items', 2),
            );
    }

    public function test_index_includes_per_room_summary_and_whole_house(): void
    {
        $user = $this->userWithPermission('view rooms');
        $room = Room::factory()->create();
        Item::factory()->count(2)->create(['room_id' => $room->id, 'unit_price' => 10, 'quantity' => 1]);
        Item::factory()->create(['room_id' => null, 'unit_price' => 3, 'quantity' => 1]);

        $this->withoutVite()->actingAs($user)->get('/rooms')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rooms.data.0.summary.items_count', 2)
                ->where('rooms.data.0.summary.total', 20)
                ->where('wholeHouse.items_count', 1)
                ->where('wholeHouse.total', 3),
            );
    }

    public function test_reorder_persists_positions(): void
    {
        $user = $this->userWithPermission('edit rooms');
        $a = Room::factory()->create(['sort_order' => 0]);
        $b = Room::factory()->create(['sort_order' => 1]);
        $c = Room::factory()->create(['sort_order' => 2]);

        $this->actingAs($user)
            ->put('/rooms/reorder', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $c->fresh()?->sort_order);
        $this->assertSame(1, $a->fresh()?->sort_order);
        $this->assertSame(2, $b->fresh()?->sort_order);
    }

    public function test_reorder_rejects_unknown_ids(): void
    {
        $user = $this->userWithPermission('edit rooms');

        $this->actingAs($user)
            ->put('/rooms/reorder', ['ids' => ['01999999-0000-7000-8000-000000000000']])
            ->assertSessionHasErrors('ids.0');
    }

    public function test_reorder_requires_edit_permission(): void
    {
        $room = Room::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put('/rooms/reorder', ['ids' => [$room->id]])
            ->assertForbidden();
    }
}
