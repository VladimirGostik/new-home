<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ItemPaymentsData extends Data
{
    /** @param list<ExpenseData> $payments */
    public function __construct(
        public readonly array $payments,
        public readonly ItemPaymentSummaryData $summary,
    ) {}
}
