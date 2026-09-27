<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ItemAllocation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ItemAllocationData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $room_id,
        public readonly ?string $room_name,
        public readonly float $quantity,
        public readonly ?float $line_total,
    ) {}

    public static function fromModel(ItemAllocation $allocation, ?float $unitPrice): self
    {
        $quantity = (float) $allocation->quantity;

        return new self(
            id: $allocation->id,
            room_id: $allocation->room_id,
            room_name: $allocation->room?->name,
            quantity: $quantity,
            line_total: $unitPrice !== null ? round($unitPrice * $quantity, 2) : null,
        );
    }
}
