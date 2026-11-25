<?php

namespace Database\Seeders;

use Database\Seeders\Auth\PermissionRoleTableSeeder;
use Database\Seeders\Auth\UserTableSeeder;
use Illuminate\Database\Seeder;
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
            throw new LogicException('AuthTableSeeder is a development seeder and may only run in local or testing environments.');
        }

        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        $this->call(PermissionRoleTableSeeder::class);
        $this->call(UserTableSeeder::class);
    }
}
