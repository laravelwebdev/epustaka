<?php

namespace App\Policies;

use App\Models\AutoBorrow;
use App\Models\User;

class AutoBorrowPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AutoBorrow $autoBorrow): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AutoBorrow $autoBorrow): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AutoBorrow $autoBorrow): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can replicate the model.
     */
    public function replicate(User $user, AutoBorrow $autoBorrow): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can run action.
     */
    public function runAction(User $user): bool
    {
        return $user->is_admin;
    }
}