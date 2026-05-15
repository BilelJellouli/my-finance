<?php

namespace App\Policies;

use App\Models\PlannedTransaction;
use App\Models\User;

class PlannedTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PlannedTransaction $plannedTransaction): bool
    {
        return $plannedTransaction->ownerEntity->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PlannedTransaction $plannedTransaction): bool
    {
        return $plannedTransaction->ownerEntity->user_id === $user->id;
    }

    public function delete(User $user, PlannedTransaction $plannedTransaction): bool
    {
        return $plannedTransaction->ownerEntity->user_id === $user->id;
    }
}
