<?php

namespace App\Policies;

use App\Models\User;

class ProductionPolicy
{
    /**
     * Determine whether the user can view production history.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can record a production batch.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }
}
