<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Idempotent: re-running never duplicates roles or permissions.
     */
    public function run(): void
    {
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $manage = Permission::firstOrCreate(['name' => 'products.manage']);
        $purchase = Permission::firstOrCreate(['name' => 'products.purchase']);
        $viewAllTransactions = Permission::firstOrCreate(['name' => 'transactions.view-all']);

        Role::firstOrCreate(['name' => 'admin'])
            ->syncPermissions([$manage, $purchase, $viewAllTransactions]);

        Role::firstOrCreate(['name' => 'user'])
            ->syncPermissions([$purchase]);
    }
}
