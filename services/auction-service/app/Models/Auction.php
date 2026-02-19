<?php

namespace App\Models;

use App\RPC\Adapters\BiddingServiceAdapter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'ended_at',
        'created_by',
        'winner_user_id',
        'winning_bid_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'starting_price' => 'decimal:2',
        'reserve_price' => 'decimal:2',
        'current_highest_bid' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Get the bidding service client instance.
     */
    protected function getBiddingServiceClient(): BiddingServiceAdapter
    {
        return app(BiddingServiceAdapter::class);
    }

    /**
     * Get the bids for the auction via RPC call to bidding-service.
     * 
     * @param array $filters Optional filters for the bids
     * @return array
     */
    public function bids(array $filters = []): array
    {
        try {
            return $this->getBiddingServiceClient()->getAuctionBids($this->id, $filters);
        } catch (\Exception $e) {
            \Log::error('Failed to get auction bids via RPC', [
                'auction_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get the product images for the auction.
     */
    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)->ordered();
    }

    /**
     * Get the primary product image for the auction.
     */
    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->primary();
    }

    /**
     * Get the highest bid for the auction via RPC call to bidding-service.
     */
    public function highestBid(): ?array
    {
        try {
            return $this->getBiddingServiceClient()->getHighestBid($this->id);
        } catch (\Exception $e) {
            \Log::error('Failed to get highest bid via RPC', [
                'auction_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get the bid count for the auction via RPC call to bidding-service.
     */
    public function getBidCount(): int
    {
        try {
            return $this->getBiddingServiceClient()->getBidCount($this->id);
        } catch (\Exception $e) {
            \Log::error('Failed to get bid count via RPC', [
                'auction_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get bid history for the auction via RPC call to bidding-service.
     */
    public function getBidHistory(int $limit = 50, int $offset = 0): array
    {
        try {
            return $this->getBiddingServiceClient()->getBidHistory($this->id, $limit, $offset);
        } catch (\Exception $e) {
            \Log::error('Failed to get bid history via RPC', [
                'auction_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return [
                'data' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset
            ];
        }
    }

    /**
     * Get the product images for the auction.
     */
    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /**
     * Check if the auction is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' &&
               now()->between($this->starts_at, $this->ends_at);
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
        return $this->primaryImage?->cdn_url;
    }

    /**
     * Get the total number of images.
     */
    public function getImageCountAttribute(): int
    {
        return $this->productImages()->count();
    }
}
