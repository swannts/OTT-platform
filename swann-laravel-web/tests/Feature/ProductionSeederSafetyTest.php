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
