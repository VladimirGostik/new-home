<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ItemPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Nízka',
            self::Medium => 'Stredná',
            self::High => 'Vysoká',
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
