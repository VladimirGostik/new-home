<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemAllocation>
 */
final class ItemAllocationFactory extends Factory
{
    protected $model = ItemAllocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'room_id' => null,
            'quantity' => fake()->numberBetween(1, 5),
        ];
    }
}
