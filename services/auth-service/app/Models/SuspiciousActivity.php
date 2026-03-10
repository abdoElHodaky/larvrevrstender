<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

/**
 * Suspicious Activity Model
 * 
 * Tracks suspicious login activities and security incidents.
 * Provides Eloquent interface for suspicious_activities table.
 */
class SuspiciousActivity extends Model
{
    use HasFactory;

    protected $table = 'suspicious_activities';
    
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'activity_type',
        'severity',
        'description',
        'metadata',
        'detected_at',
        'resolved_at',
        'status'
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Activity type constants
     */
    const TYPE_MULTIPLE_FAILED_LOGINS = 'multiple_failed_logins';
    const TYPE_UNUSUAL_LOCATION = 'unusual_location';
    const TYPE_SUSPICIOUS_USER_AGENT = 'suspicious_user_agent';
    const TYPE_BRUTE_FORCE = 'brute_force';
    const TYPE_ACCOUNT_TAKEOVER = 'account_takeover';

    /**
     * Severity constants
     */
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_INVESTIGATING = 'investigating';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_FALSE_POSITIVE = 'false_positive';

    /**
     * Scope to get activities by severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope to get activities by type
     */
    public function scopeByType($query, string $activityType)
    {
        return $query->where('activity_type', $activityType);
    }

    /**
     * Scope to get unresolved activities
     */
    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_INVESTIGATING]);
    }

    /**
     * Scope to get activities by IP
     */
    public function scopeByIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope to get recent activities
     */
    public function scopeRecent($query, Carbon $since)
    {
        return $query->where('detected_at', '>=', $since);
    }

    /**
     * Check if activity is high priority
     */
    public function isHighPriority(): bool
    {
        return in_array($this->severity, [self::SEVERITY_HIGH, self::SEVERITY_CRITICAL]);
    }

    /**
     * Mark activity as resolved
     */
    public function markResolved(): bool
    {
        return $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now()
        ]);
    }
}
