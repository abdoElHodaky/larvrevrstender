<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'payment_method_reference',
        'customer_id',
        'type',
        'provider',
        'provider_method_id',
        'provider_customer_id',
        'card_last_four',
        'card_brand',
        'card_type',
        'card_country',
        'card_fingerprint',
        'card_exp_month',
        'card_exp_year',
        'bank_name',
        'bank_account_type',
        'bank_account_last_four',
        'bank_routing_number',
        'bank_country',
        'wallet_type',
        'wallet_account_id',
        'token',
        'encrypted_data',
        'fingerprint_hash',
        'status',
        'is_default',
        'is_verified',
        'verified_at',
        'expires_at',
        'last_used_at',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_address_line1',
        'billing_address_line2',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country',
        'metadata',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'encrypted_data' => 'array',
        'metadata' => 'array',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_default' => 'boolean',
        'is_verified' => 'boolean',
        'card_exp_month' => 'integer',
        'card_exp_year' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'token',
        'encrypted_data',
        'provider_method_id',
        'provider_customer_id',
    ];

    /**
     * Type constants.
     */
    const TYPE_CARD = 'card';
    const TYPE_BANK_ACCOUNT = 'bank_account';
    const TYPE_WALLET = 'wallet';
    const TYPE_CASH = 'cash';

    /**
     * Status constants.
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_EXPIRED = 'expired';
    const STATUS_FAILED_VERIFICATION = 'failed_verification';

    /**
     * Provider constants.
     */
    const PROVIDER_STRIPE = 'stripe';
    const PROVIDER_PAYPAL = 'paypal';
    const PROVIDER_MADA = 'mada';
    const PROVIDER_STC_PAY = 'stc_pay';

    /**
     * Get the customer that owns the payment method.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get payments that used this payment method.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope for active payment methods.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for verified payment methods.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for default payment methods.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for payment methods by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for payment methods by provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope for payment methods by customer.
     */
    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope for non-expired payment methods.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Check if payment method is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if payment method is verified.
     */
    public function isVerified(): bool
    {
        return $this->is_verified;
    }

    /**
     * Check if payment method is default.
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Check if payment method is expired.
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if payment method can be used.
     */
    public function canBeUsed(): bool
    {
        return $this->isActive() && 
               $this->isVerified() && 
               !$this->isExpired();
    }

    /**
     * Mark payment method as default.
     */
    public function markAsDefault(): void
    {
        // Remove default flag from other payment methods for this customer
        static::where('customer_id', $this->customer_id)
              ->where('id', '!=', $this->id)
              ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Mark payment method as verified.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Mark payment method as used.
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Deactivate payment method.
     */
    public function deactivate(string $reason = null): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['deactivation_reason'] = $reason;
        $metadata['deactivated_at'] = now()->toISOString();

        $this->update([
            'status' => self::STATUS_INACTIVE,
            'is_default' => false,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get display name for payment method.
     */
    public function getDisplayNameAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_CARD => $this->getCardDisplayName(),
            self::TYPE_BANK_ACCOUNT => $this->getBankAccountDisplayName(),
            self::TYPE_WALLET => $this->getWalletDisplayName(),
            default => ucfirst($this->type),
        };
    }

    /**
     * Get card display name.
     */
    private function getCardDisplayName(): string
    {
        $brand = ucfirst($this->card_brand ?? 'Card');
        $lastFour = $this->card_last_four;
        
        return $lastFour ? "{$brand} ending in {$lastFour}" : $brand;
    }

    /**
     * Get bank account display name.
     */
    private function getBankAccountDisplayName(): string
    {
        $bankName = $this->bank_name ?? 'Bank Account';
        $lastFour = $this->bank_account_last_four;
        
        return $lastFour ? "{$bankName} ending in {$lastFour}" : $bankName;
    }

    /**
     * Get wallet display name.
     */
    private function getWalletDisplayName(): string
    {
        return match ($this->wallet_type) {
            'apple_pay' => 'Apple Pay',
            'google_pay' => 'Google Pay',
            'samsung_pay' => 'Samsung Pay',
            'paypal' => 'PayPal',
            'stc_pay' => 'STC Pay',
            default => ucfirst($this->wallet_type ?? 'Digital Wallet'),
        };
    }

    /**
     * Get masked card number.
     */
    public function getMaskedCardNumberAttribute(): ?string
    {
        if ($this->type !== self::TYPE_CARD || !$this->card_last_four) {
            return null;
        }

        return '**** **** **** ' . $this->card_last_four;
    }

    /**
     * Get expiration display.
     */
    public function getExpirationDisplayAttribute(): ?string
    {
        if ($this->type !== self::TYPE_CARD || !$this->card_exp_month || !$this->card_exp_year) {
            return null;
        }

        return sprintf('%02d/%d', $this->card_exp_month, $this->card_exp_year);
    }

    /**
     * Get status display.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_FAILED_VERIFICATION => 'Failed Verification',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get provider display name.
     */
    public function getProviderDisplayAttribute(): string
    {
        return match ($this->provider) {
            self::PROVIDER_STRIPE => 'Stripe',
            self::PROVIDER_PAYPAL => 'PayPal',
            self::PROVIDER_MADA => 'Mada',
            self::PROVIDER_STC_PAY => 'STC Pay',
            default => ucfirst($this->provider),
        };
    }

    /**
     * Encrypt sensitive data.
     */
    public function encryptData(array $data): void
    {
        $this->update([
            'encrypted_data' => $data,
            'token' => Crypt::encryptString(json_encode($data)),
        ]);
    }

    /**
     * Decrypt sensitive data.
     */
    public function decryptData(): ?array
    {
        if (!$this->token) {
            return $this->encrypted_data;
        }

        try {
            return json_decode(Crypt::decryptString($this->token), true);
        } catch (\Exception $e) {
            return $this->encrypted_data;
        }
    }

    /**
     * Generate fingerprint hash for duplicate detection.
     */
    public function generateFingerprintHash(): string
    {
        $data = [
            'customer_id' => $this->customer_id,
            'type' => $this->type,
            'provider' => $this->provider,
        ];

        // Add type-specific data for fingerprinting
        switch ($this->type) {
            case self::TYPE_CARD:
                $data['card_fingerprint'] = $this->card_fingerprint;
                $data['card_last_four'] = $this->card_last_four;
                $data['card_exp_month'] = $this->card_exp_month;
                $data['card_exp_year'] = $this->card_exp_year;
                break;
                
            case self::TYPE_BANK_ACCOUNT:
                $data['bank_account_last_four'] = $this->bank_account_last_four;
                $data['bank_routing_number'] = $this->bank_routing_number;
                break;
                
            case self::TYPE_WALLET:
                $data['wallet_type'] = $this->wallet_type;
                $data['wallet_account_id'] = $this->wallet_account_id;
                break;
        }

        return hash('sha256', json_encode($data));
    }

    /**
     * Check for duplicate payment methods.
     */
    public function hasDuplicate(): bool
    {
        $fingerprintHash = $this->generateFingerprintHash();
        
        return static::where('customer_id', $this->customer_id)
                    ->where('fingerprint_hash', $fingerprintHash)
                    ->where('id', '!=', $this->id)
                    ->exists();
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($paymentMethod) {
            // Generate payment method reference
            if (!$paymentMethod->payment_method_reference) {
                $paymentMethod->payment_method_reference = 'PM-' . strtoupper(uniqid());
            }

            // Generate fingerprint hash
            $paymentMethod->fingerprint_hash = $paymentMethod->generateFingerprintHash();
        });

        static::updating(function ($paymentMethod) {
            // Update fingerprint hash if relevant data changed
            if ($paymentMethod->isDirty(['card_fingerprint', 'card_last_four', 'bank_account_last_four', 'wallet_account_id'])) {
                $paymentMethod->fingerprint_hash = $paymentMethod->generateFingerprintHash();
            }
        });
    }
}
