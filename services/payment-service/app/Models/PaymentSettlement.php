<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PaymentSettlement extends Model
{
    protected $table = 'payment_settlements';

    protected $fillable = [
        'gateway',
        'settlement_id',
        'settlement_date',
        'net_amount',
        'gross_amount',
        'fee_amount',
        'currency',
        'reconciliation_status',
        'reconciled_at',
        'reconciliation_notes',
        'gateway_data',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'net_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'reconciled_at' => 'datetime',
        'gateway_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Scope to filter by gateway
     */
    public function scopeForGateway(Builder $query, string $gateway): Builder
    {
        return $query->where('gateway', $gateway);
    }

    /**
     * Scope to filter by settlement date
     */
    public function scopeForDate(Builder $query, $date): Builder
    {
        return $query->whereDate('settlement_date', $date);
    }

    /**
     * Scope to get unreconciled settlements
     */
    public function scopeUnreconciled(Builder $query): Builder
    {
        return $query->where('reconciliation_status', '!=', 'reconciled');
    }

    /**
     * Scope to get reconciled settlements
     */
    public function scopeReconciled(Builder $query): Builder
    {
        return $query->where('reconciliation_status', 'reconciled');
    }

    /**
     * Check if settlement is reconciled
     */
    public function isReconciled(): bool
    {
        return $this->reconciliation_status === 'reconciled';
    }

    /**
     * Mark settlement as reconciled
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
     * Get the calculated fee percentage
     */
    public function getFeePercentageAttribute(): float
    {
        if ($this->gross_amount > 0) {
            return ($this->fee_amount / $this->gross_amount) * 100;
        }
        return 0.0;
    }
}
