<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PaymentReconciliationSummary extends Model
{
    protected $table = 'payment_reconciliation_summaries';

    protected $fillable = [
        'reconciliation_date',
        'gateways_processed',
        'transactions_reconciled',
        'discrepancies_found',
        'discrepancies_resolved',
        'total_amount_reconciled',
        'processing_time_ms',
        'job_id',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'gateways_processed' => 'integer',
        'transactions_reconciled' => 'integer',
        'discrepancies_found' => 'integer',
        'discrepancies_resolved' => 'integer',
        'total_amount_reconciled' => 'decimal:2',
        'processing_time_ms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = true;

    /**
     * Scope to filter by reconciliation date
     */
    public function scopeForDate(Builder $query, $date): Builder
    {
        return $query->whereDate('reconciliation_date', $date);
    }

    /**
     * Scope to get recent summaries
     */
    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('reconciliation_date', '>=', now()->subDays($days));
    }

    /**
     * Scope to filter by job ID
     */
    public function scopeForJob(Builder $query, string $jobId): Builder
    {
        return $query->where('job_id', $jobId);
    }

    /**
     * Get the discrepancy resolution rate
     */
    public function getResolutionRateAttribute(): float
    {
        if ($this->discrepancies_found > 0) {
            return ($this->discrepancies_resolved / $this->discrepancies_found) * 100;
        }
        return 100.0; // No discrepancies found means 100% resolution rate
    }

    /**
     * Get processing time in seconds
     */
    public function getProcessingTimeSecondsAttribute(): float
    {
        return $this->processing_time_ms / 1000;
    }

    /**
     * Check if reconciliation was successful (no unresolved discrepancies)
     */
    public function isSuccessful(): bool
    {
        return $this->discrepancies_found === $this->discrepancies_resolved;
    }

    /**
     * Get average processing time per transaction
     */
    public function getAverageProcessingTimeAttribute(): float
    {
        if ($this->transactions_reconciled > 0) {
            return $this->processing_time_ms / $this->transactions_reconciled;
        }
        return 0.0;
    }
}
