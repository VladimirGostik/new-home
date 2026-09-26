<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemVariantVote;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class ItemVariantVoteService
{
    public function cast(ItemVariant $variant, User $user): ItemVariantVote
    {
        try {
            return DB::transaction(fn (): ItemVariantVote => ItemVariantVote::updateOrCreate(
                ['item_id' => $variant->item_id, 'user_id' => $user->id],
                ['item_variant_id' => $variant->id],
            ));
        } catch (UniqueConstraintViolationException) {
            // Lost the create race to a concurrent request; the prior DB::transaction already
            // rolled back, so this runs in a fresh transaction against the winner's row.
            return DB::transaction(function () use ($variant, $user): ItemVariantVote {
                ItemVariantVote::query()
                    ->where('item_id', $variant->item_id)
                    ->where('user_id', $user->id)
                    ->update(['item_variant_id' => $variant->id]);

                return ItemVariantVote::query()
                    ->where('item_id', $variant->item_id)
                    ->where('user_id', $user->id)
                    ->firstOrFail();
            });
        }
    }

    public function retract(Item $item, User $user): void
    {
        $item->variantVotes()->where('user_id', $user->id)->delete();
    }
}
