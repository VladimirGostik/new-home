<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CreateItemVariantData;
use App\Data\PatchItemVariantData;
use App\Data\UpdateItemVariantData;
use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

final readonly class ItemVariantService
{
    public function __construct(
        private TemporaryUploadService $uploads,
        private RemoteImageDownloader $remoteImages,
    ) {}

    public function create(Item $item, CreateItemVariantData $data): ItemVariant
    {
        $image = $data->photo_url !== null ? $this->remoteImages->download($data->photo_url) : null;

        try {
            return DB::transaction(function () use ($item, $data, $image): ItemVariant {
                /** @var ItemVariant $variant */
                $variant = $item->variants()->create([
                    'name' => $data->name,
                    'unit_price' => $data->unit_price,
                    'url' => $data->url,
                ]);

                if ($data->photo_uuid !== null) {
                    $this->uploads->moveToModel($variant, 'photo', $data->photo_uuid);
                }

                $image?->attachTo($variant, 'photo');

                $this->markComparisonStale($item);

                /** @var ItemVariant $variant */
                $variant = $variant->fresh(['media']);

                return $variant;
            });
        } finally {
            $image?->discard();
        }
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

            $this->markComparisonStale($item);

            return $variant;
        });
    }

    /**
     * Partial update — only the sent keys change; the rest keep their current value
     * and the photo is left untouched, then reuses update() for the mirror + stale logic.
     */
    public function patch(ItemVariant $variant, PatchItemVariantData $data): ItemVariant
    {
        $merged = new UpdateItemVariantData(
            name: $data->name instanceof Optional ? $variant->name : $data->name,
            unit_price: $data->unit_price instanceof Optional ? ($variant->unit_price !== null ? (float) $variant->unit_price : null) : $data->unit_price,
            url: $data->url instanceof Optional ? $variant->url : $data->url,
        );

        return $this->update($variant, $merged);
    }

    public function replacePhotoFromUrl(ItemVariant $variant, string $url): ItemVariant
    {
        $image = $this->remoteImages->download($url);

        try {
            return DB::transaction(function () use ($variant, $image): ItemVariant {
                $image->attachTo($variant, 'photo');

                /** @var ItemVariant $variant */
                $variant = $variant->fresh(['media', 'item']);

                /** @var Item $item */
                $item = $variant->item;

                if ($item->selected_variant_id === $variant->id) {
                    $this->mirrorOntoItem($item, $variant);
                }

                $this->markComparisonStale($item);

                return $variant;
            });
        } finally {
            $image->discard();
        }
    }

    public function delete(ItemVariant $variant): void
    {
        DB::transaction(function () use ($variant): void {
            /** @var Item $item */
            $item = $variant->item;

            $variant->delete();

            $this->markComparisonStale($item);
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

    private function markComparisonStale(Item $item): void
    {
        if ($item->variant_comparison !== null && ! $item->variant_comparison_is_stale) {
            $item->update(['variant_comparison_is_stale' => true]);
        }
    }
}
