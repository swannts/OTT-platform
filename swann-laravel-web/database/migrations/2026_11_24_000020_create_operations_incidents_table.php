<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->text('summary');
            $table->string('severity', 16)->index();
            $table->string('status', 24)->default('open')->index();
            $table->json('affected_services')->nullable();
            $table->unsignedBigInteger('opened_by')->index();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->unsignedBigInteger('resolved_by')->nullable()->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity', 'created_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('operations_incidents') && DB::table('operations_incidents')->exists()) {
            throw new RuntimeException('Operational incident history exists; retain it and roll back application code only.');
        }
        Schema::dropIfExists('operations_incidents');
    }
};
