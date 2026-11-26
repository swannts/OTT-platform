<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staging_dataset_records', function (Blueprint $table): void {
            $table->string('record_id', 36)->nullable()->after('entity_key');
            $table->index(['entity_type', 'record_id'], 'staging_dataset_record_id_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('staging_dataset_records', function (Blueprint $table): void {
            $table->dropIndex('staging_dataset_record_id_lookup_idx');
            $table->dropColumn('record_id');
        });
    }
};
