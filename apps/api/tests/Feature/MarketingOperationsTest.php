<?php

namespace Tests\Feature;

use App\Jobs\DispatchMarketingCampaign;
use App\Jobs\SendMarketingCampaignDelivery;
use App\Mail\MarketingCampaignMail;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignDelivery;
use App\Models\MarketingConsent;
use App\Models\User;
use Database\Seeders\RbacFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Banner\Models\Banner;
use Tests\TestCase;

class MarketingOperationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacFoundationSeeder::class);
    }

    public function test_campaign_requires_an_independent_approval_before_scheduling_and_publishing(): void
    {
        Carbon::setTestNow(now());
        Queue::fake();
        $maker = User::factory()->create();
        $maker->assignRole('marketing_manager');
        $approver = User::factory()->create();
        $approver->assignRole('marketing_manager');

        $campaign = $this->actingAs($maker, 'sanctum')->postJson('/api/admin/marketing/campaigns', [
            'name' => 'Consent launch', 'kind' => 'notification', 'channel' => 'email',
            'audience_segment' => 'email_consented', 'subject' => 'New titles', 'body' => 'A new collection is available.',
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data');

        $this->postJson('/api/admin/marketing/campaigns/'.$campaign['id'].'/submit')->assertOk()->assertJsonPath('data.status', 'pending_approval');
        $this->postJson('/api/admin/marketing/campaigns/'.$campaign['id'].'/approve')->assertForbidden();
        $this->actingAs($approver, 'sanctum')->postJson('/api/admin/marketing/campaigns/'.$campaign['id'].'/approve')
            ->assertOk()->assertJsonPath('data.status', 'approved');

        $this->postJson('/api/admin/marketing/campaigns/'.$campaign['id'].'/schedule', [
            'scheduled_at' => now()->addMinute()->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.status', 'scheduled');

        Carbon::setTestNow(now()->addMinutes(2));
        Artisan::call('marketing:publish-scheduled');
        $this->assertDatabaseHas('marketing_campaigns', ['id' => $campaign['id'], 'status' => 'published']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'marketing.campaign.published', 'resource_id' => (string) $campaign['id']]);
        Queue::assertPushed(DispatchMarketingCampaign::class, fn ($job) => $job->campaignId === $campaign['id']);
        Carbon::setTestNow();
    }

    public function test_campaign_audience_counts_and_dispatch_require_current_channel_consent(): void
    {
        Queue::fake();
        $manager = User::factory()->create();
        $manager->assignRole('marketing_manager');
        $subscribed = User::factory()->create(['is_subscribe' => 1]);
        $unconsented = User::factory()->create(['is_subscribe' => 1]);
        MarketingConsent::query()->create(['user_id' => $subscribed->id, 'channel' => 'email', 'consented' => true, 'consented_at' => now(), 'source' => 'account']);
        MarketingConsent::query()->create(['user_id' => $unconsented->id, 'channel' => 'email', 'consented' => false, 'source' => 'account']);

        $campaign = MarketingCampaign::query()->create([
            'name' => 'Subscriber email', 'slug' => 'subscriber-email-'.Str::lower(Str::random(8)),
            'kind' => 'notification', 'channel' => 'email', 'audience_segment' => 'subscribers',
            'subject' => 'Hello', 'body' => 'Message', 'status' => 'published', 'created_by' => $manager->id,
            'published_at' => now(),
        ]);
        (new DispatchMarketingCampaign($campaign->id))->handle();

        $this->assertDatabaseHas('marketing_campaign_deliveries', ['campaign_id' => $campaign->id, 'user_id' => $subscribed->id, 'status' => 'queued']);
        $this->assertDatabaseMissing('marketing_campaign_deliveries', ['campaign_id' => $campaign->id, 'user_id' => $unconsented->id]);
    }

    public function test_public_unsubscribe_revokes_consent_and_public_campaign_response_has_no_audience_data(): void
    {
        $user = User::factory()->create();
        $manager = User::factory()->create();
        $manager->assignRole('marketing_manager');
        $rawToken = bin2hex(random_bytes(32));
        MarketingConsent::query()->create(['user_id' => $user->id, 'channel' => 'email', 'consented' => true, 'consented_at' => now(), 'source' => 'account']);
        $campaign = MarketingCampaign::query()->create([
            'name' => 'Landing', 'slug' => 'landing-'.Str::lower(Str::random(8)), 'kind' => 'landing_page',
            'body' => 'Public page', 'status' => 'published', 'created_by' => $manager->id, 'published_at' => now(),
        ]);
        $delivery = MarketingCampaignDelivery::query()->create([
            'campaign_id' => $campaign->id, 'user_id' => $user->id, 'channel' => 'email',
            'status' => 'sent', 'sent_at' => now(), 'unsubscribe_token_hash' => hash('sha256', $rawToken),
        ]);

        $this->get('/api/marketing/unsubscribe/'.$rawToken)->assertOk()->assertSee('Confirm');
        $this->assertDatabaseHas('marketing_consents', ['user_id' => $user->id, 'channel' => 'email', 'consented' => 1]);
        $this->post('/api/marketing/unsubscribe/'.$rawToken)->assertOk()->assertSee('unsubscribed');
        $this->assertDatabaseHas('marketing_consents', ['user_id' => $user->id, 'channel' => 'email', 'consented' => 0]);
        $response = $this->getJson('/api/v3/marketing/campaigns/'.$campaign->slug)->assertOk();
        $response->assertJsonMissingPath('data.email')->assertJsonMissingPath('data.user_id')->assertJsonPath('data.name', 'Landing');
        $this->assertDatabaseHas('marketing_campaign_deliveries', ['id' => $delivery->id, 'unsubscribe_token_hash' => null]);
    }

    public function test_delivery_job_rechecks_consent_before_sending(): void
    {
        $manager = User::factory()->create();
        $customer = User::factory()->create();
        MarketingConsent::query()->create(['user_id' => $customer->id, 'channel' => 'email', 'consented' => false, 'source' => 'account']);
        $campaign = MarketingCampaign::query()->create([
            'name' => 'Consent revoked', 'slug' => 'consent-revoked-'.Str::lower(Str::random(8)),
            'kind' => 'notification', 'channel' => 'email', 'audience_segment' => 'email_consented',
            'subject' => 'Hello', 'body' => 'Body', 'status' => 'published', 'created_by' => $manager->id,
            'published_at' => now(),
        ]);
        $delivery = MarketingCampaignDelivery::query()->create([
            'campaign_id' => $campaign->id, 'user_id' => $customer->id, 'channel' => 'email', 'status' => 'queued',
        ]);

        (new SendMarketingCampaignDelivery($delivery->id))->handle();
        $this->assertDatabaseHas('marketing_campaign_deliveries', ['id' => $delivery->id, 'status' => 'cancelled']);
    }

    public function test_consented_campaign_email_is_sent_with_unsubscribe_and_tracking_links(): void
    {
        Mail::fake();
        $manager = User::factory()->create();
        $customer = User::factory()->create();
        MarketingConsent::query()->create(['user_id' => $customer->id, 'channel' => 'email', 'consented' => true, 'consented_at' => now(), 'source' => 'account']);
        $campaign = MarketingCampaign::query()->create([
            'name' => 'Opt-in message', 'slug' => 'opt-in-'.Str::lower(Str::random(8)), 'kind' => 'notification',
            'channel' => 'email', 'audience_segment' => 'email_consented', 'subject' => 'New release',
            'body' => 'See the newest release.', 'call_to_action' => 'Watch now', 'destination_url' => 'https://noor.example/watch',
            'status' => 'published', 'created_by' => $manager->id, 'published_at' => now(),
        ]);
        $delivery = MarketingCampaignDelivery::query()->create([
            'campaign_id' => $campaign->id, 'user_id' => $customer->id, 'channel' => 'email', 'status' => 'queued',
        ]);

        (new SendMarketingCampaignDelivery($delivery->id))->handle();

        $this->assertDatabaseHas('marketing_campaign_deliveries', ['id' => $delivery->id, 'status' => 'sent']);
        Mail::assertSent(MarketingCampaignMail::class, fn (MarketingCampaignMail $mail): bool => $mail->hasTo($customer->email));
        $this->assertNotNull($delivery->fresh()->tracking_token_hash);
        $this->assertNotNull($delivery->fresh()->unsubscribe_token_hash);
    }

    public function test_marketing_manager_can_schedule_banners_but_public_feed_hides_inactive_and_future_banners(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('marketing_manager');
        $created = $this->actingAs($manager, 'sanctum')->postJson('/api/admin/marketing/banners', [
            'title' => 'Future banner', 'banner_for' => 'home', 'status' => true,
            'starts_at' => now()->addDay()->toIso8601String(), 'poster_url' => 'https://cdn.example.test/future.jpg',
        ])->assertCreated()->assertJsonPath('data.status', true)->json('data');
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'marketing.banner.created']);
        $this->putJson('/api/admin/marketing/banners/'.$created['id'], [
            'title' => 'Future banner updated', 'banner_for' => 'home', 'status' => true,
            'starts_at' => now()->addDay()->toIso8601String(), 'poster_url' => 'https://cdn.example.test/future.jpg',
        ])->assertOk()->assertJsonPath('data.title', 'Future banner updated');
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'marketing.banner.updated']);
        Banner::query()->create(['title' => 'Inactive', 'banner_for' => 'home', 'status' => false]);
        Banner::query()->create(['title' => 'Visible', 'banner_for' => 'home', 'status' => true]);

        $response = $this->getJson('/api/banners?status=0')->assertOk();
        $titles = collect($response->json('data.data'))->pluck('title');
        $this->assertTrue($titles->contains('Visible'));
        $this->assertFalse($titles->contains('Inactive'));
        $this->assertFalse($titles->contains('Future banner'));
    }

    public function test_marketing_tracking_records_opens_and_redirects_only_to_the_campaign_destination(): void
    {
        $manager = User::factory()->create();
        $user = User::factory()->create();
        $campaign = MarketingCampaign::query()->create([
            'name' => 'Trackable', 'slug' => 'trackable-'.Str::lower(Str::random(8)), 'kind' => 'notification',
            'channel' => 'email', 'audience_segment' => 'email_consented', 'body' => 'Tracked body',
            'destination_url' => 'https://noor.example.test/watch', 'status' => 'published',
            'created_by' => $manager->id, 'published_at' => now(),
        ]);
        $openToken = bin2hex(random_bytes(32));
        $clickToken = bin2hex(random_bytes(32));
        $delivery = MarketingCampaignDelivery::query()->create([
            'campaign_id' => $campaign->id, 'user_id' => $user->id, 'channel' => 'email', 'status' => 'sent',
            'sent_at' => now(), 'tracking_token_hash' => hash('sha256', $openToken),
        ]);
        $this->get('/api/marketing/open/'.$openToken)->assertOk()->assertHeader('Content-Type', 'image/gif');
        $delivery->forceFill(['tracking_token_hash' => hash('sha256', $clickToken)])->save();
        $this->get('/api/marketing/click/'.$clickToken)->assertRedirect('https://noor.example.test/watch');
        $this->assertNotNull($delivery->fresh()->opened_at);
        $this->assertNotNull($delivery->fresh()->clicked_at);
    }
}
