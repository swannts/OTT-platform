<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_license_windows', function (Blueprint $table): void {
            $table->id();
            $table->string('content_type', 32);
            $table->string('content_id', 64);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['content_type', 'content_id']);
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_license_windows');
    }
};
