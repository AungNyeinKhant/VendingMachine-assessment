<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Everyone sees their own purchases; transactions.view-all also sees
     * everybody's (that scoping lives in the repository call).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->user_id || $user->can('transactions.view-all');
    }
}
