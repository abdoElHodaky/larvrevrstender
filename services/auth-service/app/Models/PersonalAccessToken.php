<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personal Access Token Model with PHP 8.3 + Laravel 12 optimizations
 */
class PersonalAccessToken extends Model
{
    protected $table = 'personal_access_tokens';

    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at'
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    /**
     * Get the tokenable model.
     */
    public function tokenable()
    {
        return $this->morphTo();
    }

    /**
     * Scope for expired tokens.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope for unused tokens.
     */
    public function scopeUnused($query, int $days = 30)
    {
        return $query->where('last_used_at', '<', now()->subDays($days))
            ->orWhereNull('last_used_at');
    }

    /**
     * Scope for tokens by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('tokenable_id', $userId)
            ->where('tokenable_type', 'App\\Models\\User');
    }

    /**
     * Check if token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if token is unused for specified days.
     */
    public function isUnused(int $days = 30): bool
    {
        return !$this->last_used_at || $this->last_used_at->lt(now()->subDays($days));
    }

    /**
     * Bulk cleanup expired tokens (PHP 8.3 optimized).
     */
    public static function cleanupExpired(int $batchSize = 1000): int
    {
        return static::expired()->limit($batchSize)->delete();
    }

    /**
     * Bulk cleanup unused tokens (PHP 8.3 optimized).
     */
    public static function cleanupUnused(int $days = 30, int $batchSize = 1000): int
    {
        return static::unused($days)->limit($batchSize)->delete();
    }
}
