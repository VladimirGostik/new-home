<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ItemVariant;
use App\Models\ItemVariantVote;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ItemVariantListItemData extends Data
{
    /** @param list<string> $voter_names */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?float $unit_price,
        public readonly ?string $url,
        public readonly ?string $photo_uuid,
        public readonly ?string $photo_url,
        public readonly ?string $photo_thumb_url,
        public readonly int $vote_count,
        public readonly array $voter_names,
        public readonly bool $is_my_vote,
        public readonly bool $is_selected,
    ) {}

    public static function fromModel(ItemVariant $variant, ?string $selectedVariantId, ?string $currentUserId): self
    {
        $variant->loadMissing(['media', 'votes.user']);

        $unitPrice = $variant->unit_price !== null ? (float) $variant->unit_price : null;

        /** @var list<string> $voterNames */
        $voterNames = $variant->votes
            ->map(fn (ItemVariantVote $vote): ?string => $vote->user?->name)
            ->filter()
            ->sort()
            ->values()
            ->all();

        return new self(
            id: $variant->id,
            name: $variant->name,
            unit_price: $unitPrice,
            url: $variant->url,
            photo_uuid: $variant->getFirstMedia('photo')?->uuid,
            photo_url: $variant->getFirstMediaUrl('photo') ?: null,
            photo_thumb_url: $variant->getFirstMediaUrl('photo', 'thumb') ?: null,
            vote_count: $variant->votes->count(),
            voter_names: $voterNames,
            is_my_vote: $currentUserId !== null && $variant->votes->contains(fn (ItemVariantVote $vote): bool => $vote->user_id === $currentUserId),
            is_selected: $selectedVariantId !== null && $selectedVariantId === $variant->id,
        );
    }
}
