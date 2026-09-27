<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CreateItemData;
use App\Data\ItemAllocationInputData;
use App\Data\PatchItemData;
use App\Data\SaveItemVariantComparisonData;
use App\Data\UpdateItemData;
use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\ItemAllocation;
use App\Models\ItemVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

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
                    'unit_price' => $data->unit_price,
                    'url' => $data->url,
                    'assigned_user_id' => $data->assigned_user_id,
                    'status' => $data->status,
                    'priority' => $data->priority,
                ]);

                $item = $this->syncAllocations($item, $data->resolvedAllocations());

                if ($data->photo_uuid !== null) {
                    $this->uploads->moveToModel($item, 'photo', $data->photo_uuid);
                }

                $image?->attachTo($item, 'photo');

                /** @var Item $item */
                $item = $item->fresh(['media', 'allocations.room']);

                return $item;
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
                'assigned_user_id' => $data->assigned_user_id,
                'status' => $data->status,
                'priority' => $data->priority,
            ];

            if (! $hasSelectedVariant) {
                $attributes['unit_price'] = $data->unit_price;
                $attributes['url'] = $data->url;
            }

            $item->update($attributes);

            $item = $this->syncAllocations($item, $data->allocations);

            if (! $hasSelectedVariant) {
                if ($data->photo_uuid !== null) {
                    $item->clearMediaCollection('photo');
                    $this->uploads->moveToModel($item, 'photo', $data->photo_uuid);
                } elseif ($data->remove_photo) {
                    $item->clearMediaCollection('photo');
                }
            }

            /** @var Item $item */
            $item = $item->fresh(['media', 'allocations.room']);

            return $item;
        });
    }

    /**
     * Full replace: rows matching the given room keys are updated in place (keeping
     * their id / audit trail), the rest are created or deleted. Mirrors the legacy
     * `room_id` / `quantity` columns onto the item afterwards.
     *
     * @param  list<ItemAllocationInputData>  $allocations
     */
    public function syncAllocations(Item $item, array $allocations): Item
    {
        return DB::transaction(function () use ($item, $allocations): Item {
            /** @var Item $item */
            $item = Item::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            /** @var Collection<string, ItemAllocation> $existing */
            $existing = $item->allocations()->get()->keyBy(fn (ItemAllocation $allocation): string => $allocation->room_id ?? '');

            $keptKeys = [];

            foreach ($allocations as $allocation) {
                $key = $allocation->room_id ?? '';
                $keptKeys[] = $key;

                $row = $existing->get($key);

                if ($row !== null) {
                    $row->update(['quantity' => $allocation->quantity]);

                    continue;
                }

                $item->allocations()->create([
                    'room_id' => $allocation->room_id,
                    'quantity' => $allocation->quantity,
                ]);
            }

            // Eloquent Collection::except() matches primary keys, not these room-keyed
            // array keys — reject() preserves the keyBy() keys so this actually works.
            $existing
                ->reject(fn (ItemAllocation $allocation, string $key): bool => in_array($key, $keptKeys, true))
                ->each(fn (ItemAllocation $allocation) => $allocation->delete());

            $item->update([
                'room_id' => count($allocations) === 1 ? $allocations[0]->room_id : null,
                'quantity' => array_sum(array_map(fn (ItemAllocationInputData $allocation): float => $allocation->quantity, $allocations)),
            ]);

            /** @var Item $item */
            $item = $item->fresh(['media', 'allocations.room']);

            return $item;
        });
    }

    public function patch(Item $item, PatchItemData $data): Item
    {
        if ($item->selected_variant_id !== null) {
            $errors = [];

            if (! $data->unit_price instanceof Optional) {
                $errors['unit_price'] = [__('app.item_price_from_selected_variant')];
            }

            if (! $data->url instanceof Optional) {
                $errors['url'] = [__('app.item_price_from_selected_variant')];
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        return DB::transaction(function () use ($item, $data): Item {
            /** @var array<string, mixed> $attributes */
            $attributes = $data->toArray();
            $item->update($attributes);

            /** @var Item $item */
            $item = $item->fresh(['media', 'allocations.room']);

            return $item;
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

                /** @var Item $item */
                $item = $item->fresh(['media']);

                return $item;
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

            /** @var Item $item */
            $item = $item->fresh();

            return $item;
        });
    }

    public function delete(Item $item): void
    {
        DB::transaction(function () use ($item): void {
            // Deleted explicitly (not left to the FK cascade) so LogsActivity records each removal.
            $item->allocations()->get()->each(fn (ItemAllocation $allocation) => $allocation->delete());
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
