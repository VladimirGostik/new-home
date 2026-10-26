<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum ItemPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    public static function fromAmounts(?float $total, float $paid): self
    {
        if (round($paid, 2) <= 0.0) {
            return self::Unpaid;
        }

        if ($total === null) {
            return self::Partial;
        }

        return round($paid, 2) >= round($total, 2) ? self::Paid : self::Partial;
    }

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Nezaplatené',
            self::Partial => 'Čiastočne zaplatené',
            self::Paid => 'Zaplatené',
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
