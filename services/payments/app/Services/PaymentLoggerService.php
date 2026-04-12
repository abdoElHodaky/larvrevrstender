<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class PaymentLoggerService
{
    private const SENSITIVE_FIELDS = [
        'card_number',
        'cvv',
        'card_cvv',
        'ssn',
        'account_number',
        'routing_number',
        'password',
        'token',
        'secret',
        'key',
        'authorization',
        'signature',
    ];

    /**
     * Log payment processing start.
     */
    public function logPaymentStart(array $paymentData): void
    {
        $this->logWithContext('info', 'Payment processing started', [
            'event' => 'payment.processing.started',
            'payment_data' => $this->sanitizeData($paymentData),
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log payment processing success.
     */
    public function logPaymentSuccess(int $paymentId, array $result): void
    {
        $this->logWithContext('info', 'Payment processed successfully', [
            'event' => 'payment.processing.success',
            'payment_id' => $paymentId,
            'result' => $this->sanitizeData($result),
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log payment processing failure.
     */
    public function logPaymentFailure(array $paymentData, string $error, array $context = []): void
    {
        $this->logWithContext('error', 'Payment processing failed', [
            'event' => 'payment.processing.failed',
            'payment_data' => $this->sanitizeData($paymentData),
            'error' => $error,
            'context' => $this->sanitizeData($context),
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log refund processing.
     */
    public function logRefundProcessing(int $paymentId, float $amount, string $reason): void
    {
        $this->logWithContext('info', 'Refund processing started', [
            'event' => 'refund.processing.started',
            'payment_id' => $paymentId,
            'refund_amount' => $amount,
            'reason' => $reason,
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log refund success.
     */
    public function logRefundSuccess(int $paymentId, int $refundId, array $result): void
    {
        $this->logWithContext('info', 'Refund processed successfully', [
            'event' => 'refund.processing.success',
            'payment_id' => $paymentId,
            'refund_id' => $refundId,
            'result' => $this->sanitizeData($result),
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log refund failure.
     */
    public function logRefundFailure(int $paymentId, string $error, array $context = []): void
    {
        $this->logWithContext('error', 'Refund processing failed', [
            'event' => 'refund.processing.failed',
            'payment_id' => $paymentId,
            'error' => $error,
            'context' => $this->sanitizeData($context),
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log webhook received.
     */
    public function logWebhookReceived(string $provider, string $eventType, array $headers): void
    {
        $this->logWithContext('info', 'Webhook received', [
            'event' => 'webhook.received',
            'provider' => $provider,
            'event_type' => $eventType,
            'headers' => $this->sanitizeData($headers),
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log webhook processing success.
     */
    public function logWebhookSuccess(string $webhookId, string $provider, array $result): void
    {
        $this->logWithContext('info', 'Webhook processed successfully', [
            'event' => 'webhook.processing.success',
            'webhook_id' => $webhookId,
            'provider' => $provider,
            'result' => $this->sanitizeData($result),
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log webhook processing failure.
     */
    public function logWebhookFailure(string $webhookId, string $provider, string $error, array $context = []): void
    {
        $this->logWithContext('error', 'Webhook processing failed', [
            'event' => 'webhook.processing.failed',
            'webhook_id' => $webhookId,
            'provider' => $provider,
            'error' => $error,
            'context' => $this->sanitizeData($context),
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log gateway communication.
     */
    public function logGatewayCommunication(string $gateway, string $action, array $request, array $response): void
    {
        $this->logWithContext('info', 'Gateway communication', [
            'event' => 'gateway.communication',
            'gateway' => $gateway,
            'action' => $action,
            'request' => $this->sanitizeData($request),
            'response' => $this->sanitizeData($response),
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log gateway error.
     */
    public function logGatewayError(string $gateway, string $action, string $error, array $context = []): void
    {
        $this->logWithContext('error', 'Gateway error', [
            'event' => 'gateway.error',
            'gateway' => $gateway,
            'action' => $action,
            'error' => $error,
            'context' => $this->sanitizeData($context),
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log payment method creation.
     */
    public function logPaymentMethodCreated(int $customerId, string $type, string $provider): void
    {
        $this->logWithContext('info', 'Payment method created', [
            'event' => 'payment_method.created',
            'customer_id' => $customerId,
            'type' => $type,
            'provider' => $provider,
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log payment method deletion.
     */
    public function logPaymentMethodDeleted(int $paymentMethodId, int $customerId): void
    {
        $this->logWithContext('info', 'Payment method deleted', [
            'event' => 'payment_method.deleted',
            'payment_method_id' => $paymentMethodId,
            'customer_id' => $customerId,
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log transaction created.
     */
    public function logTransactionCreated(string $transactionReference, string $type, float $amount): void
    {
        $this->logWithContext('info', 'Transaction created', [
            'event' => 'transaction.created',
            'transaction_reference' => $transactionReference,
            'type' => $type,
            'amount' => $amount,
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log security event.
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        $this->logWithContext('warning', 'Security event', [
            'event' => "security.{$event}",
            'context' => $this->sanitizeData($context),
            'correlation_id' => $this->getCorrelationId(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Log performance metrics.
     */
    public function logPerformanceMetrics(string $operation, float $duration, array $metrics = []): void
    {
        $this->logWithContext('info', 'Performance metrics', [
            'event' => 'performance.metrics',
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'metrics' => $metrics,
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log rate limiting event.
     */
    public function logRateLimitEvent(string $key, int $attempts, int $maxAttempts): void
    {
        $this->logWithContext('warning', 'Rate limit event', [
            'event' => 'rate_limit.exceeded',
            'key' => $key,
            'attempts' => $attempts,
            'max_attempts' => $maxAttempts,
            'correlation_id' => $this->getCorrelationId(),
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Log audit event.
     */
    public function logAuditEvent(string $action, array $data, ?int $userId = null): void
    {
        $this->logWithContext('info', 'Audit event', [
            'event' => 'audit.action',
            'action' => $action,
            'data' => $this->sanitizeData($data),
            'user_id' => $userId,
            'correlation_id' => $this->getCorrelationId(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Log configuration change.
     */
    public function logConfigurationChange(string $key, $oldValue, $newValue, ?int $userId = null): void
    {
        $this->logWithContext('info', 'Configuration changed', [
            'event' => 'configuration.changed',
            'key' => $key,
            'old_value' => $this->sanitizeData($oldValue),
            'new_value' => $this->sanitizeData($newValue),
            'user_id' => $userId,
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log with context information.
     */
    private function logWithContext(string $level, string $message, array $context): void
    {
        $enrichedContext = array_merge($context, [
            'service' => 'payment-service',
            'timestamp' => now()->toISOString(),
            'request_id' => Request::header('X-Request-ID'),
            'session_id' => session()->getId(),
        ]);

        Log::$level($message, $enrichedContext);
    }

    /**
     * Sanitize sensitive data from logs.
     */
    private function sanitizeData($data): mixed
    {
        if (is_array($data)) {
            return $this->sanitizeArray($data);
        }

        if (is_object($data)) {
            return $this->sanitizeArray((array) $data);
        }

        return $data;
    }

    /**
     * Sanitize array data.
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            
            // Check if key contains sensitive information
            $isSensitive = false;
            foreach (self::SENSITIVE_FIELDS as $sensitiveField) {
                if (str_contains($lowerKey, $sensitiveField)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = $this->maskSensitiveValue($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } elseif (is_object($value)) {
                $sanitized[$key] = $this->sanitizeArray((array) $value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Mask sensitive values.
     */
    private function maskSensitiveValue($value): string
    {
        if (is_null($value)) {
            return '[NULL]';
        }

        $stringValue = (string) $value;
        $length = strlen($stringValue);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        // Show first 2 and last 2 characters for longer values
        return substr($stringValue, 0, 2) . str_repeat('*', $length - 4) . substr($stringValue, -2);
    }

    /**
     * Get correlation ID for request tracking.
     */
    private function getCorrelationId(): string
    {
        return Request::header('X-Correlation-ID') ?? 
               Request::header('X-Request-ID') ?? 
               uniqid('payment_', true);
    }

    /**
     * Log exception with full context.
     */
    public function logException(\Throwable $exception, array $context = []): void
    {
        $this->logWithContext('error', 'Exception occurred', [
            'event' => 'exception.occurred',
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'exception_trace' => $exception->getTraceAsString(),
            'context' => $this->sanitizeData($context),
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log API request/response.
     */
    public function logApiCall(string $method, string $url, array $headers, $requestBody, $responseBody, int $statusCode, float $duration): void
    {
        $this->logWithContext('info', 'API call', [
            'event' => 'api.call',
            'method' => $method,
            'url' => $url,
            'headers' => $this->sanitizeData($headers),
            'request_body' => $this->sanitizeData($requestBody),
            'response_body' => $this->sanitizeData($responseBody),
            'status_code' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }

    /**
     * Log database query performance.
     */
    public function logSlowQuery(string $query, array $bindings, float $duration): void
    {
        $this->logWithContext('warning', 'Slow database query', [
            'event' => 'database.slow_query',
            'query' => $query,
            'bindings' => $this->sanitizeData($bindings),
            'duration_ms' => round($duration * 1000, 2),
            'correlation_id' => $this->getCorrelationId(),
        ]);
    }
}
