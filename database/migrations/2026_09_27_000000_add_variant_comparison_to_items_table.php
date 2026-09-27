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
            $table->text('variant_comparison')->nullable()->after('selected_variant_id');
            $table->timestamp('variant_comparison_generated_at')->nullable()->after('variant_comparison');
            $table->boolean('variant_comparison_is_stale')->default(false)->after('variant_comparison_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropColumn(['variant_comparison', 'variant_comparison_generated_at', 'variant_comparison_is_stale']);
        });
    }
};
