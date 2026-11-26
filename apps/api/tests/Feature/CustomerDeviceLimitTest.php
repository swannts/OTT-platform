<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class CustomerDeviceLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_at_the_registered_device_limit_does_not_register_another_device(): void
    {
        $user = User::factory()->create(['password' => Hash::make('device-limit-test-password')]);
        $existingToken = $user->createToken('existing-device')->accessToken;
        Device::query()->create([
            'user_id' => $user->getKey(),
            'sanctum_token_id' => $existingToken->getKey(),
            'device_id' => 'server-issued-existing-device',
            'device_name' => 'Existing device',
            'platform' => 'android',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'device-limit-test-password',
            'device_name' => 'New device',
            'platform' => 'ios',
        ]);

        $response->assertStatus(406)
            ->assertJsonStructure(['error', 'other_device', 'device_management_token']);

        $this->assertSame(1, Device::query()->where('user_id', $user->getKey())->count());
        $this->assertDatabaseHas('devices', [
            'user_id' => $user->getKey(),
            'sanctum_token_id' => $existingToken->getKey(),
        ]);

        $managementToken = PersonalAccessToken::findToken($response->json('device_management_token'));
        $this->assertNotNull($managementToken);
        $this->assertSame(['device-sessions:manage'], $managementToken->abilities);
        $this->assertTrue($managementToken->expires_at->isFuture());
    }
}
