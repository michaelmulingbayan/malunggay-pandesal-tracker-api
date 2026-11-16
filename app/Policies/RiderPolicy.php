<?php

namespace App\Policies;

use App\Models\Rider;
use App\Models\User;

class RiderPolicy
{
    /**
     * Determine whether the user can list riders.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can register a new rider.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update a rider (including activating or deactivating it).
     */
    public function update(User $user, Rider $rider): bool
    {
        return $user->isAdmin();
    }
}
