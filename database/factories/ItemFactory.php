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

    public function configure(): static
    {
        return $this->afterCreating(function (Item $item): void {
            if ($item->allocations()->doesntExist()) {
                $item->allocations()->create([
                    'room_id' => $item->room_id,
                    'quantity' => $item->quantity,
                ]);
            }
        });
    }

    /**
     * Replaces the default single allocation with the given rows, and keeps the
     * legacy room_id / quantity mirror in sync with the same rule as the service.
     *
     * @param  list<array{room_id: ?string, quantity: float}>  $allocations
     */
    public function allocatedTo(array $allocations): self
    {
        return $this->afterCreating(function (Item $item) use ($allocations): void {
            $item->allocations()->delete();

            foreach ($allocations as $allocation) {
                $item->allocations()->create($allocation);
            }

            $item->update([
                'room_id' => count($allocations) === 1 ? $allocations[0]['room_id'] : null,
                'quantity' => array_sum(array_column($allocations, 'quantity')),
            ]);
        });
    }

    public function withVariantComparison(bool $stale = false): self
    {
        return $this->state(fn (): array => [
            'variant_comparison' => fake()->paragraph(),
            'variant_comparison_generated_at' => now(),
            'variant_comparison_is_stale' => $stale,
        ]);
    }
}
