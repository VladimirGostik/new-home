<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

final class RoomSeeder extends Seeder
{
    /** @var array<int, array{name: string, description: string}> */
    public const array ROOMS = [
        ['name' => 'Obývačka', 'description' => 'Sedačka, stolík, svetlá'],
        ['name' => 'Kuchyňa', 'description' => 'Spotrebiče a doplnky'],
        ['name' => 'Spálňa', 'description' => 'Posteľ, šatník'],
        ['name' => 'Kúpeľňa', 'description' => 'Batérie, zrkadlo, doplnky'],
        ['name' => 'Detská izba', 'description' => 'Stôl, poličky'],
    ];

    public function run(): void
    {
        foreach (self::ROOMS as $position => $room) {
            Room::firstOrCreate(
                ['name' => $room['name']],
                ['description' => $room['description'], 'sort_order' => $position],
            );
        }
    }
}
