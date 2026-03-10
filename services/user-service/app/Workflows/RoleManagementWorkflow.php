<?php

namespace App\Workflows;

use App\Workflows\Activities\RoleActivities;
use App\Workflows\Activities\AuthServiceActivities;
use Workflow\Workflow;
use Workflow\ActivityStub;

/**
 * Role Management Workflow with RPC Integration
 * 
 * Orchestrates role management operations with auth-service synchronization
 * using RPC-based inter-service communication.
 */
class RoleManagementWorkflow extends Workflow
{
    /**
     * Main workflow execution method.
     */
    public function execute(array $input)
    {
        $action = $input['action'] ?? 'unknown';

        return match ($action) {
            'list' => $this->listRolesWorkflow($input),
            'create' => $this->createRoleWorkflow($input),
            'show' => $this->showRoleWorkflow($input),
            'update' => $this->updateRoleWorkflow($input),
            'delete' => $this->deleteRoleWorkflow($input),
            'getPermissions' => $this->getRolePermissionsWorkflow($input),
            'assignPermissions' => $this->assignPermissionsWorkflow($input),
            'revokePermissions' => $this->revokePermissionsWorkflow($input),
            'syncPermissions' => $this->syncPermissionsWorkflow($input),
            'getUsers' => $this->getRoleUsersWorkflow($input),
            default => throw new \InvalidArgumentException("Unknown action: {$action}")
        };
    }

    /**
     * List roles workflow.
     */
    private function listRolesWorkflow(array $input): array
    {
        // Step 1: Get roles from database
        $roles = ActivityStub::make(RoleActivities::class)->listRoles(
            $input['filters'] ?? [],
            $input['pagination'] ?? []
        );

        // Step 2: Log activity
        ActivityStub::make(RoleActivities::class)->logActivity([
            'action' => 'roles.listed',
            'description' => 'Roles listing accessed',
            'properties' => [
                'filters' => $input['filters'] ?? [],
                'total_roles' => count($roles['data'] ?? [])
            ]
        ]);

        return $roles;
    }

    /**
     * Create role workflow.
     */
    private function createRoleWorkflow(array $input): array
    {
        $roleData = $input['role_data'];
        $permissions = $input['permissions'] ?? [];

        // Step 1: Create role in database
        $role = ActivityStub::make(RoleActivities::class)->createRole($roleData);

        // Step 2: Assign permissions if provided
        if (!empty($permissions)) {
            ActivityStub::make(RoleActivities::class)->assignPermissionsToRole(
                $role['id'],
                $permissions
            );
        }

        // Step 3: Notify auth-service about role creation
        $authResult = ActivityStub::make(AuthServiceActivities::class)->notifyRoleCreated([
            'role_id' => $role['id'],
            'name' => $role['name'],
            'display_name' => $role['display_name'],
            'permissions' => $permissions
        ]);

        // Step 4: Log activity
        ActivityStub::make(RoleActivities::class)->logActivity([
            'action' => 'role.created',
            'description' => "Role '{$role['name']}' created",
            'subject_type' => 'Role',
            'subject_id' => $role['id'],
            'properties' => [
                'role_name' => $role['name'],
                'permissions_assigned' => count($permissions),
                'auth_service_notified' => $authResult['success'] ?? false
            ]
        ]);

        return [
            'role' => $role,
            'permissions_assigned' => count($permissions),
            'auth_service_sync' => $authResult
        ];
    }

    /**
     * Show role workflow.
     */
    private function showRoleWorkflow(array $input): array
    {
        $roleId = $input['role_id'];
        $includePermissions = $input['include_permissions'] ?? true;
        $includeUsers = $input['include_users'] ?? false;

        // Step 1: Get role details
        $role = ActivityStub::make(RoleActivities::class)->getRole($roleId);

        // Step 2: Get permissions if requested
        if ($includePermissions) {
            $role['permissions'] = ActivityStub::make(RoleActivities::class)
                ->getRolePermissions($roleId);
        }

        // Step 3: Get users if requested
        if ($includeUsers) {
            $role['users'] = ActivityStub::make(RoleActivities::class)
                ->getRoleUsers($roleId);
        }

        // Step 4: Log activity
        ActivityStub::make(RoleActivities::class)->logActivity([
            'action' => 'role.viewed',
            'description' => "Role '{$role['name']}' viewed",
            'subject_type' => 'Role',
            'subject_id' => $roleId,
            'properties' => [
                'include_permissions' => $includePermissions,
                'include_users' => $includeUsers
            ]
        ]);

        return $role;
    }

    /**
     * Update role workflow.
     */
    private function updateRoleWorkflow(array $input): array
    {
        $roleId = $input['role_id'];
        $updates = $input['updates'];

        // Step 1: Get current role data
        $currentRole = ActivityStub::make(RoleActivities::class)->getRole($roleId);

        // Step 2: Update role in database
        $updatedRole = ActivityStub::make(RoleActivities::class)->updateRole($roleId, $updates);

        // Step 3: Sync changes with auth-service
        $authResult = ActivityStub::make(AuthServiceActivities::class)->syncRoleUpdate([
            'role_id' => $roleId,
            'changes' => $updates,
            'old_data' => $currentRole,
            'new_data' => $updatedRole
        ]);

        // Step 4: Log activity
        ActivityStub::make(RoleActivities::class)->logActivity([
            'action' => 'role.updated',
            'description' => "Role '{$updatedRole['name']}' updated",
            'subject_type' => 'Role',
            'subject_id' => $roleId,
            'properties' => [
                'changes' => $updates,
                'auth_service_synced' => $authResult['success'] ?? false
            ]
        ]);

        return [
            'role' => $updatedRole,
            'changes' => $updates,
            'auth_service_sync' => $authResult
        ];
    }

    /**
     * Delete role workflow.
     */
    private function deleteRoleWorkflow(array $input): array
    {
        $roleId = $input['role_id'];

        // Step 1: Get role data before deletion
        $role = ActivityStub::make(RoleActivities::class)->getRole($roleId);

        // Step 2: Check if role can be deleted
        $canDelete = ActivityStub::make(RoleActivities::class)->canDeleteRole($roleId);
        if (!$canDelete['allowed']) {
            throw new \Exception($canDelete['reason']);
        }

        // Step 3: Revoke all permissions from role
        ActivityStub::make(RoleActivities::class)->revokeAllPermissionsFromRole($roleId);

        // Step 4: Notify auth-service about role deletion
        $authResult = ActivityStub::make(AuthServiceActivities::class)->notifyRoleDeleted([
            'role_id' => $roleId,
            'role_name' => $role['name']
        ]);

        // Step 5: Delete role from database
        $deleted = ActivityStub::make(RoleActivities::class)->deleteRole($roleId);

        // Step 6: Log activity
        ActivityStub::make(RoleActivities::class)->logActivity([
            'action' => 'role.deleted',
            'description' => "Role '{$role['name']}' deleted",
            'subject_type' => 'Role',
            'subject_id' => $roleId,
            'properties' => [
                'role_name' => $role['name'],
                'auth_service_notified' => $authResult['success'] ?? false
            ]
        ]);

        return [
            'deleted' => $deleted,
            'role_name' => $role['name'],
            'auth_service_sync' => $authResult
        ];
    }

    /**
     * Get role permissions workflow.
     */
    private function getRolePermissionsWorkflow(array $input): array
    {
        $roleId = $input['role_id'];

        // Step 1: Get role permissions
        $permissions = ActivityStub::make(RoleActivities::class)->getRolePermissions($roleId);

        // Step 2: Log activity
        ActivityStub::make(RoleActivities::class)->logActivity([
            'action' => 'role.permissions.viewed',
            'description' => 'Role permissions viewed',
            'subject_type' => 'Role',
            'subject_id' => $roleId,
            'properties' => [
                'permissions_count' => count($permissions)
            ]
        ]);

        return $permissions;
    }

    /**
     * Assign permissions to role workflow.
     */
    private function assignPermissionsWorkflow(array $input): array
    {
        $roleId = $input['role_id'];
        $permissions = $input['permissions'];

        // Step 1: Assign permissions to role
        $assigned = ActivityStub::make(RoleActivities::class)->assignPermissionsToRole(
            $roleId,
            $permissions
        );

        // Step 2: Invalidate tokens for users with this role
        $usersWithRole = ActivityStub::make(RoleActivities::class)->getRoleUsers($roleId);
        foreach ($usersWithRole as $user) {
            ActivityStub::make(AuthServiceActivities::class)->invalidateUserTokens(
                $user['id'],
                'role_permissions_changed'
            );
        }

        // Step 3: Notify auth-service about permission changes
        $authResult = ActivityStub::make(AuthServiceActivities::class)->syncRolePermissions([
            'role_id' => $roleId,
            'action' => 'assign',
            'permissions' => $permissions
        ]);

        // Step 4: Log activity
        ActivityStub::make(RoleActivities::class)->logActivity([
            'action' => 'role.permissions.assigned',
            'description' => 'Permissions assigned to role',
            'subject_type' => 'Role',
            'subject_id' => $roleId,
            'properties' => [
                'assigned_permissions' => $permissions,
                'affected_users' => count($usersWithRole),
                'auth_service_synced' => $authResult['success'] ?? false
            ]
        ]);

        return [
            'assigned_permissions' => $assigned,
            'affected_users' => count($usersWithRole),
            'auth_service_sync' => $authResult
        ];
    }

    /**
     * Revoke permissions from role workflow.
     */
    private function revokePermissionsWorkflow(array $input): array
    {
        $roleId = $input['role_id'];
        $permissions = $input['permissions'];

        // Step 1: Revoke permissions from role
        $revoked = ActivityStub::make(RoleActivities::class)->revokePermissionsFromRole(
            $roleId,
            $permissions
        );

        // Step 2: Invalidate tokens for users with this role
        $usersWithRole = ActivityStub::make(RoleActivities::class)->getRoleUsers($roleId);
        foreach ($usersWithRole as $user) {
            ActivityStub::make(AuthServiceActivities::class)->invalidateUserTokens(
                $user['id'],
                'role_permissions_changed'
            );
        }

        // Step 3: Notify auth-service about permission changes
        $authResult = ActivityStub::make(AuthServiceActivities::class)->syncRolePermissions([
            'role_id' => $roleId,
            'action' => 'revoke',
            'permissions' => $permissions
        ]);

        // Step 4: Log activity
        ActivityStub::make(RoleActivities::class)->logActivity([
            'action' => 'role.permissions.revoked',
            'description' => 'Permissions revoked from role',
            'subject_type' => 'Role',
            'subject_id' => $roleId,
            'properties' => [
                'revoked_permissions' => $permissions,
                'affected_users' => count($usersWithRole),
                'auth_service_synced' => $authResult['success'] ?? false
            ]
        ]);

        return [
            'revoked_permissions' => $revoked,
            'affected_users' => count($usersWithRole),
            'auth_service_sync' => $authResult
        ];
    }

    /**
     * Sync role permissions workflow.
     */
    private function syncPermissionsWorkflow(array $input): array
    {
        $roleId = $input['role_id'];
        $permissions = $input['permissions'];

        // Step 1: Get current permissions
        $currentPermissions = ActivityStub::make(RoleActivities::class)->getRolePermissions($roleId);

        // Step 2: Sync permissions (replace all)
        $synced = ActivityStub::make(RoleActivities::class)->syncRolePermissions(
            $roleId,
            $permissions
        );

        // Step 3: Invalidate tokens for users with this role
        $usersWithRole = ActivityStub::make(RoleActivities::class)->getRoleUsers($roleId);
        foreach ($usersWithRole as $user) {
            ActivityStub::make(AuthServiceActivities::class)->invalidateUserTokens(
                $user['id'],
                'role_permissions_synced'
            );
        }

        // Step 4: Notify auth-service about permission sync
        $authResult = ActivityStub::make(AuthServiceActivities::class)->syncRolePermissions([
            'role_id' => $roleId,
            'action' => 'sync',
            'permissions' => $permissions,
            'previous_permissions' => array_column($currentPermissions, 'name')
        ]);

        // Step 5: Log activity
        ActivityStub::make(RoleActivities::class)->logActivity([
            'action' => 'role.permissions.synced',
            'description' => 'Role permissions synchronized',
            'subject_type' => 'Role',
            'subject_id' => $roleId,
            'properties' => [
                'new_permissions' => $permissions,
                'previous_permissions' => array_column($currentPermissions, 'name'),
                'affected_users' => count($usersWithRole),
                'auth_service_synced' => $authResult['success'] ?? false
            ]
        ]);

        return [
            'synced_permissions' => $synced,
            'affected_users' => count($usersWithRole),
            'auth_service_sync' => $authResult
        ];
    }

    /**
     * Get role users workflow.
     */
    private function getRoleUsersWorkflow(array $input): array
    {
        $roleId = $input['role_id'];
        $pagination = $input['pagination'] ?? [];

        // Step 1: Get users assigned to role
        $users = ActivityStub::make(RoleActivities::class)->getRoleUsers($roleId, $pagination);

        // Step 2: Log activity
        ActivityStub::make(RoleActivities::class)->logActivity([
            'action' => 'role.users.viewed',
            'description' => 'Role users viewed',
            'subject_type' => 'Role',
            'subject_id' => $roleId,
            'properties' => [
                'users_count' => count($users['data'] ?? [])
            ]
        ]);

        return $users;
    }
}
