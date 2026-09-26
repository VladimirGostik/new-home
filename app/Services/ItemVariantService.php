<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CreateItemVariantData;
use App\Data\UpdateItemVariantData;
use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Support\Facades\DB;

final readonly class ItemVariantService
{
    public function __construct(
        private TemporaryUploadService $uploads,
    ) {}

    public function create(Item $item, CreateItemVariantData $data): ItemVariant
    {
        return DB::transaction(function () use ($item, $data): ItemVariant {
            /** @var ItemVariant $variant */
            $variant = $item->variants()->create([
                'name' => $data->name,
                'unit_price' => $data->unit_price,
                'url' => $data->url,
            ]);

            if ($data->photo_uuid !== null) {
                $this->uploads->moveToModel($variant, 'photo', $data->photo_uuid);
            }

            /** @var ItemVariant $variant */
            $variant = $variant->fresh(['media']);

            return $variant;
        });
    }

    public function update(ItemVariant $variant, UpdateItemVariantData $data): ItemVariant
    {
        return DB::transaction(function () use ($variant, $data): ItemVariant {
            $variant->update([
                'name' => $data->name,
                'unit_price' => $data->unit_price,
                'url' => $data->url,
            ]);

            if ($data->photo_uuid !== null) {
                $variant->clearMediaCollection('photo');
                $this->uploads->moveToModel($variant, 'photo', $data->photo_uuid);
            } elseif ($data->remove_photo) {
                $variant->clearMediaCollection('photo');
            }

            /** @var ItemVariant $variant */
            $variant = $variant->fresh(['media', 'item']);

            /** @var Item $item */
            $item = $variant->item;

            if ($item->selected_variant_id === $variant->id) {
                $this->mirrorOntoItem($item, $variant);
            }

            return $variant;
        });
    }

    public function delete(ItemVariant $variant): void
    {
        DB::transaction(function () use ($variant): void {
            $variant->delete();
        });
    }

    public function select(Item $item, ItemVariant $variant): Item
    {
        return DB::transaction(function () use ($item, $variant): Item {
            $item->update(['selected_variant_id' => $variant->id]);
            $this->mirrorOntoItem($item, $variant);

            /** @var Item $item */
            $item = $item->fresh(['media']);

            return $item;
        });
    }

    public function unselect(Item $item): Item
    {
        return DB::transaction(function () use ($item): Item {
            if ($item->selected_variant_id === null) {
                return $item;
            }

            $item->update(['selected_variant_id' => null, 'unit_price' => null, 'url' => null]);
            $item->clearMediaCollection('photo');

            /** @var Item $item */
            $item = $item->fresh(['media']);

            return $item;
        });
    }

    private function mirrorOntoItem(Item $item, ItemVariant $variant): void
    {
        $item->update(['unit_price' => $variant->unit_price, 'url' => $variant->url]);

        $photo = $variant->getFirstMedia('photo');

        if ($photo !== null) {
            $photo->copy($item, 'photo');
        } else {
            $item->clearMediaCollection('photo');
        }
    }
}
