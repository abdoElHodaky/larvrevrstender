<?php

namespace App\Workflows\Activities;

/**
 * Process Payment Activity
 * 
 * Handles payment processing through the payment service via RPC.
 * This activity integrates with the existing payment service infrastructure.
 */
class ProcessPaymentActivity extends BaseRpcActivity
{
    /**
     * Execute payment processing
     *
     * @param array $orderData Order data including payment information
     * @return array Payment result with payment ID
     */
    public function __invoke(array $orderData): array
    {
        $this->validateData($orderData, [
            'order_id',
            'payment_data',
            'amount',
            'currency'
        ]);
        
        $paymentData = [
            'order_id' => $orderData['order_id'],
            'amount' => $orderData['amount'],
            'currency' => $orderData['currency'],
            'payment_method' => $orderData['payment_data']['method'],
            'payment_details' => $orderData['payment_data']['details'],
            'customer_id' => $orderData['customer_id'] ?? null,
            'description' => "Payment for order #{$orderData['order_id']}"
        ];
        
        $result = $this->callRpc('payment-service', 'processPayment', $paymentData);
        
        if (!$result['success']) {
            throw new \Exception("Payment processing failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return $this->successResponse([
            'payment_id' => $result['data']['payment_id'],
            'transaction_id' => $result['data']['transaction_id'],
            'status' => $result['data']['status'],
            'amount' => $result['data']['amount'],
            'currency' => $result['data']['currency']
        ]);
    }
}

