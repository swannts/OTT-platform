<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->boolean('consented')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->string('source', 32)->default('account');
            $table->timestamps();
            $table->unique(['user_id', 'channel']);
            $table->index(['channel', 'consented']);
        });

        Schema::create('marketing_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 150)->nullable()->unique();
            $table->string('kind', 24);
            $table->string('channel', 16)->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->string('audience_segment', 32)->default('all_consented');
            $table->string('subject', 191)->nullable();
            $table->text('body');
            $table->string('call_to_action', 80)->nullable();
            $table->string('destination_url', 2048)->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable()->index();
            $table->json('featured_content')->nullable();
            $table->json('landing_page')->nullable();
            $table->json('variant_b')->nullable();
            $table->unsignedTinyInteger('variant_b_percent')->default(0);
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('marketing_campaign_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->char('variant', 1)->default('A');
            $table->string('status', 16)->default('queued')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->char('tracking_token_hash', 64)->nullable()->unique();
            $table->char('unsubscribe_token_hash', 64)->nullable()->unique();
            $table->string('failure_code', 100)->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'user_id', 'channel']);
            $table->index(['campaign_id', 'status']);
        });

        Schema::table('banners', function (Blueprint $table): void {
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->string('click_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->dropColumn(['starts_at', 'ends_at', 'click_url']);
        });
        Schema::dropIfExists('marketing_campaign_deliveries');
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('marketing_consents');
    }
};
