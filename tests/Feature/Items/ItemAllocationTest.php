<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\ItemAllocation;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsers;
use Tests\TestCase;

final class ItemAllocationTest extends TestCase
{
    use CreatesUsers;
    use RefreshDatabase;

    // ── store — happy ────────────────────────────────────────────────────────

    public function test_store_with_multiple_rooms_and_whole_house_creates_matching_allocations(): void
    {
        $user = $this->userWithPermission('create items');
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();

        $response = $this->actingAs($user)->post('/items', [
            'name' => 'Dlažba',
            'allocations' => [
                ['room_id' => $roomA->id, 'quantity' => 12.5],
                ['room_id' => $roomB->id, 'quantity' => 8],
                ['room_id' => null, 'quantity' => 2.25],
            ],
        ]);

        $response->assertRedirect(route('items.index'));
        $item = Item::query()->where('name', 'Dlažba')->firstOrFail();
        $this->assertSame(3, $item->allocations()->count());
        $this->assertSame('22.75', $item->quantity);
        $this->assertNull($item->room_id);
    }

    public function test_store_with_single_allocation_mirrors_room_id(): void
    {
        $user = $this->userWithPermission('create items');
        $room = Room::factory()->create();

        $response = $this->actingAs($user)->post('/items', [
            'name' => 'Koberec',
            'allocations' => [
                ['room_id' => $room->id, 'quantity' => 4],
            ],
        ]);

        $response->assertRedirect(route('items.index'));
        $item = Item::query()->where('name', 'Koberec')->firstOrFail();
        $this->assertSame($room->id, $item->room_id);
        $this->assertSame('4.00', $item->quantity);
    }

    // ── update — happy ───────────────────────────────────────────────────────

    public function test_update_diff_keeps_ids_of_unchanged_allocation_rows(): void
    {
        $user = $this->userWithPermission('edit items');
        $roomA = Room::factory()->create();
        $roomB = Room::factory()->create();
        $item = Item::factory()->allocatedTo([
            ['room_id' => $roomA->id, 'quantity' => 5],
            ['room_id' => $roomB->id, 'quantity' => 3],
        ])->create();
        $unchangedId = $item->allocations()->where('room_id', $roomA->id)->firstOrFail()->id;

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'allocations' => [
                ['room_id' => $roomA->id, 'quantity' => 5],
                ['room_id' => $roomB->id, 'quantity' => 10],
            ],
        ]);

        $response->assertRedirect(route('items.index'));
        $this->assertSame(2, $item->allocations()->count());
        $this->assertDatabaseHas('item_allocations', ['id' => $unchangedId, 'room_id' => $roomA->id, 'quantity' => 5]);
        $this->assertDatabaseHas('item_allocations', ['item_id' => $item->id, 'room_id' => $roomB->id, 'quantity' => 10]);
    }

    // ── store/update — failure (422) ────────────────────────────────────────

    public function test_store_with_allocation_missing_quantity_returns_422(): void
    {
        $user = $this->userWithPermission('create items');
        $room = Room::factory()->create();

        $response = $this->actingAs($user)->post('/items', [
            'name' => 'Dlažba',
            'allocations' => [['room_id' => $room->id]],
        ]);

        $response->assertInvalid(['allocations.0.quantity']);
        $this->assertDatabaseMissing('items', ['name' => 'Dlažba']);
    }

    public function test_update_with_missing_allocations_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$item->id}", ['name' => $item->name]);

        $response->assertInvalid(['allocations']);
    }

    public function test_update_with_empty_allocations_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$item->id}", ['name' => $item->name, 'allocations' => []]);

        $response->assertInvalid(['allocations']);
    }

    public function test_update_with_allocation_missing_quantity_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'allocations' => [['room_id' => $room->id]],
        ]);

        $response->assertInvalid(['allocations.0.quantity']);
    }

    public function test_update_with_allocation_quantity_zero_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'allocations' => [['room_id' => null, 'quantity' => 0]],
        ]);

        $response->assertInvalid(['allocations.0.quantity']);
    }

    public function test_update_with_allocation_quantity_zero_shows_friendly_message_without_field_path_or_raw_decimal(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'allocations' => [['room_id' => null, 'quantity' => 0]],
        ]);

        $response->assertInvalid(['allocations.0.quantity' => 'Množstvo musí byť aspoň 0,01.']);
    }

    public function test_update_with_allocation_quantity_over_two_decimals_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'allocations' => [['room_id' => null, 'quantity' => 0.001]],
        ]);

        $response->assertInvalid(['allocations.0.quantity']);
    }

    public function test_update_with_allocation_quantity_over_max_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'allocations' => [['room_id' => null, 'quantity' => 10000]],
        ]);

        $response->assertInvalid(['allocations.0.quantity']);
    }

    public function test_update_with_duplicate_room_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $room = Room::factory()->create();
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'allocations' => [
                ['room_id' => $room->id, 'quantity' => 1],
                ['room_id' => $room->id, 'quantity' => 2],
            ],
        ]);

        $response->assertInvalid(['allocations']);
    }

    public function test_update_with_two_whole_house_allocations_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'allocations' => [
                ['room_id' => null, 'quantity' => 1],
                ['room_id' => null, 'quantity' => 2],
            ],
        ]);

        $response->assertInvalid(['allocations']);
    }

    public function test_update_with_allocations_total_over_limit_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'allocations' => [
                ['room_id' => null, 'quantity' => 9999.99],
                ['room_id' => null, 'quantity' => 9999.98],
            ],
        ]);

        $response->assertInvalid(['allocations']);
    }

    public function test_update_with_unknown_room_uuid_returns_422(): void
    {
        $user = $this->userWithPermission('edit items');
        $item = Item::factory()->create();

        $response = $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'allocations' => [['room_id' => '00000000-0000-0000-0000-000000000000', 'quantity' => 1]],
        ]);

        $response->assertInvalid(['allocations.0.room_id']);
    }

    public function test_store_with_allocations_and_legacy_room_id_shows_friendly_prohibits_message(): void
    {
        $user = $this->userWithPermission('create items');
        $room = Room::factory()->create();

        $response = $this->actingAs($user)->post('/items', [
            'name' => 'Dlažba',
            'room_id' => $room->id,
            'quantity' => 1,
            'allocations' => [['room_id' => $room->id, 'quantity' => 1]],
        ]);

        $response->assertInvalid([
            'allocations' => 'Pole allocations nemôžete kombinovať s room_id / quantity — použite len jeden z nich.',
        ]);
    }

    // ── audit ────────────────────────────────────────────────────────────────

    public function test_allocation_changes_are_activity_logged(): void
    {
        $user = $this->userWithPermission('create items', 'edit items');
        $room = Room::factory()->create();

        $this->actingAs($user)->post('/items', [
            'name' => 'Zrkadlo',
            'allocations' => [['room_id' => $room->id, 'quantity' => 1]],
        ]);
        $item = Item::query()->where('name', 'Zrkadlo')->firstOrFail();
        $morphClass = (new ItemAllocation)->getMorphClass();

        $this->assertDatabaseHas('activity_log', ['subject_type' => $morphClass, 'event' => 'created']);

        $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'allocations' => [['room_id' => $room->id, 'quantity' => 9]],
        ]);

        $this->assertDatabaseHas('activity_log', ['subject_type' => $morphClass, 'event' => 'updated']);

        $this->actingAs($user)->put("/items/{$item->id}", [
            'name' => $item->name,
            'allocations' => [['room_id' => null, 'quantity' => 9]],
        ]);

        $this->assertDatabaseHas('activity_log', ['subject_type' => $morphClass, 'event' => 'deleted']);
    }
}
