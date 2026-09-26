<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CreateItemData;
use App\Data\UpdateItemData;
use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Support\Facades\DB;

final readonly class ItemService
{
    public function __construct(
        private TemporaryUploadService $uploads,
    ) {}

    public function create(CreateItemData $data): Item
    {
        return DB::transaction(function () use ($data): Item {
            /** @var Item $item */
            $item = Item::create([
                'name' => $data->name,
                'note' => $data->note,
                'room_id' => $data->room_id,
                'unit_price' => $data->unit_price,
                'quantity' => $data->quantity,
                'url' => $data->url,
                'assigned_user_id' => $data->assigned_user_id,
                'status' => $data->status,
                'priority' => $data->priority,
            ]);

            if ($data->photo_uuid !== null) {
                $this->uploads->moveToModel($item, 'photo', $data->photo_uuid);
            }

            return $item->fresh(['media']);
        });
    }

    public function update(Item $item, UpdateItemData $data): Item
    {
        return DB::transaction(function () use ($item, $data): Item {
            $hasSelectedVariant = $item->selected_variant_id !== null;

            $attributes = [
                'name' => $data->name,
                'note' => $data->note,
                'room_id' => $data->room_id,
                'quantity' => $data->quantity,
                'assigned_user_id' => $data->assigned_user_id,
                'status' => $data->status,
                'priority' => $data->priority,
            ];

            if (! $hasSelectedVariant) {
                $attributes['unit_price'] = $data->unit_price;
                $attributes['url'] = $data->url;
            }

            $item->update($attributes);

            if (! $hasSelectedVariant) {
                if ($data->photo_uuid !== null) {
                    $item->clearMediaCollection('photo');
                    $this->uploads->moveToModel($item, 'photo', $data->photo_uuid);
                } elseif ($data->remove_photo) {
                    $item->clearMediaCollection('photo');
                }
            }

            return $item->fresh(['media']);
        });
    }

    public function toggleStatus(Item $item): Item
    {
        return DB::transaction(function () use ($item): Item {
            $item->update([
                'status' => $item->status === ItemStatus::Planned ? ItemStatus::Bought : ItemStatus::Planned,
            ]);

            return $item->fresh();
        });
    }

    public function delete(Item $item): void
    {
        DB::transaction(function () use ($item): void {
            $item->variants()->get()->each(fn (ItemVariant $variant) => $variant->delete());
            $item->delete();
        });
    }
}
