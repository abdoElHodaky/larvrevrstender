<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TokenInvalidation extends Model
{
    use HasFactory;

    protected $table = 'token_invalidations';

    protected $fillable = [
        'token_id',
        'user_id',
        'token_type',
        'invalidated_at',
        'reason',
        'ip_address',
        'user_agent',
        'metadata'
    ];

    protected $casts = [
        'invalidated_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to get invalidations by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get invalidations by token type
     */
    public function scopeByTokenType($query, string $tokenType)
    {
        return $query->where('token_type', $tokenType);
    }

    /**
     * Scope to get recent invalidations
     */
    public function scopeRecent($query, Carbon $since)
    {
        return $query->where('invalidated_at', '>=', $since);
    }

    /**
     * Scope to get invalidations by reason
     */
    public function scopeByReason($query, string $reason)
    {
        return $query->where('reason', $reason);
    }

    /**
     * Scope to get invalidations by IP address
     */
    public function scopeByIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Get the user that owns this token invalidation
     */
    public function user()
    {
        return $this->belongsTo(AuthUser::class, 'user_id', 'user_id');
    }
}

