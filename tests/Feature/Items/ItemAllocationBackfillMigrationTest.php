<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\ItemAllocation;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ItemAllocationBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_creates_one_allocation_per_item_matching_room_and_quantity(): void
    {
        $room = Room::factory()->create();
        $withRoom = Item::factory()->create(['room_id' => $room->id, 'quantity' => 3]);
        $withoutRoom = Item::factory()->create(['room_id' => null, 'quantity' => 5]);

        ItemAllocation::query()->delete();
        $this->assertSame(0, ItemAllocation::count());

        $migration = require database_path('migrations/2026_09_27_130100_backfill_item_allocations.php');
        // @phpstan-ignore-next-line (anonymous migration class — require()'s return type is unknown to PHPStan)
        $migration->up();

        $this->assertSame(2, ItemAllocation::count());
        $this->assertDatabaseHas('item_allocations', ['item_id' => $withRoom->id, 'room_id' => $room->id, 'quantity' => 3]);
        $this->assertDatabaseHas('item_allocations', ['item_id' => $withoutRoom->id, 'room_id' => null, 'quantity' => 5]);
    }

    public function test_backfill_run_twice_does_not_duplicate_rows(): void
    {
        Item::factory()->count(3)->create();

        ItemAllocation::query()->delete();

        $migration = require database_path('migrations/2026_09_27_130100_backfill_item_allocations.php');
        // @phpstan-ignore-next-line (anonymous migration class — require()'s return type is unknown to PHPStan)
        $migration->up();
        $countAfterFirstRun = ItemAllocation::count();

        // @phpstan-ignore-next-line (anonymous migration class — require()'s return type is unknown to PHPStan)
        $migration->up();

        $this->assertSame(3, $countAfterFirstRun);
        $this->assertSame($countAfterFirstRun, ItemAllocation::count());
    }
}
