<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

/**
 * Permission Management Controller
 * 
 * Handles permission CRUD operations for the authentication service.
 */
class PermissionController extends Controller
{
    /**
     * Display a listing of permissions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query();

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
        $permissions = $query->with(['roles'])
                            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $permissions,
        ]);
    }

    /**
     * Store a newly created permission
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
            'guard_name' => 'sometimes|string|max:255',
        ]);

        $permission = Permission::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Permission created successfully',
            'data' => $permission,
        ], 201);
    }

    /**
     * Display the specified permission
     */
    public function show(Permission $permission): JsonResponse
    {
        $permission->load(['roles']);

        return response()->json([
            'status' => 'success',
            'data' => $permission,
        ]);
    }

    /**
     * Update the specified permission
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:permissions,name,' . $permission->id,
            'guard_name' => 'sometimes|string|max:255',
        ]);

        $permission->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Permission updated successfully',
            'data' => $permission->fresh(['roles']),
        ]);
    }

    /**
     * Remove the specified permission
     */
    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Permission deleted successfully',
        ]);
    }

    /**
     * Get roles that have this permission
     */
    public function getRoles(Permission $permission): JsonResponse
    {
        $roles = $permission->roles;

        return response()->json([
            'status' => 'success',
            'data' => $roles,
        ]);
    }

    /**
     * Assign permission to roles
     */
    public function assignToRoles(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        foreach ($validated['roles'] as $roleName) {
            $role = \Spatie\Permission\Models\Role::findByName($roleName);
            $role->givePermissionTo($permission);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Permission assigned to roles successfully',
            'data' => $permission->fresh(['roles']),
        ]);
    }

    /**
     * Remove permission from roles
     */
    public function removeFromRoles(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string',
        ]);

        foreach ($validated['roles'] as $roleName) {
            $role = \Spatie\Permission\Models\Role::findByName($roleName);
            $role->revokePermissionTo($permission);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Permission removed from roles successfully',
            'data' => $permission->fresh(['roles']),
        ]);
    }
}
