<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Workflows\RoleManagementWorkflow;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Role Controller with RPC-based RBAC Management
 * 
 * Handles role management operations with Laravel Gates authorization
 * and RPC-based inter-service communication.
 */
class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        $workflow = RoleManagementWorkflow::start([
            'action' => 'list',
            'filters' => $request->only(['search', 'status', 'guard_name']),
            'pagination' => [
                'page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 15)
            ]
        ]);

        return response()->json([
            'message' => 'Roles listing initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Role::class);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'guard_name' => 'sometimes|string|max:255',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $workflow = RoleManagementWorkflow::start([
            'action' => 'create',
            'role_data' => $validator->validated(),
            'permissions' => $request->get('permissions', [])
        ]);

        return response()->json([
            'message' => 'Role creation initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Display the specified role.
     */
    public function show(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('view', $role);

        $workflow = RoleManagementWorkflow::start([
            'action' => 'show',
            'role_id' => $role->id,
            'include_permissions' => $request->boolean('include_permissions', true),
            'include_users' => $request->boolean('include_users', false)
        ]);

        return response()->json([
            'message' => 'Role details retrieval initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('update', $role);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $role->id,
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'guard_name' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $workflow = RoleManagementWorkflow::start([
            'action' => 'update',
            'role_id' => $role->id,
            'updates' => $validator->validated()
        ]);

        return response()->json([
            'message' => 'Role update initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): JsonResponse
    {
        Gate::authorize('delete', $role);

        $workflow = RoleManagementWorkflow::start([
            'action' => 'delete',
            'role_id' => $role->id
        ]);

        return response()->json([
            'message' => 'Role deletion initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Get role permissions.
     */
    public function getPermissions(Role $role): JsonResponse
    {
        Gate::authorize('view', $role);

        $workflow = RoleManagementWorkflow::start([
            'action' => 'getPermissions',
            'role_id' => $role->id
        ]);

        return response()->json([
            'message' => 'Role permissions retrieval initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Assign permissions to role.
     */
    public function assignPermissions(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('assignPermissions', $role);

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $workflow = RoleManagementWorkflow::start([
            'action' => 'assignPermissions',
            'role_id' => $role->id,
            'permissions' => $request->permissions
        ]);

        return response()->json([
            'message' => 'Permission assignment initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Revoke permissions from role.
     */
    public function revokePermissions(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('revokePermissions', $role);

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $workflow = RoleManagementWorkflow::start([
            'action' => 'revokePermissions',
            'role_id' => $role->id,
            'permissions' => $request->permissions
        ]);

        return response()->json([
            'message' => 'Permission revocation initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Sync role permissions (replace all permissions).
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('syncPermissions', $role);

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $workflow = RoleManagementWorkflow::start([
            'action' => 'syncPermissions',
            'role_id' => $role->id,
            'permissions' => $request->permissions
        ]);

        return response()->json([
            'message' => 'Permission synchronization initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Get users assigned to this role.
     */
    public function getUsers(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('viewUsers', $role);

        $workflow = RoleManagementWorkflow::start([
            'action' => 'getUsers',
            'role_id' => $role->id,
            'pagination' => [
                'page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 15)
            ]
        ]);

        return response()->json([
            'message' => 'Role users retrieval initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }
}
