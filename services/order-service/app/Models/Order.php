<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_number',
        'part_request_id',
        'winning_bid_id',
        'customer_id',
        'merchant_id',
        'total_amount',
        'part_cost',
        'delivery_cost',
        'tax_amount',
        'platform_fee',
        'currency',
        'status',
        'delivery_address',
        'delivery_method',
        'tracking_number',
        'estimated_delivery',
        'actual_delivery',
        'payment_method',
        'payment_reference',
        'payment_due_at',
        'paid_at',
        'notes',
        'status_history',
        'customer_rating',
        'customer_feedback',
        'merchant_rating',
        'merchant_feedback',
        'zatca_invoice_hash',
        'zatca_metadata',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
        'part_cost' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'delivery_address' => 'array',
        'estimated_delivery' => 'datetime',
        'actual_delivery' => 'datetime',
        'payment_due_at' => 'datetime',
        'paid_at' => 'datetime',
        'notes' => 'array',
        'status_history' => 'array',
        'zatca_metadata' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_PAYMENT_CONFIRMED = 'payment_confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_DISPUTED = 'disputed';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($order) {
            if (!$order->order_number) {
                $order->order_number = $order->generateOrderNumber();
            }
        });
    }

    /**
     * Get the part request that owns the order.
     */
    public function partRequest(): BelongsTo
    {
        return $this->belongsTo(PartRequest::class);
    }

    /**
     * Get the winning bid that created the order.
     */
    public function winningBid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'winning_bid_id');
    }

    /**
     * Scope for orders by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for orders by customer.
     */
    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope for orders by merchant.
     */
    public function scopeByMerchant($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope for paid orders.
     */
    public function scopePaid($query)
    {
        return $query->whereNotNull('paid_at');
    }

    /**
     * Scope for overdue payments.
     */
    public function scopeOverduePayment($query)
    {
        return $query->where('status', self::STATUS_PENDING_PAYMENT)
                    ->where('payment_due_at', '<', now());
    }

    /**
     * Scope for orders requiring delivery.
     */
    public function scopeRequiringDelivery($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PAYMENT_CONFIRMED,
            self::STATUS_PROCESSING,
            self::STATUS_SHIPPED
        ]);
    }

    /**
     * Check if order is paid.
     */
    public function isPaid(): bool
    {
        return !is_null($this->paid_at);
    }

    /**
     * Check if payment is overdue.
     */
    public function isPaymentOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT 
               && $this->payment_due_at 
               && $this->payment_due_at->isPast();
    }

    /**
     * Check if order can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_PAYMENT,
            self::STATUS_PAYMENT_CONFIRMED,
            self::STATUS_PROCESSING
        ]);
    }

    /**
     * Check if order can be rated.
     */
    public function canBeRated(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Get status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING_PAYMENT => 'Pending Payment',
            self::STATUS_PAYMENT_CONFIRMED => 'Payment Confirmed',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_SHIPPED => 'Shipped',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_DISPUTED => 'Disputed',
            default => 'Unknown'
        };
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING_PAYMENT => 'yellow',
            self::STATUS_PAYMENT_CONFIRMED => 'blue',
            self::STATUS_PROCESSING => 'purple',
            self::STATUS_SHIPPED => 'indigo',
            self::STATUS_DELIVERED => 'green',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_CANCELLED => 'red',
            self::STATUS_REFUNDED => 'orange',
            self::STATUS_DISPUTED => 'red',
            default => 'gray'
        };
    }

    /**
     * Generate unique order number.
     */
    public function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $timestamp = now()->format('ymd');
        $random = strtoupper(Str::random(4));
        
        return $prefix . '-' . $timestamp . '-' . $random;
    }

    /**
     * Update order status with history tracking.
     */
    public function updateStatus(string $newStatus, string $note = null): void
    {
        $oldStatus = $this->status;
        
        // Add to status history
        $statusHistory = $this->status_history ?? [];
        $statusHistory[] = [
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'changed_at' => now()->toISOString(),
            'note' => $note
        ];
        
        $this->update([
            'status' => $newStatus,
            'status_history' => $statusHistory
        ]);
    }

    /**
     * Confirm payment.
     */
    public function confirmPayment(string $paymentMethod, string $paymentReference = null): void
    {
        $this->update([
            'status' => self::STATUS_PAYMENT_CONFIRMED,
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'paid_at' => now()
        ]);
        
        $this->updateStatus(self::STATUS_PAYMENT_CONFIRMED, 'Payment confirmed');
    }

    /**
     * Mark as shipped.
     */
    public function markAsShipped(string $trackingNumber = null, \DateTime $estimatedDelivery = null): void
    {
        $updateData = ['status' => self::STATUS_SHIPPED];
        
        if ($trackingNumber) {
            $updateData['tracking_number'] = $trackingNumber;
        }
        
        if ($estimatedDelivery) {
            $updateData['estimated_delivery'] = $estimatedDelivery;
        }
        
        $this->update($updateData);
        $this->updateStatus(self::STATUS_SHIPPED, 'Order shipped' . ($trackingNumber ? " with tracking: $trackingNumber" : ''));
    }

    /**
     * Mark as delivered.
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'actual_delivery' => now()
        ]);
        
        $this->updateStatus(self::STATUS_DELIVERED, 'Order delivered');
    }

    /**
     * Complete the order.
     */
    public function complete(): void
    {
        $this->updateStatus(self::STATUS_COMPLETED, 'Order completed');
    }

    /**
     * Cancel the order.
     */
    public function cancel(string $reason = null): void
    {
        if (!$this->canBeCancelled()) {
            throw new \Exception('Order cannot be cancelled in current status');
        }
        
        $this->updateStatus(self::STATUS_CANCELLED, $reason ?? 'Order cancelled');
    }

    /**
     * Add customer rating and feedback.
     */
    public function addCustomerRating(int $rating, string $feedback = null): void
    {
        if (!$this->canBeRated()) {
            throw new \Exception('Order cannot be rated in current status');
        }
        
        $this->update([
            'customer_rating' => $rating,
            'customer_feedback' => $feedback
        ]);
    }

    /**
     * Add merchant rating and feedback.
     */
    public function addMerchantRating(int $rating, string $feedback = null): void
    {
        if (!$this->canBeRated()) {
            throw new \Exception('Order cannot be rated in current status');
        }
        
        $this->update([
            'merchant_rating' => $rating,
            'merchant_feedback' => $feedback
        ]);
    }

    /**
     * Add note to order.
     */
    public function addNote(string $note, string $author = 'system'): void
    {
        $notes = $this->notes ?? [];
        $notes[] = [
            'note' => $note,
            'author' => $author,
            'created_at' => now()->toISOString()
        ];
        
        $this->update(['notes' => $notes]);
    }

    /**
     * Calculate platform fee (example: 5% of part cost).
     */
    public function calculatePlatformFee(): float
    {
        return round($this->part_cost * 0.05, 2);
    }

    /**
     * Calculate tax amount (example: 15% VAT in Saudi Arabia).
     */
    public function calculateTaxAmount(): float
    {
        $taxableAmount = $this->part_cost + $this->delivery_cost;
        return round($taxableAmount * 0.15, 2);
    }

    /**
     * Recalculate total amount.
     */
    public function recalculateTotal(): void
    {
        $this->update([
            'platform_fee' => $this->calculatePlatformFee(),
            'tax_amount' => $this->calculateTaxAmount(),
            'total_amount' => $this->part_cost + $this->delivery_cost + $this->calculatePlatformFee() + $this->calculateTaxAmount()
        ]);
    }
}

