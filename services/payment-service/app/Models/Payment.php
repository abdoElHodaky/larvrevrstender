<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'payment_reference',
        'invoice_id',
        'order_id',
        'customer_id',
        'merchant_id',
        'amount',
        'currency',
        'type',
        'payment_method',
        'payment_provider',
        'provider_transaction_id',
        'payment_details',
        'card_last_four',
        'card_brand',
        'card_token',
        'status',
        'initiated_at',
        'processed_at',
        'completed_at',
        'failed_at',
        'failure_reason',
        'failure_code',
        'failure_message',
        'refunded_amount',
        'refunded_at',
        'refund_reason',
        'risk_assessment',
        'requires_3ds',
        '3ds_status',
        'gateway_request',
        'gateway_response',
        'webhook_data',
        'gateway_fee',
        'platform_fee',
        'net_amount',
        'reconciled',
        'reconciled_at',
        'reconciliation_reference',
        'zatca_payment_reference',
        'zatca_payment_data',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'initiated_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'requires_3ds' => 'boolean',
        'reconciled' => 'boolean',
        'payment_details' => 'array',
        'risk_assessment' => 'array',
        'gateway_request' => 'array',
        'gateway_response' => 'array',
        'webhook_data' => 'array',
        'zatca_payment_data' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * Type constants
     */
    const TYPE_PAYMENT = 'payment';
    const TYPE_REFUND = 'refund';
    const TYPE_PARTIAL_REFUND = 'partial_refund';

    /**
     * Payment method constants
     */
    const METHOD_CARD = 'card';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_WALLET = 'wallet';
    const METHOD_CASH = 'cash';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($payment) {
            if (!$payment->payment_reference) {
                $payment->payment_reference = $payment->generatePaymentReference();
            }
        });
    }

    /**
     * Get the invoice that owns the payment.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Scope for payments by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for payments by customer.
     */
    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope for payments by merchant.
     */
    public function scopeByMerchant($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope for payments by method.
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope for successful payments.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed payments.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for pending payments.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Scope for unreconciled payments.
     */
    public function scopeUnreconciled($query)
    {
        return $query->where('reconciled', false);
    }

    /**
     * Check if payment is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Check if payment failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if payment can be refunded.
     */
    public function canBeRefunded(): bool
    {
        return $this->isSuccessful() && 
               $this->type === self::TYPE_PAYMENT &&
               $this->refunded_amount < $this->amount;
    }

    /**
     * Get status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_PARTIALLY_REFUNDED => 'Partially Refunded',
            default => 'Unknown'
        };
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_PROCESSING => 'blue',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_FAILED => 'red',
            self::STATUS_CANCELLED => 'gray',
            self::STATUS_REFUNDED => 'orange',
            self::STATUS_PARTIALLY_REFUNDED => 'orange',
            default => 'gray'
        };
    }

    /**
     * Get payment method display name.
     */
    public function getPaymentMethodDisplayAttribute(): string
    {
        return match($this->payment_method) {
            self::METHOD_CARD => 'Credit/Debit Card',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_WALLET => 'Digital Wallet',
            self::METHOD_CASH => 'Cash',
            default => ucfirst(str_replace('_', ' ', $this->payment_method))
        };
    }

    /**
     * Get masked card number.
     */
    public function getMaskedCardNumberAttribute(): ?string
    {
        if (!$this->card_last_four) {
            return null;
        }
        
        return '**** **** **** ' . $this->card_last_four;
    }

    /**
     * Get refundable amount.
     */
    public function getRefundableAmountAttribute(): float
    {
        return $this->amount - $this->refunded_amount;
    }

    /**
     * Generate unique payment reference.
     */
    public function generatePaymentReference(): string
    {
        $prefix = 'PAY';
        $timestamp = now()->format('ymdHis');
        $random = strtoupper(Str::random(4));
        
        return $prefix . '-' . $timestamp . '-' . $random;
    }

    /**
     * Mark payment as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'processed_at' => now()
        ]);
    }

    /**
     * Mark payment as completed.
     */
    public function markAsCompleted(array $gatewayResponse = null): void
    {
        $updateData = [
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now()
        ];
        
        if ($gatewayResponse) {
            $updateData['gateway_response'] = $gatewayResponse;
        }
        
        // Calculate net amount after fees
        $updateData['net_amount'] = $this->amount - $this->gateway_fee - $this->platform_fee;
        
        $this->update($updateData);
        
        // Mark associated invoice as paid
        if ($this->invoice && $this->type === self::TYPE_PAYMENT) {
            $this->invoice->markAsPaid($this->payment_reference);
        }
    }

    /**
     * Mark payment as failed.
     */
    public function markAsFailed(string $reason, string $code = null, string $message = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'failure_reason' => $reason,
            'failure_code' => $code,
            'failure_message' => $message
        ]);
    }

    /**
     * Cancel payment.
     */
    public function cancel(string $reason = null): void
    {
        if (!$this->isPending()) {
            throw new \Exception('Only pending payments can be cancelled');
        }
        
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'metadata' => array_merge($this->metadata ?? [], [
                'cancelled_at' => now()->toISOString(),
                'cancel_reason' => $reason
            ])
        ]);
    }

    /**
     * Process refund.
     */
    public function processRefund(float $amount, string $reason = null): self
    {
        if (!$this->canBeRefunded()) {
            throw new \Exception('Payment cannot be refunded');
        }
        
        if ($amount > $this->refundable_amount) {
            throw new \Exception('Refund amount exceeds refundable amount');
        }
        
        // Create refund payment record
        $refund = self::create([
            'invoice_id' => $this->invoice_id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'merchant_id' => $this->merchant_id,
            'amount' => $amount,
            'currency' => $this->currency,
            'type' => $amount >= $this->refundable_amount ? self::TYPE_REFUND : self::TYPE_PARTIAL_REFUND,
            'payment_method' => $this->payment_method,
            'payment_provider' => $this->payment_provider,
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'refund_reason' => $reason,
            'metadata' => [
                'original_payment_id' => $this->id,
                'original_payment_reference' => $this->payment_reference
            ]
        ]);
        
        // Update original payment
        $newRefundedAmount = $this->refunded_amount + $amount;
        $newStatus = $newRefundedAmount >= $this->amount ? self::STATUS_REFUNDED : self::STATUS_PARTIALLY_REFUNDED;
        
        $this->update([
            'refunded_amount' => $newRefundedAmount,
            'refunded_at' => now(),
            'status' => $newStatus
        ]);
        
        return $refund;
    }

    /**
     * Mark as reconciled.
     */
    public function markAsReconciled(string $reconciliationReference = null): void
    {
        $this->update([
            'reconciled' => true,
            'reconciled_at' => now(),
            'reconciliation_reference' => $reconciliationReference
        ]);
    }

    /**
     * Add webhook data.
     */
    public function addWebhookData(array $webhookData): void
    {
        $existingData = $this->webhook_data ?? [];
        $existingData[] = array_merge($webhookData, [
            'received_at' => now()->toISOString()
        ]);
        
        $this->update(['webhook_data' => $existingData]);
    }

    /**
     * Calculate processing time in seconds.
     */
    public function getProcessingTimeAttribute(): ?int
    {
        if (!$this->initiated_at || !$this->completed_at) {
            return null;
        }
        
        return $this->completed_at->diffInSeconds($this->initiated_at);
    }

    /**
     * Get risk score.
     */
    public function getRiskScoreAttribute(): ?float
    {
        return $this->risk_assessment['score'] ?? null;
    }

    /**
     * Check if payment requires 3D Secure.
     */
    public function requires3ds(): bool
    {
        return $this->requires_3ds;
    }

    /**
     * Get 3D Secure status display.
     */
    public function get3dsStatusDisplayAttribute(): ?string
    {
        if (!$this->requires_3ds) {
            return null;
        }
        
        return match($this->{'3ds_status'}) {
            'authenticated' => 'Authenticated',
            'attempted' => 'Attempted',
            'failed' => 'Failed',
            'unavailable' => 'Unavailable',
            default => 'Unknown'
        };
    }
}

