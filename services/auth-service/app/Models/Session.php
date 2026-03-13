<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class Session extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'sessions';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'last_activity' => 'integer',
    ];

    /**
     * Get the user that owns the session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id');
    }

    /**
     * Scope a query to only include active sessions.
     */
    public function scopeActive($query, int $threshold = 3600)
    {
        return $query->where('last_activity', '>=', Carbon::now()->subSeconds($threshold)->timestamp);
    }

    /**
     * Scope a query to only include sessions for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include sessions within a date range.
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        $query->where('last_activity', '>=', is_int($startDate) ? $startDate : $startDate->timestamp);
        
        if ($endDate) {
            $query->where('last_activity', '<=', is_int($endDate) ? $endDate : $endDate->timestamp);
        }
        
        return $query;
    }

    /**
     * Scope a query to only include sessions from specific IP addresses.
     */
    public function scopeFromIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Get the decoded payload.
     */
    protected function payload(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? unserialize(base64_decode($value)) : [],
            set: fn ($value) => base64_encode(serialize($value))
        );
    }

    /**
     * Get the formatted last activity time.
     */
    protected function lastActivityFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => \Carbon\Carbon::createFromTimestamp($this->last_activity)
        );
    }

    /**
     * Check if the session is active.
     */
    public function isActive(int $threshold = 3600): bool
    {
        return $this->last_activity >= (Carbon::now()->timestamp - $threshold);
    }

    /**
     * Get session duration in seconds.
     */
    public function getDurationAttribute(): int
    {
        return Carbon::now()->timestamp - $this->last_activity;
    }
}
