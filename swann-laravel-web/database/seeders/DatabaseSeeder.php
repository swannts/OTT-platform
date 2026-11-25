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

        // The default seeding command must never replace local user, catalog, or
        // business data with sample fixtures. Run a specific development seeder
        // explicitly when sample data is needed.
        $this->call(RbacFoundationSeeder::class);
        $this->call(LocalAdminBootstrapSeeder::class);
    }
}
