<?php

namespace App\Workflows;

use App\Workflows\Activities\ProcessPaymentActivity;
use App\Workflows\Activities\ReserveInventoryActivity;
use App\Workflows\Activities\ScheduleShippingActivity;
use App\Workflows\Compensation\RefundPaymentActivity;
use App\Workflows\Compensation\ReleaseInventoryActivity;
use App\Workflows\Compensation\CancelShippingActivity;
use App\Models\Order;
use App\States\Orders\Draft;
use App\States\Orders\AwaitingPayment;
use App\States\Orders\Paid;
use App\States\Orders\Processing;
use App\States\Orders\Shipped;
use App\States\Orders\Completed;
use App\States\Orders\Cancelled;
use Workflow\Workflow;
use Throwable;
use Illuminate\Support\Facades\Log;

/**
 * Order Saga Workflow
 * 
 * Orchestrates the complete order processing flow including:
 * - Payment processing
 * - Inventory reservation
 * - Shipping scheduling
 * - Automatic compensation on failures
 * 
 * Integrates with existing RPC infrastructure and state machine.
 */
class OrderSagaWorkflow extends Workflow
{
    /**
     * Execute the order processing saga
     *
     * @param array $orderData Order data including all necessary information
     * @return array Final result of the saga execution
     */
    public function execute(array $orderData)
    {
        $orderId = $orderData['order_id'];
        
        Log::info("Order Saga started", [
            'order_id' => $orderId,
            'workflow_id' => $this->getWorkflowId(),
            'saga_data' => $orderData
        ]);
        
        try {
            // Step 1: Update order state to awaiting payment
            $this->updateOrderState($orderId, AwaitingPayment::class, 'Payment processing initiated');
            
            // Step 2: Process payment
            Log::info("Processing payment", ['order_id' => $orderId]);
            $paymentResult = yield activity(ProcessPaymentActivity::class, $orderData);
            
            if (!$paymentResult['success']) {
                throw new \Exception('Payment processing failed: ' . ($paymentResult['error'] ?? 'Unknown error'));
            }
            
            $paymentId = $paymentResult['data']['payment_id'];
            $this->addCompensation(fn () => activity(RefundPaymentActivity::class, $paymentId));
            
            // Update order state to paid
            $this->updateOrderState($orderId, Paid::class, 'Payment completed successfully');
            
            // Step 3: Reserve inventory
            Log::info("Reserving inventory", ['order_id' => $orderId]);
            $inventoryResult = yield activity(ReserveInventoryActivity::class, $orderData);
            
            if (!$inventoryResult['success']) {
                throw new \Exception('Inventory reservation failed: ' . ($inventoryResult['error'] ?? 'Unknown error'));
            }
            
            $reservationId = $inventoryResult['data']['reservation_id'];
            $this->addCompensation(fn () => activity(ReleaseInventoryActivity::class, $reservationId));
            
            // Update order state to processing
            $this->updateOrderState($orderId, Processing::class, 'Inventory reserved, preparing for shipment');
            
            // Step 4: Schedule shipping
            Log::info("Scheduling shipping", ['order_id' => $orderId]);
            $shippingResult = yield activity(ScheduleShippingActivity::class, $orderData);
            
            if (!$shippingResult['success']) {
                throw new \Exception('Shipping scheduling failed: ' . ($shippingResult['error'] ?? 'Unknown error'));
            }
            
            $shipmentId = $shippingResult['data']['shipment_id'];
            $this->addCompensation(fn () => activity(CancelShippingActivity::class, $shipmentId));
            
            // Update order state to shipped
            $this->updateOrderState($orderId, Shipped::class, 'Order shipped successfully');
            
            // Step 5: Complete order
            $this->updateOrderState($orderId, Completed::class, 'Order completed successfully');
            
            $finalResult = [
                'status' => 'completed',
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'reservation_id' => $reservationId,
                'shipment_id' => $shipmentId,
                'tracking_number' => $shippingResult['data']['tracking_number'],
                'estimated_delivery' => $shippingResult['data']['estimated_delivery'],
                'completed_at' => now()->toISOString()
            ];
            
            Log::info("Order Saga completed successfully", [
                'order_id' => $orderId,
                'workflow_id' => $this->getWorkflowId(),
                'result' => $finalResult
            ]);
            
            return $finalResult;
            
        } catch (Throwable $th) {
            Log::error("Order Saga failed", [
                'order_id' => $orderId,
                'workflow_id' => $this->getWorkflowId(),
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            
            // Update order state to cancelled
            $this->updateOrderState($orderId, Cancelled::class, 'Order cancelled due to processing failure: ' . $th->getMessage());
            
            // Execute compensation activities in reverse order
            Log::info("Executing compensation activities", ['order_id' => $orderId]);
            yield from $this->compensate();
            
            Log::info("Compensation completed", ['order_id' => $orderId]);
            
            // Re-throw the exception to mark workflow as failed
            throw $th;
        }
    }
    
    /**
     * Update order state using existing state machine
     *
     * @param int $orderId Order ID
     * @param string $stateClass Target state class
     * @param string|null $reason Reason for state change
     */
    private function updateOrderState(int $orderId, string $stateClass, ?string $reason = null): void
    {
        try {
            $order = Order::find($orderId);
            if ($order) {
                $order->transitionToState($stateClass, $reason);
                
                Log::info("Order state updated", [
                    'order_id' => $orderId,
                    'new_state' => $stateClass,
                    'reason' => $reason,
                    'workflow_id' => $this->getWorkflowId()
                ]);
            } else {
                Log::warning("Order not found for state update", [
                    'order_id' => $orderId,
                    'target_state' => $stateClass
                ]);
            }
        } catch (Throwable $e) {
            Log::error("Failed to update order state", [
                'order_id' => $orderId,
                'target_state' => $stateClass,
                'error' => $e->getMessage()
            ]);
            
            // Don't throw here as state update failure shouldn't stop the workflow
            // The workflow can continue and the state can be corrected later
        }
    }
    
    /**
     * Get workflow ID for logging and correlation
     */
    private function getWorkflowId(): ?string
    {
        return method_exists($this, 'getId') ? $this->getId() : null;
    }
}

