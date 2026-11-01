<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('blogs')) {
            Schema::create('blogs', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('summary')->nullable();
                $table->longText('content');
                $table->string('image_url')->nullable();
                $table->string('category')->default('General');
                $table->json('tags')->nullable();
                $table->string('author_name')->default('NOOR Editorial');
                $table->string('author_avatar')->nullable();
                $table->integer('reading_time')->default(5);
                $table->unsignedBigInteger('views_count')->default(0);
                $table->boolean('is_featured')->default(false);
                $table->tinyInteger('status')->default(1);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'published_at']);
                $table->index('category');
                $table->index('is_featured');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
