<?php

namespace App\Workflows\Activities;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Validate Payment Data Activity
 * 
 * Validates payment information and checks payment gateway availability
 * before processing the payment.
 */
class ValidatePaymentDataActivity extends BaseRpcActivity
{
    private \App\RPC\Adapters\OrderServiceAdapter $orderAdapter;

    public function __construct(
        int $index,
        string $now,
        \Workflow\Models\StoredWorkflow $storedWorkflow,
        ...$arguments
    ) {
        parent::__construct($index, $now, $storedWorkflow, ...$arguments);
        $this->orderAdapter = app(\App\RPC\Adapters\OrderServiceAdapter::class);
    }
    /**
     * Execute the payment data validation activity
     *
     * @param array $data Payment data to validate
     * @return array Validation result
     * @throws Exception
     */
    public function execute(array $data): array
    {
        Log::info("ValidatePaymentDataActivity started", [
            'saga_id' => $this->getSagaId(),
            'order_id' => $data['order_id'] ?? null
        ]);

        try {
            // Validate required fields
            $this->validateData($data, [
                'order_id',
                'amount',
                'currency',
                'payment_method',
                'payment_details',
                'customer_id'
            ]);

            // Validate payment amount
            if ($data['amount'] <= 0) {
                throw new Exception('Payment amount must be greater than zero');
            }

            // Validate currency
            if (!in_array($data['currency'], ['USD', 'EUR', 'SAR', 'AED'])) {
                throw new Exception('Unsupported currency: ' . $data['currency']);
            }

            // Validate payment method
            $supportedMethods = ['credit_card', 'debit_card', 'paypal', 'stripe', 'bank_transfer'];
            if (!in_array($data['payment_method'], $supportedMethods)) {
                throw new Exception('Unsupported payment method: ' . $data['payment_method']);
            }

            // Validate payment details based on method
            $this->validatePaymentDetails($data['payment_method'], $data['payment_details']);

            // Check if order exists and is valid for payment
            $orderValidation = $this->validateOrder($data['order_id'], $data['customer_id']);
            if (!$orderValidation['success']) {
                throw new Exception('Order validation failed: ' . $orderValidation['error']);
            }

            // Check payment gateway availability
            $gatewayCheck = $this->checkPaymentGatewayAvailability($data['payment_method']);
            if (!$gatewayCheck['available']) {
                throw new Exception('Payment gateway unavailable: ' . $gatewayCheck['reason']);
            }

            Log::info("ValidatePaymentDataActivity completed successfully", [
                'saga_id' => $this->getSagaId(),
                'order_id' => $data['order_id']
            ]);

            return $this->successResponse([
                'order_id' => $data['order_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'payment_method' => $data['payment_method'],
                'customer_id' => $data['customer_id'],
                'gateway_available' => true,
                'order_valid' => true,
                'validated_at' => now()->toISOString()
            ]);

        } catch (Exception $e) {
            $this->logError($e);
            
            Log::error("ValidatePaymentDataActivity failed", [
                'saga_id' => $this->getSagaId(),
                'order_id' => $data['order_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($e->getMessage(), [
                'order_id' => $data['order_id'] ?? null,
                'validation_step' => 'payment_data_validation'
            ]);
        }
    }

    /**
     * Validate payment details based on payment method
     */
    private function validatePaymentDetails(string $method, array $details): void
    {
        switch ($method) {
            case 'credit_card':
            case 'debit_card':
                $required = ['card_number', 'expiry_month', 'expiry_year', 'cvv', 'cardholder_name'];
                foreach ($required as $field) {
                    if (empty($details[$field])) {
                        throw new Exception("Missing required card field: {$field}");
                    }
                }
                break;

            case 'paypal':
                if (empty($details['paypal_email'])) {
                    throw new Exception('PayPal email is required');
                }
                break;

            case 'stripe':
                if (empty($details['stripe_token'])) {
                    throw new Exception('Stripe token is required');
                }
                break;

            case 'bank_transfer':
                $required = ['bank_account', 'routing_number', 'account_holder_name'];
                foreach ($required as $field) {
                    if (empty($details[$field])) {
                        throw new Exception("Missing required bank field: {$field}");
                    }
                }
                break;
        }
    }

    /**
     * Validate order exists and is valid for payment
     */
    private function validateOrder(int $orderId, int $customerId): array
    {
        try {
            // Call order service via adapter to validate order
            $orderData = $this->orderAdapter->getOrderById($orderId, $customerId);

            if (!$orderData) {
                return ['success' => false, 'error' => 'Order not found or access denied'];
            }

            $order = $orderData['order'] ?? $orderData;
            if (!$order) {
                return ['success' => false, 'error' => 'Order data not available'];
            }

            // Check if order is in a payable state
            $payableStatuses = ['pending', 'confirmed', 'processing'];
            if (!in_array($order['status'], $payableStatuses)) {
                return ['success' => false, 'error' => 'Order is not in a payable state: ' . $order['status']];
            }

            return ['success' => true, 'order' => $order];

        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Order validation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check payment gateway availability
     */
    private function checkPaymentGatewayAvailability(string $method): array
    {
        // In a real implementation, this would check:
        // - Gateway service health
        // - API rate limits
        // - Maintenance windows
        // - Regional availability
        
        // For now, simulate availability check
        $gatewayStatus = [
            'credit_card' => ['available' => true, 'reason' => null],
            'debit_card' => ['available' => true, 'reason' => null],
            'paypal' => ['available' => true, 'reason' => null],
            'stripe' => ['available' => true, 'reason' => null],
            'bank_transfer' => ['available' => true, 'reason' => null],
        ];

        return $gatewayStatus[$method] ?? ['available' => false, 'reason' => 'Unknown payment method'];
    }
}
