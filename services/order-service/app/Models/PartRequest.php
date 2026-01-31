<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class PartRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'vehicle_id',
        'title',
        'description',
        'part_category',
        'part_number',
        'brand_preference',
        'condition_preference',
        'budget_min',
        'budget_max',
        'urgency',
        'images',
        'specifications',
        'location_preferences',
        'status',
        'expires_at',
        'bid_count',
        'lowest_bid',
        'highest_bid',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'images' => 'array',
        'specifications' => 'array',
        'location_preferences' => 'array',
        'metadata' => 'array',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'lowest_bid' => 'decimal:2',
        'highest_bid' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_ACTIVE = 'active';
    const STATUS_CLOSED = 'closed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Urgency constants
     */
    const URGENCY_LOW = 'low';
    const URGENCY_MEDIUM = 'medium';
    const URGENCY_HIGH = 'high';
    const URGENCY_URGENT = 'urgent';

    /**
     * Condition constants
     */
    const CONDITION_NEW = 'new';
    const CONDITION_USED = 'used';
    const CONDITION_REFURBISHED = 'refurbished';
    const CONDITION_ANY = 'any';

    /**
     * Get the bids for the part request.
     */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    /**
     * Get active bids for the part request.
     */
    public function activeBids(): HasMany
    {
        return $this->hasMany(Bid::class)->where('status', Bid::STATUS_PENDING);
    }

    /**
     * Get the winning bid (if any).
     */
    public function winningBid(): HasOne
    {
        return $this->hasOne(Bid::class)->where('status', Bid::STATUS_ACCEPTED);
    }

    /**
     * Get the order created from this request.
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    /**
     * Scope for active requests.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for requests by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('part_category', $category);
    }

    /**
     * Scope for requests by urgency.
     */
    public function scopeByUrgency($query, string $urgency)
    {
        return $query->where('urgency', $urgency);
    }

    /**
     * Scope for requests within budget range.
     */
    public function scopeWithinBudget($query, float $minBudget, float $maxBudget = null)
    {
        $query->where(function ($q) use ($minBudget, $maxBudget) {
            $q->where('budget_min', '<=', $maxBudget ?? $minBudget)
              ->where('budget_max', '>=', $minBudget);
        });
        
        return $query;
    }

    /**
     * Scope for non-expired requests.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Check if request is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && !$this->isExpired();
    }

    /**
     * Check if request is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if request can receive bids.
     */
    public function canReceiveBids(): bool
    {
        return $this->isActive() && !$this->hasWinningBid();
    }

    /**
     * Check if request has a winning bid.
     */
    public function hasWinningBid(): bool
    {
        return $this->winningBid()->exists();
    }

    /**
     * Get budget range display.
     */
    public function getBudgetRangeAttribute(): string
    {
        if (!$this->budget_min && !$this->budget_max) {
            return 'Not specified';
        }
        
        if ($this->budget_min && $this->budget_max) {
            return number_format($this->budget_min, 2) . ' - ' . number_format($this->budget_max, 2) . ' SAR';
        }
        
        if ($this->budget_min) {
            return 'From ' . number_format($this->budget_min, 2) . ' SAR';
        }
        
        return 'Up to ' . number_format($this->budget_max, 2) . ' SAR';
    }

    /**
     * Get urgency color for UI.
     */
    public function getUrgencyColorAttribute(): string
    {
        return match($this->urgency) {
            self::URGENCY_LOW => 'green',
            self::URGENCY_MEDIUM => 'yellow',
            self::URGENCY_HIGH => 'orange',
            self::URGENCY_URGENT => 'red',
            default => 'gray'
        };
    }

    /**
     * Get time remaining until expiration.
     */
    public function getTimeRemainingAttribute(): ?string
    {
        if (!$this->expires_at) {
            return null;
        }
        
        if ($this->isExpired()) {
            return 'Expired';
        }
        
        return $this->expires_at->diffForHumans();
    }

    /**
     * Update bid statistics.
     */
    public function updateBidStats(): void
    {
        $activeBids = $this->activeBids;
        
        $this->update([
            'bid_count' => $activeBids->count(),
            'lowest_bid' => $activeBids->min('amount'),
            'highest_bid' => $activeBids->max('amount'),
        ]);
    }

    /**
     * Close the request.
     */
    public function close(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_CLOSED,
            'metadata' => array_merge($this->metadata ?? [], [
                'closed_at' => now()->toISOString(),
                'close_reason' => $reason
            ])
        ]);
    }

    /**
     * Cancel the request.
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'metadata' => array_merge($this->metadata ?? [], [
                'cancelled_at' => now()->toISOString(),
                'cancel_reason' => $reason
            ])
        ]);
    }

    /**
     * Extend expiration date.
     */
    public function extendExpiration(int $days): void
    {
        $newExpiration = $this->expires_at 
            ? $this->expires_at->addDays($days)
            : now()->addDays($days);
            
        $this->update(['expires_at' => $newExpiration]);
    }
}

