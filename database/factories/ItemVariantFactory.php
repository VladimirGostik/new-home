<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemVariant>
 */
final class ItemVariantFactory extends Factory
{
    protected $model = ItemVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var array<int, string> $words */
        $words = fake()->unique()->words(2);

        return [
            'item_id' => Item::factory(),
            'name' => ucfirst(implode(' ', $words)),
            'unit_price' => fake()->optional()->randomFloat(2, 1, 200),
            'url' => fake()->optional()->url(),
        ];
    }
}
