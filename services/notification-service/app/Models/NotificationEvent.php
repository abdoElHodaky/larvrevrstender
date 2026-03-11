<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class NotificationEvent extends Model
{
    protected $table = 'notification_events';

    protected $fillable = [
        'notification_id',
        'user_id',
        'event_type',
        'event_data',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'event_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Get the notification that owns this event
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    /**
     * Scope to filter by event type
     */
    public function scopeEventType(Builder $query, string $type): Builder
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope to filter by user
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get recent events
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', Carbon::now()->subHours($hours));
    }

    /**
     * Scope to filter by IP address
     */
    public function scopeFromIp(Builder $query, string $ipAddress): Builder
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Get events for a specific notification
     */
    public function scopeForNotification(Builder $query, string $notificationId): Builder
    {
        return $query->where('notification_id', $notificationId);
    }

    /**
     * Common event types
     */
    public const EVENT_TYPES = [
        'sent' => 'sent',
        'delivered' => 'delivered',
        'opened' => 'opened',
        'clicked' => 'clicked',
        'bounced' => 'bounced',
        'failed' => 'failed',
        'unsubscribed' => 'unsubscribed',
    ];

    /**
     * Check if this is a delivery event
     */
    public function isDeliveryEvent(): bool
    {
        return in_array($this->event_type, ['sent', 'delivered', 'opened']);
    }

    /**
     * Check if this is a failure event
     */
    public function isFailureEvent(): bool
    {
        return in_array($this->event_type, ['bounced', 'failed']);
    }
}
