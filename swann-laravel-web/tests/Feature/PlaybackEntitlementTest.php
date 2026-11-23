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
        $this->activateProfile($user, $profile, $request->ip());

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

    public function test_child_profile_cannot_play_restricted_title_even_when_it_is_free(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, true);
        $contentId = $this->createContent('free', 1);
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile, $request->ip());

        $this->expectException(AuthorizationException::class);
        app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
    }

    public function test_paid_title_requires_an_active_subscription_with_sufficient_plan_level(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, false);
        $contentId = $this->createContent('paid');
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile, $request->ip());

        $this->expectException(AuthorizationException::class);
        app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
    }

    public function test_active_subscriber_with_a_sufficient_plan_can_play_paid_title(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, false);
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile, $request->ip());

        $planId = DB::table('plan')->insertGetId([
            'name' => 'Playback test plan',
            'identifier' => 'playback-test-' . Str::uuid(),
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

    public function test_paid_ppv_requires_a_paid_transaction_and_valid_access_window(): void
    {
        $user = User::factory()->create();
        $profile = $this->createProfile($user, false);
        $request = request()->duplicate();
        $request->setUserResolver(fn () => $user);
        $this->activateProfile($user, $profile, $request->ip());
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(PlaybackEntitlementService::class)->authorize($user, $contentId, 'movie', $profile->id, $request);
        $this->assertSame($contentId, $result['content']->id);
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

    private function activateProfile(User $user, UserMultiProfile $profile, string $ip): void
    {
        Device::create([
            'user_id' => $user->id,
            'device_id' => $ip,
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
