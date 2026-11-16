<?php

namespace App\Policies;

use App\Models\StockDistribution;
use App\Models\StockReturn;
use App\Models\User;

class StockReturnPolicy
{
    /**
     * Determine whether the user can list stock returns.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can return stock from the given distribution.
     *
     * Admins can record a return for anyone; riders only for stock assigned to them.
     */
    public function create(User $user, StockDistribution $distribution): bool
    {
        return $user->isAdmin() || $distribution->rider?->user_id === $user->id;
    }

    /**
     * Determine whether the user can verify a stock return.
     */
    public function verify(User $user, StockReturn $stockReturn): bool
    {
        return $user->isAdmin();
    }
}
