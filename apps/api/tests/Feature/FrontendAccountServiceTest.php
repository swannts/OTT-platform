<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Services\Frontend\FrontendAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Modules\Tax\Models\Tax;
use Tests\TestCase;

class FrontendAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_logout_scopes_by_user_id(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $otherDevice = Device::create([
            'user_id' => $other->id,
            'device_id' => 'other-device-1',
            'device_name' => 'Phone',
            'active_profile_id' => null,
            'platform' => 'android',
        ]);

        $ownerDevice = Device::create([
            'user_id' => $owner->id,
            'device_id' => 'owner-device-1',
            'device_name' => 'Web',
            'active_profile_id' => null,
            'platform' => 'web',
        ]);

        $service = new FrontendAccountService;

        // Attempting to log out another user's device id must fail
        $this->assertFalse($service->deviceLogout($owner->id, null, $otherDevice->id));
        $this->assertDatabaseHas('devices', ['id' => $otherDevice->id]);

        // Logging out own device succeeds
        $this->assertTrue($service->deviceLogout($owner->id, 'owner-device-1'));
        $this->assertDatabaseMissing('devices', ['id' => $ownerDevice->id]);
    }

    public function test_get_payment_details_and_check_subscription(): void
    {
        $user = User::factory()->create();
        $planBasic = Plan::create([
            'name' => 'Basic',
            'identifier' => 'basic',
            'price' => 100,
            'discount' => 1,
            'discount_percentage' => 10,
            'total_price' => 90,
            'level' => 1,
            'duration' => 1,
            'duration_value' => 1,
            'type' => 'Monthly',
            'status' => 1,
        ]);
        $planPro = Plan::create([
            'name' => 'Pro',
            'identifier' => 'pro',
            'price' => 200,
            'discount' => 0,
            'discount_percentage' => 0,
            'total_price' => 200,
            'level' => 2,
            'duration' => 1,
            'duration_value' => 1,
            'type' => 'Monthly',
            'status' => 1,
        ]);

        Tax::create([
            'title' => 'VAT',
            'type' => 'percentage',
            'value' => 10,
            'status' => 1,
        ]);

        $service = new FrontendAccountService;
        $details = $service->getPaymentDetails($planBasic->id);

        $this->assertSame(200, $details['status']);
        $this->assertEquals(90, $details['payload']['subtotal']);
        $this->assertEquals(9, $details['payload']['tax']);
        $this->assertEquals(99, $details['payload']['total']);

        Subscription::create([
            'plan_id' => $planPro->id,
            'user_id' => $user->id,
            'level' => 2,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        $this->assertTrue($service->checkSubscription($user->id, $planBasic->id));
        $this->assertTrue($service->checkSubscription($user->id, $planPro->id));
    }
}
