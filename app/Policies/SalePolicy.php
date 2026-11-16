<?php

namespace App\Policies;

use App\Models\StockDistribution;
use App\Models\User;

class SalePolicy
{
    /**
     * Determine whether the user can view sales reports.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can record a sale from the given distribution.
     *
     * Admins can record a sale for anyone; riders only from stock assigned to them.
     */
    public function create(User $user, StockDistribution $distribution): bool
    {
        return $user->isAdmin() || $distribution->rider?->user_id === $user->id;
    }
}
