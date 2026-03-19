<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Services\OrderInvoiceService;
use App\Services\OrderEscrowService;
use App\States\AwaitingPayment;
use App\RPC\Adapters\NotificationServiceAdapter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * OrderCreatedListener
 * 
 * Orchestrates the complete payment workflow when an order is created.
 * Handles invoice creation, escrow setup, and payment workflow initiation.
 */
class OrderCreatedListener implements ShouldQueue
{
    use InteractsWithQueue;

    private OrderInvoiceService $invoiceService;
    private OrderEscrowService $escrowService;
    private NotificationServiceAdapter $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(
        OrderInvoiceService $invoiceService,
        OrderEscrowService $escrowService,
        NotificationServiceAdapter $notificationService
    ) {
        $this->invoiceService = $invoiceService;
        $this->escrowService = $escrowService;
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(OrderCreated $event): void
    {
        try {
            $order = $event->order;

            Log::info('OrderCreated event received - initiating payment workflow', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'current_state' => $order->state::class,
                'total_amount' => $order->total_amount
            ]);

            // Check if order can transition to AwaitingPayment
            if ($order->canTransitionTo(AwaitingPayment::class)) {
                // Transition to AwaitingPayment state
                $order->transitionToState(AwaitingPayment::class, 'Order created - awaiting payment');

                Log::info('Order transitioned to AwaitingPayment state', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number
                ]);

                // Initiate complete payment workflow
                $this->initiatePaymentWorkflow($order);

            } else {
                Log::warning('Order cannot transition to AwaitingPayment state', [
                    'order_id' => $order->id,
                    'current_state' => $order->state::class,
                    'available_transitions' => $order->getAvailableTransitions()
                ]);
            }

        } catch (Exception $e) {
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
     * Initiate complete payment workflow for order.
     */
    private function initiatePaymentWorkflow($order): void
    {
        try {
            Log::info('Initiating payment workflow for order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount
            ]);

            // Step 1: Create invoice from order
            $invoiceResult = $this->createInvoiceForOrder($order);
            
            if (!$invoiceResult['success']) {
                throw new Exception('Invoice creation failed: ' . $invoiceResult['message']);
            }

            $invoice = $invoiceResult['invoice'];

            // Step 2: Send invoice to customer
            $this->sendInvoiceToCustomer($invoice['id']);

            // Step 3: Send payment notifications
            $this->sendPaymentNotifications($order, $invoice);

            // Step 4: Prepare escrow account (will be funded when payment is received)
            $this->prepareEscrowForOrder($order, $invoice);

            Log::info('Payment workflow initiated successfully', [
                'order_id' => $order->id,
                'invoice_id' => $invoice['id'],
                'invoice_number' => $invoice['invoice_number']
            ]);

        } catch (Exception $e) {
            Log::error('Failed to initiate payment workflow', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            // Don't re-throw here as the order state transition should still succeed
            // But log it as a critical issue for manual intervention
            Log::critical('Payment workflow initiation failed - manual intervention required', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create invoice for the order.
     */
    private function createInvoiceForOrder($order): array
    {
        try {
            Log::info('Creating invoice for order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ]);

            $result = $this->invoiceService->createInvoiceFromOrder($order);

            if ($result['success']) {
                Log::info('Invoice created successfully', [
                    'order_id' => $order->id,
                    'invoice_id' => $result['invoice']['id'],
                    'invoice_number' => $result['invoice']['invoice_number']
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Error creating invoice for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create invoice: ' . $e->getMessage(),
                'invoice' => null
            ];
        }
    }

    /**
     * Send invoice to customer.
     */
    private function sendInvoiceToCustomer(int $invoiceId): void
    {
        try {
            Log::info('Sending invoice to customer', ['invoice_id' => $invoiceId]);

            $result = $this->invoiceService->sendInvoice($invoiceId);

            if ($result['success']) {
                Log::info('Invoice sent successfully', ['invoice_id' => $invoiceId]);
            } else {
                Log::warning('Failed to send invoice', [
                    'invoice_id' => $invoiceId,
                    'error' => $result['message']
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error sending invoice to customer', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send payment notifications to customer and merchant.
     */
    private function sendPaymentNotifications($order, array $invoice): void
    {
        try {
            // Customer notification - payment due
            $this->sendCustomerPaymentNotification($order, $invoice);

            // Merchant notification - order placed, payment pending
            $this->sendMerchantOrderNotification($order, $invoice);

        } catch (Exception $e) {
            Log::error('Error sending payment notifications', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send payment due notification to customer.
     */
    private function sendCustomerPaymentNotification($order, array $invoice): void
    {
        try {
            $notificationData = [
                'user_id' => $order->customer_id,
                'type' => 'payment_due',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'invoice_id' => $invoice['id'],
                    'invoice_number' => $invoice['invoice_number'],
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'due_date' => $order->payment_due_at?->toISOString(),
                    'payment_link' => $this->generatePaymentLink($invoice),
                    'merchant_name' => $invoice['merchant_name'] ?? 'Merchant'
                ]
            ];

            $result = $this->notificationService->sendNotification($notificationData);

            Log::info('Customer payment notification sent', [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'success' => $result['success'] ?? false
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send customer payment notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send order placed notification to merchant.
     */
    private function sendMerchantOrderNotification($order, array $invoice): void
    {
        try {
            $notificationData = [
                'user_id' => $order->merchant_id,
                'type' => 'order_placed',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'invoice_id' => $invoice['id'],
                    'invoice_number' => $invoice['invoice_number'],
                    'order_amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'customer_name' => $invoice['customer_name'] ?? 'Customer',
                    'delivery_address' => $order->delivery_address,
                    'estimated_delivery' => $order->estimated_delivery?->toISOString(),
                    'payment_status' => 'pending'
                ]
            ];

            $result = $this->notificationService->sendNotification($notificationData);

            Log::info('Merchant order notification sent', [
                'order_id' => $order->id,
                'merchant_id' => $order->merchant_id,
                'success' => $result['success'] ?? false
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send merchant order notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Prepare escrow account for order (will be funded when payment is received).
     */
    private function prepareEscrowForOrder($order, array $invoice): void
    {
        try {
            Log::info('Preparing escrow for order', [
                'order_id' => $order->id,
                'invoice_id' => $invoice['id']
            ]);

            // Create escrow account (unfunded initially)
            $escrowResult = $this->escrowService->createEscrowForOrder($order, [
                'payment_id' => null, // Will be set when payment is received
                'invoice_id' => $invoice['id']
            ]);

            if ($escrowResult['success']) {
                Log::info('Escrow prepared successfully', [
                    'order_id' => $order->id,
                    'escrow_id' => $escrowResult['escrow']['id']
                ]);
            } else {
                Log::warning('Failed to prepare escrow', [
                    'order_id' => $order->id,
                    'error' => $escrowResult['message']
                ]);
            }

        } catch (Exception $e) {
            Log::error('Error preparing escrow for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate payment link for invoice.
     */
    private function generatePaymentLink(array $invoice): string
    {
        // Generate payment link based on invoice
        $baseUrl = config('app.frontend_url', 'https://app.example.com');
        return $baseUrl . '/payments/invoice/' . $invoice['id'];
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrderCreated $event, \Throwable $exception): void
    {
        Log::critical('OrderCreatedListener failed permanently', [
            'order_id' => $event->order->id ?? null,
            'order_number' => $event->order->order_number ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Could send alert to administrators here
        // Could also update order status to indicate payment workflow failure
    }
}
