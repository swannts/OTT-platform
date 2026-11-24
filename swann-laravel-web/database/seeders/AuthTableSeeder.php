<?php

namespace Database\Seeders;

use Database\Seeders\Auth\PermissionRoleTableSeeder;
use Database\Seeders\Auth\UserTableSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * Class AuthTableSeeder.
 */
class AuthTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('AuthTableSeeder is a destructive development seeder and cannot run outside local or testing environments.');
        }

        Schema::disableForeignKeyConstraints();

        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        $this->call(PermissionRoleTableSeeder::class);
        $this->call(UserTableSeeder::class);

        Schema::enableForeignKeyConstraints();
    }
}
