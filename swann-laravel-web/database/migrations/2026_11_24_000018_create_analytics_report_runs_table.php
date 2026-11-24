<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_report_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('requested_by')->index();
            $table->string('metric_group', 24)->index();
            $table->date('date_from');
            $table->date('date_to');
            $table->timestamp('scheduled_at')->index();
            $table->string('status', 16)->default('queued')->index();
            $table->json('result')->nullable();
            $table->string('error_code', 48)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('analytics_report_runs') && DB::table('analytics_report_runs')->exists()) {
            throw new RuntimeException('Scheduled analytics reports exist; retain report history and roll back application code only.');
        }
        Schema::dropIfExists('analytics_report_runs');
    }
};
