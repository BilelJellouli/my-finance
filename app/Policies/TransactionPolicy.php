<?php

namespace App\Policies;

use App\Models\PlannedTransaction;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        return $transaction->plannedTransaction->ownerEntity->user_id === $user->id;
    }

    public function create(User $user, PlannedTransaction $plannedTransaction): bool
    {
        return $plannedTransaction->ownerEntity->user_id === $user->id;
    }
}
