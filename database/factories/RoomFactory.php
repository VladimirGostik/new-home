<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
final class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var array<int, string> $words */
        $words = fake()->unique()->words(2);

        return [
            'name' => ucfirst(implode(' ', $words)),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
