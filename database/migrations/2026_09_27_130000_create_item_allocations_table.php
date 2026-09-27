<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignUuid('room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->timestamps();

            $table->unique(['item_id', 'room_id'], 'item_allocations_item_room_unique');
            $table->index('room_id');
        });

        // Postgres treats NULLs as distinct, so the composite unique index above allows
        // multiple "Celý dom" (room_id IS NULL) rows per item — this partial index closes that gap.
        DB::statement('CREATE UNIQUE INDEX item_allocations_item_house_unique ON item_allocations (item_id) WHERE room_id IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_allocations');
    }
};
