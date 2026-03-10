<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view') || $user->hasRole(['admin', 'user-manager']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        return $user->can('users.view') || $user->hasRole(['admin', 'user-manager']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create') || $user->hasRole(['admin', 'user-manager']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile (limited fields)
        if ($user->id === $model->id) {
            return true;
        }

        return $user->can('users.update') || $user->hasRole(['admin', 'user-manager']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Prevent self-deletion
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('users.delete') || $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->can('users.restore') || $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->can('users.force-delete') || $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can assign permissions to other users.
     */
    public function assignPermissions(User $user, User $model): bool
    {
        // Prevent self-permission assignment
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('users.assign-permissions') || $user->hasRole(['admin', 'permission-manager']);
    }

    /**
     * Determine whether the user can revoke permissions from other users.
     */
    public function revokePermissions(User $user, User $model): bool
    {
        // Prevent self-permission revocation
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('users.revoke-permissions') || $user->hasRole(['admin', 'permission-manager']);
    }

    /**
     * Determine whether the user can assign roles to other users.
     */
    public function assignRoles(User $user, User $model): bool
    {
        // Prevent self-role assignment
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('users.assign-roles') || $user->hasRole(['admin', 'role-manager']);
    }

    /**
     * Determine whether the user can revoke roles from other users.
     */
    public function revokeRoles(User $user, User $model): bool
    {
        // Prevent self-role revocation
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('users.revoke-roles') || $user->hasRole(['admin', 'role-manager']);
    }

    /**
     * Determine whether the user can manage user status (activate/deactivate/suspend).
     */
    public function manageStatus(User $user, User $model): bool
    {
        // Prevent self-status management
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('users.manage-status') || $user->hasRole(['admin', 'user-manager']);
    }

    /**
     * Determine whether the user can view user activities.
     */
    public function viewActivities(User $user, User $model): bool
    {
        // Users can view their own activities
        if ($user->id === $model->id) {
            return true;
        }

        return $user->can('users.view-activities') || $user->hasRole(['admin', 'auditor']);
    }

    /**
     * Determine whether the user can impersonate other users.
     */
    public function impersonate(User $user, User $model): bool
    {
        // Prevent self-impersonation
        if ($user->id === $model->id) {
            return false;
        }

        // Only super admins can impersonate
        return $user->can('users.impersonate') || $user->hasRole(['super-admin']);
    }
}
