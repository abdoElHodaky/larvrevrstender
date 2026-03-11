<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auth User Role Pivot Model
 * 
 * Represents user-role assignments in auth-service.
 */
class AuthUserRole extends Model
{
    protected $table = 'auth_user_roles';

    protected $fillable = [
        'user_id',
        'role_id',
        'assigned_at',
        'assigned_by'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'role_id' => 'integer',
        'assigned_by' => 'integer',
        'assigned_at' => 'datetime'
    ];

    /**
     * Get the user for this assignment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id', 'user_id');
    }

    /**
     * Get the role for this assignment.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(AuthRole::class, 'role_id', 'role_id');
    }

    /**
     * Bulk assign roles to users (PHP 8.3 optimized).
     */
    public static function bulkAssign(array $userIds, array $roleIds, int $assignedBy): int
    {
        $assignments = collect($userIds)
            ->crossJoin($roleIds)
            ->map(fn(array $pair) => [
                'user_id' => $pair[0],
                'role_id' => $pair[1],
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ])
            ->toArray();

        return static::insert($assignments);
    }

    /**
     * Bulk revoke roles from users (PHP 8.3 optimized).
     */
    public static function bulkRevoke(array $userIds, array $roleIds): int
    {
        return static::whereIn('user_id', $userIds)
            ->whereIn('role_id', $roleIds)
            ->delete();
    }
}
