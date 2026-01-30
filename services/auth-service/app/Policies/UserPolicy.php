<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->id === $model->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Admin can update any user, users can update themselves
        return $user->isAdmin() || $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Only admin can delete users, but not themselves
        return $user->isAdmin() && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can manage the profile.
     */
    public function manageProfile(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->isAdmin();
    }

    /**
     * Determine whether the user can change the status.
     */
    public function changeStatus(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can manage sessions.
     */
    public function manageSessions(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->isAdmin();
    }

    /**
     * Determine whether the user can revoke sessions.
     */
    public function revokeSessions(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->isAdmin();
    }

    /**
     * Determine whether the user can manage two-factor authentication.
     */
    public function manageTwoFactor(User $user, User $model): bool
    {
        // Only the user themselves can manage their 2FA
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can view login history.
     */
    public function viewLoginHistory(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->isAdmin();
    }

    /**
     * Determine whether the user can impersonate another user.
     */
    public function impersonate(User $user, User $model): bool
    {
        return $user->isAdmin() && 
               $user->id !== $model->id && 
               !$model->isAdmin();
    }

    /**
     * Determine whether the user can export user data.
     */
    public function export(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can bulk update users.
     */
    public function bulkUpdate(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can assign roles.
     */
    public function assignRole(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }
}

