<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Item;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ItemListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $note,
        public readonly ?string $room_id,
        public readonly ?string $room_name,
        public readonly ?float $unit_price,
        public readonly int $quantity,
        public readonly ?float $total_price,
        public readonly ?string $url,
        public readonly ?string $assigned_user_id,
        public readonly ?string $assigned_user_name,
        public readonly string $status,
        public readonly string $priority,
        public readonly ?string $photo_uuid,
        public readonly ?string $photo_url,
        public readonly ?string $photo_thumb_url,
        public readonly string $created_at,
    ) {}

    public static function fromModel(Item $item): self
    {
        $item->loadMissing(['room', 'assignedUser', 'media']);

        $unitPrice = $item->unit_price !== null ? (float) $item->unit_price : null;

        return new self(
            id: $item->id,
            name: $item->name,
            note: $item->note,
            room_id: $item->room_id,
            room_name: $item->room?->name,
            unit_price: $unitPrice,
            quantity: $item->quantity,
            total_price: $unitPrice !== null ? round($unitPrice * $item->quantity, 2) : null,
            url: $item->url,
            assigned_user_id: $item->assigned_user_id,
            assigned_user_name: $item->assignedUser?->name,
            status: $item->status->value,
            priority: $item->priority->value,
            photo_uuid: $item->getFirstMedia('photo')?->uuid,
            photo_url: $item->getFirstMediaUrl('photo') ?: null,
            photo_thumb_url: $item->getFirstMediaUrl('photo', 'thumb') ?: null,
            created_at: $item->created_at?->toIso8601String() ?? '',
        );
    }
}
