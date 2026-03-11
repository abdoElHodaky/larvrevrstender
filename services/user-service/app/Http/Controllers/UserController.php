<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Workflows\UserManagementWorkflow;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $query = User::query();

        // Apply filters
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('role')) {
            $query->role($request->get('role'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $users = $query->with(['roles', 'permissions'])
                      ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users,
            'message' => 'Users retrieved successfully'
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'status' => 'nullable|in:active,inactive,suspended',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name'
        ]);

        $userData = $request->only(['name', 'email', 'phone', 'status']);
        $userData['password'] = Hash::make($request->password);
        $userData['status'] = $userData['status'] ?? 'active';

        // Start user creation workflow
        $workflow = UserManagementWorkflow::start([
            'action' => 'create',
            'user_data' => $userData,
            'roles' => $request->roles ?? [],
            'created_by' => auth()->id()
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'workflow_id' => $workflow->id(),
                'message' => 'User creation workflow started'
            ],
            'message' => 'User creation initiated successfully'
        ], 202);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        $user->load(['roles', 'permissions', 'activities' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return response()->json([
            'success' => true,
            'data' => $user,
            'message' => 'User retrieved successfully'
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8|confirmed',
            'status' => 'sometimes|in:active,inactive,suspended',
            'phone' => 'sometimes|string|max:20',
            'avatar' => 'sometimes|string|max:255',
        ]);

        $data = $request->only(['name', 'email', 'status', 'phone', 'avatar']);

        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Start user update workflow
        $workflow = UserManagementWorkflow::start([
            'action' => 'update',
            'user_id' => $user->id,
            'user_data' => $data,
            'updated_by' => auth()->id()
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'workflow_id' => $workflow->id(),
                'message' => 'User update workflow started'
            ],
            'message' => 'User update initiated successfully'
        ], 202);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        // Start user deletion workflow
        $workflow = UserManagementWorkflow::start([
            'action' => 'delete',
            'user_id' => $user->id,
            'deleted_by' => auth()->id()
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'workflow_id' => $workflow->id(),
                'message' => 'User deletion workflow started'
            ],
            'message' => 'User deletion initiated successfully'
        ], 202);
    }

    /**
     * Get user permissions.
     */
    public function getPermissions(User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        $permissions = $user->getAllPermissions();

        return response()->json([
            'success' => true,
            'data' => [
                'direct_permissions' => $user->permissions,
                'role_permissions' => $user->getPermissionsViaRoles(),
                'all_permissions' => $permissions
            ],
            'message' => 'User permissions retrieved successfully'
        ]);
    }

    /**
     * Assign permissions to user.
     */
    public function assignPermissions(Request $request, User $user): JsonResponse
    {
        Gate::authorize('assignPermissions', $user);

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        // Start permission assignment workflow
        $workflow = UserManagementWorkflow::start([
            'action' => 'assign_permissions',
            'user_id' => $user->id,
            'permissions' => $request->permissions,
            'assigned_by' => auth()->id()
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'workflow_id' => $workflow->id(),
                'message' => 'Permission assignment workflow started'
            ],
            'message' => 'Permission assignment initiated successfully'
        ], 202);
    }

    /**
     * Revoke permissions from user.
     */
    public function revokePermissions(Request $request, User $user): JsonResponse
    {
        Gate::authorize('revokePermissions', $user);

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        // Start permission revocation workflow
        $workflow = UserManagementWorkflow::start([
            'action' => 'revoke_permissions',
            'user_id' => $user->id,
            'permissions' => $request->permissions,
            'revoked_by' => auth()->id()
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'workflow_id' => $workflow->id(),
                'message' => 'Permission revocation workflow started'
            ],
            'message' => 'Permission revocation initiated successfully'
        ], 202);
    }

    /**
     * Get user roles.
     */
    public function getRoles(User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        $roles = $user->roles()->with('permissions')->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
            'message' => 'User roles retrieved successfully'
        ]);
    }

    /**
     * Assign roles to user.
     */
    public function assignRoles(Request $request, User $user): JsonResponse
    {
        Gate::authorize('assignRoles', $user);

        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name'
        ]);

        // Start role assignment workflow
        $workflow = UserManagementWorkflow::start([
            'action' => 'assign_roles',
            'user_id' => $user->id,
            'roles' => $request->roles,
            'assigned_by' => auth()->id()
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'workflow_id' => $workflow->id(),
                'message' => 'Role assignment workflow started'
            ],
            'message' => 'Role assignment initiated successfully'
        ], 202);
    }

    /**
     * Revoke roles from user.
     */
    public function revokeRoles(Request $request, User $user): JsonResponse
    {
        Gate::authorize('revokeRoles', $user);

        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name'
        ]);

        // Start role revocation workflow
        $workflow = UserManagementWorkflow::start([
            'action' => 'revoke_roles',
            'user_id' => $user->id,
            'roles' => $request->roles,
            'revoked_by' => auth()->id()
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'workflow_id' => $workflow->id(),
                'message' => 'Role revocation workflow started'
            ],
            'message' => 'Role revocation initiated successfully'
        ], 202);
    }
}
