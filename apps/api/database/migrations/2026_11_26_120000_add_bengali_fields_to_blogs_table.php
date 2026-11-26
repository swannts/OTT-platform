<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blogs')) {
            return;
        }

        Schema::table('blogs', function (Blueprint $table): void {
            if (! Schema::hasColumn('blogs', 'title_bn')) {
                $table->string('title_bn')->nullable()->after('title');
            }
            if (! Schema::hasColumn('blogs', 'summary_bn')) {
                $table->text('summary_bn')->nullable()->after('summary');
            }
            if (! Schema::hasColumn('blogs', 'content_bn')) {
                $table->longText('content_bn')->nullable()->after('content');
            }
            if (! Schema::hasColumn('blogs', 'category_bn')) {
                $table->string('category_bn')->nullable()->after('category');
            }
            if (! Schema::hasColumn('blogs', 'tags_bn')) {
                $table->json('tags_bn')->nullable()->after('tags');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('blogs')) {
            return;
        }

        Schema::table('blogs', function (Blueprint $table): void {
            $columns = ['title_bn', 'summary_bn', 'content_bn', 'category_bn', 'tags_bn'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('blogs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
