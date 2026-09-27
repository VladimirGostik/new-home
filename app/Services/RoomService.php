<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CreateRoomData;
use App\Data\UpdateRoomData;
use App\Models\Item;
use App\Models\ItemAllocation;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

final readonly class RoomService
{
    public function create(CreateRoomData $data): Room
    {
        return DB::transaction(function () use ($data): Room {
            $maxSortOrder = Room::max('sort_order');
            $nextSortOrder = (is_numeric($maxSortOrder) ? (int) $maxSortOrder : 0) + 1;

            /** @var Room $room */
            $room = Room::create([
                'name' => $data->name,
                'description' => $data->description,
                // New rooms go to the end unless a position is given explicitly.
                'sort_order' => $data->sort_order ?? $nextSortOrder,
            ]);

            /** @var Room $room */
            $room = $room->fresh();

            return $room;
        });
    }

    public function update(Room $room, UpdateRoomData $data): Room
    {
        return DB::transaction(function () use ($room, $data): Room {
            $room->update([
                'name' => $data->name,
                'description' => $data->description,
                'sort_order' => $data->sort_order ?? $room->sort_order,
            ]);

            /** @var Room $room */
            $room = $room->fresh();

            return $room;
        });
    }

    /**
     * Persist display order: position in `$ids` becomes `sort_order`.
     *
     * @param  array<int, string>  $ids
     */
    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            foreach (array_values($ids) as $position => $id) {
                Room::whereKey($id)->update(['sort_order' => $position]);
            }
        });
    }

    /**
     * Allocations in the room merge into each item's existing "Celý dom" row (quantity
     * summed) or move there (room_id set to null); item totals never change. The
     * legacy items.room_id mirror is fixed up automatically by its FK's nullOnDelete.
     */
    public function delete(Room $room): void
    {
        DB::transaction(function () use ($room): void {
            $allocations = $room->allocations()->get();

            // Lock the affected items in a stable (sorted) order — same row ItemService::syncAllocations()
            // locks — so a concurrent allocations edit serializes instead of racing this merge.
            $itemIds = $allocations->pluck('item_id')->unique()->sort()->values();
            $items = Item::query()->whereIn('id', $itemIds)->orderBy('id')->lockForUpdate()->with('allocations')->get()->keyBy('id');

            foreach ($allocations as $allocation) {
                /** @var Item|null $item */
                $item = $items->get($allocation->item_id);

                if ($item === null) {
                    continue;
                }

                /** @var ItemAllocation|null $house */
                $house = $item->allocations->firstWhere('room_id', null);

                if ($house !== null) {
                    $house->update(['quantity' => $house->quantity + $allocation->quantity]);
                    $allocation->delete();

                    continue;
                }

                $allocation->update(['room_id' => null]);
            }

            $room->delete();
        });
    }
}
