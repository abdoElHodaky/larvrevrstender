<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscrowReleaseCondition extends Model
{
    use HasFactory;

    // Condition type constants
    const TYPE_DELIVERY_CONFIRMED = 'delivery_confirmed';
    const TYPE_INSPECTION_PASSED = 'inspection_passed';
    const TYPE_TIME_ELAPSED = 'time_elapsed';
    const TYPE_MANUAL_APPROVAL = 'manual_approval';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'escrow_id',
        'condition_type',
        'condition_data',
        'is_met',
        'met_at'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'condition_data' => 'array',
        'is_met' => 'boolean',
        'met_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the escrow that owns this condition.
     */
    public function escrow(): BelongsTo
    {
        return $this->belongsTo(Escrow::class);
    }

    /**
     * Mark this condition as met.
     */
    public function markAsMet(): void
    {
        $this->update([
            'is_met' => true,
            'met_at' => now()
        ]);
    }

    /**
     * Check if this condition is automatically checkable.
     */
    public function isAutoCheckable(): bool
    {
        return $this->condition_type === self::TYPE_TIME_ELAPSED;
    }

    /**
     * Check if time-based condition is met.
     */
    public function checkTimeCondition(): bool
    {
        if ($this->condition_type !== self::TYPE_TIME_ELAPSED) {
            return false;
        }

        $days = $this->condition_data['days'] ?? 7;
        $targetDate = $this->escrow->created_at->addDays($days);

        return now()->greaterThanOrEqualTo($targetDate);
    }

    /**
     * Scope for met conditions.
     */
    public function scopeMet($query)
    {
        return $query->where('is_met', true);
    }

    /**
     * Scope for unmet conditions.
     */
    public function scopeUnmet($query)
    {
        return $query->where('is_met', false);
    }

    /**
     * Scope for conditions by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('condition_type', $type);
    }

    /**
     * Scope for delivery confirmation conditions.
     */
    public function scopeDeliveryConfirmation($query)
    {
        return $query->where('condition_type', self::TYPE_DELIVERY_CONFIRMED);
    }

    /**
     * Scope for inspection conditions.
     */
    public function scopeInspection($query)
    {
        return $query->where('condition_type', self::TYPE_INSPECTION_PASSED);
    }

    /**
     * Scope for time-based conditions.
     */
    public function scopeTimeElapsed($query)
    {
        return $query->where('condition_type', self::TYPE_TIME_ELAPSED);
    }

    /**
     * Scope for manual approval conditions.
     */
    public function scopeManualApproval($query)
    {
        return $query->where('condition_type', self::TYPE_MANUAL_APPROVAL);
    }
}

