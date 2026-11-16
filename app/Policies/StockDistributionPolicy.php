<?php

namespace App\Policies;

use App\Models\User;

class StockDistributionPolicy
{
    /**
     * Determine whether the user can view stock distributions.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can assign stock to riders.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }
}
