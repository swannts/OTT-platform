<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerDeviceSessionRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_device_revocation_ends_playback_and_consumes_management_token(): void
    {
        $user = User::factory()->create();
        $deviceToken = $user->createToken('tv-device')->accessToken;
        $managementToken = $user->createToken(
            'device-management',
            ['device-sessions:manage'],
            now()->addMinutes(5),
        );
        $device = Device::query()->create([
            'user_id' => $user->getKey(),
            'sanctum_token_id' => $deviceToken->getKey(),
            'device_id' => 'tv-device-id',
            'device_name' => 'Living room TV',
            'platform' => 'tv',
        ]);
        $sessionId = (string) \Illuminate\Support\Str::uuid();
        DB::table('playback_sessions')->insert([
            'session_id' => $sessionId,
            'user_id' => $user->getKey(),
            'device_token_id' => $deviceToken->getKey(),
            'profile_id' => 1,
            'content_id' => 'movie-1',
            'content_type' => 'movie',
            'last_heartbeat_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($managementToken->plainTextToken)
            ->postJson('/api/device-sessions/revoke', ['device_id' => $device->getKey()])
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $deviceToken->getKey()]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $managementToken->accessToken->getKey()]);
        $this->assertDatabaseMissing('devices', ['id' => $device->getKey()]);
        $this->assertNotNull(DB::table('playback_sessions')->where('session_id', $sessionId)->value('ended_at'));
    }

    public function test_device_management_token_cannot_revoke_another_account_device(): void
    {
        $owner = User::factory()->create();
        $foreignUser = User::factory()->create();
        $managementToken = $owner->createToken('device-management', ['device-sessions:manage'], now()->addMinutes(5));
        $foreignToken = $foreignUser->createToken('foreign-device')->accessToken;
        $foreignDevice = Device::query()->create([
            'user_id' => $foreignUser->getKey(),
            'sanctum_token_id' => $foreignToken->getKey(),
            'device_id' => 'foreign-device-id',
            'device_name' => 'Foreign TV',
            'platform' => 'tv',
        ]);

        $this->withToken($managementToken->plainTextToken)
            ->postJson('/api/device-sessions/revoke', ['device_id' => $foreignDevice->getKey()])
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreignToken->getKey()]);
        $this->assertDatabaseHas('devices', ['id' => $foreignDevice->getKey()]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $managementToken->accessToken->getKey()]);
    }

    public function test_device_revocation_requires_the_scoped_management_ability(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('ordinary-session', ['profile:read']);

        $this->withToken($token->plainTextToken)
            ->postJson('/api/device-sessions/revoke', ['device_id' => 123])
            ->assertForbidden();
    }

    public function test_customer_logout_ends_playback_before_revoking_the_current_device(): void
    {
        $user = User::factory()->create();
        $issuedToken = $user->createToken('phone-device');
        $deviceToken = $issuedToken->accessToken;
        $device = Device::query()->create([
            'user_id' => $user->getKey(),
            'sanctum_token_id' => $deviceToken->getKey(),
            'device_id' => 'phone-device-id',
            'device_name' => 'Phone',
            'platform' => 'android',
        ]);
        $sessionId = (string) \Illuminate\Support\Str::uuid();
        DB::table('playback_sessions')->insert([
            'session_id' => $sessionId,
            'user_id' => $user->getKey(),
            'device_token_id' => $deviceToken->getKey(),
            'profile_id' => 1,
            'content_id' => 'movie-2',
            'content_type' => 'movie',
            'last_heartbeat_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($issuedToken->plainTextToken)->getJson('/api/logout')->assertOk();

        $this->assertNotNull(DB::table('playback_sessions')->where('session_id', $sessionId)->value('ended_at'));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $deviceToken->getKey()]);
        $this->assertDatabaseMissing('devices', ['id' => $device->getKey()]);
    }
}
