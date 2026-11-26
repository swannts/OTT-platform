<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_reconciliation_runs', function (Blueprint $table): void {
            $table->unsignedBigInteger('provider_refunded_minor')->nullable()->after('provider_currency');
            $table->unsignedBigInteger('local_refunded_minor')->nullable()->after('provider_refunded_minor');
            $table->boolean('refunds_match')->nullable()->after('status_matches');
        });
    }

    public function down(): void
    {
        Schema::table('payment_reconciliation_runs', function (Blueprint $table): void {
            $table->dropColumn(['provider_refunded_minor', 'local_refunded_minor', 'refunds_match']);
        });
    }
};
