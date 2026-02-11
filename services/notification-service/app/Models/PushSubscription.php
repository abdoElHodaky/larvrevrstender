<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NotificationChannels\WebPush\PushSubscription as WebPushSubscription;

class PushSubscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
        'device_type',
        'browser',
        'platform',
        'is_active',
        'last_used_at',
        'expires_at',
        'notification_preferences',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'notification_preferences' => 'array',
    ];

    /**
     * Get the user that owns this push subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Convert to WebPush subscription format.
     */
    public function toWebPushSubscription(): WebPushSubscription
    {
        return new WebPushSubscription(
            $this->endpoint,
            $this->public_key,
            $this->auth_token,
            $this->content_encoding
        );
    }

    /**
     * Scope to get active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope to get subscriptions for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get subscriptions by device type.
     */
    public function scopeByDeviceType($query, string $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }

    /**
     * Scope to get subscriptions by browser.
     */
    public function scopeByBrowser($query, string $browser)
    {
        return $query->where('browser', 'like', "%{$browser}%");
    }

    /**
     * Check if subscription is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Mark subscription as used.
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Deactivate subscription.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Activate subscription.
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Check if user allows specific notification type.
     */
    public function allowsNotificationType(string $type): bool
    {
        if (!$this->notification_preferences) {
            return true; // Allow all by default
        }

        return $this->notification_preferences[$type] ?? true;
    }

    /**
     * Update notification preferences.
     */
    public function updateNotificationPreferences(array $preferences): void
    {
        $currentPreferences = $this->notification_preferences ?? [];
        $updatedPreferences = array_merge($currentPreferences, $preferences);
        
        $this->update(['notification_preferences' => $updatedPreferences]);
    }

    /**
     * Get device info as formatted string.
     */
    public function getDeviceInfoAttribute(): string
    {
        $parts = array_filter([
            $this->browser,
            $this->platform,
            $this->device_type,
        ]);

        return implode(' - ', $parts) ?: 'Unknown Device';
    }

    /**
     * Clean up expired subscriptions.
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', now())
                    ->orWhere('is_active', false)
                    ->where('updated_at', '<', now()->subDays(30))
                    ->delete();
    }

    /**
     * Get subscription statistics.
     */
    public static function getStatistics(): array
    {
        $total = static::count();
        $active = static::active()->count();
        $byDeviceType = static::active()
                             ->selectRaw('device_type, COUNT(*) as count')
                             ->groupBy('device_type')
                             ->pluck('count', 'device_type')
                             ->toArray();
        
        $byBrowser = static::active()
                          ->selectRaw('browser, COUNT(*) as count')
                          ->groupBy('browser')
                          ->orderBy('count', 'desc')
                          ->limit(10)
                          ->pluck('count', 'browser')
                          ->toArray();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'by_device_type' => $byDeviceType,
            'by_browser' => $byBrowser,
            'activity_rate' => $total > 0 ? round(($active / $total) * 100, 2) : 0,
        ];
    }
}
