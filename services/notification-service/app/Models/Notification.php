<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'notification_id',
        'user_id',
        'user_type',
        'type',
        'title',
        'message',
        'data',
        'channels',
        'channel_status',
        'priority',
        'scheduled_at',
        'expires_at',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'delivery_attempts',
        'max_attempts',
        'next_retry_at',
        'related_type',
        'related_id',
        'related_data',
        'failure_reasons',
        'last_error',
        'language',
        'timezone',
        'personalization_data',
        'tracking_data',
        'is_bulk',
        'campaign_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'data' => 'array',
        'channels' => 'array',
        'channel_status' => 'array',
        'scheduled_at' => 'datetime',
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'related_data' => 'array',
        'failure_reasons' => 'array',
        'personalization_data' => 'array',
        'tracking_data' => 'array',
        'is_bulk' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_READ = 'read';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Priority constants
     */
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    /**
     * Channel constants
     */
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_SMS = 'sms';
    const CHANNEL_PUSH = 'push';
    const CHANNEL_IN_APP = 'in_app';

    /**
     * Notification type constants
     */
    const TYPE_BID_RECEIVED = 'bid_received';
    const TYPE_BID_ACCEPTED = 'bid_accepted';
    const TYPE_BID_REJECTED = 'bid_rejected';
    const TYPE_ORDER_CREATED = 'order_created';
    const TYPE_ORDER_STATUS_CHANGED = 'order_status_changed';
    const TYPE_PAYMENT_REMINDER = 'payment_reminder';
    const TYPE_PAYMENT_RECEIVED = 'payment_received';
    const TYPE_INVOICE_SENT = 'invoice_sent';
    const TYPE_PART_REQUEST_EXPIRING = 'part_request_expiring';
    const TYPE_NEW_PART_REQUEST = 'new_part_request';
    const TYPE_SYSTEM_MAINTENANCE = 'system_maintenance';
    const TYPE_PROMOTIONAL = 'promotional';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($notification) {
            if (!$notification->notification_id) {
                $notification->notification_id = Str::uuid();
            }
        });
    }

    /**
     * Scope for notifications by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for notifications by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for notifications by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for notifications by priority.
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for pending notifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for scheduled notifications ready to send.
     */
    public function scopeReadyToSend($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->where(function ($q) {
                        $q->whereNull('scheduled_at')
                          ->orWhere('scheduled_at', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope for failed notifications ready for retry.
     */
    public function scopeReadyForRetry($query)
    {
        return $query->where('status', self::STATUS_FAILED)
                    ->where('delivery_attempts', '<', 'max_attempts')
                    ->where(function ($q) {
                        $q->whereNull('next_retry_at')
                          ->orWhere('next_retry_at', '<=', now());
                    });
    }

    /**
     * Scope for unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope for expired notifications.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
                    ->where('expires_at', '<', now());
    }

    /**
     * Check if notification is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if notification is sent.
     */
    public function isSent(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_DELIVERED, self::STATUS_READ]);
    }

    /**
     * Check if notification is read.
     */
    public function isRead(): bool
    {
        return $this->status === self::STATUS_READ;
    }

    /**
     * Check if notification is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if notification is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if notification can be retried.
     */
    public function canBeRetried(): bool
    {
        return $this->isFailed() && 
               $this->delivery_attempts < $this->max_attempts &&
               !$this->isExpired();
    }

    /**
     * Get status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_READ => 'Read',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown'
        };
    }

    /**
     * Get priority display name.
     */
    public function getPriorityDisplayAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_URGENT => 'Urgent',
            default => 'Medium'
        };
    }

    /**
     * Get priority color for UI.
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'green',
            self::PRIORITY_MEDIUM => 'blue',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_URGENT => 'red',
            default => 'gray'
        };
    }

    /**
     * Mark notification as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now()
        ]);
    }

    /**
     * Mark notification as delivered.
     */
    public function markAsDelivered(string $channel = null): void
    {
        $updateData = [
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now()
        ];
        
        if ($channel) {
            $channelStatus = $this->channel_status ?? [];
            $channelStatus[$channel] = [
                'status' => 'delivered',
                'delivered_at' => now()->toISOString()
            ];
            $updateData['channel_status'] = $channelStatus;
        }
        
        $this->update($updateData);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): void
    {
        if (!$this->isRead()) {
            $this->update([
                'status' => self::STATUS_READ,
                'read_at' => now()
            ]);
        }
    }

    /**
     * Mark notification as failed.
     */
    public function markAsFailed(string $error = null, string $channel = null): void
    {
        $failureReasons = $this->failure_reasons ?? [];
        
        if ($channel) {
            $failureReasons[$channel] = [
                'error' => $error,
                'failed_at' => now()->toISOString(),
                'attempt' => $this->delivery_attempts + 1
            ];
            
            $channelStatus = $this->channel_status ?? [];
            $channelStatus[$channel] = [
                'status' => 'failed',
                'error' => $error,
                'failed_at' => now()->toISOString()
            ];
        } else {
            $failureReasons['general'] = [
                'error' => $error,
                'failed_at' => now()->toISOString(),
                'attempt' => $this->delivery_attempts + 1
            ];
        }
        
        $updateData = [
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'delivery_attempts' => $this->delivery_attempts + 1,
            'failure_reasons' => $failureReasons,
            'last_error' => $error
        ];
        
        if ($channel) {
            $updateData['channel_status'] = $channelStatus;
        }
        
        // Set next retry time if retries are available
        if ($this->delivery_attempts + 1 < $this->max_attempts) {
            $retryDelay = $this->calculateRetryDelay($this->delivery_attempts + 1);
            $updateData['next_retry_at'] = now()->addMinutes($retryDelay);
        }
        
        $this->update($updateData);
    }

    /**
     * Calculate retry delay based on attempt number.
     */
    private function calculateRetryDelay(int $attempt): int
    {
        // Exponential backoff: 5, 15, 45 minutes
        return 5 * pow(3, $attempt - 1);
    }

    /**
     * Cancel notification.
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'metadata' => array_merge($this->metadata ?? [], [
                'cancelled_at' => now()->toISOString(),
                'cancel_reason' => $reason
            ])
        ]);
    }

    /**
     * Add tracking data.
     */
    public function addTrackingData(string $event, array $data = []): void
    {
        $trackingData = $this->tracking_data ?? [];
        $trackingData[] = array_merge($data, [
            'event' => $event,
            'timestamp' => now()->toISOString()
        ]);
        
        $this->update(['tracking_data' => $trackingData]);
    }

    /**
     * Get delivery success rate for channel.
     */
    public function getChannelSuccessRate(string $channel): ?float
    {
        $channelStatus = $this->channel_status[$channel] ?? null;
        
        if (!$channelStatus) {
            return null;
        }
        
        return $channelStatus['status'] === 'delivered' ? 100.0 : 0.0;
    }

    /**
     * Get time since creation.
     */
    public function getTimeSinceCreatedAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get time until scheduled.
     */
    public function getTimeUntilScheduledAttribute(): ?string
    {
        if (!$this->scheduled_at) {
            return null;
        }
        
        if ($this->scheduled_at->isPast()) {
            return 'Overdue';
        }
        
        return $this->scheduled_at->diffForHumans();
    }

    /**
     * Get time until expiration.
     */
    public function getTimeUntilExpirationAttribute(): ?string
    {
        if (!$this->expires_at) {
            return null;
        }
        
        if ($this->expires_at->isPast()) {
            return 'Expired';
        }
        
        return $this->expires_at->diffForHumans();
    }

    /**
     * Check if notification supports channel.
     */
    public function supportsChannel(string $channel): bool
    {
        return in_array($channel, $this->channels);
    }

    /**
     * Get channel status.
     */
    public function getChannelStatus(string $channel): ?array
    {
        return $this->channel_status[$channel] ?? null;
    }

    /**
     * Get successful channels.
     */
    public function getSuccessfulChannelsAttribute(): array
    {
        if (!$this->channel_status) {
            return [];
        }
        
        return array_keys(array_filter($this->channel_status, function ($status) {
            return $status['status'] === 'delivered';
        }));
    }

    /**
     * Get failed channels.
     */
    public function getFailedChannelsAttribute(): array
    {
        if (!$this->channel_status) {
            return [];
        }
        
        return array_keys(array_filter($this->channel_status, function ($status) {
            return $status['status'] === 'failed';
        }));
    }
}

