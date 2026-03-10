<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
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
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
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

        $user->update($data);

        // Log activity
        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['updated_fields' => array_keys($data)])
            ->log('User updated');

        return response()->json([
            'success' => true,
            'data' => $user->fresh(['roles', 'permissions']),
            'message' => 'User updated successfully'
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        // Log activity before deletion
        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['deleted_user' => $user->toArray()])
            ->log('User deleted');

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get user permissions.
     */
    public function getPermissions(User $user): JsonResponse
    {
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
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $permissions = Permission::whereIn('name', $request->permissions)->get();
        $user->givePermissionTo($permissions);

        // Log activity
        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['assigned_permissions' => $request->permissions])
            ->log('Permissions assigned to user');

        return response()->json([
            'success' => true,
            'data' => $user->fresh(['permissions']),
            'message' => 'Permissions assigned successfully'
        ]);
    }

    /**
     * Revoke permissions from user.
     */
    public function revokePermissions(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $permissions = Permission::whereIn('name', $request->permissions)->get();
        $user->revokePermissionTo($permissions);

        // Log activity
        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['revoked_permissions' => $request->permissions])
            ->log('Permissions revoked from user');

        return response()->json([
            'success' => true,
            'data' => $user->fresh(['permissions']),
            'message' => 'Permissions revoked successfully'
        ]);
    }

    /**
     * Get user roles.
     */
    public function getRoles(User $user): JsonResponse
    {
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
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name'
        ]);

        $roles = Role::whereIn('name', $request->roles)->get();
        $user->assignRole($roles);

        // Log activity
        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['assigned_roles' => $request->roles])
            ->log('Roles assigned to user');

        return response()->json([
            'success' => true,
            'data' => $user->fresh(['roles']),
            'message' => 'Roles assigned successfully'
        ]);
    }

    /**
     * Revoke roles from user.
     */
    public function revokeRoles(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name'
        ]);

        $roles = Role::whereIn('name', $request->roles)->get();
        $user->removeRole($roles);

        // Log activity
        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['revoked_roles' => $request->roles])
            ->log('Roles revoked from user');

        return response()->json([
            'success' => true,
            'data' => $user->fresh(['roles']),
            'message' => 'Roles revoked successfully'
        ]);
    }
}
