<?php

namespace App\Workflows\Compensation;

use App\Workflows\Activities\BaseRpcActivity;

/**
 * Refund Payment Compensation Activity
 * 
 * Handles payment refund as compensation when order processing fails.
 * This activity reverses the payment transaction.
 */
class RefundPaymentActivity extends BaseRpcActivity
{
    /**
     * Execute payment refund
     *
     * @param string $paymentId Payment ID to refund
     * @return array Refund result
     */
    public function __invoke(string $paymentId): array
    {
        if (empty($paymentId)) {
            throw new \Exception("Payment ID is required for refund");
        }
        
        $refundData = [
            'payment_id' => $paymentId,
            'reason' => 'Order processing failed - automatic compensation',
            'refund_type' => 'full',
            'saga_id' => $this->getSagaId()
        ];
        
        $result = $this->callRpc('payment-service', 'refundPayment', $refundData);
        
        if (!$result['success']) {
            throw new \Exception("Payment refund failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return $this->successResponse([
            'refund_id' => $result['data']['refund_id'],
            'refund_amount' => $result['data']['refund_amount'],
            'refund_status' => $result['data']['status'],
            'original_payment_id' => $paymentId
        ]);
    }
}

