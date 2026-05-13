<?php

namespace App\Policies;

use App\Models\Entity;
use App\Models\User;

class EntityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Entity $entity): bool
    {
        return $entity->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Entity $entity): bool
    {
        return $entity->user_id === $user->id;
    }

    public function delete(User $user, Entity $entity): bool
    {
        return $entity->user_id === $user->id && ! $entity->isPersonal();
    }
}
