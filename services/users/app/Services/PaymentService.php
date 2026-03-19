<?php

declare(strict_types=1);

namespace App\Services;

use Shared\RPC\Clients\PaymentServiceClient;
use Shared\RPC\ValueObjects\RpcResponse;
use Shared\RPC\Exceptions\RpcException;

/**
 * Payment Service - PHP 8.3 & Laravel 12 Implementation
 * 
 * Handles user payment operations via RPC communication with payment-service.
 * Acts as a facade layer for user-specific payment operations.
 */
final readonly class PaymentService
{
    public function __construct(
        private PaymentServiceClient $paymentClient,
    ) {}

    /**
     * Get user's payment methods via RPC
     */
    public function getUserPaymentMethods(int $userId): RpcResponse
    {
        try {
            return $this->paymentClient->getPaymentMethods($userId);
        } catch (RpcException $e) {
            throw new RpcException(
                "Failed to retrieve payment methods for user {$userId}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Add payment method for user via RPC
     */
    public function addUserPaymentMethod(int $userId, array $methodData): RpcResponse
    {
        try {
            return $this->paymentClient->addPaymentMethod($userId, $methodData);
        } catch (RpcException $e) {
            throw new RpcException(
                "Failed to add payment method for user {$userId}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Process payment for user via RPC
     */
    public function processUserPayment(array $paymentData): RpcResponse
    {
        try {
            return $this->paymentClient->processPayment($paymentData);
        } catch (RpcException $e) {
            throw new RpcException(
                "Failed to process payment: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Validate payment data via RPC
     */
    public function validatePayment(array $paymentData): RpcResponse
    {
        try {
            return $this->paymentClient->validatePayment($paymentData);
        } catch (RpcException $e) {
            throw new RpcException(
                "Failed to validate payment: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
