<?php

namespace App\Services;

use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentMethodService
{
    /**
     * Create a new payment method.
     */
    public function createPaymentMethod(int $customerId, array $data): PaymentMethod
    {
        $this->validatePaymentMethodData($data);

        // Check for duplicates
        if ($this->isDuplicate($customerId, $data)) {
            throw new \Exception('A payment method with these details already exists.');
        }

        // Create payment method with provider tokenization
        $paymentMethodData = $this->preparePaymentMethodData($customerId, $data);
        
        // Tokenize with payment provider if needed
        if (isset($data['card_number']) || isset($data['bank_account_number'])) {
            $tokenizationResult = $this->tokenizeWithProvider($data);
            $paymentMethodData = array_merge($paymentMethodData, $tokenizationResult);
        }

        $paymentMethod = PaymentMethod::create($paymentMethodData);

        // Set as default if it's the first payment method
        if ($this->isFirstPaymentMethod($customerId)) {
            $paymentMethod->markAsDefault();
        }

        // Verify payment method if required
        if ($this->requiresVerification($paymentMethod)) {
            $this->initiateVerification($paymentMethod);
        } else {
            $paymentMethod->markAsVerified();
        }

        Log::info('Payment method created', [
            'payment_method_id' => $paymentMethod->id,
            'customer_id' => $customerId,
            'type' => $paymentMethod->type,
            'provider' => $paymentMethod->provider,
        ]);

        return $paymentMethod;
    }

    /**
     * Get customer payment methods.
     */
    public function getCustomerPaymentMethods(int $customerId, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = PaymentMethod::byCustomer($customerId);

        // Apply filters
        if (isset($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (isset($filters['provider'])) {
            $query->byProvider($filters['provider']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_verified'])) {
            $query->where('is_verified', $filters['is_verified']);
        }

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->active()->notExpired();
        }

        return $query->orderBy('is_default', 'desc')
                    ->orderBy('last_used_at', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->get();
    }

    /**
     * Update payment method.
     */
    public function updatePaymentMethod(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $allowedFields = [
            'billing_name',
            'billing_email',
            'billing_phone',
            'billing_address_line1',
            'billing_address_line2',
            'billing_city',
            'billing_state',
            'billing_postal_code',
            'billing_country',
            'card_exp_month',
            'card_exp_year',
            'metadata',
        ];

        $updateData = array_intersect_key($data, array_flip($allowedFields));

        // Update expiration date if provided
        if (isset($updateData['card_exp_month']) || isset($updateData['card_exp_year'])) {
            $this->updateCardExpiration($paymentMethod, $updateData);
        }

        $paymentMethod->update($updateData);

        Log::info('Payment method updated', [
            'payment_method_id' => $paymentMethod->id,
            'updated_fields' => array_keys($updateData),
        ]);

        return $paymentMethod->fresh();
    }

    /**
     * Set payment method as default.
     */
    public function setAsDefault(PaymentMethod $paymentMethod): PaymentMethod
    {
        if (!$paymentMethod->canBeUsed()) {
            throw new \Exception('Cannot set inactive or unverified payment method as default.');
        }

        $paymentMethod->markAsDefault();

        Log::info('Payment method set as default', [
            'payment_method_id' => $paymentMethod->id,
            'customer_id' => $paymentMethod->customer_id,
        ]);

        return $paymentMethod->fresh();
    }

    /**
     * Delete payment method.
     */
    public function deletePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        $customerId = $paymentMethod->customer_id;
        $wasDefault = $paymentMethod->is_default;

        // Soft delete the payment method
        $paymentMethod->delete();

        // If this was the default payment method, set another one as default
        if ($wasDefault) {
            $this->setNewDefaultPaymentMethod($customerId);
        }

        Log::info('Payment method deleted', [
            'payment_method_id' => $paymentMethod->id,
            'customer_id' => $customerId,
            'was_default' => $wasDefault,
        ]);

        return true;
    }

    /**
     * Verify payment method.
     */
    public function verifyPaymentMethod(PaymentMethod $paymentMethod, array $verificationData = []): array
    {
        try {
            $result = match ($paymentMethod->provider) {
                'stripe' => $this->verifyStripePaymentMethod($paymentMethod, $verificationData),
                'paypal' => $this->verifyPayPalPaymentMethod($paymentMethod, $verificationData),
                'mada' => $this->verifyMadaPaymentMethod($paymentMethod, $verificationData),
                'stc_pay' => $this->verifyStcPayPaymentMethod($paymentMethod, $verificationData),
                default => ['success' => false, 'message' => 'Verification not supported for this provider'],
            };

            if ($result['success']) {
                $paymentMethod->markAsVerified();
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Payment method verification failed', [
                'payment_method_id' => $paymentMethod->id,
                'provider' => $paymentMethod->provider,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate payment method data.
     */
    private function validatePaymentMethodData(array $data): void
    {
        $rules = [
            'type' => 'required|string|in:card,bank_account,wallet,cash',
            'provider' => 'required|string|in:stripe,paypal,mada,stc_pay',
        ];

        // Type-specific validation rules
        $typeSpecificRules = match ($data['type'] ?? null) {
            'card' => [
                'card_number' => 'required_without:provider_method_id|string|min:13|max:19',
                'card_exp_month' => 'required|integer|between:1,12',
                'card_exp_year' => 'required|integer|min:' . date('Y'),
                'card_cvv' => 'required_without:provider_method_id|string|min:3|max:4',
                'card_holder_name' => 'required|string|max:255',
            ],
            'bank_account' => [
                'bank_account_number' => 'required_without:provider_method_id|string',
                'bank_routing_number' => 'required|string',
                'bank_account_type' => 'required|string|in:checking,savings',
                'bank_account_holder_name' => 'required|string|max:255',
            ],
            'wallet' => [
                'wallet_type' => 'required|string|in:apple_pay,google_pay,samsung_pay,paypal,stc_pay',
                'wallet_account_id' => 'required_without:provider_method_id|string',
            ],
            default => []
        };
        $rules = array_merge($rules, $typeSpecificRules);

        // Billing address validation
        $rules = array_merge($rules, [
            'billing_name' => 'required|string|max:255',
            'billing_email' => 'required|email|max:255',
            'billing_address_line1' => 'required|string|max:255',
            'billing_city' => 'required|string|max:255',
            'billing_country' => 'required|string|size:2',
        ]);

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Check if payment method is duplicate.
     */
    private function isDuplicate(int $customerId, array $data): bool
    {
        $query = PaymentMethod::byCustomer($customerId)
                              ->byType($data['type'])
                              ->byProvider($data['provider']);

        match ($data['type']) {
            'card' => (function() use ($query, $data) {
                if (isset($data['card_fingerprint'])) {
                    $query->where('card_fingerprint', $data['card_fingerprint']);
                } elseif (isset($data['card_number'])) {
                    $lastFour = substr($data['card_number'], -4);
                    $query->where('card_last_four', $lastFour)
                          ->where('card_exp_month', $data['card_exp_month'])
                          ->where('card_exp_year', $data['card_exp_year']);
                }
            })(),
            'bank_account' => (function() use ($query, $data) {
                if (isset($data['bank_account_number'])) {
                    $lastFour = substr($data['bank_account_number'], -4);
                    $query->where('bank_account_last_four', $lastFour)
                          ->where('bank_routing_number', $data['bank_routing_number']);
                }
            })(),
            'wallet' => (function() use ($query, $data) {
                if (isset($data['wallet_account_id'])) {
                    $query->where('wallet_account_id', $data['wallet_account_id']);
                }
            })(),
            default => null
        };

        return $query->exists();
    }

    /**
     * Prepare payment method data for creation.
     */
    private function preparePaymentMethodData(int $customerId, array $data): array
    {
        $paymentMethodData = [
            'customer_id' => $customerId,
            'type' => $data['type'],
            'provider' => $data['provider'],
            'status' => PaymentMethod::STATUS_ACTIVE,
            'is_verified' => false,
            'billing_name' => $data['billing_name'],
            'billing_email' => $data['billing_email'],
            'billing_phone' => $data['billing_phone'] ?? null,
            'billing_address_line1' => $data['billing_address_line1'],
            'billing_address_line2' => $data['billing_address_line2'] ?? null,
            'billing_city' => $data['billing_city'],
            'billing_state' => $data['billing_state'] ?? null,
            'billing_postal_code' => $data['billing_postal_code'] ?? null,
            'billing_country' => $data['billing_country'],
            'metadata' => $data['metadata'] ?? [],
        ];

        // Add type-specific data
        $typeSpecificData = match ($data['type']) {
            'card' => [
                'card_last_four' => isset($data['card_number']) ? substr($data['card_number'], -4) : null,
                'card_brand' => $data['card_brand'] ?? $this->detectCardBrand($data['card_number'] ?? ''),
                'card_type' => $data['card_type'] ?? 'credit',
                'card_country' => $data['card_country'] ?? null,
                'card_fingerprint' => $data['card_fingerprint'] ?? null,
                'card_exp_month' => $data['card_exp_month'],
                'card_exp_year' => $data['card_exp_year'],
                'expires_at' => $this->calculateCardExpiration($data['card_exp_month'], $data['card_exp_year']),
            ],
            'bank_account' => [
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_type' => $data['bank_account_type'],
                'bank_account_last_four' => isset($data['bank_account_number']) ? substr($data['bank_account_number'], -4) : null,
                'bank_routing_number' => $data['bank_routing_number'],
                'bank_country' => $data['bank_country'] ?? $data['billing_country'],
            ],
            'wallet' => [
                'wallet_type' => $data['wallet_type'],
                'wallet_account_id' => $data['wallet_account_id'] ?? null,
            ],
            default => []
        };
        $paymentMethodData = array_merge($paymentMethodData, $typeSpecificData);

        return $paymentMethodData;
    }

    /**
     * Tokenize payment method with provider.
     */
    private function tokenizeWithProvider(array $data): array
    {
        return match ($data['provider']) {
            'stripe' => $this->tokenizeWithStripe($data),
            'paypal' => $this->tokenizeWithPayPal($data),
            'mada' => $this->tokenizeWithMada($data),
            'stc_pay' => $this->tokenizeWithStcPay($data),
            default => [],
        };
    }

    /**
     * Tokenize with Stripe.
     */
    private function tokenizeWithStripe(array $data): array
    {
        // Implementation would use Stripe's API to create a payment method
        // This is a simplified version
        return [
            'provider_method_id' => 'pm_' . uniqid(),
            'provider_customer_id' => 'cus_' . uniqid(),
            'token' => 'tok_' . uniqid(),
        ];
    }

    /**
     * Tokenize with PayPal.
     */
    private function tokenizeWithPayPal(array $data): array
    {
        // Implementation would use PayPal's vaulting API
        return [
            'provider_method_id' => 'PAYPAL_' . uniqid(),
            'token' => 'PAYPAL_TOKEN_' . uniqid(),
        ];
    }

    /**
     * Tokenize with Mada.
     */
    private function tokenizeWithMada(array $data): array
    {
        // Implementation would use Mada's tokenization service
        return [
            'provider_method_id' => 'MADA_' . uniqid(),
            'token' => 'MADA_TOKEN_' . uniqid(),
        ];
    }

    /**
     * Tokenize with STC Pay.
     */
    private function tokenizeWithStcPay(array $data): array
    {
        // Implementation would use STC Pay's API
        return [
            'provider_method_id' => 'STC_' . uniqid(),
            'token' => 'STC_TOKEN_' . uniqid(),
        ];
    }

    /**
     * Check if this is the first payment method for customer.
     */
    private function isFirstPaymentMethod(int $customerId): bool
    {
        return PaymentMethod::byCustomer($customerId)->count() === 1;
    }

    /**
     * Check if payment method requires verification.
     */
    private function requiresVerification(PaymentMethod $paymentMethod): bool
    {
        return match ($paymentMethod->type) {
            'bank_account' => true,
            'card' => $paymentMethod->provider === 'mada', // Mada cards often require verification
            default => false,
        };
    }

    /**
     * Initiate payment method verification.
     */
    private function initiateVerification(PaymentMethod $paymentMethod): void
    {
        // Implementation would depend on the provider and type
        Log::info('Payment method verification initiated', [
            'payment_method_id' => $paymentMethod->id,
            'type' => $paymentMethod->type,
            'provider' => $paymentMethod->provider,
        ]);
    }

    /**
     * Set new default payment method when current default is deleted.
     */
    private function setNewDefaultPaymentMethod(int $customerId): void
    {
        $newDefault = PaymentMethod::byCustomer($customerId)
                                  ->active()
                                  ->verified()
                                  ->notExpired()
                                  ->orderBy('last_used_at', 'desc')
                                  ->first();

        if ($newDefault) {
            $newDefault->markAsDefault();
        }
    }

    /**
     * Update card expiration date.
     */
    private function updateCardExpiration(PaymentMethod $paymentMethod, array $data): void
    {
        if ($paymentMethod->type !== 'card') {
            return;
        }

        $month = $data['card_exp_month'] ?? $paymentMethod->card_exp_month;
        $year = $data['card_exp_year'] ?? $paymentMethod->card_exp_year;

        $data['expires_at'] = $this->calculateCardExpiration($month, $year);
    }

    /**
     * Calculate card expiration date.
     */
    private function calculateCardExpiration(int $month, int $year): \DateTime
    {
        // Card expires at the end of the expiration month
        return new \DateTime("$year-$month-01 23:59:59");
    }

    /**
     * Detect card brand from card number.
     */
    private function detectCardBrand(string $cardNumber): string
    {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);

        if (preg_match('/^4/', $cardNumber)) {
            return 'visa';
        } elseif (preg_match('/^5[1-5]/', $cardNumber)) {
            return 'mastercard';
        } elseif (preg_match('/^3[47]/', $cardNumber)) {
            return 'amex';
        } elseif (preg_match('/^6(?:011|5)/', $cardNumber)) {
            return 'discover';
        } elseif (preg_match('/^9/', $cardNumber)) {
            return 'mada';
        }

        return 'unknown';
    }

    /**
     * Verify Stripe payment method.
     */
    private function verifyStripePaymentMethod(PaymentMethod $paymentMethod, array $data): array
    {
        // Stripe payment methods are typically verified automatically
        return ['success' => true, 'message' => 'Payment method verified'];
    }

    /**
     * Verify PayPal payment method.
     */
    private function verifyPayPalPaymentMethod(PaymentMethod $paymentMethod, array $data): array
    {
        // PayPal verification would use their API
        return ['success' => true, 'message' => 'Payment method verified'];
    }

    /**
     * Verify Mada payment method.
     */
    private function verifyMadaPaymentMethod(PaymentMethod $paymentMethod, array $data): array
    {
        // Mada verification might require additional steps
        return ['success' => true, 'message' => 'Payment method verified'];
    }

    /**
     * Verify STC Pay payment method.
     */
    private function verifyStcPayPaymentMethod(PaymentMethod $paymentMethod, array $data): array
    {
        // STC Pay verification would use their API
        return ['success' => true, 'message' => 'Payment method verified'];
    }
}
