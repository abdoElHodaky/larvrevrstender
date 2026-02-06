<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'gateway_id',
        'transaction_id',
        'reference_id',
        'type',
        'amount',
        'currency',
        'status',
        'gateway_response',
        'gateway_transaction_id',
        'processed_at',
        'failed_at',
        'failure_reason',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const TYPE_PAYMENT = 'payment';

    const TYPE_REFUND = 'refund';

    const TYPE_PARTIAL_REFUND = 'partial_refund';

    const TYPE_CHARGEBACK = 'chargeback';

    const TYPE_AUTHORIZATION = 'authorization';

    const TYPE_CAPTURE = 'capture';

    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSING = 'processing';

    const STATUS_COMPLETED = 'completed';

    const STATUS_FAILED = 'failed';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_REFUNDED = 'refunded';

    /**
     * Get the payment that owns the transaction.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the gateway used for this transaction.
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'gateway_id');
    }

    /**
     * Scope for successful transactions.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed transactions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for pending transactions.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope by transaction type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if transaction is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if transaction failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Mark transaction as completed.
     */
    public function markAsCompleted(array $gatewayResponse = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'processed_at' => now(),
            'gateway_response' => $gatewayResponse,
        ]);
    }

    /**
     * Mark transaction as failed.
     */
    public function markAsFailed(string $reason, array $gatewayResponse = []): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'failure_reason' => $reason,
            'gateway_response' => $gatewayResponse,
        ]);
    }
}
