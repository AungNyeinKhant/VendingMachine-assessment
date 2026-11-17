<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Demo accounts (idempotent). Role assignment happens here, in the seeder
     * only — application code always checks permissions, never role names.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@vending.test'],
            ['name' => 'Admin', 'password' => Hash::make('password')],
        );
        $admin->assignRole('admin');

        $user = User::firstOrCreate(
            ['email' => 'user@vending.test'],
            ['name' => 'Demo User', 'password' => Hash::make('password')],
        );
        $user->assignRole('user');
    }
}
