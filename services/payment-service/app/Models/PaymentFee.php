<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PaymentFee extends Model
{
    protected $table = 'payment_fees';

    protected $fillable = [
        'payment_id',
        'transaction_id',
        'gateway',
        'fee_type',
        'fee_amount',
        'transaction_amount',
        'currency',
        'transaction_date',
        'reconciliation_status',
        'reconciled_at',
        'reconciliation_notes',
        'gateway_data',
    ];

    protected $casts = [
        'fee_amount' => 'decimal:2',
        'transaction_amount' => 'decimal:2',
        'transaction_date' => 'date',
        'reconciled_at' => 'datetime',
        'gateway_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = true;

    // Fee type constants
    const TYPE_PROCESSING = 'processing';
    const TYPE_GATEWAY = 'gateway';
    const TYPE_INTERCHANGE = 'interchange';
    const TYPE_ASSESSMENT = 'assessment';
    const TYPE_CHARGEBACK = 'chargeback';
    const TYPE_REFUND = 'refund';
    const TYPE_MONTHLY = 'monthly';
    const TYPE_SETUP = 'setup';
    const TYPE_OTHER = 'other';

    const FEE_TYPES = [
        self::TYPE_PROCESSING,
        self::TYPE_GATEWAY,
        self::TYPE_INTERCHANGE,
        self::TYPE_ASSESSMENT,
        self::TYPE_CHARGEBACK,
        self::TYPE_REFUND,
        self::TYPE_MONTHLY,
        self::TYPE_SETUP,
        self::TYPE_OTHER,
    ];

    // Reconciliation status constants
    const STATUS_PENDING = 'pending';
    const STATUS_RECONCILED = 'reconciled';
    const STATUS_DISPUTED = 'disputed';

    const RECONCILIATION_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RECONCILED,
        self::STATUS_DISPUTED,
    ];

    /**
     * Get the payment that owns this fee
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
     * Scope to filter by transaction date
     */
    public function scopeForDate(Builder $query, $date): Builder
    {
        return $query->whereDate('transaction_date', $date);
    }

    /**
     * Scope to get unreconciled fees
     */
    public function scopeUnreconciled(Builder $query): Builder
    {
        return $query->where('reconciliation_status', '!=', 'reconciled');
    }

    /**
     * Scope to get reconciled fees
     */
    public function scopeReconciled(Builder $query): Builder
    {
        return $query->where('reconciliation_status', 'reconciled');
    }

    /**
     * Scope to filter by fee type
     */
    public function scopeOfType(Builder $query, string $feeType): Builder
    {
        return $query->where('fee_type', $feeType);
    }

    /**
     * Scope to filter by reconciliation status
     */
    public function scopeWithReconciliationStatus(Builder $query, string $status): Builder
    {
        return $query->where('reconciliation_status', $status);
    }

    /**
     * Scope to get processing fees
     */
    public function scopeProcessingFees(Builder $query): Builder
    {
        return $query->where('fee_type', self::TYPE_PROCESSING);
    }

    /**
     * Scope to get chargeback fees
     */
    public function scopeChargebackFees(Builder $query): Builder
    {
        return $query->where('fee_type', self::TYPE_CHARGEBACK);
    }

    /**
     * Check if fee is reconciled
     */
    public function isReconciled(): bool
    {
        return $this->reconciliation_status === 'reconciled';
    }

    /**
     * Check if fee is disputed
     */
    public function isDisputed(): bool
    {
        return $this->reconciliation_status === self::STATUS_DISPUTED;
    }

    /**
     * Mark fee as reconciled
     */
    public function markAsReconciled(array $gatewayData = [], string $notes = ''): bool
    {
        return $this->update([
            'reconciliation_status' => 'reconciled',
            'reconciled_at' => now(),
            'reconciliation_notes' => $notes ?: 'Reconciled via automated process',
            'gateway_data' => array_merge($this->gateway_data ?? [], $gatewayData),
        ]);
    }

    /**
     * Update fee amount from gateway data
     */
    public function updateFromGateway(array $gatewayData): bool
    {
        $updateData = [
            'gateway_data' => array_merge($this->gateway_data ?? [], $gatewayData),
        ];

        if (isset($gatewayData['fee_amount'])) {
            $updateData['fee_amount'] = $gatewayData['fee_amount'];
        }

        if (isset($gatewayData['transaction_amount'])) {
            $updateData['transaction_amount'] = $gatewayData['transaction_amount'];
        }

        return $this->update($updateData);
    }

    /**
     * Get the fee percentage of transaction amount
     */
    public function getFeePercentageAttribute(): float
    {
        if ($this->transaction_amount > 0) {
            return ($this->fee_amount / $this->transaction_amount) * 100;
        }
        return 0.0;
    }

    /**
     * Get the net amount after fees
     */
    public function getNetAmountAttribute(): float
    {
        return $this->transaction_amount - $this->fee_amount;
    }

    /**
     * Check if fee amount is within expected range (for validation)
     */
    public function isWithinExpectedRange(float $expectedFee, float $tolerance = 0.01): bool
    {
        return abs($this->fee_amount - $expectedFee) <= $tolerance;
    }
}
