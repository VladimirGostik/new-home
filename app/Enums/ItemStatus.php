<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ItemStatus: string
{
    case Planned = 'planned';
    case Bought = 'bought';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Plánované',
            self::Bought => 'Kúpené',
        };
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['id' => $case->value, 'name' => $case->label()],
            self::cases(),
        );
    }
}
