<?php

namespace App\Policies;

use App\Models\User;

class PandesalPricePolicy
{
    /**
     * Anyone signed in can see the price (riders need it when selling).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Only admins can change the price.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }
}
