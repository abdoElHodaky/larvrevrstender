<?php

namespace App\Services;

use App\Models\Order;
use App\RPC\Adapters\PaymentServiceAdapter;
use App\RPC\Adapters\UserServiceAdapter;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * OrderInvoiceService
 * 
 * Bridges orders to the existing payment service invoice system.
 * Creates invoices from order data and initiates payment workflows.
 */
class OrderInvoiceService
{
    private PaymentServiceAdapter $paymentService;
    private UserServiceAdapter $userService;

    public function __construct(
        PaymentServiceAdapter $paymentService,
        UserServiceAdapter $userService
    ) {
        $this->paymentService = $paymentService;
        $this->userService = $userService;
    }

    /**
     * Create invoice from order and initiate payment workflow.
     */
    public function createInvoiceFromOrder(Order $order): array
    {
        try {
            Log::info('Creating invoice from order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount
            ]);

            // Get customer and merchant details
            $customerData = $this->getUserDetails($order->customer_id);
            $merchantData = $this->getUserDetails($order->merchant_id);

            // Prepare invoice data from order
            $invoiceData = $this->prepareInvoiceData($order, $customerData, $merchantData);

            // Create invoice via payment service
            $invoiceResponse = $this->paymentService->createInvoice($invoiceData);

            if (!$invoiceResponse['success']) {
                throw new Exception('Failed to create invoice: ' . $invoiceResponse['message']);
            }

            $invoice = $invoiceResponse['data'];

            Log::info('Invoice created successfully', [
                'order_id' => $order->id,
                'invoice_id' => $invoice['id'],
                'invoice_number' => $invoice['invoice_number']
            ]);

            return [
                'success' => true,
                'message' => 'Invoice created successfully',
                'invoice' => $invoice
            ];

        } catch (Exception $e) {
            Log::error('Failed to create invoice from order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create invoice: ' . $e->getMessage(),
                'invoice' => null
            ];
        }
    }

    /**
     * Prepare invoice data from order information.
     */
    private function prepareInvoiceData(Order $order, array $customerData, array $merchantData): array
    {
        // Parse delivery address from order
        $deliveryAddress = is_string($order->delivery_address) 
            ? json_decode($order->delivery_address, true) 
            : $order->delivery_address;

        // Parse order metadata for additional details
        $orderMetadata = is_string($order->metadata) 
            ? json_decode($order->metadata, true) 
            : ($order->metadata ?? []);

        return [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'merchant_id' => $order->merchant_id,
            'subtotal' => $order->part_cost + $order->delivery_cost,
            'tax_amount' => $order->tax_amount,
            'platform_fee' => $order->platform_fee,
            'delivery_fee' => $order->delivery_cost,
            'discount_amount' => 0.00, // No discounts in current implementation
            'total_amount' => $order->total_amount,
            'currency' => $order->currency ?? 'SAR',
            'status' => 'draft', // Start as draft, will be sent after creation
            'invoice_date' => now(),
            'due_date' => $order->payment_due_at ?? now()->addDays(7),
            'billing_address' => $deliveryAddress,
            
            // Customer details
            'customer_name' => $customerData['name'] ?? 'Unknown Customer',
            'customer_email' => $customerData['email'] ?? null,
            'customer_phone' => $customerData['phone'] ?? null,
            'customer_tax_id' => $customerData['tax_id'] ?? null,
            
            // Merchant details
            'merchant_name' => $merchantData['name'] ?? 'Unknown Merchant',
            'merchant_tax_number' => $merchantData['tax_number'] ?? null,
            'merchant_address' => $merchantData['address'] ?? [],
            
            // Order-specific details for line items
            'part_description' => $orderMetadata['part_description'] ?? 'Vehicle Part',
            'part_number' => $orderMetadata['part_number'] ?? null,
            'brand' => $orderMetadata['brand'] ?? null,
            'condition' => $orderMetadata['condition'] ?? null,
            'part_cost' => $order->part_cost,
            'delivery_cost' => $order->delivery_cost,
            'warranty_months' => $orderMetadata['warranty_months'] ?? null,
            
            // Additional metadata
            'notes' => $order->notes,
            'metadata' => [
                'order_number' => $order->order_number,
                'winning_bid_id' => $order->winning_bid_id,
                'part_request_id' => $order->part_request_id,
                'created_via' => 'order_automation',
                'created_at' => now()->toISOString()
            ]
        ];
    }

    /**
     * Get user details from user service.
     */
    private function getUserDetails(int $userId): array
    {
        try {
            $userResponse = $this->userService->getUser($userId);
            
            if ($userResponse['success']) {
                return $userResponse['data'];
            }

            Log::warning('Failed to get user details', [
                'user_id' => $userId,
                'error' => $userResponse['message'] ?? 'Unknown error'
            ]);

            return [
                'name' => 'Unknown User',
                'email' => null,
                'phone' => null
            ];

        } catch (Exception $e) {
            Log::error('Error getting user details', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'name' => 'Unknown User',
                'email' => null,
                'phone' => null
            ];
        }
    }

    /**
     * Send invoice to customer via payment service.
     */
    public function sendInvoice(int $invoiceId): array
    {
        try {
            $response = $this->paymentService->sendInvoice($invoiceId);

            Log::info('Invoice sent to customer', [
                'invoice_id' => $invoiceId,
                'success' => $response['success']
            ]);

            return $response;

        } catch (Exception $e) {
            Log::error('Failed to send invoice', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send invoice: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get invoice status from payment service.
     */
    public function getInvoiceStatus(int $invoiceId): array
    {
        try {
            return $this->paymentService->getInvoice($invoiceId);
        } catch (Exception $e) {
            Log::error('Failed to get invoice status', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get invoice status: ' . $e->getMessage()
            ];
        }
    }
}
