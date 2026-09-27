<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Item;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ItemVariantComparisonData extends Data
{
    public function __construct(
        public readonly string $text,
        public readonly string $generated_at,
        public readonly bool $is_stale,
    ) {}

    public static function fromItem(Item $item): ?self
    {
        if ($item->variant_comparison === null) {
            return null;
        }

        return new self(
            text: $item->variant_comparison,
            generated_at: $item->variant_comparison_generated_at?->toIso8601String() ?? '',
            is_stale: $item->variant_comparison_is_stale,
        );
    }
}
