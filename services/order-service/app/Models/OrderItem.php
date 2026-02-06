<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'part_request_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_price',
        'total_price',
        'tax_amount',
        'discount_amount',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order that owns the item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the part request associated with this item.
     */
    public function partRequest(): BelongsTo
    {
        return $this->belongsTo(PartRequest::class);
    }

    /**
     * Calculate the final price after tax and discount.
     */
    public function getFinalPriceAttribute(): float
    {
        return $this->total_price + $this->tax_amount - $this->discount_amount;
    }

    /**
     * Calculate the unit price after discount.
     */
    public function getDiscountedUnitPriceAttribute(): float
    {
        if ($this->quantity > 0) {
            return ($this->total_price - $this->discount_amount) / $this->quantity;
        }

        return $this->unit_price;
    }
}
