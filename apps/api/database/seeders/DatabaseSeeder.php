<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->call(ProductionAdminBootstrapSeeder::class);

            return;
        }

        // Local seeding initializes RBAC and only creates the explicitly configured
        // role-account fixtures. Catalog and business demo data stay opt-in.
        $this->call(LocalAdminRoleAccountsSeeder::class);
    }
}
