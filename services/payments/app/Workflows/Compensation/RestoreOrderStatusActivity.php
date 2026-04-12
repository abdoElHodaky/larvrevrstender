<?php

namespace App\Workflows\Compensation;

use App\Workflows\Activities\BaseRpcActivity;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Restore Order Status Activity (Compensation)
 * 
 * Restores the original order status when payment confirmation fails.
 * This is an idempotent compensation activity that safely handles
 * cases where the order status has already been restored or doesn't exist.
 */
class RestoreOrderStatusActivity extends BaseRpcActivity
{
    /**
     * Execute the order status restoration compensation activity
     *
     * @param array $data Order data from failed saga step
     * @return array Order status restoration result
     */
    public function execute(array $data): array
    {
        Log::info("RestoreOrderStatusActivity (compensation) started", [
            'saga_id' => $this->getSagaId(),
            'order_id' => $data['order_id'] ?? null,
            'payment_id' => $data['payment_id'] ?? null
        ]);

        try {
            // This is a compensation activity, so we need to be very defensive
            $orderId = $data['order_id'] ?? null;
            
            if (!$orderId) {
                Log::info("No order ID provided for status restoration - treating as idempotent success", [
                    'saga_id' => $this->getSagaId()
                ]);
                
                return $this->successResponse([
                    'restored' => false,
                    'reason' => 'no_order_to_restore',
                    'message' => 'No order ID provided - idempotent operation'
                ]);
            }

            // Determine the restoration status
            $restorationStatus = $this->determineRestorationStatus($data);

            // Prepare order restoration data
            $orderRestorationData = [
                'order_id' => $orderId,
                'status' => $restorationStatus,
                'payment_id' => $data['payment_id'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'restoration_reason' => 'payment_saga_compensation',
                'updated_by' => 'payment_saga_compensation',
                'saga_id' => $this->getSagaId(),
                'restored_at' => now()->toISOString()
            ];

            // Call order service to restore order status
            $result = $this->callRpc('order-service', 'restoreStatus', $orderRestorationData);

            if (!$result['success']) {
                // If the RPC call fails, we still want to continue the saga
                // Log the error but don't throw an exception
                Log::warning("Order status restoration RPC failed", [
                    'saga_id' => $this->getSagaId(),
                    'order_id' => $orderId,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
                
                return $this->successResponse([
                    'order_id' => $orderId,
                    'restored' => false,
                    'reason' => 'rpc_failed',
                    'error' => $result['error'] ?? 'Unknown error',
                    'message' => 'Order status restoration RPC failed but saga will continue'
                ]);
            }

            $orderData = $result['data'] ?? [];

            Log::info("RestoreOrderStatusActivity completed successfully", [
                'saga_id' => $this->getSagaId(),
                'order_id' => $orderId,
                'restored_status' => $restorationStatus,
                'previous_status' => $orderData['previous_status'] ?? null
            ]);

            return $this->successResponse([
                'order_id' => $orderId,
                'payment_id' => $data['payment_id'] ?? null,
                'restored' => true,
                'restored_status' => $restorationStatus,
                'previous_status' => $orderData['previous_status'] ?? null,
                'restored_at' => now()->toISOString(),
                'order_data' => $orderData
            ]);

        } catch (Exception $e) {
            // In compensation activities, we should not throw exceptions
            $this->logError($e);
            
            Log::error("RestoreOrderStatusActivity failed", [
                'saga_id' => $this->getSagaId(),
                'order_id' => $data['order_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            // Return success with failure details to prevent saga from failing
            return $this->successResponse([
                'order_id' => $data['order_id'] ?? null,
                'restored' => false,
                'reason' => 'restoration_failed',
                'error' => $e->getMessage(),
                'message' => 'Order status restoration failed but saga will continue'
            ]);
        }
    }

    /**
     * Determine the appropriate restoration status for the order
     */
    private function determineRestorationStatus(array $data): string
    {
        // If we have the previous status from the update step, use that
        if (isset($data['previous_status'])) {
            return $data['previous_status'];
        }

        // If we have the original order data, try to determine the appropriate status
        if (isset($data['order_data']['status'])) {
            return $data['order_data']['status'];
        }

        // Default restoration statuses based on payment failure context
        $paymentStatus = $data['status'] ?? null;
        
        switch ($paymentStatus) {
            case 'failed':
            case 'cancelled':
                return 'payment_failed';
                
            case 'pending':
            case 'processing':
                return 'pending'; // Restore to pending if payment was still processing
                
            default:
                // Safe default - restore to a status that indicates payment issues
                return 'payment_failed';
        }
    }
}
