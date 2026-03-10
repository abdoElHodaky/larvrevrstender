<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Permission;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Permission Policy for RBAC Authorization
 * 
 * Defines authorization rules for permission management operations
 * using Laravel's native policy system.
 */
class PermissionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any permissions.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('permissions.view') || $user->hasRole(['admin', 'permission-manager']);
    }

    /**
     * Determine whether the user can view the permission.
     */
    public function view(User $user, Permission $permission): bool
    {
        return $user->can('permissions.view') || $user->hasRole(['admin', 'permission-manager']);
    }

    /**
     * Determine whether the user can create permissions.
     */
    public function create(User $user): bool
    {
        return $user->can('permissions.create') || $user->hasRole(['admin', 'permission-manager']);
    }

    /**
     * Determine whether the user can update the permission.
     */
    public function update(User $user, Permission $permission): bool
    {
        // Prevent modification of system permissions
        $systemPermissions = [
            'super-admin.*',
            'admin.*',
            'system.*'
        ];

        foreach ($systemPermissions as $systemPerm) {
            if (fnmatch($systemPerm, $permission->name)) {
                return $user->hasRole('super-admin');
            }
        }

        return $user->can('permissions.update') || $user->hasRole(['admin', 'permission-manager']);
    }

    /**
     * Determine whether the user can delete the permission.
     */
    public function delete(User $user, Permission $permission): bool
    {
        // Prevent deletion of system permissions
        $systemPermissions = [
            'super-admin.*',
            'admin.*',
            'system.*',
            'users.view',
            'users.create',
            'users.update',
            'users.delete'
        ];

        foreach ($systemPermissions as $systemPerm) {
            if (fnmatch($systemPerm, $permission->name)) {
                return false; // System permissions cannot be deleted
            }
        }

        // Check if permission is assigned to any roles
        if ($permission->roles()->count() > 0) {
            return false; // Cannot delete permission assigned to roles
        }

        return $user->can('permissions.delete') || $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can assign permission to roles.
     */
    public function assignToRoles(User $user, Permission $permission): bool
    {
        return $user->can('permissions.assign-to-roles') || $user->hasRole(['admin', 'permission-manager']);
    }

    /**
     * Determine whether the user can revoke permission from roles.
     */
    public function revokeFromRoles(User $user, Permission $permission): bool
    {
        // Prevent revoking system permissions from admin roles
        $systemPermissions = ['super-admin.*', 'admin.*'];
        foreach ($systemPermissions as $systemPerm) {
            if (fnmatch($systemPerm, $permission->name)) {
                return $user->hasRole('super-admin');
            }
        }

        return $user->can('permissions.revoke-from-roles') || $user->hasRole(['admin', 'permission-manager']);
    }

    /**
     * Determine whether the user can view users who have this permission.
     */
    public function viewUsers(User $user, Permission $permission): bool
    {
        return $user->can('permissions.view-users') || $user->hasRole(['admin', 'permission-manager', 'user-manager']);
    }

    /**
     * Determine whether the user can bulk create permissions.
     */
    public function bulkCreate(User $user): bool
    {
        return $user->can('permissions.bulk-create') || $user->hasRole(['admin', 'permission-manager']);
    }

    /**
     * Determine whether the user can view permission categories.
     */
    public function viewCategories(User $user): bool
    {
        return $user->can('permissions.view-categories') || $user->hasRole(['admin', 'permission-manager']);
    }

    /**
     * Determine whether the user can export permissions.
     */
    public function export(User $user): bool
    {
        return $user->can('permissions.export') || $user->hasRole(['admin', 'permission-manager']);
    }

    /**
     * Determine whether the user can import permissions.
     */
    public function import(User $user): bool
    {
        return $user->can('permissions.import') || $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can bulk delete permissions.
     */
    public function bulkDelete(User $user): bool
    {
        return $user->can('permissions.bulk-delete') || $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can manage permission categories.
     */
    public function manageCategories(User $user): bool
    {
        return $user->can('permissions.manage-categories') || $user->hasRole(['admin', 'permission-manager']);
    }

    /**
     * Determine whether the user can duplicate permissions.
     */
    public function duplicate(User $user, Permission $permission): bool
    {
        // Cannot duplicate system permissions
        $systemPermissions = ['super-admin.*', 'admin.*', 'system.*'];
        foreach ($systemPermissions as $systemPerm) {
            if (fnmatch($systemPerm, $permission->name)) {
                return false;
            }
        }

        return $user->can('permissions.create') || $user->hasRole(['admin', 'permission-manager']);
    }
}
