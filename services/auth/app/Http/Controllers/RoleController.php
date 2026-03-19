<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Role Management Controller
 * 
 * Handles role CRUD operations and permission assignments
 * for the authentication service.
 */
class RoleController extends Controller
{
    /**
     * Display a listing of roles
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::query();

        // Apply filters
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('guard_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('guard_name')) {
            $query->where('guard_name', $request->get('guard_name'));
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $roles = $query->with(['permissions'])
                      ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $roles,
        ]);
    }

    /**
     * Store a newly created role
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'guard_name' => 'sometimes|string|max:255',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
        ]);

        if (isset($validated['permissions'])) {
            $role->givePermissionTo($validated['permissions']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Role created successfully',
            'data' => $role->load('permissions'),
        ], 201);
    }

    /**
     * Display the specified role
     */
    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions']);

        return response()->json([
            'status' => 'success',
            'data' => $role,
        ]);
    }

    /**
     * Update the specified role
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $role->id,
            'guard_name' => 'sometimes|string|max:255',
        ]);

        $role->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Role updated successfully',
            'data' => $role->fresh(['permissions']),
        ]);
    }

    /**
     * Remove the specified role
     */
    public function destroy(Role $role): JsonResponse
    {
        // Prevent deletion of admin role if it's the last one
        if ($role->name === 'admin' && Role::where('name', 'admin')->count() <= 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete the last admin role',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Get permissions for this role
     */
    public function getPermissions(Role $role): JsonResponse
    {
        $permissions = $role->permissions;

        return response()->json([
            'status' => 'success',
            'data' => $permissions,
        ]);
    }

    /**
     * Assign permissions to role
     */
    public function assignPermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->givePermissionTo($validated['permissions']);

        return response()->json([
            'status' => 'success',
            'message' => 'Permissions assigned successfully',
            'data' => $role->fresh(['permissions']),
        ]);
    }

    /**
     * Revoke permissions from role
     */
    public function revokePermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        $role->revokePermissionTo($validated['permissions']);

        return response()->json([
            'status' => 'success',
            'message' => 'Permissions revoked successfully',
            'data' => $role->fresh(['permissions']),
        ]);
    }

    /**
     * Sync permissions for role (replace all permissions)
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->syncPermissions($validated['permissions']);

        return response()->json([
            'status' => 'success',
            'message' => 'Permissions synchronized successfully',
            'data' => $role->fresh(['permissions']),
        ]);
    }

    /**
     * Get users with this role
     */
    public function getUsers(Role $role): JsonResponse
    {
        $users = $role->users;

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }
}
