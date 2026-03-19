<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * User Management Controller
 * 
 * Handles user CRUD operations, role and permission management
 * for the authentication service.
 */
class UserController extends Controller
{
    /**
     * Display a listing of users
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

        // Pagination
        $perPage = $request->get('per_page', 15);
        $users = $query->with(['roles', 'permissions'])
                      ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }

    /**
     * Display the specified user
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'permissions']);

        return response()->json([
            'status' => 'success',
            'data' => $user,
        ]);
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully',
            'data' => $user->fresh(['roles', 'permissions']),
        ]);
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user): JsonResponse
    {
        // Prevent deletion of the last admin user
        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete the last admin user',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Get user permissions
     */
    public function getPermissions(User $user): JsonResponse
    {
        $permissions = $user->getAllPermissions();

        return response()->json([
            'status' => 'success',
            'data' => [
                'direct_permissions' => $user->permissions,
                'role_permissions' => $user->getPermissionsViaRoles(),
                'all_permissions' => $permissions,
            ],
        ]);
    }

    /**
     * Assign permissions to user
     */
    public function assignPermissions(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $user->givePermissionTo($validated['permissions']);

        return response()->json([
            'status' => 'success',
            'message' => 'Permissions assigned successfully',
            'data' => $user->fresh(['permissions']),
        ]);
    }

    /**
     * Revoke permissions from user
     */
    public function revokePermissions(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        $user->revokePermissionTo($validated['permissions']);

        return response()->json([
            'status' => 'success',
            'message' => 'Permissions revoked successfully',
            'data' => $user->fresh(['permissions']),
        ]);
    }

    /**
     * Get user roles
     */
    public function getRoles(User $user): JsonResponse
    {
        $roles = $user->roles;

        return response()->json([
            'status' => 'success',
            'data' => $roles,
        ]);
    }

    /**
     * Assign roles to user
     */
    public function assignRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $user->assignRole($validated['roles']);

        return response()->json([
            'status' => 'success',
            'message' => 'Roles assigned successfully',
            'data' => $user->fresh(['roles']),
        ]);
    }

    /**
     * Revoke roles from user
     */
    public function revokeRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string',
        ]);

        $user->removeRole($validated['roles']);

        return response()->json([
            'status' => 'success',
            'message' => 'Roles revoked successfully',
            'data' => $user->fresh(['roles']),
        ]);
    }
}
