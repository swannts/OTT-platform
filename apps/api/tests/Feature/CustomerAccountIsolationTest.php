<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Models\UserMultiProfile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAccountIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_private_customer_routes_require_authentication(): void
    {
        $this->getJson('/api/user-detail?id=1')->assertUnauthorized();
        $this->getJson('/api/user-profile-list?user_id=1')->assertUnauthorized();
        $this->getJson('/api/v3/profile-details?user_id=1')->assertUnauthorized();
        $this->getJson('/api/logout')->assertUnauthorized();
        $this->getJson('/api/logout-all')->assertUnauthorized();
        $this->getJson('/api/logout-all-data?user_id=1')->assertNotFound();
    }

    public function test_profile_listing_ignores_a_forged_user_id_and_device_logout_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownProfile = UserMultiProfile::create(['user_id' => $owner->id, 'name' => 'Owner']);
        $foreignProfile = UserMultiProfile::create(['user_id' => $other->id, 'name' => 'Foreign']);
        $foreignDevice = Device::create([
            'user_id' => $other->id,
            'device_id' => 'foreign-device',
            'device_name' => 'Other browser',
            'active_profile' => $foreignProfile->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/user-profile-list?user_id=' . $other->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $ownProfile->id])
            ->assertJsonMissing(['id' => $foreignProfile->id]);

        $this->actingAs($owner, 'sanctum')->getJson('/api/device-logout?id=' . $foreignDevice->id)->assertNotFound();
        $this->assertDatabaseHas('devices', ['id' => $foreignDevice->id, 'user_id' => $other->id]);
    }

    public function test_parental_pin_is_hashed_and_never_serialized(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/change-pin', [
            'pin' => ['1', '2', '3', '4'],
            'confirm_pin' => ['1', '2', '3', '4'],
        ])->assertOk();

        $user->refresh();
        $this->assertNull($user->pin);
        $this->assertTrue(Hash::check('1234', $user->pin_hash));
        $this->assertArrayNotHasKey('pin', $user->toArray());
        $this->assertArrayNotHasKey('otp', $user->toArray());

        $this->actingAs($user, 'sanctum')->postJson('/api/verify-pin', [
            'pin' => ['1', '2', '3', '4'],
            'user_id' => User::factory()->create()->id,
        ])->assertOk();
    }
}
