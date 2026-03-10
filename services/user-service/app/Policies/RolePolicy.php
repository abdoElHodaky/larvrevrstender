<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Role Policy for RBAC Authorization
 * 
 * Defines authorization rules for role management operations
 * using Laravel's native policy system.
 */
class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view') || $user->hasRole(['admin', 'role-manager']);
    }

    /**
     * Determine whether the user can view the role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view') || $user->hasRole(['admin', 'role-manager']);
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can('roles.create') || $user->hasRole(['admin', 'role-manager']);
    }

    /**
     * Determine whether the user can update the role.
     */
    public function update(User $user, Role $role): bool
    {
        // Prevent modification of super admin role
        if ($role->name === 'super-admin') {
            return $user->hasRole('super-admin');
        }

        return $user->can('roles.update') || $user->hasRole(['admin', 'role-manager']);
    }

    /**
     * Determine whether the user can delete the role.
     */
    public function delete(User $user, Role $role): bool
    {
        // Prevent deletion of system roles
        $systemRoles = ['super-admin', 'admin', 'user'];
        if (in_array($role->name, $systemRoles)) {
            return false;
        }

        // Check if role has users assigned
        if ($role->users()->count() > 0) {
            return false; // Cannot delete role with assigned users
        }

        return $user->can('roles.delete') || $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can assign permissions to roles.
     */
    public function assignPermissions(User $user, Role $role): bool
    {
        // Prevent modification of super admin role permissions
        if ($role->name === 'super-admin') {
            return $user->hasRole('super-admin');
        }

        return $user->can('roles.assign-permissions') || $user->hasRole(['admin', 'role-manager']);
    }

    /**
     * Determine whether the user can revoke permissions from roles.
     */
    public function revokePermissions(User $user, Role $role): bool
    {
        // Prevent modification of super admin role permissions
        if ($role->name === 'super-admin') {
            return $user->hasRole('super-admin');
        }

        return $user->can('roles.revoke-permissions') || $user->hasRole(['admin', 'role-manager']);
    }

    /**
     * Determine whether the user can sync permissions for roles.
     */
    public function syncPermissions(User $user, Role $role): bool
    {
        // Prevent modification of super admin role permissions
        if ($role->name === 'super-admin') {
            return $user->hasRole('super-admin');
        }

        return $user->can('roles.sync-permissions') || $user->hasRole(['admin', 'role-manager']);
    }

    /**
     * Determine whether the user can view users assigned to roles.
     */
    public function viewUsers(User $user, Role $role): bool
    {
        return $user->can('roles.view-users') || $user->hasRole(['admin', 'role-manager', 'user-manager']);
    }

    /**
     * Determine whether the user can duplicate roles.
     */
    public function duplicate(User $user, Role $role): bool
    {
        // Cannot duplicate system roles
        $systemRoles = ['super-admin', 'admin'];
        if (in_array($role->name, $systemRoles)) {
            return false;
        }

        return $user->can('roles.create') || $user->hasRole(['admin', 'role-manager']);
    }

    /**
     * Determine whether the user can export roles.
     */
    public function export(User $user): bool
    {
        return $user->can('roles.export') || $user->hasRole(['admin', 'role-manager']);
    }

    /**
     * Determine whether the user can import roles.
     */
    public function import(User $user): bool
    {
        return $user->can('roles.import') || $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can bulk delete roles.
     */
    public function bulkDelete(User $user): bool
    {
        return $user->can('roles.bulk-delete') || $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can manage role hierarchy.
     */
    public function manageHierarchy(User $user): bool
    {
        return $user->can('roles.manage-hierarchy') || $user->hasRole(['admin']);
    }
}
