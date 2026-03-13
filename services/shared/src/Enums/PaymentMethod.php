<?php

namespace Shared\Enums;

/**
 * Payment Methods Enum (PHP 8.3)
 * 
 * Defines available payment methods in the system
 */
enum PaymentMethod: string
{
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case PAYPAL = 'paypal';
    case STRIPE = 'stripe';
    case BANK_TRANSFER = 'bank_transfer';
    case APPLE_PAY = 'apple_pay';
    case GOOGLE_PAY = 'google_pay';
    case CRYPTOCURRENCY = 'cryptocurrency';
    case CASH_ON_DELIVERY = 'cash_on_delivery';

    /**
     * Get digital payment methods
     */
    public static function getDigitalMethods(): array
    {
        return [
            self::CREDIT_CARD,
            self::DEBIT_CARD,
            self::PAYPAL,
            self::STRIPE,
            self::APPLE_PAY,
            self::GOOGLE_PAY,
            self::CRYPTOCURRENCY
        ];
    }

    /**
     * Get instant payment methods
     */
    public static function getInstantMethods(): array
    {
        return [
            self::CREDIT_CARD,
            self::DEBIT_CARD,
            self::PAYPAL,
            self::STRIPE,
            self::APPLE_PAY,
            self::GOOGLE_PAY
        ];
    }

    /**
     * Get methods that require verification
     */
    public static function getVerificationRequiredMethods(): array
    {
        return [
            self::BANK_TRANSFER,
            self::CRYPTOCURRENCY
        ];
    }

    /**
     * Check if payment method is digital
     */
    public function isDigital(): bool
    {
        return in_array($this, self::getDigitalMethods());
    }

    /**
     * Check if payment method is instant
     */
    public function isInstant(): bool
    {
        return in_array($this, self::getInstantMethods());
    }

    /**
     * Check if payment method requires verification
     */
    public function requiresVerification(): bool
    {
        return in_array($this, self::getVerificationRequiredMethods());
    }

    /**
     * Get processing fee percentage
     */
    public function getProcessingFeePercentage(): float
    {
        return match($this) {
            self::CREDIT_CARD => 2.9,
            self::DEBIT_CARD => 2.4,
            self::PAYPAL => 3.4,
            self::STRIPE => 2.9,
            self::APPLE_PAY => 2.9,
            self::GOOGLE_PAY => 2.9,
            self::BANK_TRANSFER => 0.5,
            self::CRYPTOCURRENCY => 1.0,
            self::CASH_ON_DELIVERY => 0.0
        };
    }

    /**
     * Get payment method display name
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::CREDIT_CARD => 'Credit Card',
            self::DEBIT_CARD => 'Debit Card',
            self::PAYPAL => 'PayPal',
            self::STRIPE => 'Stripe',
            self::APPLE_PAY => 'Apple Pay',
            self::GOOGLE_PAY => 'Google Pay',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::CRYPTOCURRENCY => 'Cryptocurrency',
            self::CASH_ON_DELIVERY => 'Cash on Delivery'
        };
    }

    /**
     * Get supported currencies for payment method
     */
    public function getSupportedCurrencies(): array
    {
        return match($this) {
            self::CREDIT_CARD, self::DEBIT_CARD, self::STRIPE => ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
            self::PAYPAL => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'],
            self::APPLE_PAY, self::GOOGLE_PAY => ['USD', 'EUR', 'GBP'],
            self::BANK_TRANSFER => ['USD', 'EUR'],
            self::CRYPTOCURRENCY => ['BTC', 'ETH', 'USDT'],
            self::CASH_ON_DELIVERY => ['USD', 'EUR', 'GBP']
        };
    }
}
