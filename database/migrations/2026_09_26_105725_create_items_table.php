<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->text('note')->nullable();
            $table->foreignUuid('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('url', 2048)->nullable();
            $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('planned');
            $table->string('priority', 16)->default('medium');
            $table->timestamps();

            $table->index('room_id');
            $table->index(['assigned_user_id', 'status']);
            $table->index('status');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
