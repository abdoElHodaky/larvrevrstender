<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bid extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'part_request_id',
        'merchant_id',
        'amount',
        'currency',
        'description',
        'part_condition',
        'brand',
        'part_number',
        'warranty_months',
        'images',
        'specifications',
        'delivery_days',
        'delivery_cost',
        'delivery_options',
        'status',
        'rejection_reason',
        'expires_at',
        'terms_conditions',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'images' => 'array',
        'specifications' => 'array',
        'delivery_options' => 'array',
        'terms_conditions' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_WITHDRAWN = 'withdrawn';
    const STATUS_EXPIRED = 'expired';

    /**
     * Get the part request that owns the bid.
     */
    public function partRequest(): BelongsTo
    {
        return $this->belongsTo(PartRequest::class);
    }

    /**
     * Get the order created from this bid (if accepted).
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'winning_bid_id');
    }

    /**
     * Scope for pending bids.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for accepted bids.
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * Scope for bids by merchant.
     */
    public function scopeByMerchant($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope for bids within amount range.
     */
    public function scopeWithinAmountRange($query, float $minAmount, float $maxAmount = null)
    {
        $query->where('amount', '>=', $minAmount);
        
        if ($maxAmount) {
            $query->where('amount', '<=', $maxAmount);
        }
        
        return $query;
    }

    /**
     * Scope for non-expired bids.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Check if bid is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING && !$this->isExpired();
    }

    /**
     * Check if bid is accepted.
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Check if bid is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if bid can be accepted.
     */
    public function canBeAccepted(): bool
    {
        return $this->isPending() && $this->partRequest->canReceiveBids();
    }

    /**
     * Check if bid can be withdrawn.
     */
    public function canBeWithdrawn(): bool
    {
        return $this->isPending();
    }

    /**
     * Get total cost including delivery.
     */
    public function getTotalCostAttribute(): float
    {
        return $this->amount + ($this->delivery_cost ?? 0);
    }

    /**
     * Get warranty display.
     */
    public function getWarrantyDisplayAttribute(): string
    {
        if (!$this->warranty_months) {
            return 'No warranty';
        }
        
        if ($this->warranty_months < 12) {
            return $this->warranty_months . ' month' . ($this->warranty_months > 1 ? 's' : '');
        }
        
        $years = floor($this->warranty_months / 12);
        $remainingMonths = $this->warranty_months % 12;
        
        $display = $years . ' year' . ($years > 1 ? 's' : '');
        
        if ($remainingMonths > 0) {
            $display .= ' ' . $remainingMonths . ' month' . ($remainingMonths > 1 ? 's' : '');
        }
        
        return $display;
    }

    /**
     * Get delivery time display.
     */
    public function getDeliveryTimeDisplayAttribute(): string
    {
        if (!$this->delivery_days) {
            return 'Not specified';
        }
        
        if ($this->delivery_days === 1) {
            return 'Next day';
        }
        
        if ($this->delivery_days <= 7) {
            return $this->delivery_days . ' days';
        }
        
        $weeks = floor($this->delivery_days / 7);
        $remainingDays = $this->delivery_days % 7;
        
        $display = $weeks . ' week' . ($weeks > 1 ? 's' : '');
        
        if ($remainingDays > 0) {
            $display .= ' ' . $remainingDays . ' day' . ($remainingDays > 1 ? 's' : '');
        }
        
        return $display;
    }

    /**
     * Accept the bid.
     */
    public function accept(): void
    {
        if (!$this->canBeAccepted()) {
            throw new \Exception('Bid cannot be accepted');
        }
        
        // Update bid status
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'metadata' => array_merge($this->metadata ?? [], [
                'accepted_at' => now()->toISOString()
            ])
        ]);
        
        // Reject all other bids for this request
        $this->partRequest->bids()
            ->where('id', '!=', $this->id)
            ->where('status', self::STATUS_PENDING)
            ->update([
                'status' => self::STATUS_REJECTED,
                'rejection_reason' => 'Another bid was accepted'
            ]);
        
        // Close the part request
        $this->partRequest->close('Winning bid selected');
    }

    /**
     * Reject the bid.
     */
    public function reject(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'metadata' => array_merge($this->metadata ?? [], [
                'rejected_at' => now()->toISOString()
            ])
        ]);
    }

    /**
     * Withdraw the bid.
     */
    public function withdraw(string $reason = null): void
    {
        if (!$this->canBeWithdrawn()) {
            throw new \Exception('Bid cannot be withdrawn');
        }
        
        $this->update([
            'status' => self::STATUS_WITHDRAWN,
            'metadata' => array_merge($this->metadata ?? [], [
                'withdrawn_at' => now()->toISOString(),
                'withdraw_reason' => $reason
            ])
        ]);
        
        // Update part request bid statistics
        $this->partRequest->updateBidStats();
    }

    /**
     * Check if bid matches request budget.
     */
    public function isWithinBudget(): bool
    {
        $request = $this->partRequest;
        
        if (!$request->budget_min && !$request->budget_max) {
            return true; // No budget constraints
        }
        
        $totalCost = $this->total_cost;
        
        if ($request->budget_min && $totalCost < $request->budget_min) {
            return false;
        }
        
        if ($request->budget_max && $totalCost > $request->budget_max) {
            return false;
        }
        
        return true;
    }

    /**
     * Get competitive ranking among all bids for the request.
     */
    public function getCompetitiveRankingAttribute(): int
    {
        return $this->partRequest->activeBids()
                   ->where('amount', '<', $this->amount)
                   ->count() + 1;
    }
}

