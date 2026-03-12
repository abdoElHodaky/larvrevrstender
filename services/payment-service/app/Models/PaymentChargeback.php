<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PaymentChargeback extends Model
{
    protected $table = 'payment_chargebacks';

    protected $fillable = [
        'payment_id',
        'gateway',
        'gateway_chargeback_id',
        'amount',
        'currency',
        'reason_code',
        'reason_description',
        'status',
        'dispute_date',
        'response_due_date',
        'resolved_at',
        'reconciled_at',
        'reconciliation_notes',
        'gateway_data',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'dispute_date' => 'datetime',
        'response_due_date' => 'datetime',
        'resolved_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'gateway_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = true;

    // Status constants
    const STATUS_OPEN = 'open';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_WON = 'won';
    const STATUS_LOST = 'lost';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_EXPIRED = 'expired';
    const STATUS_RECONCILED = 'reconciled';

    const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_WON,
        self::STATUS_LOST,
        self::STATUS_ACCEPTED,
        self::STATUS_EXPIRED,
        self::STATUS_RECONCILED,
    ];

    /**
     * Get the payment that owns this chargeback
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
     * Scope to get unreconciled chargebacks
     */
    public function scopeUnreconciled(Builder $query): Builder
    {
        return $query->where('status', '!=', 'reconciled');
    }

    /**
     * Scope to get reconciled chargebacks
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
     * Scope to get open chargebacks
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope to get overdue chargebacks (past response due date)
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('response_due_date', '<', now())
                    ->whereIn('status', [self::STATUS_OPEN, self::STATUS_UNDER_REVIEW]);
    }

    /**
     * Check if chargeback is reconciled
     */
    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }

    /**
     * Check if chargeback is open
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if chargeback is resolved (won or lost)
     */
    public function isResolved(): bool
    {
        return in_array($this->status, [self::STATUS_WON, self::STATUS_LOST]);
    }

    /**
     * Check if chargeback is overdue
     */
    public function isOverdue(): bool
    {
        return $this->response_due_date && 
               $this->response_due_date->isPast() && 
               in_array($this->status, [self::STATUS_OPEN, self::STATUS_UNDER_REVIEW]);
    }

    /**
     * Mark chargeback as reconciled
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

        if (isset($gatewayData['resolved_at']) && $gatewayData['resolved_at']) {
            $updateData['resolved_at'] = $gatewayData['resolved_at'];
        }

        return $this->update($updateData);
    }

    /**
     * Get days until response due date
     */
    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->response_due_date) {
            return null;
        }

        return now()->diffInDays($this->response_due_date, false);
    }
}
