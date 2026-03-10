<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::query();

        // Apply filters
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('guard_name')) {
            $query->where('guard_name', $request->get('guard_name'));
        }

        // Include counts
        $query->withCount(['permissions', 'users']);

        // Include relationships if requested
        if ($request->get('include_permissions')) {
            $query->with('permissions');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $roles = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $roles,
            'message' => 'Roles retrieved successfully'
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'guard_name' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $data = $request->only(['name', 'description', 'guard_name']);
        $data['guard_name'] = $data['guard_name'] ?? 'web';

        $role = Role::create($data);

        // Assign permissions if provided
        if ($request->has('permissions')) {
            $permissions = Permission::whereIn('name', $request->permissions)->get();
            $role->givePermissionTo($permissions);
        }

        // Log activity
        activity()
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->withProperties([
                'role_data' => $data,
                'assigned_permissions' => $request->permissions ?? []
            ])
            ->log('Role created');

        return response()->json([
            'success' => true,
            'data' => $role->load('permissions'),
            'message' => 'Role created successfully'
        ], 201);
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions', 'users']);

        return response()->json([
            'success' => true,
            'data' => $role,
            'message' => 'Role retrieved successfully'
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $role->id,
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $data = $request->only(['name', 'description']);
        $role->update($data);

        // Update permissions if provided
        if ($request->has('permissions')) {
            $permissions = Permission::whereIn('name', $request->permissions)->get();
            $role->syncPermissions($permissions);
        }

        // Log activity
        activity()
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->withProperties([
                'updated_fields' => array_keys($data),
                'updated_permissions' => $request->permissions ?? null
            ])
            ->log('Role updated');

        return response()->json([
            'success' => true,
            'data' => $role->fresh(['permissions', 'users']),
            'message' => 'Role updated successfully'
        ]);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): JsonResponse
    {
        // Check if role is assigned to any users
        $usersCount = $role->users()->count();

        if ($usersCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete role. It is assigned to {$usersCount} user(s)."
            ], 422);
        }

        // Log activity before deletion
        activity()
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->withProperties(['deleted_role' => $role->toArray()])
            ->log('Role deleted');

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * Get role permissions.
     */
    public function getPermissions(Role $role): JsonResponse
    {
        $permissions = $role->permissions;

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'message' => 'Role permissions retrieved successfully'
        ]);
    }

    /**
     * Assign permissions to role.
     */
    public function assignPermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $permissions = Permission::whereIn('name', $request->permissions)->get();
        $role->givePermissionTo($permissions);

        // Log activity
        activity()
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->withProperties(['assigned_permissions' => $request->permissions])
            ->log('Permissions assigned to role');

        return response()->json([
            'success' => true,
            'data' => $role->fresh(['permissions']),
            'message' => 'Permissions assigned successfully'
        ]);
    }

    /**
     * Revoke permissions from role.
     */
    public function revokePermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $permissions = Permission::whereIn('name', $request->permissions)->get();
        $role->revokePermissionTo($permissions);

        // Log activity
        activity()
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->withProperties(['revoked_permissions' => $request->permissions])
            ->log('Permissions revoked from role');

        return response()->json([
            'success' => true,
            'data' => $role->fresh(['permissions']),
            'message' => 'Permissions revoked successfully'
        ]);
    }

    /**
     * Sync permissions with role.
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $permissions = Permission::whereIn('name', $request->permissions)->get();
        $role->syncPermissions($permissions);

        // Log activity
        activity()
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->withProperties(['synced_permissions' => $request->permissions])
            ->log('Permissions synced with role');

        return response()->json([
            'success' => true,
            'data' => $role->fresh(['permissions']),
            'message' => 'Permissions synced successfully'
        ]);
    }

    /**
     * Get role users.
     */
    public function getUsers(Role $role): JsonResponse
    {
        $users = $role->users()->with(['roles', 'permissions'])->get();

        return response()->json([
            'success' => true,
            'data' => $users,
            'message' => 'Role users retrieved successfully'
        ]);
    }

    /**
     * Get roles for a specific guard.
     */
    public function getByGuard(string $guardName): JsonResponse
    {
        $roles = Role::where('guard_name', $guardName)
                    ->with('permissions')
                    ->withCount(['permissions', 'users'])
                    ->get();

        return response()->json([
            'success' => true,
            'data' => $roles,
            'message' => "Roles for guard '{$guardName}' retrieved successfully"
        ]);
    }

    /**
     * Get role statistics.
     */
    public function getStatistics(): JsonResponse
    {
        $statistics = [
            'total_roles' => Role::count(),
            'roles_by_guard' => Role::groupBy('guard_name')
                                   ->selectRaw('guard_name, count(*) as count')
                                   ->pluck('count', 'guard_name'),
            'most_assigned_roles' => Role::withCount('users')
                                        ->orderByDesc('users_count')
                                        ->limit(10)
                                        ->get(),
            'roles_with_most_permissions' => Role::withCount('permissions')
                                                ->orderByDesc('permissions_count')
                                                ->limit(10)
                                                ->get(),
            'unused_roles' => Role::doesntHave('users')->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics,
            'message' => 'Role statistics retrieved successfully'
        ]);
    }
}
