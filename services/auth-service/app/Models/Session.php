<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Session Model with PHP 8.3 + Laravel 12 optimizations
 */
class Session extends Model
{
    protected $table = 'sessions';

    protected $fillable = [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'last_activity' => 'integer',
        'payload' => 'string'
    ];

    /**
     * Get the user that owns the session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'user_id', 'user_id');
    }

    /**
     * Scope for active sessions.
     */
    public function scopeActive($query)
    {
        return $query->where('last_activity', '>', now()->subMinutes(config('session.lifetime', 120))->timestamp);
    }

    /**
     * Scope for sessions by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for sessions by IP address.
     */
    public function scopeFromIp($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope for recent sessions.
     */
    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('last_activity', '>', now()->subMinutes($minutes)->timestamp);
    }

    /**
     * Check if session is active.
     */
    public function isActive(): bool
    {
        $sessionLifetime = config('session.lifetime', 120);
        return $this->last_activity > now()->subMinutes($sessionLifetime)->timestamp;
    }

    /**
     * Get formatted last activity time.
     */
    protected function formattedLastActivity(): Attribute
    {
        return Attribute::make(
            get: fn() => \Carbon\Carbon::createFromTimestamp($this->last_activity)->diffForHumans()
        );
    }

    /**
     * Get session analytics using collection methods (PHP 8.3).
     */
    public static function getSessionAnalytics(int $userId, int $days = 30): array
    {
        $sessions = static::forUser($userId)
            ->where('last_activity', '>', now()->subDays($days)->timestamp)
            ->get();

        return [
            'total_sessions' => $sessions->count(),
            'unique_ips' => $sessions->pluck('ip_address')->unique()->count(),
            'browsers' => $sessions->groupBy(fn($session) => $session->getBrowserFromUserAgent())
                ->map(fn($group) => $group->count())
                ->toArray(),
            'daily_activity' => $sessions->groupBy(fn($session) => 
                \Carbon\Carbon::createFromTimestamp($session->last_activity)->format('Y-m-d')
            )->map(fn($group) => $group->count())->toArray()
        ];
    }

    /**
     * Extract browser from user agent (PHP 8.3 optimized).
     */
    private function getBrowserFromUserAgent(): string
    {
        return match(true) {
            str_contains($this->user_agent, 'Chrome') => 'Chrome',
            str_contains($this->user_agent, 'Firefox') => 'Firefox',
            str_contains($this->user_agent, 'Safari') => 'Safari',
            str_contains($this->user_agent, 'Edge') => 'Edge',
            default => 'Other'
        };
    }

    /**
     * Bulk cleanup expired sessions (PHP 8.3 optimized).
     */
    public static function cleanupExpired(): int
    {
        $sessionLifetime = config('session.lifetime', 120);
        $expiredThreshold = now()->subMinutes($sessionLifetime)->timestamp;

        return static::where('last_activity', '<', $expiredThreshold)->delete();
    }
}
