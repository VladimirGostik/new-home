<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_variant_votes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('item_id');
            $table->uuid('item_variant_id');
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->foreign(['item_variant_id', 'item_id'])->references(['id', 'item_id'])->on('item_variants')->cascadeOnDelete();
            $table->unique(['item_id', 'user_id']);
            $table->index('item_variant_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_variant_votes');
    }
};
