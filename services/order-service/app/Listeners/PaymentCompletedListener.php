<?php

namespace App\Listeners;

use App\Models\Order;
use App\States\Paid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class PaymentCompletedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        try {
            // Extract payment information from the event
            $paymentId = $event->payment->id ?? null;
            $orderId = $event->payment->order_id ?? null;
            $paymentReference = $event->payment->reference ?? null;

            if (!$orderId) {
                Log::warning('PaymentCompleted event received without order_id', [
                    'payment_id' => $paymentId,
                    'event' => get_class($event)
                ]);
                return;
            }

            // Find the order
            $order = Order::find($orderId);
            if (!$order) {
                Log::warning('Order not found for PaymentCompleted event', [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId
                ]);
                return;
            }

            // Check if order is in AwaitingPayment state
            if (!$order->canTransitionTo(Paid::class)) {
                Log::warning('Order cannot transition to Paid state', [
                    'order_id' => $orderId,
                    'current_state' => $order->state::class,
                    'payment_id' => $paymentId
                ]);
                return;
            }

            // Transition order to Paid state
            $order->transitionToState(Paid::class, "Payment completed - Reference: {$paymentReference}");

            Log::info('Order successfully transitioned to Paid state', [
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'payment_reference' => $paymentReference
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle PaymentCompleted event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'event' => get_class($event)
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(object $event, \Throwable $exception): void
    {
        Log::error('PaymentCompletedListener failed permanently', [
            'error' => $exception->getMessage(),
            'event' => get_class($event)
        ]);
    }
}
