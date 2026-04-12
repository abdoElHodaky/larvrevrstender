<?php

namespace App\Workflows\Activities;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Update Order Status Activity
 * 
 * Updates the related order status to reflect payment completion.
 * This step notifies the order service that payment has been processed.
 */
class UpdateOrderStatusActivity extends BaseRpcActivity
{
    /**
     * Get the order service adapter instance
     * Using service locator pattern to avoid serialization issues
     */
    private function getOrderAdapter(): \App\RPC\Adapters\OrderServiceAdapter
    {
        return app(\App\RPC\Adapters\OrderServiceAdapter::class);
    }
    /**
     * Execute the order status update activity
     *
     * @param array $data Payment data from confirmation step
     * @return array Order status update result
     * @throws Exception
     */
    public function execute(array $data): array
    {
        Log::info("UpdateOrderStatusActivity started", [
            'saga_id' => $this->getSagaId(),
            'order_id' => $data['order_id'] ?? null,
            'payment_id' => $data['payment_id'] ?? null
        ]);

        try {
            // Validate required fields from previous step
            $this->validateData($data, [
                'order_id',
                'payment_id',
                'payment_reference',
                'customer_id',
                'amount',
                'status'
            ]);

            // Determine new order status based on payment status
            $newOrderStatus = $this->determineOrderStatus($data['status'], $data['payment_method'] ?? null);

            // Prepare payment context data for adapter
            $paymentData = [
                'payment_id' => $data['payment_id'],
                'payment_reference' => $data['payment_reference'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD',
                'payment_method' => $data['payment_method'] ?? null,
                'confirmed_at' => $data['confirmed_at'] ?? now()->toISOString(),
                'saga_id' => $this->getSagaId()
            ];

            // Call order service via adapter to update order status
            $orderData = $this->getOrderAdapter()->updateOrderStatus($data['order_id'], $newOrderStatus, $paymentData);

            if (!$orderData) {
                throw new Exception('Order status update failed: No response from order service');
            }

            Log::info("UpdateOrderStatusActivity completed successfully", [
                'saga_id' => $this->getSagaId(),
                'order_id' => $data['order_id'],
                'payment_id' => $data['payment_id'],
                'new_status' => $newOrderStatus,
                'previous_status' => $orderData['previous_status'] ?? null
            ]);

            return $this->successResponse([
                'order_id' => $data['order_id'],
                'payment_id' => $data['payment_id'],
                'payment_reference' => $data['payment_reference'],
                'new_status' => $newOrderStatus,
                'previous_status' => $orderData['previous_status'] ?? null,
                'updated_at' => $orderData['updated_at'] ?? now()->toISOString(),
                'order_data' => $orderData
            ]);

        } catch (Exception $e) {
            $this->logError($e);
            
            Log::error("UpdateOrderStatusActivity failed", [
                'saga_id' => $this->getSagaId(),
                'order_id' => $data['order_id'] ?? null,
                'payment_id' => $data['payment_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($e->getMessage(), [
                'order_id' => $data['order_id'] ?? null,
                'payment_id' => $data['payment_id'] ?? null,
                'update_step' => 'order_status_update'
            ]);
        }
    }

    /**
     * Determine new order status based on payment status
     */
    private function determineOrderStatus(string $paymentStatus, ?string $paymentMethod): string
    {
        // Map payment statuses to order statuses
        $statusMapping = [
            'completed' => 'paid',
            'authorized' => 'payment_authorized',
            'pending' => 'payment_pending',
            'processing' => 'payment_processing',
            'failed' => 'payment_failed',
            'cancelled' => 'payment_cancelled',
            'refunded' => 'refunded',
            'partially_refunded' => 'partially_refunded'
        ];

        $orderStatus = $statusMapping[$paymentStatus] ?? 'payment_unknown';

        // Special handling for different payment methods
        switch ($paymentMethod) {
            case 'bank_transfer':
                // Bank transfers might need different order status handling
                if ($paymentStatus === 'pending') {
                    $orderStatus = 'awaiting_bank_transfer';
                } elseif ($paymentStatus === 'completed') {
                    $orderStatus = 'paid';
                }
                break;
                
            case 'credit_card':
            case 'debit_card':
                // Card payments that are authorized but not captured
                if ($paymentStatus === 'authorized') {
                    $orderStatus = 'payment_authorized';
                } elseif ($paymentStatus === 'completed') {
                    $orderStatus = 'paid';
                }
                break;
                
            case 'paypal':
            case 'stripe':
                // These gateways typically complete immediately
                if ($paymentStatus === 'completed') {
                    $orderStatus = 'paid';
                }
                break;
        }

        return $orderStatus;
    }
}
