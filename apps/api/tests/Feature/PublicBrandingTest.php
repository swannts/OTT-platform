<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_branding_defaults_to_noor(): void
    {
        $this->getJson('/api/v3/branding')
            ->assertOk()
            ->assertJsonPath('data.platform_name', 'NOOR Islamic Cinema');
    }

    public function test_public_branding_returns_only_allowlisted_settings(): void
    {
        DB::table('settings')->insert([
            ['name' => 'user_app_name', 'val' => '<strong>Example Cinema</strong>'],
            ['name' => 'app_name', 'val' => 'Example Business'],
            ['name' => 'inquriy_email', 'val' => 'invalid email'],
            ['name' => 'smtp_password', 'val' => 'test-placeholder'],
        ]);

        $response = $this->getJson('/api/v3/branding')->assertOk();
        $response->assertExactJson([
            'success' => true,
            'data' => [
                'platform_name' => 'Example Cinema',
                'business_name' => 'Example Business',
                'description' => null,
                'support_email' => null,
                'support_phone' => null,
                'copyright_text' => null,
            ],
            'meta' => ['request_id' => $response->headers->get('X-Request-Id')],
        ]);
    }
}
