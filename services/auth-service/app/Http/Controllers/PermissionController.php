<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\ValidationException;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query();

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

        // Include role count
        $query->withCount('roles');

        // Pagination
        $perPage = $request->get('per_page', 15);
        $permissions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'message' => 'Permissions retrieved successfully'
        ]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
            'description' => 'nullable|string|max:500',
            'guard_name' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100'
        ]);

        $data = $request->only(['name', 'description', 'guard_name', 'category']);
        $data['guard_name'] = $data['guard_name'] ?? 'web';

        $permission = Permission::create($data);

        // Log activity
        activity()
            ->performedOn($permission)
            ->causedBy(auth()->user())
            ->withProperties(['permission_data' => $data])
            ->log('Permission created');

        return response()->json([
            'success' => true,
            'data' => $permission,
            'message' => 'Permission created successfully'
        ], 201);
    }

    /**
     * Display the specified permission.
     */
    public function show(Permission $permission): JsonResponse
    {
        $permission->load(['roles', 'users']);

        return response()->json([
            'success' => true,
            'data' => $permission,
            'message' => 'Permission retrieved successfully'
        ]);
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255|unique:permissions,name,' . $permission->id,
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:100'
        ]);

        $data = $request->only(['name', 'description', 'category']);
        $permission->update($data);

        // Log activity
        activity()
            ->performedOn($permission)
            ->causedBy(auth()->user())
            ->withProperties(['updated_fields' => array_keys($data)])
            ->log('Permission updated');

        return response()->json([
            'success' => true,
            'data' => $permission->fresh(['roles', 'users']),
            'message' => 'Permission updated successfully'
        ]);
    }

    /**
     * Remove the specified permission.
     */
    public function destroy(Permission $permission): JsonResponse
    {
        // Check if permission is assigned to any roles or users
        $rolesCount = $permission->roles()->count();
        $usersCount = $permission->users()->count();

        if ($rolesCount > 0 || $usersCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete permission. It is assigned to {$rolesCount} role(s) and {$usersCount} user(s)."
            ], 422);
        }

        // Log activity before deletion
        activity()
            ->performedOn($permission)
            ->causedBy(auth()->user())
            ->withProperties(['deleted_permission' => $permission->toArray()])
            ->log('Permission deleted');

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully'
        ]);
    }

    /**
     * Get permissions grouped by category.
     */
    public function getByCategory(): JsonResponse
    {
        $permissions = Permission::all()->groupBy('category');

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'message' => 'Permissions grouped by category retrieved successfully'
        ]);
    }

    /**
     * Get permissions for a specific guard.
     */
    public function getByGuard(string $guardName): JsonResponse
    {
        $permissions = Permission::where('guard_name', $guardName)->get();

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'message' => "Permissions for guard '{$guardName}' retrieved successfully"
        ]);
    }

    /**
     * Bulk create permissions.
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*.name' => 'required|string|max:255|distinct',
            'permissions.*.description' => 'nullable|string|max:500',
            'permissions.*.guard_name' => 'nullable|string|max:255',
            'permissions.*.category' => 'nullable|string|max:100'
        ]);

        $permissions = collect($request->permissions)->map(function ($permissionData) {
            $permissionData['guard_name'] = $permissionData['guard_name'] ?? 'web';
            $permissionData['created_at'] = now();
            $permissionData['updated_at'] = now();
            return $permissionData;
        });

        // Check for existing permissions
        $existingNames = Permission::whereIn('name', $permissions->pluck('name'))->pluck('name');
        if ($existingNames->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Some permissions already exist: ' . $existingNames->implode(', ')
            ], 422);
        }

        $createdPermissions = Permission::insert($permissions->toArray());

        // Log activity
        activity()
            ->causedBy(auth()->user())
            ->withProperties(['created_permissions' => $permissions->pluck('name')])
            ->log('Bulk permissions created');

        return response()->json([
            'success' => true,
            'data' => Permission::whereIn('name', $permissions->pluck('name'))->get(),
            'message' => 'Permissions created successfully'
        ], 201);
    }

    /**
     * Get permission statistics.
     */
    public function getStatistics(): JsonResponse
    {
        $statistics = [
            'total_permissions' => Permission::count(),
            'permissions_by_guard' => Permission::groupBy('guard_name')
                                               ->selectRaw('guard_name, count(*) as count')
                                               ->pluck('count', 'guard_name'),
            'permissions_by_category' => Permission::groupBy('category')
                                                  ->selectRaw('category, count(*) as count')
                                                  ->pluck('count', 'category'),
            'most_assigned_permissions' => Permission::withCount(['roles', 'users'])
                                                    ->orderByDesc('roles_count')
                                                    ->orderByDesc('users_count')
                                                    ->limit(10)
                                                    ->get(),
            'unused_permissions' => Permission::doesntHave('roles')
                                             ->doesntHave('users')
                                             ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics,
            'message' => 'Permission statistics retrieved successfully'
        ]);
    }
}
