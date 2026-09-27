<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Room;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RoomOptionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}

    public static function fromModel(Room $room): self
    {
        return new self(
            id: $room->id,
            name: $room->name,
        );
    }
}
