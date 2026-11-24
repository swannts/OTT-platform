<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\UserMultiProfile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Entertainment\Services\PlaybackMediaUrlService;
use Modules\Entertainment\Services\PlaybackSessionService;
use Tests\TestCase;

class PlaybackSessionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_duplicate_start_is_idempotent_and_concurrent_stream_limit_is_enforced(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('playback-test')->accessToken;
        $service = app(PlaybackSessionService::class);

        $first = $service->start($user, $token, 1, 'content-1', 'movie');
        $duplicate = $service->start($user, $token, 1, 'content-1', 'movie');

        $this->assertSame($first['session_id'], $duplicate['session_id']);
        $this->assertSame(1, DB::table('playback_sessions')->where('user_id', $user->id)->count());

        try {
            $service->start($user, $token, 1, 'content-2', 'movie');
            $this->fail('A second concurrent stream should be denied at the default limit.');
        } catch (AuthorizationException) {
            $this->assertSame(1, DB::table('playback_sessions')->where('user_id', $user->id)->whereNull('ended_at')->count());
        }
    }

    public function test_nested_plan_snapshot_allows_the_configured_simultaneous_stream_limit(): void
    {
        $user = User::factory()->create();
        $planId = DB::table('plan')->insertGetId([
            'name' => 'Two stream plan', 'identifier' => 'two-streams-'.Str::uuid(),
            'level' => 1, 'duration' => 'month', 'duration_value' => 1, 'price' => 10,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('subscriptions')->insert([
            'user_id' => $user->id, 'plan_id' => $planId, 'status' => 'active',
            'start_date' => now(), 'end_date' => now()->addMonth(),
            'plan_type' => json_encode(['data' => [['plan_id' => $planId, 'plan_type' => ['data' => [[
                'slug' => 'simultaneous-streams', 'limitation_value' => 1, 'limit' => ['value' => '2'],
            ]]]]]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $token = $user->createToken('playback-test')->accessToken;
        $service = app(PlaybackSessionService::class);

        $service->start($user, $token, 1, 'content-1', 'movie');
        $service->start($user, $token, 1, 'content-2', 'movie');
        $this->assertSame(2, DB::table('playback_sessions')->where('user_id', $user->id)->whereNull('ended_at')->count());

        $this->expectException(AuthorizationException::class);
        $service->start($user, $token, 1, 'content-3', 'movie');
    }

    public function test_only_the_owning_account_and_device_token_can_refresh_or_end_a_session(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('playback-test')->accessToken;
        $session = app(PlaybackSessionService::class)->start($user, $token, 1, 'content-1', 'movie');

        $foreignUser = User::factory()->create();
        $foreignToken = $foreignUser->createToken('playback-test')->accessToken;

        try {
            app(PlaybackSessionService::class)->heartbeat($foreignUser, $foreignToken, $session['session_id']);
            $this->fail('A different account must not refresh another account playback session.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        app(PlaybackSessionService::class)->end($user, $token, $session['session_id']);
        app(PlaybackSessionService::class)->end($user, $token, $session['session_id']);
        $this->assertNotNull(DB::table('playback_sessions')->where('session_id', $session['session_id'])->value('ended_at'));
    }

    public function test_suspended_account_cannot_start_or_refresh_a_playback_session(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('playback-test')->accessToken;
        $service = app(PlaybackSessionService::class);
        $session = $service->start($user, $token, 1, 'content-1', 'movie');

        DB::table('users')->where('id', $user->id)->update(['status' => 0]);

        try {
            $service->start($user, $token, 1, 'content-2', 'movie');
            $this->fail('A suspended account must not start another playback session.');
        } catch (AuthorizationException) {
            $this->assertSame(1, DB::table('playback_sessions')->where('user_id', $user->id)->whereNull('ended_at')->count());
        }

        $this->expectException(AuthorizationException::class);
        $service->heartbeat($user, $token, $session['session_id']);
    }

    public function test_customer_end_endpoint_records_one_diagnostic_event_for_idempotent_end(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('playback-test');
        $session = app(PlaybackSessionService::class)->start($user, $token->accessToken, 1, 'content-12', 'movie');

        $this->withToken($token->plainTextToken)->postJson('/api/v3/playback/sessions/'.$session['session_id'].'/end')->assertOk();
        $this->withToken($token->plainTextToken)->postJson('/api/v3/playback/sessions/'.$session['session_id'].'/end')->assertOk();

        $this->assertSame(1, DB::table('playback_diagnostic_events')->where('session_id', $session['session_id'])->where('event_code', 'playback.session_ended')->count());
        $this->assertDatabaseHas('playback_diagnostic_events', ['user_id' => $user->id, 'content_type' => 'movie', 'content_id' => 'content-12']);
    }

    public function test_private_hls_manifest_signs_each_segment_and_revoked_session_is_denied(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $profile = UserMultiProfile::create(['user_id' => $user->id, 'name' => 'Parent', 'is_child_profile' => 0]);
        $token = $user->createToken('playback-test')->accessToken;
        Device::create([
            'user_id' => $user->id,
            'sanctum_token_id' => $token->getKey(),
            'device_id' => (string) Str::uuid(),
            'device_name' => 'Test device',
            'platform' => 'test',
            'active_profile' => $profile->id,
        ]);
        DB::table('subscriptions')->insert([
            'user_id' => $user->id, 'status' => 'active', 'start_date' => now(), 'end_date' => now()->addMonth(),
            'plan_type' => json_encode(['data' => [[
                'plan_type' => ['data' => [[
                    'slug' => 'max-video-quality', 'limitation_value' => 1, 'limit' => 720,
                ]]],
            ]]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $asset = MediaAsset::create([
            'uploaded_by' => $user->id,
            'original_name' => 'title.mp4',
            'disk' => 'private',
            'source_path' => 'media/source.mp4',
            'rendition_path' => 'media/renditions/test',
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
            'size_bytes' => 100,
            'sha256' => str_repeat('a', 64),
            'status' => 'ready',
        ]);
        DB::table('entertainments')->insert([
            'id' => 'playback-media-title',
            'name' => 'Private title',
            'type' => 'movie',
            'movie_access' => 'free',
            'media_asset_id' => $asset->id,
            'status' => 1,
            'video_upload_type' => 'URL',
            'video_url_input' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('private')->put('media/renditions/test/master.m3u8', "#EXTM3U\n#EXT-X-STREAM-INF:BANDWIDTH=1500000,RESOLUTION=1280x720\n720p.m3u8\n#EXT-X-STREAM-INF:BANDWIDTH=5000000,RESOLUTION=1920x1080\n1080p.m3u8\n#EXTINF:5,\nsegments/part-1.ts\n");
        Storage::disk('private')->put('media/renditions/test/segments/part-1.ts', 'segment');
        $session = app(PlaybackSessionService::class)->start($user, $token, $profile->id, 'playback-media-title', 'movie');
        $url = URL::temporarySignedRoute('playback.media', now()->addSeconds(90), [
            'assetId' => $asset->id,
            'file' => 'master.m3u8',
            'sessionId' => $session['session_id'],
        ]);

        $manifestResponse = $this->get($url);
        $manifestResponse->assertOk()->assertSee('part-1.ts')->assertSee('720p.m3u8')->assertDontSee('1080p.m3u8');
        preg_match('/(https?:[^\s]+part-1\.ts[^\s]*)/', $manifestResponse->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $segmentResponse = $this->get($matches[1]);
        $segmentResponse->assertOk();
        $this->assertSame('7', $segmentResponse->headers->get('Content-Length'));

        $highQualityUrl = app(PlaybackMediaUrlService::class)->sign($asset->id, '1080p_00001.ts', $session['session_id']);
        $this->get($highQualityUrl)->assertForbidden();

        $this->travel(91)->seconds();
        try {
            $expiresAt = Carbon::parse(DB::table('playback_sessions')->where('session_id', $session['session_id'])->value('expires_at'));
            $this->assertTrue($expiresAt->isFuture(), 'The playback session should still be active after the URL expires.');
            $this->get($url)->assertForbidden();
        } finally {
            $this->travelBack();
        }

        app(PlaybackSessionService::class)->end($user, $token, $session['session_id']);
        $this->get($url)->assertForbidden();
    }
}
