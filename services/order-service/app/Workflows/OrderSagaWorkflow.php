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
use Workflow\ActivityOptions;
use Workflow\RetryOptions;
use Throwable;
use Illuminate\Support\Facades\Log;
use function Workflow\activity;

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
            'workflow_id' => $this->workflowId(),
            'saga_data' => $orderData
        ]);

        // Configure activity options with retry policies and timeouts
        $standardActivityOptions = ActivityOptions::new()
            ->withRetryOptions(
                RetryOptions::new()
                    ->withMaximumAttempts(3)
                    ->withInitialInterval(1) // 1 second
                    ->withMaximumInterval(60) // 1 minute
                    ->withBackoffCoefficient(2.0) // Exponential backoff
            )
            ->withStartToCloseTimeout(180); // 3 minutes

        $criticalActivityOptions = ActivityOptions::new()
            ->withRetryOptions(
                RetryOptions::new()
                    ->withMaximumAttempts(5)
                    ->withInitialInterval(2) // 2 seconds
                    ->withMaximumInterval(300) // 5 minutes
                    ->withBackoffCoefficient(2.0)
            )
            ->withStartToCloseTimeout(600); // 10 minutes

        $inventoryActivityOptions = ActivityOptions::new()
            ->withRetryOptions(
                RetryOptions::new()
                    ->withMaximumAttempts(4)
                    ->withInitialInterval(1) // 1 second
                    ->withMaximumInterval(120) // 2 minutes
                    ->withBackoffCoefficient(2.0)
            )
            ->withStartToCloseTimeout(300); // 5 minutes
        
        try {
            // Step 1: Update order state to awaiting payment
            $this->updateOrderState($orderId, AwaitingPayment::class, 'Payment processing initiated');
            
            // Step 2: Process payment (Critical - higher retry count)
            Log::info("Processing payment", ['order_id' => $orderId]);
            $paymentResult = yield activity(ProcessPaymentActivity::class, $orderData)
                ->withActivityOptions($criticalActivityOptions);
            
            if (!$paymentResult['success']) {
                throw new \Exception('Payment processing failed: ' . ($paymentResult['error'] ?? 'Unknown error'));
            }
            
            $paymentId = $paymentResult['data']['payment_id'];
            $this->addCompensation(fn () => activity(RefundPaymentActivity::class, $paymentId)
                ->withActivityOptions($criticalActivityOptions));
            
            // Update order state to paid
            $this->updateOrderState($orderId, Paid::class, 'Payment completed successfully');
            
            // Step 3: Reserve inventory (Inventory-specific retry policy)
            Log::info("Reserving inventory", ['order_id' => $orderId]);
            $inventoryResult = yield activity(ReserveInventoryActivity::class, $orderData)
                ->withActivityOptions($inventoryActivityOptions);
            
            if (!$inventoryResult['success']) {
                throw new \Exception('Inventory reservation failed: ' . ($inventoryResult['error'] ?? 'Unknown error'));
            }
            
            $reservationId = $inventoryResult['data']['reservation_id'];
            $this->addCompensation(fn () => activity(ReleaseInventoryActivity::class, $reservationId)
                ->withActivityOptions($inventoryActivityOptions));
            
            // Update order state to processing
            $this->updateOrderState($orderId, Processing::class, 'Inventory reserved, preparing for shipment');
            
            // Step 4: Schedule shipping
            Log::info("Scheduling shipping", ['order_id' => $orderId]);
            $shippingResult = yield activity(ScheduleShippingActivity::class, $orderData)
                ->withActivityOptions($standardActivityOptions);
            
            if (!$shippingResult['success']) {
                throw new \Exception('Shipping scheduling failed: ' . ($shippingResult['error'] ?? 'Unknown error'));
            }
            
            $shipmentId = $shippingResult['data']['shipment_id'];
            $this->addCompensation(fn () => activity(CancelShippingActivity::class, $shipmentId)
                ->withActivityOptions($standardActivityOptions));
            
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
                'workflow_id' => $this->workflowId(),
                'result' => $finalResult
            ]);
            
            return $finalResult;
            
        } catch (Throwable $th) {
            Log::error("Order Saga failed", [
                'order_id' => $orderId,
                'workflow_id' => $this->workflowId(),
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
                    'workflow_id' => $this->workflowId()
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
    

}
