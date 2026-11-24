<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_license_territories', function (Blueprint $table): void {
            $table->id();
            $table->string('content_type', 32);
            $table->string('content_id', 64);
            $table->char('country_code', 2);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['content_type', 'content_id', 'country_code'], 'content_license_territory_unique');
            $table->index(['content_type', 'content_id'], 'content_license_territory_content_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('content_license_territories') && DB::table('content_license_territories')->exists()) {
            throw new RuntimeException('Territorial license policies exist; retain them and roll back application code only.');
        }

        Schema::dropIfExists('content_license_territories');
    }
};
