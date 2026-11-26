<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash')->nullable()->after('pin');
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNotNull('pin_hash')->exists()) {
            throw new RuntimeException('Cannot remove pin_hash while hashed parental PINs exist; restore the compatible application or reset those PINs first.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pin_hash');
        });
    }
};
