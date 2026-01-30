<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'invoice_number',
        'order_id',
        'customer_id',
        'merchant_id',
        'subtotal',
        'tax_amount',
        'platform_fee',
        'delivery_fee',
        'discount_amount',
        'total_amount',
        'currency',
        'status',
        'invoice_date',
        'due_date',
        'sent_at',
        'viewed_at',
        'paid_at',
        'billing_address',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_tax_id',
        'merchant_name',
        'merchant_tax_number',
        'merchant_address',
        'line_items',
        'zatca_uuid',
        'zatca_hash',
        'zatca_qr_code',
        'zatca_status',
        'zatca_submitted_at',
        'zatca_response',
        'payment_method',
        'payment_reference',
        'payment_metadata',
        'notes',
        'status_history',
        'email_history',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'paid_at' => 'datetime',
        'zatca_submitted_at' => 'datetime',
        'billing_address' => 'array',
        'merchant_address' => 'array',
        'line_items' => 'array',
        'zatca_qr_code' => 'array',
        'zatca_response' => 'array',
        'payment_metadata' => 'array',
        'status_history' => 'array',
        'email_history' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_VIEWED = 'viewed';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    /**
     * ZATCA status constants
     */
    const ZATCA_PENDING = 'pending';
    const ZATCA_SUBMITTED = 'submitted';
    const ZATCA_APPROVED = 'approved';
    const ZATCA_REJECTED = 'rejected';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($invoice) {
            if (!$invoice->invoice_number) {
                $invoice->invoice_number = $invoice->generateInvoiceNumber();
            }
            
            if (!$invoice->zatca_uuid) {
                $invoice->zatca_uuid = Str::uuid();
            }
        });
    }

    /**
     * Get the payments for the invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the successful payment for the invoice.
     */
    public function successfulPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', Payment::STATUS_COMPLETED);
    }

    /**
     * Scope for invoices by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for invoices by customer.
     */
    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope for invoices by merchant.
     */
    public function scopeByMerchant($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope for overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now()->toDateString())
                    ->whereIn('status', [self::STATUS_SENT, self::STATUS_VIEWED]);
    }

    /**
     * Scope for paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope for ZATCA approved invoices.
     */
    public function scopeZatcaApproved($query)
    {
        return $query->where('zatca_status', self::ZATCA_APPROVED);
    }

    /**
     * Check if invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date->isPast() && 
               in_array($this->status, [self::STATUS_SENT, self::STATUS_VIEWED]);
    }

    /**
     * Check if invoice can be paid.
     */
    public function canBePaid(): bool
    {
        return in_array($this->status, [
            self::STATUS_SENT,
            self::STATUS_VIEWED,
            self::STATUS_OVERDUE
        ]);
    }

    /**
     * Check if invoice can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_SENT,
            self::STATUS_VIEWED,
            self::STATUS_OVERDUE
        ]);
    }

    /**
     * Get status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SENT => 'Sent',
            self::STATUS_VIEWED => 'Viewed',
            self::STATUS_PAID => 'Paid',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
            default => 'Unknown'
        };
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_SENT => 'blue',
            self::STATUS_VIEWED => 'yellow',
            self::STATUS_PAID => 'green',
            self::STATUS_OVERDUE => 'red',
            self::STATUS_CANCELLED => 'red',
            self::STATUS_REFUNDED => 'orange',
            default => 'gray'
        };
    }

    /**
     * Get days until due.
     */
    public function getDaysUntilDueAttribute(): int
    {
        return now()->diffInDays($this->due_date, false);
    }

    /**
     * Get days overdue.
     */
    public function getDaysOverdueAttribute(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        
        return $this->due_date->diffInDays(now());
    }

    /**
     * Generate unique invoice number.
     */
    public function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $timestamp = now()->format('ymd');
        $random = strtoupper(Str::random(4));
        
        return $prefix . '-' . $timestamp . '-' . $random;
    }

    /**
     * Update invoice status with history tracking.
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
        
        $updateData = [
            'status' => $newStatus,
            'status_history' => $statusHistory
        ];
        
        // Set timestamps based on status
        switch ($newStatus) {
            case self::STATUS_SENT:
                $updateData['sent_at'] = now();
                break;
            case self::STATUS_VIEWED:
                if (!$this->viewed_at) {
                    $updateData['viewed_at'] = now();
                }
                break;
            case self::STATUS_PAID:
                $updateData['paid_at'] = now();
                break;
        }
        
        $this->update($updateData);
    }

    /**
     * Mark invoice as sent.
     */
    public function markAsSent(): void
    {
        $this->updateStatus(self::STATUS_SENT, 'Invoice sent to customer');
    }

    /**
     * Mark invoice as viewed.
     */
    public function markAsViewed(): void
    {
        if ($this->status === self::STATUS_SENT) {
            $this->updateStatus(self::STATUS_VIEWED, 'Invoice viewed by customer');
        }
    }

    /**
     * Mark invoice as paid.
     */
    public function markAsPaid(string $paymentReference = null): void
    {
        $updateData = [
            'payment_reference' => $paymentReference
        ];
        
        $this->update($updateData);
        $this->updateStatus(self::STATUS_PAID, 'Invoice paid');
    }

    /**
     * Cancel invoice.
     */
    public function cancel(string $reason = null): void
    {
        if (!$this->canBeCancelled()) {
            throw new \Exception('Invoice cannot be cancelled in current status');
        }
        
        $this->updateStatus(self::STATUS_CANCELLED, $reason ?? 'Invoice cancelled');
    }

    /**
     * Calculate tax amount (15% VAT in Saudi Arabia).
     */
    public function calculateTaxAmount(): float
    {
        $taxableAmount = $this->subtotal + $this->delivery_fee - $this->discount_amount;
        return round($taxableAmount * 0.15, 2);
    }

    /**
     * Recalculate total amount.
     */
    public function recalculateTotal(): void
    {
        $this->update([
            'tax_amount' => $this->calculateTaxAmount(),
            'total_amount' => $this->subtotal + $this->delivery_fee + $this->platform_fee + $this->calculateTaxAmount() - $this->discount_amount
        ]);
    }

    /**
     * Add line item to invoice.
     */
    public function addLineItem(array $item): void
    {
        $lineItems = $this->line_items ?? [];
        $lineItems[] = array_merge($item, [
            'id' => Str::uuid(),
            'added_at' => now()->toISOString()
        ]);
        
        $this->update(['line_items' => $lineItems]);
        $this->recalculateTotal();
    }

    /**
     * Remove line item from invoice.
     */
    public function removeLineItem(string $itemId): void
    {
        $lineItems = collect($this->line_items ?? [])
                    ->reject(function ($item) use ($itemId) {
                        return $item['id'] === $itemId;
                    })
                    ->values()
                    ->toArray();
        
        $this->update(['line_items' => $lineItems]);
        $this->recalculateTotal();
    }

    /**
     * Generate ZATCA QR code data.
     */
    public function generateZatcaQrCode(): array
    {
        return [
            'seller_name' => $this->merchant_name,
            'tax_number' => $this->merchant_tax_number,
            'timestamp' => $this->created_at->toISOString(),
            'total_amount' => $this->total_amount,
            'tax_amount' => $this->tax_amount,
            'invoice_hash' => $this->zatca_hash,
        ];
    }

    /**
     * Submit to ZATCA.
     */
    public function submitToZatca(): void
    {
        // This would integrate with ZATCA API
        // For now, we'll simulate the submission
        
        $this->update([
            'zatca_status' => self::ZATCA_SUBMITTED,
            'zatca_submitted_at' => now(),
            'zatca_qr_code' => $this->generateZatcaQrCode(),
            'zatca_hash' => hash('sha256', $this->invoice_number . $this->total_amount . $this->created_at->timestamp)
        ]);
    }

    /**
     * Add email to history.
     */
    public function addEmailHistory(string $type, string $recipient, bool $success = true, string $error = null): void
    {
        $emailHistory = $this->email_history ?? [];
        $emailHistory[] = [
            'type' => $type,
            'recipient' => $recipient,
            'sent_at' => now()->toISOString(),
            'success' => $success,
            'error' => $error
        ];
        
        $this->update(['email_history' => $emailHistory]);
    }

    /**
     * Get payment status.
     */
    public function getPaymentStatusAttribute(): string
    {
        if ($this->isPaid()) {
            return 'Paid';
        }
        
        $pendingPayment = $this->payments()->whereIn('status', [
            Payment::STATUS_PENDING,
            Payment::STATUS_PROCESSING
        ])->first();
        
        if ($pendingPayment) {
            return 'Payment Processing';
        }
        
        $failedPayments = $this->payments()->where('status', Payment::STATUS_FAILED)->count();
        if ($failedPayments > 0) {
            return 'Payment Failed';
        }
        
        return 'Unpaid';
    }

    /**
     * Get total paid amount.
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->payments()
                   ->where('status', Payment::STATUS_COMPLETED)
                   ->where('type', 'payment')
                   ->sum('amount');
    }

    /**
     * Get total refunded amount.
     */
    public function getTotalRefundedAttribute(): float
    {
        return $this->payments()
                   ->where('status', Payment::STATUS_COMPLETED)
                   ->whereIn('type', ['refund', 'partial_refund'])
                   ->sum('amount');
    }

    /**
     * Get remaining balance.
     */
    public function getRemainingBalanceAttribute(): float
    {
        return $this->total_amount - $this->total_paid + $this->total_refunded;
    }
}

