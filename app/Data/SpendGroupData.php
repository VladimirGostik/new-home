<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One dashboard row: a room (null id = "Celý dom") or a person (null id = unassigned).
 */
#[TypeScript]
final class SpendGroupData extends Data
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly SpendSummaryData $summary,
    ) {}
}
