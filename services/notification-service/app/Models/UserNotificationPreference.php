<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class UserNotificationPreference extends Model
{
    protected $table = 'user_notification_preferences';

    protected $fillable = [
        'user_id',
        'email_enabled',
        'sms_enabled',
        'push_enabled',
        'in_app_enabled',
        'frequency',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'timezone',
        'categories',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'quiet_hours_enabled' => 'boolean',
        'categories' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Scope to filter by user
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get users with email enabled
     */
    public function scopeEmailEnabled(Builder $query): Builder
    {
        return $query->where('email_enabled', true);
    }

    /**
     * Scope to get users with SMS enabled
     */
    public function scopeSmsEnabled(Builder $query): Builder
    {
        return $query->where('sms_enabled', true);
    }

    /**
     * Scope to get users with push notifications enabled
     */
    public function scopePushEnabled(Builder $query): Builder
    {
        return $query->where('push_enabled', true);
    }

    /**
     * Scope to get users with in-app notifications enabled
     */
    public function scopeInAppEnabled(Builder $query): Builder
    {
        return $query->where('in_app_enabled', true);
    }

    /**
     * Frequency options
     */
    public const FREQUENCIES = [
        'immediate' => 'immediate',
        'hourly' => 'hourly',
        'daily' => 'daily',
        'weekly' => 'weekly',
        'never' => 'never',
    ];

    /**
     * Get default preferences
     */
    public static function getDefaults(): array
    {
        return [
            'email_enabled' => true,
            'sms_enabled' => false,
            'push_enabled' => true,
            'in_app_enabled' => true,
            'frequency' => 'immediate',
            'quiet_hours_enabled' => false,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '08:00',
            'timezone' => 'UTC',
            'categories' => [],
        ];
    }

    /**
     * Check if notifications are allowed at current time
     */
    public function isNotificationAllowed(): bool
    {
        if (!$this->quiet_hours_enabled) {
            return true;
        }

        $now = now($this->timezone ?? 'UTC');
        $start = $now->copy()->setTimeFromTimeString($this->quiet_hours_start);
        $end = $now->copy()->setTimeFromTimeString($this->quiet_hours_end);

        // Handle overnight quiet hours (e.g., 22:00 to 08:00)
        if ($start->gt($end)) {
            return !($now->gte($start) || $now->lt($end));
        }

        // Handle same-day quiet hours (e.g., 12:00 to 14:00)
        return !($now->gte($start) && $now->lt($end));
    }

    /**
     * Check if a specific channel is enabled
     */
    public function isChannelEnabled(string $channel): bool
    {
        return match ($channel) {
            'email' => $this->email_enabled,
            'sms' => $this->sms_enabled,
            'push' => $this->push_enabled,
            'in_app' => $this->in_app_enabled,
            default => false,
        };
    }

    /**
     * Get enabled channels
     */
    public function getEnabledChannels(): array
    {
        $channels = [];
        
        if ($this->email_enabled) $channels[] = 'email';
        if ($this->sms_enabled) $channels[] = 'sms';
        if ($this->push_enabled) $channels[] = 'push';
        if ($this->in_app_enabled) $channels[] = 'in_app';
        
        return $channels;
    }
}
