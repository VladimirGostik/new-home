<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemVariantVote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class ItemVariantVoteService
{
    public function cast(ItemVariant $variant, User $user): ItemVariantVote
    {
        return DB::transaction(fn (): ItemVariantVote => ItemVariantVote::updateOrCreate(
            ['item_id' => $variant->item_id, 'user_id' => $user->id],
            ['item_variant_id' => $variant->id],
        ));
    }

    public function retract(Item $item, User $user): void
    {
        $item->variantVotes()->where('user_id', $user->id)->delete();
    }
}
