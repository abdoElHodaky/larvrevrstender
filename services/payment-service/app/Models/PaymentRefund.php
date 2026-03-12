<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PaymentRefund extends Model
{
    protected $table = 'payment_refunds';

    protected $fillable = [
        'payment_id',
        'gateway',
        'gateway_refund_id',
        'amount',
        'currency',
        'reason',
        'status',
        'processed_at',
        'reconciled_at',
        'reconciliation_notes',
        'gateway_data',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'gateway_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = true;

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_RECONCILED = 'reconciled';

    const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
        self::STATUS_RECONCILED,
    ];

    /**
     * Get the payment that owns this refund
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Scope to filter by gateway
     */
    public function scopeForGateway(Builder $query, string $gateway): Builder
    {
        return $query->where('gateway', $gateway);
    }

    /**
     * Scope to filter by date
     */
    public function scopeForDate(Builder $query, $date): Builder
    {
        return $query->whereDate('created_at', $date);
    }

    /**
     * Scope to get unreconciled refunds
     */
    public function scopeUnreconciled(Builder $query): Builder
    {
        return $query->where('status', '!=', 'reconciled');
    }

    /**
     * Scope to get reconciled refunds
     */
    public function scopeReconciled(Builder $query): Builder
    {
        return $query->where('status', 'reconciled');
    }

    /**
     * Scope to filter by status
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Check if refund is reconciled
     */
    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }

    /**
     * Check if refund is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if refund is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if refund failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Mark refund as reconciled
     */
    public function markAsReconciled(array $gatewayData = [], string $notes = ''): bool
    {
        return $this->update([
            'status' => 'reconciled',
            'reconciled_at' => now(),
            'reconciliation_notes' => $notes ?: 'Reconciled via automated process',
            'gateway_data' => array_merge($this->gateway_data ?? [], $gatewayData),
        ]);
    }

    /**
     * Update status from gateway data
     */
    public function updateFromGateway(array $gatewayData): bool
    {
        $updateData = [
            'gateway_data' => array_merge($this->gateway_data ?? [], $gatewayData),
        ];

        if (isset($gatewayData['status'])) {
            $updateData['status'] = $gatewayData['status'];
        }

        if (isset($gatewayData['processed_at'])) {
            $updateData['processed_at'] = $gatewayData['processed_at'];
        }

        return $this->update($updateData);
    }
}
