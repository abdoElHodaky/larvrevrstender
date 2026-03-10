<?php

namespace App\Workflows\Activities;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Process Payment Activity
 * 
 * Submits payment to the payment gateway and receives authorization.
 * This is the core payment processing step that interacts with external gateways.
 */
class ProcessPaymentActivity extends BaseRpcActivity
{
    /**
     * Execute the payment processing activity
     *
     * @param array $data Payment data from validation step
     * @return array Payment processing result
     * @throws Exception
     */
    public function execute(array $data): array
    {
        Log::info("ProcessPaymentActivity started", [
            'saga_id' => $this->getSagaId(),
            'order_id' => $data['order_id'] ?? null,
            'payment_method' => $data['payment_method'] ?? null
        ]);

        try {
            // Validate required fields from previous step
            $this->validateData($data, [
                'order_id',
                'amount',
                'currency',
                'payment_method',
                'payment_details',
                'customer_id'
            ]);

            // Generate unique payment reference
            $paymentReference = $this->generatePaymentReference($data['order_id'], $data['customer_id']);

            // Process payment through gateway
            $gatewayResult = $this->processPaymentThroughGateway([
                'payment_reference' => $paymentReference,
                'order_id' => $data['order_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'payment_method' => $data['payment_method'],
                'payment_details' => $data['payment_details'],
                'customer_id' => $data['customer_id'],
                'description' => $data['description'] ?? "Payment for order #{$data['order_id']}",
                'saga_id' => $this->getSagaId()
            ]);

            if (!$gatewayResult['success']) {
                throw new Exception('Payment gateway processing failed: ' . $gatewayResult['error']);
            }

            // Extract gateway response
            $gatewayData = $gatewayResult['data'];

            Log::info("ProcessPaymentActivity completed successfully", [
                'saga_id' => $this->getSagaId(),
                'order_id' => $data['order_id'],
                'payment_reference' => $paymentReference,
                'gateway_transaction_id' => $gatewayData['transaction_id'] ?? null
            ]);

            return $this->successResponse([
                'payment_reference' => $paymentReference,
                'order_id' => $data['order_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'payment_method' => $data['payment_method'],
                'customer_id' => $data['customer_id'],
                'gateway_transaction_id' => $gatewayData['transaction_id'],
                'gateway_status' => $gatewayData['status'],
                'authorization_code' => $gatewayData['authorization_code'] ?? null,
                'gateway_response' => $gatewayData['response_message'] ?? null,
                'processed_at' => now()->toISOString()
            ]);

        } catch (Exception $e) {
            $this->logError($e);
            
            Log::error("ProcessPaymentActivity failed", [
                'saga_id' => $this->getSagaId(),
                'order_id' => $data['order_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($e->getMessage(), [
                'order_id' => $data['order_id'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'processing_step' => 'gateway_processing'
            ]);
        }
    }

    /**
     * Generate unique payment reference
     */
    private function generatePaymentReference(int $orderId, int $customerId): string
    {
        $timestamp = now()->format('YmdHis');
        $random = substr(md5(uniqid()), 0, 6);
        return "PAY_{$orderId}_{$customerId}_{$timestamp}_{$random}";
    }

    /**
     * Process payment through the appropriate gateway
     */
    private function processPaymentThroughGateway(array $paymentData): array
    {
        try {
            $method = $paymentData['payment_method'];
            
            return match ($method) {
                'credit_card', 'debit_card' => $this->processCreditCardPayment($paymentData),
                'paypal' => $this->processPayPalPayment($paymentData),
                'stripe' => $this->processStripePayment($paymentData),
                'bank_transfer' => $this->processBankTransferPayment($paymentData),
                default => throw new Exception("Unsupported payment method: {$method}")
            };
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'gateway' => $paymentData['payment_method']
            ];
        }
    }

    /**
     * Process credit/debit card payment
     */
    private function processCreditCardPayment(array $data): array
    {
        // In a real implementation, this would:
        // 1. Connect to credit card processor (Stripe, Square, etc.)
        // 2. Submit card details for authorization
        // 3. Handle 3D Secure if required
        // 4. Return transaction ID and status
        
        // Simulate processing delay
        usleep(500000); // 0.5 seconds
        
        // Simulate successful processing
        return [
            'success' => true,
            'data' => [
                'transaction_id' => 'CC_' . uniqid(),
                'status' => 'authorized',
                'authorization_code' => 'AUTH_' . rand(100000, 999999),
                'response_message' => 'Payment authorized successfully',
                'gateway_fee' => round($data['amount'] * 0.029, 2), // 2.9% fee
                'net_amount' => $data['amount'] - round($data['amount'] * 0.029, 2)
            ]
        ];
    }

    /**
     * Process PayPal payment
     */
    private function processPayPalPayment(array $data): array
    {
        // In a real implementation, this would:
        // 1. Create PayPal payment request
        // 2. Handle PayPal OAuth flow
        // 3. Process payment through PayPal API
        // 4. Return PayPal transaction details
        
        // Simulate processing delay
        usleep(750000); // 0.75 seconds
        
        return [
            'success' => true,
            'data' => [
                'transaction_id' => 'PP_' . uniqid(),
                'status' => 'completed',
                'authorization_code' => null,
                'response_message' => 'PayPal payment completed',
                'gateway_fee' => round($data['amount'] * 0.034, 2), // 3.4% fee
                'net_amount' => $data['amount'] - round($data['amount'] * 0.034, 2),
                'paypal_transaction_id' => 'PAYPAL_' . rand(1000000000, 9999999999)
            ]
        ];
    }

    /**
     * Process Stripe payment
     */
    private function processStripePayment(array $data): array
    {
        // In a real implementation, this would:
        // 1. Use Stripe SDK to process payment
        // 2. Handle Stripe webhooks
        // 3. Manage payment intents
        // 4. Return Stripe charge details
        
        // Simulate processing delay
        usleep(400000); // 0.4 seconds
        
        return [
            'success' => true,
            'data' => [
                'transaction_id' => 'ST_' . uniqid(),
                'status' => 'succeeded',
                'authorization_code' => null,
                'response_message' => 'Stripe payment succeeded',
                'gateway_fee' => round($data['amount'] * 0.029 + 0.30, 2), // 2.9% + $0.30
                'net_amount' => $data['amount'] - round($data['amount'] * 0.029 + 0.30, 2),
                'stripe_charge_id' => 'ch_' . uniqid()
            ]
        ];
    }

    /**
     * Process bank transfer payment
     */
    private function processBankTransferPayment(array $data): array
    {
        // In a real implementation, this would:
        // 1. Initiate ACH transfer
        // 2. Handle bank verification
        // 3. Process through banking network
        // 4. Return transfer reference
        
        // Simulate processing delay (bank transfers are slower)
        usleep(1000000); // 1 second
        
        return [
            'success' => true,
            'data' => [
                'transaction_id' => 'BT_' . uniqid(),
                'status' => 'pending', // Bank transfers are typically pending initially
                'authorization_code' => null,
                'response_message' => 'Bank transfer initiated',
                'gateway_fee' => 1.50, // Flat fee for bank transfers
                'net_amount' => $data['amount'] - 1.50,
                'ach_reference' => 'ACH_' . rand(100000000, 999999999),
                'estimated_completion' => now()->addBusinessDays(3)->toISOString()
            ]
        ];
    }
}
