<?php

namespace App\Models;

use App\RPC\Adapters\BiddingServiceAdapter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shared\Traits\EloquentDatabaseFailover;

class Auction extends Model
{
    use HasFactory, EloquentDatabaseFailover;

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
     * Get the bidding service adapter instance.
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

    /**
     * Get auction status using modern PHP 8.3 match expressions
     */
    public function getAuctionStatusAttribute(): string
    {
        return match(true) {
            $this->status === 'draft' => 'Draft',
            $this->status === 'scheduled' && now()->lt($this->starts_at) => 'Scheduled',
            $this->status === 'active' && $this->isActive() => 'Active',
            $this->status === 'ended' || now()->gt($this->ends_at) => 'Ended',
            $this->status === 'cancelled' => 'Cancelled',
            default => 'Unknown'
        };
    }

    /**
     * Find active auctions safely with database failover protection
     * Modern PHP 8.3 & Laravel 12 implementation
     */
    public static function getActiveAuctionsSafely(): \Illuminate\Database\Eloquent\Collection
    {
        $instance = new static();
        return $instance->executeFailsafeQuery('get_active_auctions', function() {
            return static::where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now())
                ->orderBy('ends_at')
                ->get();
        });
    }

    /**
     * Create auction safely with database failover protection
     * Modern PHP 8.3 typed parameters
     */
    public static function createAuctionSafely(
        string $title,
        string $description,
        int $vehicleId,
        float $startingPrice,
        ?float $reservePrice,
        \Carbon\Carbon $startsAt,
        \Carbon\Carbon $endsAt,
        int $createdBy
    ): static {
        $instance = new static();
        return $instance->createSafely([
            'title' => $title,
            'description' => $description,
            'vehicle_id' => $vehicleId,
            'starting_price' => $startingPrice,
            'reserve_price' => $reservePrice,
            'status' => 'draft',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Get auction analytics safely with modern collection methods
     * PHP 8.3 match expressions for period handling
     */
    public static function getAuctionAnalyticsSafely(string $period = 'month'): array
    {
        $instance = new static();
        
        $dateRange = match($period) {
            'day' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => throw new \InvalidArgumentException("Unsupported period: {$period}")
        };
        
        return $instance->executeFailsafeQuery('get_auction_analytics', function() use ($dateRange) {
            $auctions = static::whereBetween('created_at', $dateRange)->get();
            
            return [
                'total_auctions' => $auctions->count(),
                'active_auctions' => $auctions->where('status', 'active')->count(),
                'ended_auctions' => $auctions->where('status', 'ended')->count(),
                'cancelled_auctions' => $auctions->where('status', 'cancelled')->count(),
                'total_starting_value' => $auctions->sum('starting_price'),
                'total_current_value' => $auctions->sum('current_highest_bid'),
                'average_starting_price' => $auctions->avg('starting_price'),
                'average_current_bid' => $auctions->avg('current_highest_bid'),
                'auctions_by_status' => $auctions->groupBy('status')
                    ->map(fn($group) => $group->count())
                    ->toArray(),
                'daily_breakdown' => $auctions->groupBy(fn($auction) => $auction->created_at->format('Y-m-d'))
                    ->map(function($dailyAuctions) {
                        return [
                            'count' => $dailyAuctions->count(),
                            'total_value' => $dailyAuctions->sum('starting_price'),
                            'active' => $dailyAuctions->where('status', 'active')->count(),
                        ];
                    })
                    ->toArray(),
            ];
        });
    }

    /**
     * Get auction performance metrics safely
     * Modern implementation with database failover protection
     */
    public function getPerformanceMetricsSafely(): array
    {
        return $this->executeFailsafeQuery('get_auction_performance', function() {
            $bidCount = $this->getBidCount();
            $highestBid = $this->highestBid();
            
            $bidIncrease = $this->starting_price > 0 ? 
                (($this->current_highest_bid - $this->starting_price) / $this->starting_price) * 100 : 0;
            
            $reserveMet = $this->reserve_price ? 
                $this->current_highest_bid >= $this->reserve_price : true;
            
            return [
                'auction_id' => $this->id,
                'title' => $this->title,
                'status' => $this->auction_status,
                'bid_count' => $bidCount,
                'starting_price' => $this->starting_price,
                'current_highest_bid' => $this->current_highest_bid,
                'reserve_price' => $this->reserve_price,
                'bid_increase_percentage' => round($bidIncrease, 2),
                'reserve_met' => $reserveMet,
                'time_remaining' => $this->ends_at->gt(now()) ? 
                    $this->ends_at->diffForHumans() : 'Ended',
                'duration_hours' => $this->starts_at->diffInHours($this->ends_at),
                'elapsed_hours' => $this->starts_at->diffInHours(now()),
                'completion_percentage' => $this->starts_at->diffInHours(now()) / 
                    $this->starts_at->diffInHours($this->ends_at) * 100,
                'image_count' => $this->image_count,
                'has_images' => $this->hasImages(),
                'primary_image_url' => $this->primary_image_url,
            ];
        });
    }

    /**
     * Update auction status safely with modern PHP 8.3 features
     */
    public function updateStatusSafely(string $newStatus, ?array $additionalData = null): bool
    {
        $validStatuses = ['draft', 'scheduled', 'active', 'ended', 'cancelled'];
        
        if (!in_array($newStatus, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid auction status: {$newStatus}");
        }
        
        $updateData = ['status' => $newStatus];
        
        // Add status-specific data using match expression
        $statusData = match($newStatus) {
            'ended' => ['ended_at' => now()],
            'active' => ['started_at' => now()],
            default => []
        };
        
        if ($additionalData) {
            $updateData = array_merge($updateData, $statusData, $additionalData);
        } else {
            $updateData = array_merge($updateData, $statusData);
        }
        
        return $this->updateSafely($updateData);
    }

    /**
     * Get upcoming auctions safely with modern collection methods
     */
    public static function getUpcomingAuctionsSafely(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $instance = new static();
        return $instance->executeFailsafeQuery('get_upcoming_auctions', function() use ($limit) {
            return static::where('status', 'scheduled')
                ->where('starts_at', '>', now())
                ->orderBy('starts_at')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get ending soon auctions safely
     * Modern PHP 8.3 implementation with typed parameters
     */
    public static function getEndingSoonSafely(int $hoursThreshold = 24): \Illuminate\Database\Eloquent\Collection
    {
        $instance = new static();
        return $instance->executeFailsafeQuery('get_ending_soon_auctions', function() use ($hoursThreshold) {
            return static::where('status', 'active')
                ->where('ends_at', '>', now())
                ->where('ends_at', '<=', now()->addHours($hoursThreshold))
                ->orderBy('ends_at')
                ->get();
        });
    }

    /**
     * Bulk update auction statuses safely
     * Modern Laravel collection methods for efficient processing
     */
    public static function bulkUpdateStatusesSafely(array $auctionIds, string $newStatus): int
    {
        $instance = new static();
        return $instance->executeFailsafeTransaction('bulk_update_auction_statuses', function() use ($auctionIds, $newStatus) {
            $updateData = ['status' => $newStatus];
            
            // Add status-specific data
            if ($newStatus === 'ended') {
                $updateData['ended_at'] = now();
            }
            
            return static::whereIn('id', $auctionIds)->update($updateData);
        });
    }

    /**
     * Get auction dashboard data safely
     * Modern implementation with PHP 8.3 match expressions
     */
    public static function getDashboardDataSafely(): array
    {
        $instance = new static();
        
        return $instance->executeFailsafeQuery('get_auction_dashboard', function() {
            $today = now()->startOfDay();
            $thisWeek = now()->startOfWeek();
            $thisMonth = now()->startOfMonth();
            
            $allAuctions = static::all();
            $todayAuctions = static::where('created_at', '>=', $today)->get();
            $weekAuctions = static::where('created_at', '>=', $thisWeek)->get();
            $monthAuctions = static::where('created_at', '>=', $thisMonth)->get();
            
            return [
                'overview' => [
                    'total_auctions' => $allAuctions->count(),
                    'active_auctions' => $allAuctions->where('status', 'active')->count(),
                    'scheduled_auctions' => $allAuctions->where('status', 'scheduled')->count(),
                    'ended_auctions' => $allAuctions->where('status', 'ended')->count(),
                ],
                'today' => [
                    'new_auctions' => $todayAuctions->count(),
                    'total_value' => $todayAuctions->sum('starting_price'),
                    'active_count' => $todayAuctions->where('status', 'active')->count(),
                ],
                'this_week' => [
                    'new_auctions' => $weekAuctions->count(),
                    'total_value' => $weekAuctions->sum('starting_price'),
                    'ended_count' => $weekAuctions->where('status', 'ended')->count(),
                ],
                'this_month' => [
                    'new_auctions' => $monthAuctions->count(),
                    'total_value' => $monthAuctions->sum('starting_price'),
                    'average_value' => $monthAuctions->avg('starting_price'),
                ],
                'status_distribution' => $allAuctions->groupBy('status')
                    ->map(fn($group) => $group->count())
                    ->toArray(),
                'ending_soon' => static::getEndingSoonSafely(6)->count(), // Next 6 hours
                'recent_activity' => $allAuctions->sortByDesc('updated_at')
                    ->take(5)
                    ->map(function($auction) {
                        return [
                            'id' => $auction->id,
                            'title' => $auction->title,
                            'status' => $auction->auction_status,
                            'updated_at' => $auction->updated_at->diffForHumans(),
                        ];
                    })
                    ->values()
                    ->toArray(),
            ];
        });
    }
}
