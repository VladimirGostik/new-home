<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ItemPriority;
use App\Enums\ItemStatus;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
final class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var array<int, string> $words */
        $words = fake()->unique()->words(2);

        return [
            'name' => ucfirst(implode(' ', $words)),
            'note' => fake()->optional()->sentence(),
            'room_id' => null,
            'unit_price' => fake()->optional()->randomFloat(2, 1, 200),
            'quantity' => fake()->numberBetween(1, 5),
            'url' => fake()->optional()->url(),
            'assigned_user_id' => null,
            'status' => ItemStatus::Planned,
            'priority' => ItemPriority::Medium,
        ];
    }
}
