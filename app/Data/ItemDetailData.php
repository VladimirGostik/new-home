<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Item;
use App\Models\ItemVariant;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ItemDetailData extends Data
{
    /** @param list<ItemVariantListItemData> $variants */
    public function __construct(
        public readonly ItemListItemData $item,
        public readonly array $variants,
        public readonly ?ItemVariantComparisonData $comparison,
    ) {}

    public static function fromModel(Item $item, ?string $currentUserId = null): self
    {
        $item->loadMissing(['variants.media', 'variants.votes.user']);

        /** @var list<ItemVariantListItemData> $variants */
        $variants = $item->variants
            ->map(fn (ItemVariant $variant) => ItemVariantListItemData::fromModel($variant, $item->selected_variant_id, $currentUserId))
            ->values()
            ->all();

        return new self(
            item: ItemListItemData::fromModel($item),
            variants: $variants,
            comparison: ItemVariantComparisonData::fromItem($item),
        );
    }
}
