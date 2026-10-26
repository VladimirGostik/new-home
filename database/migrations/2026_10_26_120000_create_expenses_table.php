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
        Schema::create('expenses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('title')->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('paid_at');
            $table->string('note')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'paid_at']);
            $table->index('paid_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_item_or_title_check CHECK (item_id IS NOT NULL OR title IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
