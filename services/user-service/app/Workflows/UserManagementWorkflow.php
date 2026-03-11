<?php

namespace App\Workflows;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Workflow\Workflow;
use Workflow\ActivityStub;

class UserManagementWorkflow extends Workflow
{
    /**
     * Main workflow execution method.
     */
    public function execute(array $input)
    {
        $action = $input['action'];

        switch ($action) {
            case 'create':
                return $this->createUserWorkflow($input);
            case 'update':
                return $this->updateUserWorkflow($input);
            case 'delete':
                return $this->deleteUserWorkflow($input);
            case 'assign_permissions':
                return $this->assignPermissionsWorkflow($input);
            case 'revoke_permissions':
                return $this->revokePermissionsWorkflow($input);
            case 'assign_roles':
                return $this->assignRolesWorkflow($input);
            case 'revoke_roles':
                return $this->revokeRolesWorkflow($input);
            default:
                throw new \InvalidArgumentException("Unknown action: {$action}");
        }
    }

    /**
     * User creation workflow with auth-service integration.
     */
    private function createUserWorkflow(array $input)
    {
        $userData = $input['user_data'];
        $roles = $input['roles'] ?? [];
        $createdBy = $input['created_by'];

        // Step 1: Create user in user-service database
        $user = ActivityStub::make(UserActivities::class)->createUser($userData);

        // Step 2: Notify auth-service about new user
        $authResult = ActivityStub::make(AuthServiceActivities::class)->notifyUserCreated([
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'status' => $user->status
        ]);

        // Step 3: Assign roles if provided
        if (!empty($roles)) {
            ActivityStub::make(UserActivities::class)->assignRoles($user->id, $roles);
        }

        // Step 4: Log activity
        ActivityStub::make(ActivityLogActivities::class)->logActivity([
            'log_name' => 'user_management',
            'description' => 'User created',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'causer_id' => $createdBy,
            'properties' => [
                'user_data' => $userData,
                'assigned_roles' => $roles,
                'auth_service_response' => $authResult
            ]
        ]);

        // Step 5: Send welcome notification
        ActivityStub::make(NotificationActivities::class)->sendWelcomeNotification([
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name
        ]);

        return [
            'success' => true,
            'user' => $user,
            'auth_service_notified' => $authResult['success'] ?? false,
            'message' => 'User created successfully'
        ];
    }

    /**
     * User update workflow with auth-service synchronization.
     */
    private function updateUserWorkflow(array $input)
    {
        $userId = $input['user_id'];
        $userData = $input['user_data'];
        $updatedBy = $input['updated_by'];

        // Step 1: Get current user data
        $currentUser = ActivityStub::make(UserActivities::class)->getUser($userId);

        // Step 2: Update user in user-service database
        $updatedUser = ActivityStub::make(UserActivities::class)->updateUser($userId, $userData);

        // Step 3: Sync changes with auth-service
        $authResult = ActivityStub::make(AuthServiceActivities::class)->syncUserUpdate([
            'user_id' => $userId,
            'changes' => $userData,
            'current_data' => $currentUser->toArray(),
            'updated_data' => $updatedUser->toArray()
        ]);

        // Step 4: Log activity
        ActivityStub::make(ActivityLogActivities::class)->logActivity([
            'log_name' => 'user_management',
            'description' => 'User updated',
            'subject_type' => User::class,
            'subject_id' => $userId,
            'causer_id' => $updatedBy,
            'properties' => [
                'changes' => $userData,
                'previous_data' => $currentUser->toArray(),
                'auth_service_response' => $authResult
            ]
        ]);

        return [
            'success' => true,
            'user' => $updatedUser,
            'auth_service_synced' => $authResult['success'] ?? false,
            'message' => 'User updated successfully'
        ];
    }

    /**
     * User deletion workflow with cleanup.
     */
    private function deleteUserWorkflow(array $input)
    {
        $userId = $input['user_id'];
        $deletedBy = $input['deleted_by'];

        // Step 1: Get user data before deletion
        $user = ActivityStub::make(UserActivities::class)->getUser($userId);

        // Step 2: Revoke all tokens in auth-service
        $authResult = ActivityStub::make(AuthServiceActivities::class)->revokeAllUserTokens($userId);

        // Step 3: Clean up user sessions
        ActivityStub::make(AuthServiceActivities::class)->revokeAllUserSessions($userId);

        // Step 4: Log activity before deletion
        ActivityStub::make(ActivityLogActivities::class)->logActivity([
            'log_name' => 'user_management',
            'description' => 'User deleted',
            'subject_type' => User::class,
            'subject_id' => $userId,
            'causer_id' => $deletedBy,
            'properties' => [
                'deleted_user' => $user->toArray(),
                'auth_cleanup' => $authResult
            ]
        ]);

        // Step 5: Soft delete user
        $deletionResult = ActivityStub::make(UserActivities::class)->deleteUser($userId);

        // Step 6: Notify relevant services
        ActivityStub::make(NotificationActivities::class)->notifyUserDeletion([
            'user_id' => $userId,
            'user_email' => $user->email,
            'deleted_by' => $deletedBy
        ]);

        return [
            'success' => true,
            'user_deleted' => $deletionResult,
            'auth_cleanup_completed' => $authResult['success'] ?? false,
            'message' => 'User deleted successfully'
        ];
    }

    /**
     * Permission assignment workflow.
     */
    private function assignPermissionsWorkflow(array $input)
    {
        $userId = $input['user_id'];
        $permissions = $input['permissions'];
        $assignedBy = $input['assigned_by'];

        // Step 1: Validate permissions exist
        $validPermissions = ActivityStub::make(UserActivities::class)->validatePermissions($permissions);

        // Step 2: Assign permissions
        $result = ActivityStub::make(UserActivities::class)->assignPermissions($userId, $validPermissions);

        // Step 3: Sync with auth-service for token refresh
        ActivityStub::make(AuthServiceActivities::class)->invalidateUserTokens($userId, 'permissions_changed');

        // Step 4: Log activity
        ActivityStub::make(ActivityLogActivities::class)->logActivity([
            'log_name' => 'permission_management',
            'description' => 'Permissions assigned to user',
            'subject_type' => User::class,
            'subject_id' => $userId,
            'causer_id' => $assignedBy,
            'properties' => [
                'assigned_permissions' => $permissions,
                'valid_permissions' => $validPermissions
            ]
        ]);

        return [
            'success' => true,
            'assigned_permissions' => $validPermissions,
            'message' => 'Permissions assigned successfully'
        ];
    }

    /**
     * Permission revocation workflow.
     */
    private function revokePermissionsWorkflow(array $input)
    {
        $userId = $input['user_id'];
        $permissions = $input['permissions'];
        $revokedBy = $input['revoked_by'];

        // Step 1: Revoke permissions
        $result = ActivityStub::make(UserActivities::class)->revokePermissions($userId, $permissions);

        // Step 2: Sync with auth-service for token refresh
        ActivityStub::make(AuthServiceActivities::class)->invalidateUserTokens($userId, 'permissions_changed');

        // Step 3: Log activity
        ActivityStub::make(ActivityLogActivities::class)->logActivity([
            'log_name' => 'permission_management',
            'description' => 'Permissions revoked from user',
            'subject_type' => User::class,
            'subject_id' => $userId,
            'causer_id' => $revokedBy,
            'properties' => [
                'revoked_permissions' => $permissions
            ]
        ]);

        return [
            'success' => true,
            'revoked_permissions' => $permissions,
            'message' => 'Permissions revoked successfully'
        ];
    }

    /**
     * Role assignment workflow.
     */
    private function assignRolesWorkflow(array $input)
    {
        $userId = $input['user_id'];
        $roles = $input['roles'];
        $assignedBy = $input['assigned_by'];

        // Step 1: Validate roles exist
        $validRoles = ActivityStub::make(UserActivities::class)->validateRoles($roles);

        // Step 2: Assign roles
        $result = ActivityStub::make(UserActivities::class)->assignRoles($userId, $validRoles);

        // Step 3: Sync with auth-service for token refresh
        ActivityStub::make(AuthServiceActivities::class)->invalidateUserTokens($userId, 'roles_changed');

        // Step 4: Log activity
        ActivityStub::make(ActivityLogActivities::class)->logActivity([
            'log_name' => 'role_management',
            'description' => 'Roles assigned to user',
            'subject_type' => User::class,
            'subject_id' => $userId,
            'causer_id' => $assignedBy,
            'properties' => [
                'assigned_roles' => $roles,
                'valid_roles' => $validRoles
            ]
        ]);

        return [
            'success' => true,
            'assigned_roles' => $validRoles,
            'message' => 'Roles assigned successfully'
        ];
    }

    /**
     * Role revocation workflow.
     */
    private function revokeRolesWorkflow(array $input)
    {
        $userId = $input['user_id'];
        $roles = $input['roles'];
        $revokedBy = $input['revoked_by'];

        // Step 1: Revoke roles
        $result = ActivityStub::make(UserActivities::class)->revokeRoles($userId, $roles);

        // Step 2: Sync with auth-service for token refresh
        ActivityStub::make(AuthServiceActivities::class)->invalidateUserTokens($userId, 'roles_changed');

        // Step 3: Log activity
        ActivityStub::make(ActivityLogActivities::class)->logActivity([
            'log_name' => 'role_management',
            'description' => 'Roles revoked from user',
            'subject_type' => User::class,
            'subject_id' => $userId,
            'causer_id' => $revokedBy,
            'properties' => [
                'revoked_roles' => $roles
            ]
        ]);

        return [
            'success' => true,
            'revoked_roles' => $roles,
            'message' => 'Roles revoked successfully'
        ];
    }
}
