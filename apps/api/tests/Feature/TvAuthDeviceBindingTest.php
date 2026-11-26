<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TvLoginSession;
use App\Models\User;
use App\Models\UserMultiProfile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TvAuthDeviceBindingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tv_pairing_issues_one_token_bound_device_and_consumes_the_pairing_code(): void
    {
        $user = User::factory()->create();
        UserMultiProfile::create(['user_id' => $user->id, 'name' => 'Parent', 'is_child_profile' => 0]);
        $pairing = TvLoginSession::createSession();
        $pairing->update(['user_id' => $user->id, 'confirmed_at' => now()]);

        $this->postJson('/api/tv/check-session', [
            'session_id' => $pairing->session_id,
            'device_name' => 'Living room',
            'platform' => 'tv',
        ])->assertOk();

        $device = Device::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($device->sanctum_token_id);
        $this->assertSame(1, Device::query()->where('user_id', $user->id)->count());
        $this->assertNotNull($pairing->fresh()->consumed_at);

        $this->postJson('/api/tv/check-session', ['session_id' => $pairing->session_id])->assertStatus(409);
        $this->assertSame(1, Device::query()->where('user_id', $user->id)->count());
    }
}
