<?php

namespace App\Workflows\Activities;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Workflow\Activity;

class UserActivities extends Activity
{
    /**
     * Create a new user.
     */
    public function createUser(array $userData): User
    {
        return DB::transaction(function () use ($userData) {
            $user = User::create($userData);
            
            // Log the creation
            activity()
                ->performedOn($user)
                ->withProperties(['user_data' => $userData])
                ->log('User created via workflow');
                
            return $user;
        });
    }

    /**
     * Get user by ID.
     */
    public function getUser(int $userId): User
    {
        return User::with(['roles', 'permissions'])->findOrFail($userId);
    }

    /**
     * Update user data.
     */
    public function updateUser(int $userId, array $userData): User
    {
        return DB::transaction(function () use ($userId, $userData) {
            $user = User::findOrFail($userId);
            $originalData = $user->toArray();
            
            $user->update($userData);
            
            // Log the update
            activity()
                ->performedOn($user)
                ->withProperties([
                    'changes' => $userData,
                    'original' => $originalData
                ])
                ->log('User updated via workflow');
                
            return $user->fresh(['roles', 'permissions']);
        });
    }

    /**
     * Soft delete user.
     */
    public function deleteUser(int $userId): bool
    {
        return DB::transaction(function () use ($userId) {
            $user = User::findOrFail($userId);
            
            // Log before deletion
            activity()
                ->performedOn($user)
                ->withProperties(['deleted_user' => $user->toArray()])
                ->log('User deleted via workflow');
                
            return $user->delete();
        });
    }

    /**
     * Validate that permissions exist.
     */
    public function validatePermissions(array $permissionNames): array
    {
        $permissions = Permission::whereIn('name', $permissionNames)->get();
        $validNames = $permissions->pluck('name')->toArray();
        
        $invalidNames = array_diff($permissionNames, $validNames);
        if (!empty($invalidNames)) {
            throw new \InvalidArgumentException(
                'Invalid permissions: ' . implode(', ', $invalidNames)
            );
        }
        
        return $validNames;
    }

    /**
     * Validate that roles exist.
     */
    public function validateRoles(array $roleNames): array
    {
        $roles = Role::whereIn('name', $roleNames)->get();
        $validNames = $roles->pluck('name')->toArray();
        
        $invalidNames = array_diff($roleNames, $validNames);
        if (!empty($invalidNames)) {
            throw new \InvalidArgumentException(
                'Invalid roles: ' . implode(', ', $invalidNames)
            );
        }
        
        return $validNames;
    }

    /**
     * Assign permissions to user.
     */
    public function assignPermissions(int $userId, array $permissionNames): array
    {
        return DB::transaction(function () use ($userId, $permissionNames) {
            $user = User::findOrFail($userId);
            $permissions = Permission::whereIn('name', $permissionNames)->get();
            
            $user->givePermissionTo($permissions);
            
            // Log the assignment
            activity()
                ->performedOn($user)
                ->withProperties(['assigned_permissions' => $permissionNames])
                ->log('Permissions assigned via workflow');
                
            return $permissionNames;
        });
    }

    /**
     * Revoke permissions from user.
     */
    public function revokePermissions(int $userId, array $permissionNames): array
    {
        return DB::transaction(function () use ($userId, $permissionNames) {
            $user = User::findOrFail($userId);
            $permissions = Permission::whereIn('name', $permissionNames)->get();
            
            $user->revokePermissionTo($permissions);
            
            // Log the revocation
            activity()
                ->performedOn($user)
                ->withProperties(['revoked_permissions' => $permissionNames])
                ->log('Permissions revoked via workflow');
                
            return $permissionNames;
        });
    }

    /**
     * Assign roles to user.
     */
    public function assignRoles(int $userId, array $roleNames): array
    {
        return DB::transaction(function () use ($userId, $roleNames) {
            $user = User::findOrFail($userId);
            $roles = Role::whereIn('name', $roleNames)->get();
            
            $user->assignRole($roles);
            
            // Log the assignment
            activity()
                ->performedOn($user)
                ->withProperties(['assigned_roles' => $roleNames])
                ->log('Roles assigned via workflow');
                
            return $roleNames;
        });
    }

    /**
     * Revoke roles from user.
     */
    public function revokeRoles(int $userId, array $roleNames): array
    {
        return DB::transaction(function () use ($userId, $roleNames) {
            $user = User::findOrFail($userId);
            $roles = Role::whereIn('name', $roleNames)->get();
            
            $user->removeRole($roles);
            
            // Log the revocation
            activity()
                ->performedOn($user)
                ->withProperties(['revoked_roles' => $roleNames])
                ->log('Roles revoked via workflow');
                
            return $roleNames;
        });
    }

    /**
     * Get user permissions.
     */
    public function getUserPermissions(int $userId): array
    {
        $user = User::findOrFail($userId);
        
        return [
            'direct_permissions' => $user->permissions->pluck('name')->toArray(),
            'role_permissions' => $user->getPermissionsViaRoles()->pluck('name')->toArray(),
            'all_permissions' => $user->getAllPermissions()->pluck('name')->toArray()
        ];
    }

    /**
     * Get user roles.
     */
    public function getUserRoles(int $userId): array
    {
        $user = User::findOrFail($userId);
        
        return $user->roles->pluck('name')->toArray();
    }

    /**
     * Check if user has permission.
     */
    public function userHasPermission(int $userId, string $permission): bool
    {
        $user = User::findOrFail($userId);
        
        return $user->can($permission);
    }

    /**
     * Check if user has role.
     */
    public function userHasRole(int $userId, string $role): bool
    {
        $user = User::findOrFail($userId);
        
        return $user->hasRole($role);
    }

    /**
     * Update user status.
     */
    public function updateUserStatus(int $userId, string $status): User
    {
        return DB::transaction(function () use ($userId, $status) {
            $user = User::findOrFail($userId);
            $previousStatus = $user->status;
            
            $user->update(['status' => $status]);
            
            // Log the status change
            activity()
                ->performedOn($user)
                ->withProperties([
                    'previous_status' => $previousStatus,
                    'new_status' => $status
                ])
                ->log('User status updated via workflow');
                
            return $user;
        });
    }

    /**
     * Get user activity statistics.
     */
    public function getUserActivityStats(int $userId): array
    {
        $user = User::findOrFail($userId);
        
        return [
            'total_activities' => $user->activities()->count(),
            'recent_activities' => $user->activities()
                ->latest()
                ->limit(10)
                ->get()
                ->toArray(),
            'activities_by_type' => $user->activities()
                ->groupBy('log_name')
                ->selectRaw('log_name, count(*) as count')
                ->pluck('count', 'log_name')
                ->toArray()
        ];
    }
}
