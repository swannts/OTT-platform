<?php

namespace Tests\Feature;

use App\Support\Staging\StagingDataIntegrityValidator;
use App\Models\MediaAsset;
use App\Services\Media\MediaReadinessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RelationalStagingGenerationTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Storage::disk('private')->deleteDirectory('media/staging/feature-staging-small');
        parent::tearDown();
    }

    public function test_small_dataset_generation_is_relational_and_repeatable(): void
    {
        $testDatabase = getenv('STAGING_TEST_DATABASE') ?: 'swann_test';
        config([
            'app.env' => 'testing',
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $testDatabase,
            'staging-data.enabled' => true,
            'staging-data.dataset_id' => 'feature-staging-small',
            'staging-data.allowed_database' => $testDatabase,
            'staging-data.allowed_database_host' => '127.0.0.1',
            'staging-data.isolated_cache_prefix' => 'ott_staging_feature_test',
            'cache.prefix' => 'ott_staging_feature_test',
            'cache.default' => 'file',
            'staging-data.queue_isolated' => true,
            'queue.default' => 'database',
            'queue.connections.database.connection' => null,
            'staging-data.search_isolated' => true,
            'staging-data.media_isolated' => true,
            'filesystems.default' => 'local',
            'staging-data.external_delivery_disabled' => true,
            'mail.default' => 'array',
            'staging-data.payment_gateway' => 'synthetic',
            'staging-data.staff_password' => 'staging-feature-staff-password',
            'staging-data.customer_password' => 'staging-feature-customer-password',
        ]);

        $arguments = [
            '--profile' => 'small',
            '--history-months' => '12',
            '--seed' => '2026',
            '--reference-date' => '2026-11-26',
            '--counts' => json_encode([
                'staff' => 12,
                'customers' => 20,
                'profiles' => 24,
                'devices' => 25,
                'genres' => 12,
                'movies' => 10,
                'series' => 2,
                'seasons' => 3,
                'episodes' => 20,
                'cast_crew' => 12,
                'subscriptions' => 24,
                'payments' => 30,
                'media_assets' => 2,
                'blogs' => 20,
            ], JSON_THROW_ON_ERROR),
        ];

        $this->artisan('ott:seed-staging', $arguments)->assertExitCode(0);

        $this->assertSame(12, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'staff_user')->count());
        $this->assertSame(20, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'customer')->count());
        $this->assertSame(24, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'user_multi_profile')->count());
        $this->assertSame(25, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'device')->count());
        $this->assertSame(12, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'genre')->count());
        $this->assertSame(12, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'entertainment')->count());
        $this->assertSame(12, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'entertainment_genre_mapping')->count());
        $this->assertSame(3, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'season')->count());
        $this->assertSame(20, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'episode')->count());
        $this->assertSame(5, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'plan')->count());
        $this->assertSame(24, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'subscription')->count());
        $this->assertSame(30, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'subscription_transaction')->count());
        $this->assertSame(12, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'cast_crew')->count());
        $this->assertSame(48, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'entertainment_talent_mapping')->count());
        $this->assertSame(11, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'content_license_window')->count());
        $this->assertSame(19, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'content_license_territory')->count());
        $mediaAssetIds = DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'media_asset')->pluck('record_id');
        $this->assertCount(2, $mediaAssetIds);
        foreach (MediaAsset::query()->whereIn('id', $mediaAssetIds)->with('assetTracks')->get() as $asset) {
            $this->assertTrue(app(MediaReadinessService::class)->inspect($asset)['ready']);
            $this->assertSame($asset->sha256, hash_file('sha256', Storage::disk('private')->path($asset->source_path)));
        }
        $this->assertSame(2, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'entertainment_media_link')->count());
        $this->assertSame(20, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'blog')->count());
        $this->assertSame(18, DB::table('blogs')->where('status', 1)->whereNotNull('published_at')->count());
        $this->assertSame(2, DB::table('blogs')->where('status', 0)->whereNull('published_at')->count());
        $this->assertSame(15, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'coupon')->count());
        $this->assertSame(30, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'coupon_plan_mapping')->count());
        $this->assertSame(30, DB::table('coupon_subscription_plan')->count());
        $this->assertSame(0, DB::table('verified_payments')->count());
        $this->assertSame(0, DB::table('subscriptions')->where('status', 'active')->where('is_trial', 0)->whereNull('payment_id')->count());

        $integrity = app(StagingDataIntegrityValidator::class)->report('feature-staging-small');
        $this->assertTrue($integrity['valid'], implode('; ', $integrity['violations']));
        $this->assertFalse($integrity['complete']);

        $this->artisan('ott:seed-staging', $arguments)->assertExitCode(0);
        $this->assertSame(20, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'customer')->count());
        $this->assertSame(20, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'episode')->count());
        $this->assertSame(24, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'subscription')->count());
        $this->assertSame(30, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'subscription_transaction')->count());
        $this->assertSame(48, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'entertainment_talent_mapping')->count());
        $this->assertSame(11, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'content_license_window')->count());
        $this->assertSame(19, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'content_license_territory')->count());
        $this->assertSame(20, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'blog')->count());
        $this->assertSame(15, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'coupon')->count());
        $this->assertSame(30, DB::table('staging_dataset_records')->where('dataset_id', 'feature-staging-small')->where('entity_type', 'coupon_plan_mapping')->count());
    }
}
