<?php

namespace Tests\Unit;

use App\Support\Staging\StagingDataSafety;
use App\Support\Staging\StagingGenerationContext;
use App\Support\Staging\StagingProfile;
use InvalidArgumentException;
use Tests\TestCase;

class StagingDataFoundationTest extends TestCase
{
    public function test_profiles_include_expected_volumes_and_allow_valid_overrides(): void
    {
        $small = StagingProfile::fromConfig('small', 12, 250);
        $standard = StagingProfile::fromConfig('standard', 24, 1000, ['customers' => 20000]);

        $this->assertSame(1000, $small->counts['customers']);
        $this->assertSame(10000, config('staging-data.profiles.standard.customers'));
        $this->assertSame(20000, $standard->counts['customers']);
        $this->assertSame(36000, $standard->counts['profiles']);
        $this->assertSame(50000, $standard->counts['devices']);
        $this->assertSame(15000, StagingProfile::fromConfig('standard', 24, 1000, ['customers' => 10000, 'profiles' => 15000])->counts['profiles']);
        $this->assertSame(3000000, config('staging-data.profiles.large.playback_sessions'));
    }

    public function test_profile_rejects_invalid_counts_and_history_windows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        StagingProfile::fromConfig('standard', 0, 1000);
    }

    public function test_generation_context_has_repeatable_randomness_and_fixed_history_start(): void
    {
        $profile = StagingProfile::fromConfig('small', 12, 100);
        $first = new StagingGenerationContext('dataset-a', 2026, $profile, '2026-11-26');
        $second = new StagingGenerationContext('dataset-a', 2026, $profile, '2026-11-26');

        $this->assertSame($first->random->getInt(1, 1000000), $second->random->getInt(1, 1000000));
        $this->assertSame('2025-11-26', $first->historyStart()->toDateString());
        $this->assertSame('2026-11-26', $first->referenceDate->toDateString());
    }

    public function test_generation_context_rejects_relative_or_invalid_reference_dates(): void
    {
        $profile = StagingProfile::fromConfig('small', 12, 100);
        foreach (['tomorrow', '2026-02-30'] as $invalidDate) {
            try {
                new StagingGenerationContext('dataset-date', 1, $profile, $invalidDate);
                $this->fail("Reference date {$invalidDate} should be rejected.");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_staging_safety_gate_fails_closed_when_isolation_is_not_explicit(): void
    {
        config([
            'staging-data.enabled' => false,
            'staging-data.allowed_database' => null,
            'staging-data.allowed_database_host' => null,
            'staging-data.isolated_cache_prefix' => null,
            'staging-data.queue_isolated' => false,
            'staging-data.search_isolated' => false,
            'staging-data.media_isolated' => false,
            'staging-data.external_delivery_disabled' => false,
            'staging-data.payment_gateway' => null,
            'staging-data.staff_password' => null,
        ]);

        $violations = app(StagingDataSafety::class)->violations('standard');

        $this->assertContains('staging seeding must be explicitly enabled', $violations);
        $this->assertContains('an explicitly allowed staging database name is required', $violations);
        $this->assertContains('external email and push delivery must be disabled', $violations);
    }

    public function test_large_profile_requires_capacity_confirmation(): void
    {
        config(['staging-data.large_profile_capacity_confirmed' => false]);
        $this->assertContains(
            'large profile requires explicit database-capacity confirmation',
            app(StagingDataSafety::class)->violations('large'),
        );
    }
}
