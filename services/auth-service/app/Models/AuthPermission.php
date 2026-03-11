<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AuthPermission extends Model
{
    use HasFactory;

    protected $table = 'auth_permissions';

    protected $primaryKey = 'permission_id';

    protected $fillable = [
        'permission_name',
        'description',
        'category',
        'is_active',
        'metadata',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to get active permissions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get permissions by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to search permissions by name
     */
    public function scopeByName($query, string $name)
    {
        return $query->where('permission_name', 'like', "%{$name}%");
    }

    /**
     * Get roles that have this permission
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AuthRole::class, 'auth_role_permissions', 'permission_id', 'role_id');
    }
}

