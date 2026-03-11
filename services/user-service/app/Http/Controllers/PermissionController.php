<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Workflows\PermissionManagementWorkflow;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Permission Controller with RPC-based RBAC Management
 * 
 * Handles permission management operations with Laravel Gates authorization
 * and RPC-based inter-service communication.
 */
class PermissionController extends Controller
{
    /**
     * Display a listing of permissions.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Permission::class);

        $workflow = PermissionManagementWorkflow::start([
            'action' => 'list',
            'filters' => $request->only(['search', 'guard_name', 'category']),
            'pagination' => [
                'page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 15)
            ]
        ]);

        return response()->json([
            'message' => 'Permissions listing initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Permission::class);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'guard_name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
            'roles' => 'sometimes|array',
            'roles.*' => 'string|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $workflow = PermissionManagementWorkflow::start([
            'action' => 'create',
            'permission_data' => $validator->validated(),
            'roles' => $request->get('roles', [])
        ]);

        return response()->json([
            'message' => 'Permission creation initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Display the specified permission.
     */
    public function show(Request $request, Permission $permission): JsonResponse
    {
        Gate::authorize('view', $permission);

        $workflow = PermissionManagementWorkflow::start([
            'action' => 'show',
            'permission_id' => $permission->id,
            'include_roles' => $request->boolean('include_roles', true),
            'include_users' => $request->boolean('include_users', false)
        ]);

        return response()->json([
            'message' => 'Permission details retrieval initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        Gate::authorize('update', $permission);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:permissions,name,' . $permission->id,
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'guard_name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $workflow = PermissionManagementWorkflow::start([
            'action' => 'update',
            'permission_id' => $permission->id,
            'updates' => $validator->validated()
        ]);

        return response()->json([
            'message' => 'Permission update initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Remove the specified permission.
     */
    public function destroy(Permission $permission): JsonResponse
    {
        Gate::authorize('delete', $permission);

        $workflow = PermissionManagementWorkflow::start([
            'action' => 'delete',
            'permission_id' => $permission->id
        ]);

        return response()->json([
            'message' => 'Permission deletion initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Get roles that have this permission.
     */
    public function getRoles(Permission $permission): JsonResponse
    {
        Gate::authorize('view', $permission);

        $workflow = PermissionManagementWorkflow::start([
            'action' => 'getRoles',
            'permission_id' => $permission->id
        ]);

        return response()->json([
            'message' => 'Permission roles retrieval initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Assign permission to roles.
     */
    public function assignToRoles(Request $request, Permission $permission): JsonResponse
    {
        Gate::authorize('assignToRoles', $permission);

        $validator = Validator::make($request->all(), [
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $workflow = PermissionManagementWorkflow::start([
            'action' => 'assignToRoles',
            'permission_id' => $permission->id,
            'roles' => $request->roles
        ]);

        return response()->json([
            'message' => 'Permission assignment to roles initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Revoke permission from roles.
     */
    public function revokeFromRoles(Request $request, Permission $permission): JsonResponse
    {
        Gate::authorize('revokeFromRoles', $permission);

        $validator = Validator::make($request->all(), [
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $workflow = PermissionManagementWorkflow::start([
            'action' => 'revokeFromRoles',
            'permission_id' => $permission->id,
            'roles' => $request->roles
        ]);

        return response()->json([
            'message' => 'Permission revocation from roles initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Get users who have this permission (directly or through roles).
     */
    public function getUsers(Request $request, Permission $permission): JsonResponse
    {
        Gate::authorize('viewUsers', $permission);

        $workflow = PermissionManagementWorkflow::start([
            'action' => 'getUsers',
            'permission_id' => $permission->id,
            'include_via_roles' => $request->boolean('include_via_roles', true),
            'pagination' => [
                'page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 15)
            ]
        ]);

        return response()->json([
            'message' => 'Permission users retrieval initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Bulk create permissions.
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        Gate::authorize('bulkCreate', Permission::class);

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array|min:1',
            'permissions.*.name' => 'required|string|max:255|distinct',
            'permissions.*.display_name' => 'required|string|max:255',
            'permissions.*.description' => 'nullable|string|max:1000',
            'permissions.*.guard_name' => 'sometimes|string|max:255',
            'permissions.*.category' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $workflow = PermissionManagementWorkflow::start([
            'action' => 'bulkCreate',
            'permissions' => $request->permissions
        ]);

        return response()->json([
            'message' => 'Bulk permission creation initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }

    /**
     * Get permission categories.
     */
    public function getCategories(): JsonResponse
    {
        Gate::authorize('viewCategories', Permission::class);

        $workflow = PermissionManagementWorkflow::start([
            'action' => 'getCategories'
        ]);

        return response()->json([
            'message' => 'Permission categories retrieval initiated',
            'workflow_id' => $workflow->id()
        ], 202);
    }
}
