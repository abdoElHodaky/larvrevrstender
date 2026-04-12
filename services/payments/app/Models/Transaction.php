<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'transaction_reference',
        'payment_id',
        'invoice_id',
        'order_id',
        'customer_id',
        'merchant_id',
        'type',
        'status',
        'amount',
        'currency',
        'fee_amount',
        'net_amount',
        'description',
        'category',
        'tags',
        'custom_metadata',
        'payment_provider',
        'gateway_transaction_id',
        'gateway_response',
        'accounting_code',
        'reconciled',
        'reconciliation_reference',
        'processed_at',
        'settled_at',
        'processed_by',
        'parent_transaction_id',
        'external_reference',
        // Legacy fields for backward compatibility
        'gateway_id',
        'transaction_id',
        'reference_id',
        'failed_at',
        'failure_reason',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'tags' => 'array',
        'custom_metadata' => 'array',
        'gateway_response' => 'array',
        'processed_at' => 'datetime',
        'settled_at' => 'datetime',
        'failed_at' => 'datetime',
        'reconciled' => 'boolean',
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'metadata' => 'array', // Legacy field
    ];

    /**
     * Transaction type constants.
     */
    const TYPE_PAYMENT = 'payment';
    const TYPE_REFUND = 'refund';
    const TYPE_PARTIAL_REFUND = 'partial_refund';
    const TYPE_FEE = 'fee';
    const TYPE_CHARGEBACK = 'chargeback';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_AUTHORIZATION = 'authorization';
    const TYPE_CAPTURE = 'capture';

    /**
     * Transaction status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REVERSED = 'reversed';
    const STATUS_REFUNDED = 'refunded'; // Legacy

    /**
     * Transaction category constants.
     */
    const CATEGORY_REVENUE = 'revenue';
    const CATEGORY_EXPENSE = 'expense';
    const CATEGORY_TRANSFER = 'transfer';
    const CATEGORY_ADJUSTMENT = 'adjustment';

    /**
     * Get the payment that owns the transaction.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the customer that owns the transaction.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the merchant that owns the transaction.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    /**
     * Get the parent transaction (for refunds).
     */
    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'parent_transaction_id');
    }

    /**
     * Get child transactions (refunds of this transaction).
     */
    public function childTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'parent_transaction_id');
    }

    /**
     * Get the gateway used for this transaction (legacy).
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'gateway_id');
    }

    /**
     * Scope for transactions by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for transactions by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for transactions by customer.
     */
    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope for transactions by merchant.
     */
    public function scopeByMerchant($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope for transactions by date range.
     */
    public function scopeByDateRange($query, \DateTime $startDate, \DateTime $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for completed transactions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for pending transactions.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for failed transactions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for reconciled transactions.
     */
    public function scopeReconciled($query, bool $reconciled = true)
    {
        return $query->where('reconciled', $reconciled);
    }

    /**
     * Scope for transactions by provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('payment_provider', $provider);
    }

    /**
     * Scope for revenue transactions.
     */
    public function scopeRevenue($query)
    {
        return $query->where('category', self::CATEGORY_REVENUE)
                    ->whereIn('type', [self::TYPE_PAYMENT]);
    }

    /**
     * Scope for expense transactions.
     */
    public function scopeExpense($query)
    {
        return $query->where('category', self::CATEGORY_EXPENSE)
                    ->whereIn('type', [self::TYPE_FEE, self::TYPE_CHARGEBACK]);
    }

    /**
     * Legacy scopes for backward compatibility.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if transaction is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if transaction is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if transaction is reconciled.
     */
    public function isReconciled(): bool
    {
        return $this->reconciled;
    }

    /**
     * Check if transaction can be refunded.
     */
    public function canBeRefunded(): bool
    {
        return $this->isCompleted() && 
               $this->type === self::TYPE_PAYMENT &&
               $this->amount > 0;
    }

    /**
     * Get total refunded amount.
     */
    public function getTotalRefundedAmount(): float
    {
        return $this->childTransactions()
                   ->whereIn('type', [self::TYPE_REFUND, self::TYPE_PARTIAL_REFUND])
                   ->where('status', self::STATUS_COMPLETED)
                   ->sum('amount');
    }

    /**
     * Get remaining refundable amount.
     */
    public function getRemainingRefundableAmount(): float
    {
        if (!$this->canBeRefunded()) {
            return 0;
        }

        return $this->amount - $this->getTotalRefundedAmount();
    }

    /**
     * Mark transaction as completed.
     */
    public function markAsCompleted(array $gatewayResponse = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'processed_at' => now(),
            'gateway_response' => array_merge($this->gateway_response ?? [], $gatewayResponse),
        ]);
    }

    /**
     * Mark transaction as failed.
     */
    public function markAsFailed(string $reason, array $gatewayResponse = []): void
    {
        $metadata = $this->custom_metadata ?? [];
        $metadata['failure_reason'] = $reason;
        $metadata['failed_at'] = now()->toISOString();

        $this->update([
            'status' => self::STATUS_FAILED,
            'processed_at' => now(),
            'failed_at' => now(), // Legacy field
            'failure_reason' => $reason, // Legacy field
            'custom_metadata' => $metadata,
            'gateway_response' => array_merge($this->gateway_response ?? [], $gatewayResponse),
        ]);
    }

    /**
     * Mark transaction as reconciled.
     */
    public function markAsReconciled(string $reconciliationReference = null): void
    {
        $this->update([
            'reconciled' => true,
            'reconciliation_reference' => $reconciliationReference,
        ]);
    }

    /**
     * Add tag to transaction.
     */
    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    /**
     * Remove tag from transaction.
     */
    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        $tags = array_filter($tags, fn($t) => $t !== $tag);
        $this->update(['tags' => array_values($tags)]);
    }

    /**
     * Add metadata to transaction.
     */
    public function addMetadata(string $key, $value): void
    {
        $metadata = $this->custom_metadata ?? [];
        $metadata[$key] = $value;
        $this->update(['custom_metadata' => $metadata]);
    }

    /**
     * Get metadata value.
     */
    public function getMetadata(string $key, $default = null)
    {
        return ($this->custom_metadata ?? [])[$key] ?? $default;
    }

    /**
     * Legacy methods for backward compatibility.
     */
    public function isSuccessful(): bool
    {
        return $this->isCompleted();
    }

    /**
     * Get transaction type display name.
     */
    public function getTypeDisplayAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_PAYMENT => 'Payment',
            self::TYPE_REFUND => 'Refund',
            self::TYPE_PARTIAL_REFUND => 'Partial Refund',
            self::TYPE_FEE => 'Fee',
            self::TYPE_CHARGEBACK => 'Chargeback',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_AUTHORIZATION => 'Authorization',
            self::TYPE_CAPTURE => 'Capture',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get transaction status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REVERSED => 'Reversed',
            self::STATUS_REFUNDED => 'Refunded',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' ' . strtoupper($this->currency);
    }

    /**
     * Get formatted net amount.
     */
    public function getFormattedNetAmountAttribute(): string
    {
        return number_format($this->net_amount ?? $this->amount, 2) . ' ' . strtoupper($this->currency);
    }

    /**
     * Get processing time in seconds.
     */
    public function getProcessingTimeAttribute(): ?int
    {
        if (!$this->processed_at) {
            return null;
        }

        return $this->processed_at->diffInSeconds($this->created_at);
    }

    /**
     * Check if transaction is recent (within last hour).
     */
    public function isRecent(): bool
    {
        return $this->created_at->gt(now()->subHour());
    }

    /**
     * Check if transaction is stale (older than 30 days).
     */
    public function isStale(): bool
    {
        return $this->created_at->lt(now()->subDays(30));
    }

    /**
     * Get transaction age in human readable format.
     */
    public function getAgeAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            // Generate transaction reference
            if (!$transaction->transaction_reference) {
                $transaction->transaction_reference = 'TXN-' . strtoupper(uniqid());
            }

            // Calculate net amount if not provided
            if ($transaction->net_amount === null && $transaction->amount !== null) {
                $transaction->net_amount = $transaction->amount - ($transaction->fee_amount ?? 0);
            }

            // Set default category based on type
            if (!$transaction->category) {
                $transaction->category = match ($transaction->type) {
                    self::TYPE_PAYMENT => self::CATEGORY_REVENUE,
                    self::TYPE_FEE, self::TYPE_CHARGEBACK => self::CATEGORY_EXPENSE,
                    self::TYPE_REFUND, self::TYPE_PARTIAL_REFUND => self::CATEGORY_EXPENSE,
                    default => self::CATEGORY_ADJUSTMENT,
                };
            }
        });
    }
}
