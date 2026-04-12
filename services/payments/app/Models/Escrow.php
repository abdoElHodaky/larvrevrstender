<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Escrow extends Model
{
    use HasFactory;

    // Status constants
    const STATUS_CREATED = 'created';
    const STATUS_FUNDED = 'funded';
    const STATUS_RELEASED = 'released';
    const STATUS_DISPUTED = 'disputed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_id',
        'payment_id',
        'buyer_id',
        'seller_id',
        'amount',
        'currency',
        'status',
        'hold_until',
        'released_at'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'hold_until' => 'datetime',
        'released_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the payment associated with this escrow.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get all transactions for this escrow.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(EscrowTransaction::class);
    }

    /**
     * Get all release conditions for this escrow.
     */
    public function releaseConditions(): HasMany
    {
        return $this->hasMany(EscrowReleaseCondition::class);
    }

    /**
     * Check if escrow can be released.
     */
    public function canBeReleased(): bool
    {
        if ($this->status !== self::STATUS_FUNDED) {
            return false;
        }

        // Check if all release conditions are met
        $unmetConditions = $this->releaseConditions()
            ->where('is_met', false)
            ->count();

        return $unmetConditions === 0;
    }

    /**
     * Check if escrow is expired based on hold_until date.
     */
    public function isExpired(): bool
    {
        return $this->hold_until && $this->hold_until->isPast();
    }

    /**
     * Check if escrow can be funded.
     */
    public function canBeFunded(): bool
    {
        return $this->status === self::STATUS_CREATED;
    }

    /**
     * Check if escrow can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_CREATED, self::STATUS_FUNDED]);
    }

    /**
     * Get the remaining refundable amount.
     */
    public function getRemainingAmount(): float
    {
        $releasedAmount = $this->transactions()
            ->whereIn('type', ['release', 'partial_release'])
            ->sum('amount');

        return $this->amount - $releasedAmount;
    }

    /**
     * Scope for active escrows (not released or cancelled).
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_RELEASED, self::STATUS_CANCELLED]);
    }

    /**
     * Scope for expired escrows.
     */
    public function scopeExpired($query)
    {
        return $query->where('hold_until', '<', now());
    }

    /**
     * Scope for escrows by buyer.
     */
    public function scopeForBuyer($query, int $buyerId)
    {
        return $query->where('buyer_id', $buyerId);
    }

    /**
     * Scope for escrows by seller.
     */
    public function scopeForSeller($query, int $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }
}

