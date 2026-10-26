<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ExpenseType: string
{
    case Item = 'item';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Item => 'Platby za položky',
            self::Other => 'Vlastné výdavky',
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
