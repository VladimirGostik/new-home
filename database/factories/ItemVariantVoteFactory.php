<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ItemVariant;
use App\Models\ItemVariantVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemVariantVote>
 */
final class ItemVariantVoteFactory extends Factory
{
    protected $model = ItemVariantVote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_variant_id' => ItemVariant::factory(),
            'item_id' => function (array $attributes): ?string {
                /** @var string $variantId */
                $variantId = $attributes['item_variant_id'];

                return ItemVariant::query()->find($variantId)?->item_id;
            },
            'user_id' => User::factory(),
        ];
    }
}
