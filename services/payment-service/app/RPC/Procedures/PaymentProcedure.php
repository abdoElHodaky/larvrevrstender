<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Sajya\Server\Exceptions\RuntimeException;

class PaymentProcedure extends BaseProcedure
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * Process payment
     * 
     * @param array $params
     * @return array
     */
    public function processPayment(array $params): array
    {
        $this->validate($params, [
            'order_id' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'payment_method' => 'required|string|in:credit_card,debit_card,paypal,stripe,bank_transfer',
            'payment_details' => 'required|array',
            'customer_id' => 'sometimes|integer|min:1',
            'description' => 'sometimes|string|max:255',
        ]);

        return $this->executeWithLogging('Payment@processPayment', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for payment processing
            $key = 'payment_process:' . ($params['customer_id'] ?? request()->ip());
            if (RateLimiter::tooManyAttempts($key, 10)) {
                throw new RuntimeException(
                    'Too many payment attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            DB::beginTransaction();
            try {
                $payment = $this->paymentService->processPayment([
                    'order_id' => $params['order_id'],
                    'amount' => $params['amount'],
                    'currency' => $params['currency'],
                    'payment_method' => $params['payment_method'],
                    'payment_details' => $params['payment_details'],
                    'customer_id' => $params['customer_id'] ?? null,
                    'description' => $params['description'] ?? null,
                ]);
                
                DB::commit();
                
                // Clear rate limiting on successful payment
                RateLimiter::clear($key);
                
                return [
                    'success' => true,
                    'payment' => $payment,
                    'processed_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                // Increment rate limiting on failed payment
                RateLimiter::hit($key, 600); // 10 minutes
                
                throw new RuntimeException(
                    'Payment processing failed: ' . $e->getMessage(),
                    -32001,
                    ['order_id' => $params['order_id'], 'amount' => $params['amount']]
                );
            }
        });
    }

    /**
     * Get payment by ID
     * 
     * @param array $params
     * @return array
     */
    public function getById(array $params): array
    {
        $this->validate($params, [
            'payment_id' => 'required|integer|min:1',
            'include_details' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Payment@getById', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'payment:' . $params['payment_id'] . ':' . 
                       ($params['include_details'] ?? false ? 'with_details' : 'no_details');
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $payment = $this->paymentService->getPaymentById(
                    $params['payment_id'],
                    $params['include_details'] ?? false
                );
                
                if (!$payment) {
                    throw new RuntimeException(
                        'Payment not found',
                        -32001,
                        ['payment_id' => $params['payment_id']]
                    );
                }
                
                $result = [
                    'success' => true,
                    'payment' => $payment,
                    'retrieved_at' => now()->toISOString(),
                ];
                
                // Cache for 15 minutes
                Cache::put($cacheKey, $result, 900);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve payment: ' . $e->getMessage(),
                    -32001,
                    ['payment_id' => $params['payment_id']]
                );
            }
        });
    }

    /**
     * Refund payment
     * 
     * @param array $params
     * @return array
     */
    public function refund(array $params): array
    {
        $this->validate($params, [
            'payment_id' => 'required|integer|min:1',
            'amount' => 'sometimes|numeric|min:0.01',
            'reason' => 'required|string|max:500',
            'notify_customer' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Payment@refund', $params, function () use ($params) {
            DB::beginTransaction();
            try {
                $refund = $this->paymentService->refundPayment(
                    $params['payment_id'],
                    $params['amount'] ?? null, // null for full refund
                    $params['reason'],
                    $params['notify_customer'] ?? true
                );
                
                DB::commit();
                
                // Clear cache
                Cache::forget('payment:' . $params['payment_id'] . ':*');
                
                return [
                    'success' => true,
                    'refund' => $refund,
                    'refunded_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                throw new RuntimeException(
                    'Payment refund failed: ' . $e->getMessage(),
                    -32002,
                    ['payment_id' => $params['payment_id']]
                );
            }
        });
    }

    /**
     * Get payment status
     * 
     * @param array $params
     * @return array
     */
    public function getStatus(array $params): array
    {
        $this->validate($params, [
            'payment_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('Payment@getStatus', $params, function () use ($params) {
            try {
                $status = $this->paymentService->getPaymentStatus($params['payment_id']);
                
                return [
                    'success' => true,
                    'payment_id' => $params['payment_id'],
                    'status' => $status,
                    'checked_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to get payment status: ' . $e->getMessage(),
                    -32001,
                    ['payment_id' => $params['payment_id']]
                );
            }
        });
    }

    /**
     * Capture authorized payment
     * 
     * @param array $params
     * @return array
     */
    public function capture(array $params): array
    {
        $this->validate($params, [
            'payment_id' => 'required|integer|min:1',
            'amount' => 'sometimes|numeric|min:0.01',
        ]);

        return $this->executeWithLogging('Payment@capture', $params, function () use ($params) {
            DB::beginTransaction();
            try {
                $result = $this->paymentService->capturePayment(
                    $params['payment_id'],
                    $params['amount'] ?? null // null for full capture
                );
                
                DB::commit();
                
                // Clear cache
                Cache::forget('payment:' . $params['payment_id'] . ':*');
                
                return [
                    'success' => true,
                    'payment' => $result,
                    'captured_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                throw new RuntimeException(
                    'Payment capture failed: ' . $e->getMessage(),
                    -32003,
                    ['payment_id' => $params['payment_id']]
                );
            }
        });
    }

    /**
     * Void authorized payment
     * 
     * @param array $params
     * @return array
     */
    public function void(array $params): array
    {
        $this->validate($params, [
            'payment_id' => 'required|integer|min:1',
            'reason' => 'sometimes|string|max:255',
        ]);

        return $this->executeWithLogging('Payment@void', $params, function () use ($params) {
            DB::beginTransaction();
            try {
                $result = $this->paymentService->voidPayment(
                    $params['payment_id'],
                    $params['reason'] ?? null
                );
                
                DB::commit();
                
                // Clear cache
                Cache::forget('payment:' . $params['payment_id'] . ':*');
                
                return [
                    'success' => true,
                    'payment' => $result,
                    'voided_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                DB::rollBack();
                
                throw new RuntimeException(
                    'Payment void failed: ' . $e->getMessage(),
                    -32004,
                    ['payment_id' => $params['payment_id']]
                );
            }
        });
    }

    /**
     * Get customer payments
     * 
     * @param array $params
     * @return array
     */
    public function getCustomerPayments(array $params): array
    {
        $this->validate($params, [
            'customer_id' => 'required|integer|min:1',
            'status' => 'sometimes|string|in:pending,authorized,captured,failed,refunded,voided',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        return $this->executeWithLogging('Payment@getCustomerPayments', $params, function () use ($params) {
            try {
                $results = $this->paymentService->getCustomerPayments([
                    'customer_id' => $params['customer_id'],
                    'status' => $params['status'] ?? null,
                    'date_from' => $params['date_from'] ?? null,
                    'date_to' => $params['date_to'] ?? null,
                    'page' => $params['page'] ?? 1,
                    'per_page' => $params['per_page'] ?? 20,
                ]);
                
                return [
                    'success' => true,
                    'payments' => $results['data'],
                    'pagination' => $results['pagination'],
                    'retrieved_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve customer payments: ' . $e->getMessage(),
                    -32005,
                    ['customer_id' => $params['customer_id']]
                );
            }
        });
    }

    /**
     * Create payment method
     * 
     * @param array $params
     * @return array
     */
    public function createPaymentMethod(array $params): array
    {
        $this->validate($params, [
            'customer_id' => 'required|integer|min:1',
            'type' => 'required|string|in:credit_card,debit_card,bank_account',
            'details' => 'required|array',
            'is_default' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Payment@createPaymentMethod', $this->sanitizeForLogging($params), function () use ($params) {
            try {
                $paymentMethod = $this->paymentService->createPaymentMethod([
                    'customer_id' => $params['customer_id'],
                    'type' => $params['type'],
                    'details' => $params['details'],
                    'is_default' => $params['is_default'] ?? false,
                ]);
                
                return [
                    'success' => true,
                    'payment_method' => $paymentMethod,
                    'created_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Payment method creation failed: ' . $e->getMessage(),
                    -32006,
                    ['customer_id' => $params['customer_id'], 'type' => $params['type']]
                );
            }
        });
    }

    /**
     * Get customer payment methods
     * 
     * @param array $params
     * @return array
     */
    public function getPaymentMethods(array $params): array
    {
        $this->validate($params, [
            'customer_id' => 'required|integer|min:1',
            'type' => 'sometimes|string|in:credit_card,debit_card,bank_account',
            'active_only' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Payment@getPaymentMethods', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'payment_methods:' . $params['customer_id'] . ':' . 
                       ($params['type'] ?? 'all') . ':' . 
                       ($params['active_only'] ?? false ? 'active' : 'all');
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $paymentMethods = $this->paymentService->getCustomerPaymentMethods(
                    $params['customer_id'],
                    $params['type'] ?? null,
                    $params['active_only'] ?? true
                );
                
                $result = [
                    'success' => true,
                    'payment_methods' => $paymentMethods,
                    'retrieved_at' => now()->toISOString(),
                ];
                
                // Cache for 10 minutes
                Cache::put($cacheKey, $result, 600);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve payment methods: ' . $e->getMessage(),
                    -32007,
                    ['customer_id' => $params['customer_id']]
                );
            }
        });
    }

    /**
     * Delete payment method
     * 
     * @param array $params
     * @return array
     */
    public function deletePaymentMethod(array $params): array
    {
        $this->validate($params, [
            'payment_method_id' => 'required|integer|min:1',
            'customer_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('Payment@deletePaymentMethod', $params, function () use ($params) {
            try {
                $result = $this->paymentService->deletePaymentMethod(
                    $params['payment_method_id'],
                    $params['customer_id']
                );
                
                // Clear cache
                Cache::forget('payment_methods:' . $params['customer_id'] . ':*');
                
                return [
                    'success' => true,
                    'deleted' => $result,
                    'deleted_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Payment method deletion failed: ' . $e->getMessage(),
                    -32008,
                    ['payment_method_id' => $params['payment_method_id']]
                );
            }
        });
    }

    /**
     * Get payment statistics
     * 
     * @param array $params
     * @return array
     */
    public function getStatistics(array $params): array
    {
        $this->validate($params, [
            'period' => 'sometimes|string|in:today,week,month,quarter,year',
            'currency' => 'sometimes|string|size:3',
            'payment_method' => 'sometimes|string|in:credit_card,debit_card,paypal,stripe,bank_transfer',
        ]);

        return $this->executeWithLogging('Payment@getStatistics', $params, function () use ($params) {
            $period = $params['period'] ?? 'month';
            $currency = $params['currency'] ?? null;
            $paymentMethod = $params['payment_method'] ?? null;
            
            // Check cache first
            $cacheKey = 'payment_stats:' . $period . ':' . ($currency ?? 'all') . ':' . ($paymentMethod ?? 'all');
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $statistics = $this->paymentService->getPaymentStatistics($period, $currency, $paymentMethod);
                
                $result = [
                    'success' => true,
                    'statistics' => $statistics,
                    'period' => $period,
                    'filters' => [
                        'currency' => $currency,
                        'payment_method' => $paymentMethod,
                    ],
                    'generated_at' => now()->toISOString(),
                ];
                
                // Cache for 30 minutes
                Cache::put($cacheKey, $result, 1800);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve payment statistics: ' . $e->getMessage(),
                    -32009,
                    ['period' => $period]
                );
            }
        });
    }

    /**
     * Verify webhook signature
     * 
     * @param array $params
     * @return array
     */
    public function verifyWebhook(array $params): array
    {
        $this->validate($params, [
            'payload' => 'required|string',
            'signature' => 'required|string',
            'provider' => 'required|string|in:stripe,paypal,square',
        ]);

        return $this->executeWithLogging('Payment@verifyWebhook', $this->sanitizeForLogging($params), function () use ($params) {
            try {
                $result = $this->paymentService->verifyWebhookSignature(
                    $params['payload'],
                    $params['signature'],
                    $params['provider']
                );
                
                return [
                    'success' => true,
                    'verified' => $result['verified'],
                    'event_type' => $result['event_type'] ?? null,
                    'verified_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Webhook verification failed: ' . $e->getMessage(),
                    -32010,
                    ['provider' => $params['provider']]
                );
            }
        });
    }
}
