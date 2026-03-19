<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
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
     * Get the cart that owns the item.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
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
     * Update the total price based on quantity and unit price.
     */
    public function updateTotalPrice(): void
    {
        $this->update([
            'total_price' => $this->unit_price * $this->quantity,
        ]);
    }

    /**
     * Increase quantity by specified amount.
     */
    public function increaseQuantity(int $amount = 1): void
    {
        $this->increment('quantity', $amount);
        $this->updateTotalPrice();
    }

    /**
     * Decrease quantity by specified amount.
     */
    public function decreaseQuantity(int $amount = 1): void
    {
        $newQuantity = max(0, $this->quantity - $amount);

        if ($newQuantity === 0) {
            $this->delete();
        } else {
            $this->update(['quantity' => $newQuantity]);
            $this->updateTotalPrice();
        }
    }
}
