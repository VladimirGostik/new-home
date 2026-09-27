<?php

declare(strict_types=1);

use App\Actions\GenerateUuid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('items')
            ->select(['id', 'room_id', 'quantity', 'created_at', 'updated_at'])
            ->whereNotExists(function ($query): void {
                $query->select('id')
                    ->from('item_allocations')
                    ->whereColumn('item_allocations.item_id', 'items.id');
            })
            ->orderBy('id')
            ->chunkById(500, function ($items): void {
                $rows = $items->map(fn (object $item): array => [
                    'id' => (new GenerateUuid)->handle()->toString(),
                    'item_id' => $item->id,
                    'room_id' => $item->room_id,
                    'quantity' => max(1, (int) $item->quantity),
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ])->all();

                DB::table('item_allocations')->insert($rows);
            });
    }

    public function down(): void
    {
        // Intentionally a no-op — backfilled rows are indistinguishable from
        // service-created ones, and dropping the table (migration 130000's down) covers rollback.
    }
};
