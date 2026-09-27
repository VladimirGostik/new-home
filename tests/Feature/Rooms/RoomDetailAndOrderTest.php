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

    public function test_show_lists_multi_room_item_with_room_summary_showing_only_that_rooms_share(): void
    {
        $user = $this->userWithPermission('view rooms');
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();
        Item::factory()->allocatedTo([
            ['room_id' => $roomA->id, 'quantity' => 12.5],
            ['room_id' => $roomB->id, 'quantity' => 8],
        ])->create(['unit_price' => 2]);

        $this->withoutVite()->actingAs($user)->get("/rooms/{$roomA->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items', 1)
                ->where('items.0.total_price', 41)
                ->where('room.summary.total', 25),
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

    public function test_index_shares_multi_room_item_total_across_room_rows_and_whole_house(): void
    {
        $user = $this->userWithPermission('view rooms');
        $roomA = Room::factory()->create(['sort_order' => 0]);
        $roomB = Room::factory()->create(['sort_order' => 1]);
        Item::factory()->allocatedTo([
            ['room_id' => $roomA->id, 'quantity' => 3],
            ['room_id' => $roomB->id, 'quantity' => 1],
            ['room_id' => null, 'quantity' => 1],
        ])->create(['unit_price' => 10]);

        $this->withoutVite()->actingAs($user)->get('/rooms')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rooms.data.0.summary.total', 30)
                ->where('rooms.data.1.summary.total', 10)
                ->where('wholeHouse.total', 10),
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

    public function test_new_room_is_appended_and_update_keeps_position(): void
    {
        $user = $this->userWithPermission('create rooms', 'edit rooms');
        Room::factory()->create(['sort_order' => 4]);

        $this->actingAs($user)->post('/rooms', ['name' => 'Pracovňa'])->assertRedirect();
        $room = Room::where('name', 'Pracovňa')->firstOrFail();
        $this->assertSame(5, $room->sort_order);

        $this->actingAs($user)->put("/rooms/{$room->id}", ['name' => 'Kancelária'])->assertRedirect();
        $this->assertSame(5, $room->fresh()?->sort_order);
    }
}
