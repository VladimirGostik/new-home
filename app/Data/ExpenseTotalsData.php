<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseTotalsData extends Data
{
    public function __construct(
        public readonly float $total = 0.0,
        public readonly float $items_total = 0.0,
        public readonly float $manual_total = 0.0,
        public readonly int $count = 0,
    ) {}
}
