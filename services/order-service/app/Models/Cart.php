<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'status',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const STATUS_ACTIVE = 'active';

    const STATUS_ABANDONED = 'abandoned';

    const STATUS_CONVERTED = 'converted';

    const STATUS_EXPIRED = 'expired';

    /**
     * Get the cart items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Get the total amount of the cart.
     */
    public function getTotalAmountAttribute(): float
    {
        return $this->items->sum('total_price');
    }

    /**
     * Get the total quantity of items in the cart.
     */
    public function getTotalQuantityAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * Get the total tax amount.
     */
    public function getTotalTaxAttribute(): float
    {
        return $this->items->sum('tax_amount');
    }

    /**
     * Get the total discount amount.
     */
    public function getTotalDiscountAttribute(): float
    {
        return $this->items->sum('discount_amount');
    }

    /**
     * Check if cart is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Scope for active carts.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for expired carts.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
            ->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Add item to cart or update quantity if exists.
     */
    public function addItem(array $itemData): CartItem
    {
        $existingItem = $this->items()
            ->where('part_request_id', $itemData['part_request_id'])
            ->first();

        if ($existingItem) {
            $existingItem->increment('quantity', $itemData['quantity'] ?? 1);
            $existingItem->update([
                'total_price' => $existingItem->unit_price * $existingItem->quantity,
            ]);

            return $existingItem;
        }

        return $this->items()->create($itemData);
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(int $itemId): bool
    {
        return $this->items()->where('id', $itemId)->delete() > 0;
    }

    /**
     * Clear all items from cart.
     */
    public function clearItems(): void
    {
        $this->items()->delete();
    }
}
