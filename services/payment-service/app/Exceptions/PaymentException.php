<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class PaymentException extends Exception
{
    protected array $context;
    protected string $errorCode;
    protected int $httpStatusCode;

    public function __construct(
        string $message = '',
        string $errorCode = 'PAYMENT_ERROR',
        int $httpStatusCode = 400,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        
        $this->errorCode = $errorCode;
        $this->httpStatusCode = $httpStatusCode;
        $this->context = $context;
    }

    /**
     * Get the error code.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get the HTTP status code.
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * Get the context data.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'context' => $this->context,
            ],
            'timestamp' => now()->toISOString(),
        ], $this->httpStatusCode);
    }

    /**
     * Create a payment processing exception.
     */
    public static function processingFailed(string $message, array $context = []): self
    {
        return new self($message, 'PAYMENT_PROCESSING_FAILED', 422, $context);
    }

    /**
     * Create a payment not found exception.
     */
    public static function notFound(int $paymentId): self
    {
        return new self(
            "Payment with ID {$paymentId} not found",
            'PAYMENT_NOT_FOUND',
            404,
            ['payment_id' => $paymentId]
        );
    }

    /**
     * Create an invalid payment method exception.
     */
    public static function invalidPaymentMethod(string $message, array $context = []): self
    {
        return new self($message, 'INVALID_PAYMENT_METHOD', 422, $context);
    }

    /**
     * Create an insufficient funds exception.
     */
    public static function insufficientFunds(float $available, float $required): self
    {
        return new self(
            "Insufficient funds. Available: {$available}, Required: {$required}",
            'INSUFFICIENT_FUNDS',
            422,
            ['available' => $available, 'required' => $required]
        );
    }

    /**
     * Create a gateway error exception.
     */
    public static function gatewayError(string $gateway, string $message, array $context = []): self
    {
        return new self(
            "Gateway error from {$gateway}: {$message}",
            'GATEWAY_ERROR',
            502,
            array_merge(['gateway' => $gateway], $context)
        );
    }

    /**
     * Create a refund error exception.
     */
    public static function refundFailed(string $message, array $context = []): self
    {
        return new self($message, 'REFUND_FAILED', 422, $context);
    }

    /**
     * Create a webhook processing exception.
     */
    public static function webhookProcessingFailed(string $provider, string $message, array $context = []): self
    {
        return new self(
            "Webhook processing failed for {$provider}: {$message}",
            'WEBHOOK_PROCESSING_FAILED',
            422,
            array_merge(['provider' => $provider], $context)
        );
    }

    /**
     * Create a rate limit exceeded exception.
     */
    public static function rateLimitExceeded(int $retryAfter = 60): self
    {
        return new self(
            'Rate limit exceeded. Please try again later.',
            'RATE_LIMIT_EXCEEDED',
            429,
            ['retry_after' => $retryAfter]
        );
    }

    /**
     * Create a validation exception.
     */
    public static function validationFailed(array $errors): self
    {
        return new self(
            'Validation failed',
            'VALIDATION_FAILED',
            422,
            ['validation_errors' => $errors]
        );
    }

    /**
     * Create an authorization exception.
     */
    public static function unauthorized(string $message = 'Unauthorized access'): self
    {
        return new self($message, 'UNAUTHORIZED', 403);
    }

    /**
     * Create a configuration exception.
     */
    public static function configurationError(string $message, array $context = []): self
    {
        return new self($message, 'CONFIGURATION_ERROR', 500, $context);
    }
}
