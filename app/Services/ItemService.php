<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CreateItemData;
use App\Data\SaveItemVariantComparisonData;
use App\Data\UpdateItemData;
use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ItemService
{
    public function __construct(
        private TemporaryUploadService $uploads,
        private RemoteImageDownloader $remoteImages,
    ) {}

    public function create(CreateItemData $data): Item
    {
        $image = $data->photo_url !== null ? $this->remoteImages->download($data->photo_url) : null;

        try {
            return DB::transaction(function () use ($data, $image): Item {
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

                $image?->attachTo($item, 'photo');

                return $item->fresh(['media']);
            });
        } finally {
            $image?->discard();
        }
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

    public function replacePhotoFromUrl(Item $item, string $url): Item
    {
        if ($item->selected_variant_id !== null) {
            throw ValidationException::withMessages([
                'photo_url' => [__('app.photo_url_item_has_selected_variant')],
            ]);
        }

        $image = $this->remoteImages->download($url);

        try {
            return DB::transaction(function () use ($item, $image): Item {
                $image->attachTo($item, 'photo');

                return $item->fresh(['media']);
            });
        } finally {
            $image->discard();
        }
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

    public function saveVariantComparison(Item $item, SaveItemVariantComparisonData $data): Item
    {
        if ($item->variants()->count() < 2) {
            throw ValidationException::withMessages([
                'text' => [__('app.variant_comparison_requires_variants')],
            ]);
        }

        $item->update([
            'variant_comparison' => $data->text,
            'variant_comparison_generated_at' => now(),
            'variant_comparison_is_stale' => false,
        ]);

        $item->refresh();

        return $item;
    }
}
