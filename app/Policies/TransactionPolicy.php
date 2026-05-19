<?php

namespace App\Policies;

use App\Models\PlannedTransaction;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $this->ownsTransaction($user, $transaction);
    }

    /**
     * Used by the controller for standalone creation. The planned-link overload below
     * stays available for the legacy "record against planned" code path.
     */
    public function create(User $user, ?PlannedTransaction $plannedTransaction = null): bool
    {
        if ($plannedTransaction === null) {
            return true;
        }

        return $plannedTransaction->ownerEntity->user_id === $user->id;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $this->ownsTransaction($user, $transaction);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->ownsTransaction($user, $transaction);
    }

    private function ownsTransaction(User $user, Transaction $transaction): bool
    {
        if ($transaction->plannedTransaction?->ownerEntity?->user_id === $user->id) {
            return true;
        }
        if ($transaction->fromAccount?->entity?->user_id === $user->id) {
            return true;
        }
        if ($transaction->toAccount?->entity?->user_id === $user->id) {
            return true;
        }

        return false;
    }
}
