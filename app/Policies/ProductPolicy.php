<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Authorization checks spatie permissions, never role names — a role is just
 * a bag of permissions, and adding a role later requires no code change.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // any authenticated user can list products
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('products.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('products.manage');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('products.manage');
    }

    public function purchase(User $user, Product $product): bool
    {
        return $user->can('products.purchase');
    }
}
