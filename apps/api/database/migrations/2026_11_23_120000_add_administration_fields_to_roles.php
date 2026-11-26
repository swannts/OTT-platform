<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_admin_role')->default(false)->index();
        });

        DB::table('roles')->whereIn('name', [
            'super-admin', 'platform_admin', 'content_manager', 'content_editor', 'content_reviewer',
            'media_manager', 'subscription_manager', 'finance_manager', 'marketing_manager',
            'customer_support', 'analytics_manager', 'technical_operations_admin',
            'admin', 'demo_admin', 'channel_manager', 'video_editor',
        ])->update(['is_admin_role' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['is_admin_role']);
            $table->dropColumn(['description', 'is_active', 'is_admin_role']);
        });
    }
};
