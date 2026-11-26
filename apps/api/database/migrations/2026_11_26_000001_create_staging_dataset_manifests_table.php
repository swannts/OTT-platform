<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staging_dataset_manifests', function (Blueprint $table): void {
            $table->id();
            $table->string('dataset_id', 100)->unique();
            $table->string('profile', 16);
            $table->unsignedBigInteger('seed');
            $table->date('reference_date');
            $table->string('status', 24)->index();
            $table->json('configuration');
            $table->json('record_counts')->nullable();
            $table->text('failure_summary')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('staging_dataset_records', function (Blueprint $table): void {
            $table->id();
            $table->string('dataset_id', 100);
            $table->string('entity_type', 100);
            $table->string('entity_key', 191);
            $table->timestamps();
            $table->unique(['dataset_id', 'entity_type', 'entity_key'], 'staging_dataset_record_owner_unique');
            $table->index(['entity_type', 'entity_key'], 'staging_dataset_record_lookup_idx');
        });
    }

    public function down(): void
    {
        foreach (['staging_dataset_records', 'staging_dataset_manifests'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new \RuntimeException('Staging dataset metadata exists; retain these tables and roll back application code only.');
            }
        }
        Schema::dropIfExists('staging_dataset_records');
        Schema::dropIfExists('staging_dataset_manifests');
    }
};
