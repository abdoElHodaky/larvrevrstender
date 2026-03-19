<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentGateway extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'provider',
        'is_active',
        'is_default',
        'supported_currencies',
        'supported_countries',
        'configuration',
        'fees',
        'limits',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'supported_currencies' => 'array',
        'supported_countries' => 'array',
        'configuration' => 'array',
        'fees' => 'array',
        'limits' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const PROVIDER_STRIPE = 'stripe';

    const PROVIDER_PAYPAL = 'paypal';

    const PROVIDER_RAZORPAY = 'razorpay';

    const PROVIDER_MOYASAR = 'moyasar';

    const PROVIDER_HYPERPAY = 'hyperpay';

    const PROVIDER_TABBY = 'tabby';

    /**
     * Get the transactions for this gateway.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'gateway_id');
    }

    /**
     * Get the payments processed through this gateway.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'gateway_id');
    }

    /**
     * Scope for active gateways.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default gateway.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope by supported currency.
     */
    public function scopeSupportsCurrency($query, string $currency)
    {
        return $query->whereJsonContains('supported_currencies', $currency);
    }

    /**
     * Scope by supported country.
     */
    public function scopeSupportsCountry($query, string $country)
    {
        return $query->whereJsonContains('supported_countries', $country);
    }

    /**
     * Check if gateway supports a currency.
     */
    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->supported_currencies ?? []);
    }

    /**
     * Check if gateway supports a country.
     */
    public function supportsCountry(string $country): bool
    {
        return in_array(strtoupper($country), $this->supported_countries ?? []);
    }

    /**
     * Get the processing fee for an amount.
     */
    public function getProcessingFee(float $amount): float
    {
        $fees = $this->fees ?? [];

        $fixedFee = $fees['fixed'] ?? 0;
        $percentageFee = ($fees['percentage'] ?? 0) / 100;

        return $fixedFee + ($amount * $percentageFee);
    }

    /**
     * Check if amount is within limits.
     */
    public function isAmountWithinLimits(float $amount): bool
    {
        $limits = $this->limits ?? [];

        $minAmount = $limits['min'] ?? 0;
        $maxAmount = $limits['max'] ?? PHP_FLOAT_MAX;

        return $amount >= $minAmount && $amount <= $maxAmount;
    }

    /**
     * Set as default gateway and unset others.
     */
    public function setAsDefault(): void
    {
        static::where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }
}
