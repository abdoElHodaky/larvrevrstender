<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhook extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'webhook_id',
        'provider',
        'event_type',
        'event_id',
        'payment_id',
        'payment_reference',
        'refund_id',
        'external_transaction_id',
        'headers',
        'payload',
        'parsed_data',
        'signature',
        'signature_algorithm',
        'status',
        'received_at',
        'processed_at',
        'processing_time_ms',
        'retry_count',
        'next_retry_at',
        'signature_verified',
        'signature_verified_at',
        'source_ip',
        'user_agent',
        'is_test_event',
        'processing_result',
        'processing_errors',
        'actions_taken',
        'requires_manual_review',
        'idempotency_key',
        'content_hash',
        'duplicate_of_webhook_id',
        'workflow_id',
        'workflow_step',
        'workflow_context',
        'alert_sent',
        'alert_sent_at',
        'notification_recipients',
        'audit_trail',
        'compliance_data',
        'metadata',
        'response_status_code',
        'response_body',
        'response_sent_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'headers' => 'array',
        'parsed_data' => 'array',
        'processing_errors' => 'array',
        'actions_taken' => 'array',
        'workflow_context' => 'array',
        'notification_recipients' => 'array',
        'audit_trail' => 'array',
        'compliance_data' => 'array',
        'metadata' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'signature_verified_at' => 'datetime',
        'alert_sent_at' => 'datetime',
        'response_sent_at' => 'datetime',
        'signature_verified' => 'boolean',
        'is_test_event' => 'boolean',
        'requires_manual_review' => 'boolean',
        'alert_sent' => 'boolean',
        'retry_count' => 'integer',
        'processing_time_ms' => 'integer',
        'response_status_code' => 'integer',
    ];

    /**
     * Status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_PROCESSED = 'processed';
    const STATUS_FAILED = 'failed';
    const STATUS_IGNORED = 'ignored';
    const STATUS_DUPLICATE = 'duplicate';

    /**
     * Provider constants.
     */
    const PROVIDER_STRIPE = 'stripe';
    const PROVIDER_PAYPAL = 'paypal';
    const PROVIDER_RAZORPAY = 'razorpay';
    const PROVIDER_SQUARE = 'square';
    const PROVIDER_MADA = 'mada';
    const PROVIDER_STC_PAY = 'stc_pay';

    /**
     * Get the payment that owns the webhook.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the refund that owns the webhook.
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'refund_id');
    }

    /**
     * Get the original webhook if this is a duplicate.
     */
    public function originalWebhook(): BelongsTo
    {
        return $this->belongsTo(PaymentWebhook::class, 'duplicate_of_webhook_id');
    }

    /**
     * Get duplicate webhooks.
     */
    public function duplicates()
    {
        return $this->hasMany(PaymentWebhook::class, 'duplicate_of_webhook_id');
    }

    /**
     * Scope for webhooks by provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope for webhooks by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for webhooks by event type.
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for processed webhooks.
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', self::STATUS_PROCESSED);
    }

    /**
     * Scope for failed webhooks.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for pending webhooks.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for webhooks requiring manual review.
     */
    public function scopeRequiringReview($query)
    {
        return $query->where('requires_manual_review', true);
    }

    /**
     * Scope for webhooks ready for retry.
     */
    public function scopeReadyForRetry($query)
    {
        return $query->where('status', self::STATUS_FAILED)
                    ->where('next_retry_at', '<=', now())
                    ->where('retry_count', '<', 5); // Max 5 retries
    }

    /**
     * Scope for test webhooks.
     */
    public function scopeTestEvents($query)
    {
        return $query->where('is_test_event', true);
    }

    /**
     * Scope for production webhooks.
     */
    public function scopeProductionEvents($query)
    {
        return $query->where('is_test_event', false);
    }

    /**
     * Scope for webhooks with signature verification.
     */
    public function scopeSignatureVerified($query, bool $verified = true)
    {
        return $query->where('signature_verified', $verified);
    }

    /**
     * Check if webhook is processed.
     */
    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    /**
     * Check if webhook failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if webhook is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if webhook is a duplicate.
     */
    public function isDuplicate(): bool
    {
        return $this->status === self::STATUS_DUPLICATE;
    }

    /**
     * Check if webhook requires manual review.
     */
    public function requiresReview(): bool
    {
        return $this->requires_manual_review;
    }

    /**
     * Check if webhook can be retried.
     */
    public function canBeRetried(): bool
    {
        return $this->isFailed() && 
               $this->retry_count < 5 && 
               (!$this->next_retry_at || $this->next_retry_at <= now());
    }

    /**
     * Mark webhook as processed.
     */
    public function markAsProcessed(string $result = null, array $actions = []): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSED,
            'processed_at' => now(),
            'processing_result' => $result,
            'actions_taken' => $actions,
            'processing_errors' => null,
        ]);
    }

    /**
     * Mark webhook as failed.
     */
    public function markAsFailed(string $reason, array $errors = [], \DateTime $nextRetryAt = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'processed_at' => now(),
            'processing_result' => $reason,
            'processing_errors' => $errors,
            'retry_count' => $this->retry_count + 1,
            'next_retry_at' => $nextRetryAt,
        ]);
    }

    /**
     * Mark webhook for manual review.
     */
    public function markForReview(string $reason): void
    {
        $this->update([
            'requires_manual_review' => true,
            'processing_result' => $reason,
            'audit_trail' => array_merge($this->audit_trail ?? [], [
                [
                    'action' => 'marked_for_review',
                    'reason' => $reason,
                    'timestamp' => now()->toISOString(),
                ],
            ]),
        ]);
    }

    /**
     * Add audit trail entry.
     */
    public function addAuditEntry(string $action, array $data = []): void
    {
        $auditTrail = $this->audit_trail ?? [];
        $auditTrail[] = array_merge([
            'action' => $action,
            'timestamp' => now()->toISOString(),
        ], $data);

        $this->update(['audit_trail' => $auditTrail]);
    }

    /**
     * Get processing duration in milliseconds.
     */
    public function getProcessingDurationAttribute(): ?int
    {
        if (!$this->received_at || !$this->processed_at) {
            return null;
        }

        return $this->processed_at->diffInMilliseconds($this->received_at);
    }

    /**
     * Get status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_PROCESSED => 'Processed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_IGNORED => 'Ignored',
            self::STATUS_DUPLICATE => 'Duplicate',
            default => 'Unknown'
        };
    }

    /**
     * Get provider display name.
     */
    public function getProviderDisplayAttribute(): string
    {
        return match ($this->provider) {
            self::PROVIDER_STRIPE => 'Stripe',
            self::PROVIDER_PAYPAL => 'PayPal',
            self::PROVIDER_RAZORPAY => 'Razorpay',
            self::PROVIDER_SQUARE => 'Square',
            self::PROVIDER_MADA => 'Mada',
            self::PROVIDER_STC_PAY => 'STC Pay',
            default => ucfirst($this->provider)
        };
    }

    /**
     * Get sanitized payload for display.
     */
    public function getSanitizedPayloadAttribute(): string
    {
        // Remove sensitive information from payload for display
        $payload = $this->payload;
        
        // Replace potential sensitive data patterns
        $patterns = [
            '/("card_number":\s*")[^"]*(")/i' => '$1****$2',
            '/("cvv":\s*")[^"]*(")/i' => '$1***$2',
            '/("ssn":\s*")[^"]*(")/i' => '$1***-**-****$2',
            '/("account_number":\s*")[^"]*(")/i' => '$1****$2',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $payload = preg_replace($pattern, $replacement, $payload);
        }

        return $payload;
    }

    /**
     * Get webhook age in human readable format.
     */
    public function getAgeAttribute(): string
    {
        return $this->received_at->diffForHumans();
    }

    /**
     * Check if webhook is recent (within last hour).
     */
    public function isRecent(): bool
    {
        return $this->received_at->gt(now()->subHour());
    }

    /**
     * Check if webhook is stale (older than 24 hours).
     */
    public function isStale(): bool
    {
        return $this->received_at->lt(now()->subDay());
    }

    /**
     * Get retry delay for next attempt.
     */
    public function getRetryDelay(): int
    {
        // Exponential backoff: 1min, 5min, 15min, 30min, 1hour
        $delays = [60, 300, 900, 1800, 3600];
        $index = min($this->retry_count, count($delays) - 1);
        
        return $delays[$index];
    }

    /**
     * Schedule next retry.
     */
    public function scheduleRetry(): void
    {
        $delay = $this->getRetryDelay();
        $this->update([
            'next_retry_at' => now()->addSeconds($delay),
        ]);
    }
}
