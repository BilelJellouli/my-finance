<?php

namespace App\Policies;

use App\Models\RecurringPlan;
use App\Models\User;

class RecurringPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RecurringPlan $recurringPlan): bool
    {
        return $recurringPlan->ownerEntity->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, RecurringPlan $recurringPlan): bool
    {
        return $recurringPlan->ownerEntity->user_id === $user->id;
    }

    public function delete(User $user, RecurringPlan $recurringPlan): bool
    {
        return $recurringPlan->ownerEntity->user_id === $user->id;
    }
}
