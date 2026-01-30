<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Order Model
 * 
 * Represents a part request/order in the reverse tender system
 * 
 * @property int $id
 * @property int $customer_id
 * @property int $vehicle_id
 * @property string $order_number
 * @property string $status
 * @property string $title
 * @property string $description
 * @property array $part_details
 * @property float $budget_min
 * @property float $budget_max
 * @property array $delivery_location
 * @property bool $urgent
 * @property int $priority_score
 * @property \Carbon\Carbon $deadline
 * @property \Carbon\Carbon $published_at
 * @property \Carbon\Carbon $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Order extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'vehicle_id',
        'order_number',
        'status',
        'title',
        'description',
        'part_details',
        'budget_min',
        'budget_max',
        'delivery_location',
        'urgent',
        'priority_score',
        'deadline',
        'published_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'part_details' => 'array',
        'delivery_location' => 'array',
        'urgent' => 'boolean',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'deadline' => 'datetime',
        'published_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Order status constants
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_BIDDING = 'bidding';
    public const STATUS_AWARDED = 'awarded';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PUBLISHED,
            self::STATUS_BIDDING,
            self::STATUS_AWARDED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Get order images relationship
     */
    public function orderImages(): HasMany
    {
        return $this->hasMany(OrderImage::class);
    }

    /**
     * Get order status history relationship
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * Register media collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('part_photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

        $this->addMediaCollection('damage_photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

        $this->addMediaCollection('reference_images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

        $this->addMediaCollection('vin_photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    /**
     * Register media conversions
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumbnail')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->quality(85);

        $this->addMediaConversion('large')
            ->width(1200)
            ->height(1200)
            ->quality(85);
    }

    /**
     * Generate unique order number
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $timestamp = now()->format('Ymd');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return $prefix . $timestamp . $random;
    }

    /**
     * Check if order can be published
     */
    public function canBePublished(): bool
    {
        return $this->status === self::STATUS_DRAFT && 
               !empty($this->title) && 
               !empty($this->description) &&
               $this->budget_max > 0;
    }

    /**
     * Check if order can receive bids
     */
    public function canReceiveBids(): bool
    {
        return in_array($this->status, [self::STATUS_PUBLISHED, self::STATUS_BIDDING]) &&
               (!$this->deadline || $this->deadline->isFuture());
    }

    /**
     * Check if order is expired
     */
    public function isExpired(): bool
    {
        return $this->deadline && $this->deadline->isPast();
    }

    /**
     * Get priority label
     */
    public function getPriorityLabelAttribute(): string
    {
        return match (true) {
            $this->priority_score >= 8 => 'High',
            $this->priority_score >= 5 => 'Medium',
            default => 'Low'
        };
    }

    /**
     * Get status label with emoji
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => '📝 Draft',
            self::STATUS_PUBLISHED => '📢 Published',
            self::STATUS_BIDDING => '🎯 Bidding',
            self::STATUS_AWARDED => '🏆 Awarded',
            self::STATUS_COMPLETED => '✅ Completed',
            self::STATUS_CANCELLED => '❌ Cancelled',
            default => '❓ Unknown'
        };
    }

    /**
     * Scope for active orders
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_COMPLETED]);
    }

    /**
     * Scope for published orders
     */
    public function scopePublished($query)
    {
        return $query->whereIn('status', [self::STATUS_PUBLISHED, self::STATUS_BIDDING]);
    }

    /**
     * Scope for urgent orders
     */
    public function scopeUrgent($query)
    {
        return $query->where('urgent', true);
    }

    /**
     * Scope for orders by customer
     */
    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });
    }
}
