<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\States\AwaitingPayment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class OrderCreatedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(OrderCreated $event): void
    {
        try {
            $order = $event->order;

            Log::info('OrderCreated event received', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'current_state' => $order->state::class
            ]);

            // Check if order can transition to AwaitingPayment
            if ($order->canTransitionTo(AwaitingPayment::class)) {
                // Transition to AwaitingPayment state
                $order->transitionToState(AwaitingPayment::class, 'Order created - awaiting payment');

                Log::info('Order transitioned to AwaitingPayment state', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number
                ]);

                // TODO: Trigger PaymentService to create invoice and initiate payment
                // This will be implemented when we create the cross-service RPC calls
                $this->triggerPaymentCreation($order);

            } else {
                Log::warning('Order cannot transition to AwaitingPayment state', [
                    'order_id' => $order->id,
                    'current_state' => $order->state::class,
                    'available_transitions' => $order->getAvailableTransitions()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to handle OrderCreated event', [
                'order_id' => $event->order->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Trigger payment creation in PaymentService
     */
    private function triggerPaymentCreation($order): void
    {
        try {
            // TODO: Implement RPC call to PaymentService
            // Example structure:
            /*
            $paymentService = app('payment_service.client');
            $response = $paymentService->call('createInvoice', [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'merchant_id' => $order->merchant_id,
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'due_date' => now()->addDays(7)->toISOString(),
                'items' => [
                    [
                        'description' => "Order {$order->order_number}",
                        'amount' => $order->part_cost,
                        'tax_amount' => $order->tax_amount,
                    ]
                ]
            ]);
            */

            Log::info('Payment creation triggered for order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->total_amount
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to trigger payment creation', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            // Don't re-throw here as this is a secondary operation
            // The order state transition should still succeed
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrderCreated $event, \Throwable $exception): void
    {
        Log::error('OrderCreatedListener failed permanently', [
            'order_id' => $event->order->id ?? null,
            'error' => $exception->getMessage()
        ]);
    }
}
