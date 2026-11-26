<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseMaintenanceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_maintenance_requires_authentication(): void
    {
        $this->getJson('/api/optimize')->assertUnauthorized();
    }

    public function test_customer_bearer_token_cannot_run_database_maintenance(): void
    {
        $token = User::factory()->create()->createToken('customer')->plainTextToken;

        $this->withToken($token)->getJson('/api/optimize')->assertForbidden();
    }
}
