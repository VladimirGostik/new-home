<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseResultData extends Data
{
    public function __construct(
        public readonly ExpenseData $expense,
        public readonly ?ItemPaymentSummaryData $item_summary,
        public readonly ?string $warning,
    ) {}
}
