<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('banners', 'description_bn')) {
            Schema::table('banners', function (Blueprint $table): void {
                $table->text('description_bn')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('banners', 'description_bn')) {
            Schema::table('banners', function (Blueprint $table): void {
                $table->dropColumn('description_bn');
            });
        }
    }
};
