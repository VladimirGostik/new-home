<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Item;
use App\Models\ItemAllocation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ItemListItemData extends Data
{
    /** @param list<ItemAllocationData> $allocations */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $note,
        public readonly ?string $room_id,
        public readonly ?string $room_name,
        public readonly ?float $unit_price,
        public readonly float $quantity,
        public readonly ?float $total_price,
        public readonly array $allocations,
        public readonly ?string $url,
        public readonly ?string $assigned_user_id,
        public readonly ?string $assigned_user_name,
        public readonly string $status,
        public readonly string $priority,
        public readonly ?string $photo_uuid,
        public readonly ?string $photo_url,
        public readonly ?string $photo_thumb_url,
        public readonly string $created_at,
        public readonly ?string $selected_variant_id,
        public readonly ?string $selected_variant_name,
        public readonly int $variants_count,
    ) {}

    public static function fromModel(Item $item): self
    {
        $item->loadMissing(['allocations.room:id,name,sort_order', 'assignedUser', 'media', 'selectedVariant:id,name']);

        if (! array_key_exists('variants_count', $item->getAttributes())) {
            $item->loadCount('variants');
        }

        $unitPrice = $item->unit_price !== null ? (float) $item->unit_price : null;

        $allocations = $item->allocations
            ->sortBy(fn (ItemAllocation $allocation): array => [
                $allocation->room !== null ? 0 : 1,
                // @phpstan-ignore nullsafe.neverNull (room_id is nullable — Celý dom rows have no room)
                $allocation->room?->sort_order ?? 0,
                // @phpstan-ignore nullsafe.neverNull (room_id is nullable — Celý dom rows have no room)
                $allocation->room?->name ?? '',
            ])
            ->values();

        /** @var list<ItemAllocationData> $allocationData */
        $allocationData = $allocations
            ->map(fn (ItemAllocation $allocation) => ItemAllocationData::fromModel($allocation, $unitPrice))
            ->values()
            ->all();

        $quantity = round((float) $allocations->sum(fn (ItemAllocation $allocation): float => (float) $allocation->quantity), 2);

        $totalPrice = $unitPrice !== null
            ? round(array_sum(array_map(fn (ItemAllocationData $allocation): float => $allocation->line_total ?? 0.0, $allocationData)), 2)
            : null;

        $firstAllocation = $allocations->first();

        $roomId = $allocations->count() === 1 ? $firstAllocation?->room_id : null;

        $roomName = $allocations->count() === 1
            ? $firstAllocation?->room?->name
            // @phpstan-ignore nullsafe.neverNull (room_id is nullable — Celý dom rows have no room)
            : $allocations->map(fn (ItemAllocation $allocation): string => $allocation->room?->name ?? __('app.whole_house'))->implode(', ');

        return new self(
            id: $item->id,
            name: $item->name,
            note: $item->note,
            room_id: $roomId,
            room_name: $roomName,
            unit_price: $unitPrice,
            quantity: $quantity,
            total_price: $totalPrice,
            allocations: $allocationData,
            url: $item->url,
            assigned_user_id: $item->assigned_user_id,
            assigned_user_name: $item->assignedUser?->name,
            status: $item->status->value,
            priority: $item->priority->value,
            photo_uuid: $item->getFirstMedia('photo')?->uuid,
            photo_url: $item->getFirstMediaUrl('photo') ?: null,
            photo_thumb_url: $item->getFirstMediaUrl('photo', 'thumb') ?: null,
            created_at: $item->created_at?->toIso8601String() ?? '',
            selected_variant_id: $item->selected_variant_id,
            selected_variant_name: $item->selectedVariant?->name,
            variants_count: (int) $item->variants_count,
        );
    }
}
