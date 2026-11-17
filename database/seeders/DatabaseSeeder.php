<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Every seeder is idempotent, so this is safe to re-run (deploy step).
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ProductsSeeder::class,
            DemoProductsSeeder::class,
            UsersSeeder::class,
        ]);
    }
}
