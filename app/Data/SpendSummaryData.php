<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Aggregated spend for a set of items. Items without a price are counted
 * in `items_count` / `unpriced_count` but never in the money totals.
 */
#[TypeScript]
final class SpendSummaryData extends Data
{
    public function __construct(
        public readonly int $items_count = 0,
        public readonly int $bought_count = 0,
        public readonly int $unpriced_count = 0,
        public readonly float $total = 0.0,
        public readonly float $bought_total = 0.0,
        public readonly float $remaining_total = 0.0,
    ) {}
}
