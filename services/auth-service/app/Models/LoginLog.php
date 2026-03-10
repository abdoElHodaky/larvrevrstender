<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

/**
 * Login Log Model
 * 
 * Tracks user login attempts and activities.
 * Provides Eloquent interface for login_logs table.
 */
class LoginLog extends Model
{
    use HasFactory;

    protected $table = 'login_logs';
    
    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'login_at',
        'success',
        'failure_reason',
        'location',
        'device_info'
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'success' => 'boolean',
        'device_info' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to get failed login attempts
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope to get successful logins
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope to get logs by IP address
     */
    public function scopeByIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope to get logs by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get recent logs within timeframe
     */
    public function scopeRecent($query, Carbon $since)
    {
        return $query->where('login_at', '>=', $since);
    }

    /**
     * Scope to get logs within date range
     */
    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('login_at', [$startDate, $endDate]);
    }
}
