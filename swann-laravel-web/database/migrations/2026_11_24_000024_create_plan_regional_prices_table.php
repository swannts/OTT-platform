<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_regional_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('plan')->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->char('currency_code', 3);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['plan_id', 'country_code']);
            $table->index(['country_code', 'currency_code']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('plan_regional_prices') && DB::table('plan_regional_prices')->exists()) {
            throw new RuntimeException('Refusing to drop regional plan pricing history. Preserve or archive it before rollback.');
        }

        Schema::dropIfExists('plan_regional_prices');
    }
};
