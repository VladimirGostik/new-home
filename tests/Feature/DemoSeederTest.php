<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Room;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoomSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_rooms_in_order_and_sample_items(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(
            array_column(RoomSeeder::ROOMS, 'name'),
            Room::orderBy('sort_order')->pluck('name')->all(),
        );
        $this->assertGreaterThan(0, Item::whereNull('unit_price')->count());
        $this->assertGreaterThan(0, Item::whereNull('assigned_user_id')->count());
        $this->assertGreaterThan(0, Item::whereNull('room_id')->count());
    }

    public function test_seeding_twice_does_not_duplicate(): void
    {
        $this->seed(DatabaseSeeder::class);
        $items = Item::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, Room::count());
        $this->assertSame($items, Item::count());
    }
}
