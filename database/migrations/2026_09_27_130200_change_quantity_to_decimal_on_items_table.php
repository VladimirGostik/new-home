<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->decimal('quantity', 10, 2)->default(1)->change();
        });
    }

    public function down(): void
    {
        // Lossy for fractional quantities written after this migration ran — accepted per plan.
        Schema::table('items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('quantity')->default(1)->change();
        });
    }
};
