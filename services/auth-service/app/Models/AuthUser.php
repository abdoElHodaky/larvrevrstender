<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Auth User Model for RPC-based RBAC
 * 
 * Represents user references in auth-service for authorization operations.
 */
class AuthUser extends Model
{
    protected $table = 'auth_users';

    protected $fillable = [
        'user_id',
        'email',
        'name',
        'status',
        'remember_token'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'status' => 'string'
    ];

    /**
     * Get user's role assignments.
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(AuthUserRole::class, 'user_id', 'user_id');
    }

    /**
     * Get user's roles through assignments.
     */
    public function roles()
    {
        return $this->hasManyThrough(
            AuthRole::class,
            AuthUserRole::class,
            'user_id', // Foreign key on auth_user_roles table
            'role_id', // Foreign key on auth_roles table
            'user_id', // Local key on auth_users table
            'role_id'  // Local key on auth_user_roles table
        );
    }

    /**
     * Get all user permissions from roles (PHP 8.3 optimized).
     */
    public function getAllPermissions(): array
    {
        return $this->roles()
            ->get()
            ->flatMap(fn(AuthRole $role) => $role->permissions ?? [])
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Check if user has specific permissions (PHP 8.3 optimized).
     */
    public function hasPermissions(array $permissions): array
    {
        $userPermissions = $this->getAllPermissions();
        
        return array_combine(
            $permissions,
            array_map(
                fn(string $permission) => in_array($permission, $userPermissions),
                $permissions
            )
        );
    }

    /**
     * Scope for active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
