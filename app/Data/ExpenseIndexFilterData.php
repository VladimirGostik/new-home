<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ExpenseType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseIndexFilterData extends Data
{
    public function __construct(
        public readonly string $month,
        public readonly ?string $search = null,
        public readonly ?ExpenseType $type = null,
    ) {}
}
