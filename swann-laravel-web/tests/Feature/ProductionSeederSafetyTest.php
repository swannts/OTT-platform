<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthTableSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductionAdminBootstrapSeeder;
use Database\Seeders\Auth\UserTableSeeder;
use Database\Seeders\Auth\PermissionRoleTableSeeder;
use Database\Seeders\LocalDemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class ProductionSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_database_seeder_preserves_uploaded_files_and_skips_sample_accounts(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/keep.txt', 'user media');
        app()->detectEnvironment(static fn (): string => 'production');

        app(DatabaseSeeder::class)->setContainer(app())->run();

        Storage::disk('public')->assertExists('uploads/keep.txt');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('roles', ['name' => 'super-admin']);
    }

    public function test_local_database_seeder_only_reconciles_rbac_and_optional_admin(): void
    {
        config(['app.demo_seed_password' => null]);
        $user = User::factory()->create(['email' => 'keep-existing@example.test']);
        $genreId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('genres')->insert([
            'id' => $genreId,
            'name' => 'Existing catalog row',
            'slug' => 'existing-catalog-row',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(DatabaseSeeder::class)->setContainer(app())->run();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $user->getKey(), 'email' => 'keep-existing@example.test']);
        $this->assertDatabaseHas('genres', ['id' => $genreId, 'slug' => 'existing-catalog-row']);
        $this->assertDatabaseMissing('users', ['email' => 'demo@swann.com']);
        $this->assertDatabaseHas('roles', ['name' => 'super-admin', 'guard_name' => 'web']);
    }

    public function test_sample_and_reset_seeders_refuse_to_run_in_production(): void
    {
        app()->detectEnvironment(static fn (): string => 'production');

        $seeders = [
            \Database\Seeders\SettingSeeder::class,
            \Database\Seeders\UserProfilesSeeder::class,
            \Database\Seeders\UserWatchHistoriesSeeder::class,
            \Database\Seeders\UserSearchHistoriesSeeder::class,
            \Database\Seeders\ClipsTableSeeder::class,
            \Database\Seeders\IslamicContentSeeder::class,
            \Database\Seeders\BangladeshIslamicContentSeeder::class,
            \Database\Seeders\BlogSeeder::class,
            \Database\Seeders\MobileSettingsTableSeeder::class,
            \Database\Seeders\StaffRolesSeeder::class,
            \Modules\Ad\database\seeders\CustomAdsSettingTableSeeder::class,
            \Modules\Ad\database\seeders\VastAdsSettingTableSeeder::class,
            \Modules\Banner\database\seeders\BannerDatabaseSeeder::class,
            \Modules\CastCrew\database\seeders\CastCrewDatabaseSeeder::class,
            \Modules\Entertainment\database\seeders\EntertainmentDatabaseSeeder::class,
            \Modules\Entertainment\database\seeders\ReviewDatabaseSeeder::class,
            \Modules\Entertainment\database\seeders\ContinueWatchTableSeeder::class,
            \Modules\Entertainment\database\seeders\EntertainmentViewsTableSeeder::class,
            \Modules\Entertainment\database\seeders\LikesTableSeeder::class,
            \Modules\Entertainment\database\seeders\WatchlistDatabaseSeeder::class,
            \Modules\Episode\database\seeders\EpisodeDatabaseSeeder::class,
            \Modules\Genres\database\seeders\GenresDatabaseSeeder::class,
            \Modules\Onboarding\database\seeders\OnboardingDatabaseSeeder::class,
            \Modules\Season\database\seeders\SeasonDatabaseSeeder::class,
            \Modules\Video\database\seeders\VideoDatabaseSeeder::class,
            \Modules\LiveTV\database\seeders\LiveTvCategoryTableSeeder::class,
            \Modules\LiveTV\database\seeders\LiveTvChannelTableSeeder::class,
            \Modules\Subscriptions\database\seeders\PayPerViewSeeder::class,
            \Modules\Subscriptions\database\seeders\PlanTableSeeder::class,
            \Modules\Subscriptions\database\seeders\PlanlimitationTableSeeder::class,
            \Modules\Subscriptions\database\seeders\PlanlimitationMappingTableSeeder::class,
            \Modules\Subscriptions\database\seeders\SubscriptionsTableSeeder::class,
            \Modules\Subscriptions\database\seeders\SubscriptionsTransactionsTableSeeder::class,
            \Modules\Tax\database\seeders\TaxDatabaseSeeder::class,
            \Modules\Currency\database\seeders\CurrencyDatabaseSeeder::class,
            \Modules\FAQ\database\seeders\FAQDatabaseSeeder::class,
            \Modules\Page\database\seeders\PageDatabaseSeeder::class,
            \Modules\NotificationTemplate\database\seeders\NotificationTemplateSeeder::class,
            \Modules\World\database\seeders\CountriesTableSeeder::class,
            \Modules\World\database\seeders\StatesTableSeeder::class,
            \Modules\World\database\seeders\CitiesTableSeeder::class,
            \Modules\Coupon\database\seeders\CouponDatabaseSeeder::class,
            \Modules\Language\database\seeders\LanguageDatabaseSeeder::class,
        ];

        foreach ($seeders as $seederClass) {
            try {
                app($seederClass)->run();
                $this->fail("{$seederClass} should reject production execution.");
            } catch (LogicException) {
                $this->assertTrue(true);
            }
        }

        try {
            app(LocalDemoCatalogSeeder::class)->run();
            $this->fail('LocalDemoCatalogSeeder should reject production execution.');
        } catch (\RuntimeException) {
            $this->assertTrue(true);
        }
    }

    public function test_production_refuses_legacy_permission_truncating_seeders(): void
    {
        Permission::query()->firstOrCreate(['name' => 'preserve-me', 'guard_name' => 'web']);
        app()->detectEnvironment(static fn (): string => 'production');

        foreach ([AuthTableSeeder::class, PermissionRoleTableSeeder::class] as $seederClass) {
            try {
                app($seederClass)->run();
                $this->fail('The legacy permission seeder should be blocked in production.');
            } catch (LogicException) {
                $this->assertDatabaseHas('permissions', ['name' => 'preserve-me']);
            }
        }
    }

    public function test_sample_users_are_not_seeded_without_a_private_demo_password(): void
    {
        config(['app.demo_seed_password' => null]);

        app(UserTableSeeder::class)->run();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_bootstrap_seeder_creates_a_super_admin_once_without_overwriting_existing_passwords(): void
    {
        app()->detectEnvironment(static fn (): string => 'production');
        config([
            'app.admin_bootstrap.email' => 'bootstrap@example.test',
            'app.admin_bootstrap.password' => 'A-long-one-time-secret-2026',
            'app.admin_bootstrap.name' => 'Initial Admin',
        ]);

        $seeder = app(ProductionAdminBootstrapSeeder::class)->setContainer(app());
        $seeder->run();

        $user = User::query()->where('email', 'bootstrap@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('A-long-one-time-secret-2026', $user->password));
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => Role::query()->where('name', 'super-admin')->where('guard_name', 'web')->value('id'),
            'model_id' => $user->getKey(),
            'model_type' => User::class,
        ]);
        $this->assertTrue($user->roles()->where('roles.name', 'super-admin')->where('roles.guard_name', 'web')->exists());

        $replacementPassword = Hash::make('A-different-password-after-bootstrap');
        $user->forceFill(['password' => $replacementPassword])->save();
        $seeder->run();

        $this->assertSame($replacementPassword, $user->fresh()->password);
        $this->assertDatabaseMissing('users', ['email' => 'admin@swann.com']);
    }
}
