<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ItemPriority;
use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

final class ItemSeeder extends Seeder
{
    public function run(): void
    {
        if (Item::query()->exists()) {
            return;
        }

        $rooms = Room::query()->pluck('id', 'name');
        $owner = User::query()->where('email', 'gostikvladko9@gmail.com')->value('id');

        // [name, room (null = Celý dom), unit price (null = not priced yet), qty, assigned to owner?, status, priority]
        $items = [
            ['Rohová sedačka', 'Obývačka', 1249.00, 1, true, ItemStatus::Planned, ItemPriority::High],
            ['Konferenčný stolík', 'Obývačka', 189.00, 1, false, ItemStatus::Planned, ItemPriority::Medium],
            ['TV stolík', 'Obývačka', 229.90, 1, true, ItemStatus::Bought, ItemPriority::Low],
            ['Chladnička s mrazničkou', 'Kuchyňa', 899.00, 1, true, ItemStatus::Bought, ItemPriority::High],
            ['Sada hrncov', 'Kuchyňa', 129.00, 1, false, ItemStatus::Planned, ItemPriority::Low],
            ['Barové stoličky', 'Kuchyňa', 89.50, 3, true, ItemStatus::Planned, ItemPriority::Medium],
            ['Posteľ 160 × 200', 'Spálňa', 649.00, 1, true, ItemStatus::Planned, ItemPriority::High],
            ['Šatníková skriňa', 'Spálňa', null, 1, false, ItemStatus::Planned, ItemPriority::Medium],
            ['Umývadlová batéria', 'Kúpeľňa', 79.90, 2, true, ItemStatus::Planned, ItemPriority::Medium],
            ['Zrkadlo s osvetlením', 'Kúpeľňa', 159.00, 1, false, ItemStatus::Planned, ItemPriority::Low],
            ['Písací stôl', 'Detská izba', null, 1, true, ItemStatus::Planned, ItemPriority::Low],
            ['Poličky na hračky', 'Detská izba', 34.90, 4, false, ItemStatus::Bought, ItemPriority::Medium],
            ['Stropné svietidlá', null, 45.00, 6, false, ItemStatus::Planned, ItemPriority::Medium],
            ['Rohožky ku dverám', null, null, 2, false, ItemStatus::Planned, ItemPriority::Low],
        ];

        foreach ($items as [$name, $room, $price, $quantity, $assigned, $status, $priority]) {
            $roomId = $room !== null ? $rooms->get($room) : null;

            $item = Item::create([
                'name' => $name,
                'room_id' => $roomId,
                'unit_price' => $price,
                'quantity' => $quantity,
                'assigned_user_id' => $assigned ? $owner : null,
                'status' => $status,
                'priority' => $priority,
            ]);

            $item->allocations()->create(['room_id' => $roomId, 'quantity' => $quantity]);
        }

        $kupelna = $rooms->get('Kúpeľňa');

        $dlazba = Item::create([
            'name' => 'Dlažba',
            'room_id' => null,
            'unit_price' => 24.90,
            'quantity' => 14.5,
            'assigned_user_id' => null,
            'status' => ItemStatus::Planned,
            'priority' => ItemPriority::Medium,
        ]);

        $dlazba->allocations()->create(['room_id' => $kupelna, 'quantity' => 12.5]);
        $dlazba->allocations()->create(['room_id' => null, 'quantity' => 2]);
    }
}
