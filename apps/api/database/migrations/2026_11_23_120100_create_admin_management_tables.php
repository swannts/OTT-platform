<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('action', 125)->index();
            $table->string('resource_type', 125)->nullable()->index();
            $table->string('resource_id', 64)->nullable()->index();
            $table->string('request_id', 64)->nullable()->index();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('outcome', 20)->default('success')->index();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('admin_invitations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('email', 191)->index();
            $table->char('token_hash', 64)->unique();
            $table->json('role_names');
            $table->unsignedBigInteger('invited_by')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_invitations');
        Schema::dropIfExists('admin_audit_logs');
    }
};
