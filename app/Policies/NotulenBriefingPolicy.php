<?php

namespace App\Policies;

use App\Models\NotulenBriefing;
use App\Models\User;

class NotulenBriefingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view the list
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, NotulenBriefing $notulenBriefing): bool
    {
        // Admin can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Tim can only view their own notulen
        return $user->tim_id === $notulenBriefing->tim_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only tim role can create notulen
        return $user->isTim();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, NotulenBriefing $notulenBriefing): bool
    {
        // Admin can update any
        if ($user->isAdmin()) {
            return true;
        }

        // Tim can only update their own notulen (created by them)
        return $user->id === $notulenBriefing->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, NotulenBriefing $notulenBriefing): bool
    {
        // Only admin can delete notulen
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can download the file.
     */
    public function download(User $user, NotulenBriefing $notulenBriefing): bool
    {
        // Same rule as view
        return $this->view($user, $notulenBriefing);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, NotulenBriefing $notulenBriefing): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, NotulenBriefing $notulenBriefing): bool
    {
        return $user->isAdmin();
    }
}
