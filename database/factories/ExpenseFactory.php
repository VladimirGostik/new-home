<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
final class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => null,
            'title' => ucfirst(fake()->word().' '.fake()->word()),
            'amount' => fake()->randomFloat(2, 1, 500),
            'paid_at' => fake()->dateTimeBetween('-1 month')->format('Y-m-d'),
            'note' => null,
            'created_by' => null,
        ];
    }

    public function forItem(Item $item): self
    {
        return $this->state(fn (): array => ['item_id' => $item->id, 'title' => null]);
    }
}
