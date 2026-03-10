<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\TokenInvalidation;

/**
 * Auth Role Model for RPC-based RBAC
 * 
 * Represents role references in auth-service for authorization operations.
 */
class AuthRole extends Model
{
    protected $table = 'auth_roles';

    protected $fillable = [
        'role_id',
        'name',
        'display_name',
        'permissions'
    ];

    protected $casts = [
        'role_id' => 'integer',
        'permissions' => 'array'
    ];

    /**
     * Get role's user assignments.
     */
    public function userAssignments(): HasMany
    {
        return $this->hasMany(AuthUserRole::class, 'role_id', 'role_id');
    }

    /**
     * Get users assigned to this role.
     */
    public function users()
    {
        return $this->hasManyThrough(
            AuthUser::class,
            AuthUserRole::class,
            'role_id', // Foreign key on auth_user_roles table
            'user_id', // Foreign key on auth_users table
            'role_id', // Local key on auth_roles table
            'user_id'  // Local key on auth_user_roles table
        );
    }

    /**
     * Permissions attribute accessor (PHP 8.3 style).
     */
    protected function permissions(): Attribute
    {
        return Attribute::make(
            get: fn(?string $value) => $value ? json_decode($value, true) : [],
            set: fn(array $value) => json_encode($value)
        );
    }

    /**
     * Update role permissions using PHP 8.3 match expression.
     */
    public function updatePermissions(string $action, array $permissions): array
    {
        $currentPermissions = $this->permissions ?? [];
        
        $newPermissions = match($action) {
            'assign' => array_unique([...$currentPermissions, ...$permissions]),
            'revoke' => array_diff($currentPermissions, $permissions),
            'sync' => $permissions,
            default => throw new \InvalidArgumentException("Invalid action: {$action}")
        };

        $this->update(['permissions' => $newPermissions]);
        
        return $newPermissions;
    }

    /**
     * Get affected users for permission changes (PHP 8.3 optimized).
     */
    public function getAffectedUserIds(): array
    {
        return $this->userAssignments()
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * Bulk invalidate tokens for all users with this role.
     */
    public function invalidateUserTokens(string $reason): int
    {
        $userIds = $this->getAffectedUserIds();
        
        return collect($userIds)
            ->map(fn(int $userId) => [
                'user_id' => $userId,
                'token_type' => 'role_change',
                'reason' => $reason,
                'invalidated_at' => now(),
                'metadata' => ['method' => 'invalidateUserTokens']
            ])
            ->pipe(function($tokenInvalidations) {
                // Bulk insert invalidation records using Eloquent
                TokenInvalidation::insert($tokenInvalidations->toArray());
                return $tokenInvalidations->count();
            });
    }
}
