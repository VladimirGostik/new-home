<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DashboardData extends Data
{
    /**
     * @param  array<int, SpendGroupData>  $rooms
     * @param  array<int, SpendGroupData>  $people
     */
    public function __construct(
        public readonly SpendSummaryData $totals,
        public readonly int $unassigned_count,
        public readonly array $rooms,
        public readonly array $people,
    ) {}
}
