<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionSecurityLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'session_security_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'session_id',
        'user_id',
        'event_type',
        'ip_address',
        'user_agent',
        'details',
        'severity',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'details' => 'array', // Laravel 12 automatic JSON casting
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = true;

    /**
     * Get the session that owns the security log.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id', 'id');
    }

    /**
     * Get the user that owns the security log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id');
    }

    /**
     * Scope a query to only include logs of a specific event type.
     */
    public function scopeEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope a query to only include logs of a specific severity.
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope a query to only include recent logs.
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope a query to only include logs from specific IP addresses.
     */
    public function scopeFromIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }
}
