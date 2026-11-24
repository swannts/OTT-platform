<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Models\UserMultiProfile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Entertainment\Services\PlaybackEntitlementService;
use Tests\TestCase;

class PlaybackEntitlementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_free_title_requires_owned_profile_and_active_device_selection(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, false);
        $contentId = $this->createContent('free');
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile);

        $result = app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
        $this->assertSame($contentId, $result['content']->id);

        $anotherUser = User::factory()->create();
        $foreignProfile = $this->createProfile($anotherUser, false);
        try {
            app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $foreignProfile->id, $request);
            $this->fail('A profile owned by another account must be rejected.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    public function test_two_accounts_on_the_same_ip_use_separate_server_issued_device_bindings(): void
    {
        $contentId = $this->createContent('free');
        $request = request()->duplicate();

        foreach ([User::factory()->create(), User::factory()->create()] as $user) {
            $profile = $this->createProfile($user, false);
            $this->activateProfile($user, $profile);
            $request->setUserResolver(fn () => $user);

            $result = app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
            $this->assertSame($profile->id, $result['profile']->id);
        }
    }

    public function test_playback_rejects_requests_without_a_server_issued_sanctum_device(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, false);
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);

        $this->expectException(AuthorizationException::class);
        app(PlaybackEntitlementService::class)->authorize($user, $this->createContent('free'), 'movie', $profile->id, $request);
    }

    public function test_playback_rechecks_current_account_status_for_existing_device_tokens(): void
    {
        $contentId = $this->createContent('free');

        foreach ([['status' => 0, 'is_banned' => 0], ['status' => 1, 'is_banned' => 1]] as $accountState) {
            $user = User::factory()->create();
            $profile = $this->createProfile($user, false);
            $request = request()->duplicate();
            $request->setUserResolver(fn () => $user);
            $this->activateProfile($user, $profile);

            // Keep the authenticated model and token, as can happen when an
            // account is disabled while a request still holds its auth context.
            DB::table('users')->where('id', $user->id)->update($accountState);

            try {
                app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
                $this->fail('Suspended and banned accounts must not retain playback access through existing tokens.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_child_profile_cannot_play_restricted_title_even_when_it_is_free(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, true);
        $contentId = $this->createContent('free', 1);
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile);

        $this->expectException(AuthorizationException::class);
        app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
    }

    public function test_free_title_is_denied_when_its_license_window_has_expired(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, false);
        $contentId = $this->createContent('free');
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile);
        DB::table('content_license_windows')->insert([
            'content_type' => 'entertainment',
            'content_id' => $contentId,
            'starts_at' => now()->utc()->subDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->utc()->subSecond()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
    }

    public function test_free_title_is_denied_before_its_license_window_starts(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, false);
        $contentId = $this->createContent('free');
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile);
        DB::table('content_license_windows')->insert([
            'content_type' => 'entertainment',
            'content_id' => $contentId,
            'starts_at' => now()->utc()->addDay()->format('Y-m-d H:i:s'),
            'ends_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
    }

    public function test_episode_must_also_be_inside_its_parent_series_license_window(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, false);
        $seriesId = $this->createContent('free');
        DB::table('entertainments')->where('id', $seriesId)->update(['type' => 'tvshow']);
        $episodeId = (string) Str::uuid();
        DB::table('episodes')->insert([
            'id' => $episodeId,
            'name' => 'Licensing test episode',
            'entertainment_id' => $seriesId,
            'access' => 'free',
            'status' => 1,
            'is_restricted' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('content_license_windows')->insert([
            'content_type' => 'entertainment',
            'content_id' => $seriesId,
            'starts_at' => null,
            'ends_at' => now()->utc()->subSecond()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile);

        $this->expectException(AuthorizationException::class);
        app(PlaybackEntitlementService::class)->authorize($user, $episodeId, 'episode', $profile->id, $request);
    }

    public function test_video_is_denied_after_its_own_license_window_expires(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, false);
        $videoId = DB::table('videos')->insertGetId([
            'name' => 'Licensing test video',
            'type' => 'video',
            'access' => 'free',
            'status' => 1,
            'is_restricted' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('content_license_windows')->insert([
            'content_type' => 'video',
            'content_id' => (string) $videoId,
            'starts_at' => null,
            'ends_at' => now()->utc()->subSecond()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile);

        $this->expectException(AuthorizationException::class);
        app(PlaybackEntitlementService::class)->authorize($user, (string) $videoId, 'video', $profile->id, $request);
    }

    public function test_paid_title_requires_an_active_subscription_with_sufficient_plan_level(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, false);
        $contentId = $this->createContent('paid');
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile);

        $this->expectException(AuthorizationException::class);
        app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
    }

    public function test_expired_subscription_does_not_grant_paid_playback(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, false);
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile);
        $planId = DB::table('plan')->insertGetId([
            'name' => 'Expired playback plan',
            'identifier' => 'expired-playback-'.Str::uuid(),
            'level' => 3,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('subscriptions')->insert([
            'user_id' => $user->id,
            'plan_id' => $planId,
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'end_date' => now()->subSecond(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $contentId = $this->createContent('paid', 0, $planId);

        $this->expectException(AuthorizationException::class);
        app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
    }

    public function test_active_subscriber_with_a_sufficient_plan_can_play_paid_title(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, false);
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile);

        $planId = DB::table('plan')->insertGetId([
            'name' => 'Playback test plan',
            'identifier' => 'playback-test-'.Str::uuid(),
            'level' => 2,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('subscriptions')->insert([
            'user_id' => $user->id,
            'plan_id' => $planId,
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $contentId = $this->createContent('paid', 0, $planId);

        $result = app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
        $this->assertSame($contentId, $result['content']->id);
    }

    public function test_paid_ppv_requires_a_provider_verified_receipt_and_valid_access_window(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, false);
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile);
        $contentId = $this->createContent('pay-per-view');
        $purchaseId = DB::table('pay_per_views')->insertGetId([
            'user_id' => $user->id,
            'movie_id' => $contentId,
            'type' => 'movie',
            'content_price' => 5,
            'price' => 5,
            'view_expiry_date' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('payperviewstransactions')->insert([
            'user_id' => $user->id,
            'pay_per_view_id' => $purchaseId,
            'amount' => 5,
            'payment_type' => 'test',
            'payment_status' => 'paid',
            'transaction_id' => 'verified-ppv-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
            $this->fail('A legacy paid transaction without a verified provider receipt must not grant playback.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        DB::table('verified_payments')->insert([
            'gateway' => 'test',
            'provider_reference' => 'verified-ppv-1',
            'user_id' => $user->id,
            'purchase_type' => 'pay_per_view',
            'purchase_id' => $contentId,
            'amount_minor' => 500,
            'currency' => 'USD',
            'fulfillment_type' => 'pay_per_view',
            'fulfillment_id' => $purchaseId,
            'fulfilled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
        $this->assertSame($contentId, $result['content']->id);

        DB::table('pay_per_views')->where('id', $purchaseId)->update(['view_expiry_date' => now()->subSecond()]);
        $this->expectException(AuthorizationException::class);
        app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
    }

    public function test_legacy_decrypt_endpoint_is_not_registered(): void
    {
        $this->assertFalse(collect(\Route::getRoutes())->contains(function ($route) {
            return $route->uri() === 'v3/video/decrypt-url';
        }));
    }

    private function createProfile(User $user, bool $child): UserMultiProfile
    {
        return UserMultiProfile::create([
            'user_id' => $user->id,
            'name' => $child ? 'Child' : 'Parent',
            'is_child_profile' => $child ? 1 : 0,
        ]);
    }

    private function activateProfile(User $user, UserMultiProfile $profile): void
    {
        $token = $user->createToken('playback-test')->accessToken;
        $user->withAccessToken($token);
        Device::create([
            'user_id' => $user->id,
            'sanctum_token_id' => $token->getKey(),
            'device_id' => (string) Str::uuid(),
            'active_profile' => $profile->id,
            'device_name' => 'Test browser',
            'platform' => 'web',
        ]);
    }

    private function createContent(string $access, int $restricted = 0, ?int $planId = null): string
    {
        $id = (string) Str::uuid();
        DB::table('entertainments')->insert([
            'id' => $id,
            'name' => 'Playback test title',
            'type' => 'movie',
            'movie_access' => $access,
            'plan_id' => $planId,
            'is_restricted' => $restricted,
            'status' => 1,
            'video_upload_type' => 'URL',
            'video_url_input' => 'https://media.example.test/title.m3u8',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
