<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class LocalDemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_local_demo_seed_populates_major_product_areas_idempotently(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('swann_test', config('database.connections.mysql.database'), 'Feature tests must never connect to the local application database.');

        config(['app.demo_seed_password' => 'local-demo-only-test-password']);
        $existingUser = User::factory()->create([
            'email' => 'preserve-this-account@example.test',
            'password' => Hash::make('keep-existing-password'),
        ]);
        $originalPassword = $existingUser->password;

        $seeder = app(LocalDemoDataSeeder::class)->setContainer(app());
        $seeder->run();

        $tables = [
            'entertainments',
            'plan',
            'subscriptions',
            'verified_payments',
            'marketing_campaigns',
            'support_tickets',
            'admin_content_review_submissions',
            'admin_audit_logs',
            'analytics_report_runs',
            'operations_incidents',
            'playback_diagnostic_events',
            'content_license_windows',
            'notifications',
            'user_multi_profiles',
            'user_profiles',
            'watchlists',
            'user_watch_histories',
            'user_search_histories',
            'devices',
            'countries',
            'states',
            'cities',
            'currencies',
            'taxes',
            'seasons',
            'episodes',
            'cast_crew',
            'blogs',
            'faqs',
            'pages',
            'seo',
            'videos',
            'live_tv_category',
            'live_tv_channel',
            'entertainment_country_mapping',
            'reviews',
            'likes',
            'entertainment_views',
            'user_reminder',
        ];
        $counts = array_map(static fn (string $table): int => \Illuminate\Support\Facades\DB::table($table)->count(), $tables);

        $seeder->run();

        $this->assertSame(
            $counts,
            array_map(static fn (string $table): int => \Illuminate\Support\Facades\DB::table($table)->count(), $tables),
        );
        $this->assertDatabaseHas('users', ['id' => $existingUser->getKey(), 'email' => 'preserve-this-account@example.test']);
        $this->assertSame($originalPassword, $existingUser->fresh()->password);
        $this->assertDatabaseHas('marketing_campaigns', ['slug' => 'demo-welcome-campaign', 'status' => 'draft']);
        $this->assertDatabaseHas('support_tickets', ['subject' => 'Demo: help with subtitles', 'status' => 'open']);
        $this->assertDatabaseHas('user_multi_profiles', ['name' => 'Family', 'is_child_profile' => true]);
        $this->assertDatabaseHas('watchlists', ['type' => 'movie']);
        $this->assertDatabaseHas('devices', ['device_id' => 'demo-device-amina-web', 'platform' => 'web']);
        $this->assertDatabaseHas('countries', ['code' => 'BD', 'name' => 'Bangladesh']);
        $this->assertDatabaseHas('episodes', ['slug' => 'demo-prophetic-chronicles-episode-1', 'status' => 0]);
        $this->assertDatabaseHas('blogs', ['slug' => 'demo-reflection-and-kindness', 'status' => 0]);
        $this->assertDatabaseHas('videos', ['slug' => 'demo-short-reflection', 'status' => 0]);
        $this->assertDatabaseHas('live_tv_channel', ['slug' => 'demo-live-channel', 'status' => 0]);
        $this->assertDatabaseHas('reviews', ['review' => 'Demo review: sample customer feedback for local interface testing.']);
    }

    public function test_demo_seed_requires_a_private_password_and_local_environment(): void
    {
        config(['app.demo_seed_password' => null]);
        try {
            app(LocalDemoDataSeeder::class)->run();
            $this->fail('A configured demo password is required before creating demo accounts.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('users', 0);
        }

        app()->detectEnvironment(static fn (): string => 'production');
        config(['app.demo_seed_password' => 'unused-test-password']);
        try {
            app(LocalDemoDataSeeder::class)->run();
            $this->fail('Demo data must be rejected outside local/testing.');
        } catch (LogicException) {
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_demo_seed_refuses_to_reuse_an_unowned_fixture_account(): void
    {
        config(['app.demo_seed_password' => 'local-demo-only-test-password']);
        $existing = User::factory()->create([
            'email' => 'demo.content.manager@example.test',
            'user_type' => 'user',
            'password' => Hash::make('keep-this-existing-password'),
        ]);
        $passwordHash = $existing->password;

        try {
            app(LocalDemoDataSeeder::class)->run();
            $this->fail('An unowned account with a demo fixture email must be preserved.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('not an eligible account', $exception->getMessage());
        }

        $this->assertSame($passwordHash, $existing->fresh()->password);
        $this->assertFalse($existing->fresh()->hasAnyRole(array_keys(\App\Support\Rbac\RbacRegistry::roles())));
    }
}
