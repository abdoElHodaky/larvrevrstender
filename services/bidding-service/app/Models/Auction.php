<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Auction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'description',
        'vehicle_id',
        'starting_price',
        'reserve_price',
        'current_highest_bid',
        'status',
        'starts_at',
        'ends_at',
        'created_by',
        'winner_bid_id',
        'winning_amount',
        'completed_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'starting_price' => 'decimal:2',
        'reserve_price' => 'decimal:2',
        'current_highest_bid' => 'decimal:2',
        'winning_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the bids for the auction.
     */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    /**
     * Get the product images for the auction.
     */
    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Get the winning bid for the auction.
     */
    public function winningBid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'winner_bid_id');
    }

    /**
     * Get the primary product image for the auction.
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /**
     * Scope for active auctions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>', now());
    }

    /**
     * Scope for upcoming auctions
     */
    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>', now());
    }

    /**
     * Scope for ended auctions
     */
    public function scopeEnded($query)
    {
        return $query->where('ends_at', '<=', now());
    }

    /**
     * Check if auction is currently active
     */
    public function isActive(): bool
    {
        $now = now();
        return $this->status === 'active' && 
               $now->gte($this->starts_at) && 
               $now->lt($this->ends_at);
    }

    /**
     * Check if auction has ended
     */
    public function hasEnded(): bool
    {
        return now()->gte($this->ends_at);
    }

    /**
     * Check if reserve price is met
     */
    public function isReserveMet(): bool
    {
        if (!$this->reserve_price) {
            return true;
        }
        
        return $this->current_highest_bid >= $this->reserve_price;
    }

    /**
     * Get the highest bid for the auction.
     */
    public function highestBid()
    {
        return $this->bids()->orderBy('amount', 'desc')->first();
    }

    /**
     * Check if the auction has images.
     */
    public function hasImages(): bool
    {
        return $this->productImages()->exists();
    }

    /**
     * Get the primary image URL or default.
     */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        return $this->primaryImage?->url;
    }

    /**
     * Get the total number of images.
     */
    public function getImageCountAttribute(): int
    {
        return $this->productImages()->count();
    }

    /**
     * Get time remaining in seconds
     */
    public function getTimeRemainingAttribute(): int
    {
        if ($this->hasEnded()) {
            return 0;
        }
        
        return max(0, $this->ends_at->diffInSeconds(now()));
    }
}
