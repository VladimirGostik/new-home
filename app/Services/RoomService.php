<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CreateRoomData;
use App\Data\UpdateRoomData;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

final readonly class RoomService
{
    public function create(CreateRoomData $data): Room
    {
        return DB::transaction(function () use ($data): Room {
            /** @var Room $room */
            $room = Room::create([
                'name' => $data->name,
                'description' => $data->description,
                'sort_order' => $data->sort_order,
            ]);

            return $room->fresh();
        });
    }

    public function update(Room $room, UpdateRoomData $data): Room
    {
        return DB::transaction(function () use ($room, $data): Room {
            $room->update([
                'name' => $data->name,
                'description' => $data->description,
                'sort_order' => $data->sort_order,
            ]);

            return $room->fresh();
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
     * Items in the room are kept and fall back to "Celý dom" (items.room_id is nullOnDelete).
     */
    public function delete(Room $room): void
    {
        DB::transaction(function () use ($room): void {
            $room->delete();
        });
    }
}
