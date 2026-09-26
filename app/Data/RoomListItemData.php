<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Room;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RoomListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $sort_order,
        public readonly string $created_at,
        public readonly SpendSummaryData $summary,
    ) {}

    public static function fromModel(Room $room, ?SpendSummaryData $summary = null): self
    {
        return new self(
            id: $room->id,
            name: $room->name,
            description: $room->description,
            sort_order: $room->sort_order,
            created_at: $room->created_at?->toIso8601String() ?? '',
            summary: $summary ?? new SpendSummaryData,
        );
    }
}
