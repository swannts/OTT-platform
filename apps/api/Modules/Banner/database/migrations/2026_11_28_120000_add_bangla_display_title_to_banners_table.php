<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('banners', 'type_name_bn')) {
            Schema::table('banners', function (Blueprint $table): void {
                $table->string('type_name_bn')->nullable()->after('type_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('banners', 'type_name_bn')) {
            Schema::table('banners', function (Blueprint $table): void {
                $table->dropColumn('type_name_bn');
            });
        }
    }
};
