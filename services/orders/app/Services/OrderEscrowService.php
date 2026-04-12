<?php

namespace App\Services;

use App\Models\Order;
use App\RPC\Adapters\PaymentServiceAdapter;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * OrderEscrowService
 * 
 * Manages automatic escrow account creation for orders.
 * Provides buyer protection by holding funds until delivery confirmation.
 */
class OrderEscrowService
{
    private PaymentServiceAdapter $paymentService;

    public function __construct(PaymentServiceAdapter $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Create escrow account for order payment.
     */
    public function createEscrowForOrder(Order $order, array $paymentData): array
    {
        try {
            Log::info('Creating escrow for order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->total_amount,
                'payment_id' => $paymentData['payment_id'] ?? null
            ]);

            // Prepare escrow data
            $escrowData = $this->prepareEscrowData($order, $paymentData);

            // Create escrow via payment service
            $escrowResponse = $this->paymentService->createEscrow($escrowData);

            if (!$escrowResponse['success']) {
                throw new Exception('Failed to create escrow: ' . $escrowResponse['message']);
            }

            $escrow = $escrowResponse['data'];

            Log::info('Escrow created successfully', [
                'order_id' => $order->id,
                'escrow_id' => $escrow['id'],
                'amount' => $escrow['amount'],
                'hold_until' => $escrow['hold_until']
            ]);

            return [
                'success' => true,
                'message' => 'Escrow created successfully',
                'escrow' => $escrow
            ];

        } catch (Exception $e) {
            Log::error('Failed to create escrow for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create escrow: ' . $e->getMessage(),
                'escrow' => null
            ];
        }
    }

    /**
     * Prepare escrow data from order and payment information.
     */
    private function prepareEscrowData(Order $order, array $paymentData): array
    {
        // Calculate hold period based on estimated delivery
        $holdUntil = $this->calculateEscrowHoldPeriod($order);

        return [
            'order_id' => $order->id,
            'payment_id' => $paymentData['payment_id'],
            'buyer_id' => $order->customer_id,
            'seller_id' => $order->merchant_id,
            'amount' => $order->total_amount,
            'currency' => $order->currency ?? 'SAR',
            'hold_until' => $holdUntil,
            'release_conditions' => $this->getDefaultReleaseConditions($order),
            'metadata' => [
                'order_number' => $order->order_number,
                'winning_bid_id' => $order->winning_bid_id,
                'part_request_id' => $order->part_request_id,
                'estimated_delivery' => $order->estimated_delivery,
                'created_via' => 'order_automation',
                'created_at' => now()->toISOString()
            ]
        ];
    }

    /**
     * Calculate escrow hold period based on order details.
     */
    private function calculateEscrowHoldPeriod(Order $order): string
    {
        // If estimated delivery is set, hold until 3 days after delivery
        if ($order->estimated_delivery) {
            return $order->estimated_delivery->addDays(3)->toISOString();
        }

        // Parse order metadata for delivery timeframe
        $orderMetadata = is_string($order->metadata) 
            ? json_decode($order->metadata, true) 
            : ($order->metadata ?? []);

        // Check for delivery timeframe in metadata
        if (isset($orderMetadata['delivery_days'])) {
            $deliveryDays = (int) $orderMetadata['delivery_days'];
            return now()->addDays($deliveryDays + 3)->toISOString(); // Add 3 days buffer
        }

        // Default: 14 days from now (7 days delivery + 7 days confirmation period)
        return now()->addDays(14)->toISOString();
    }

    /**
     * Get default release conditions for order escrow.
     */
    private function getDefaultReleaseConditions(Order $order): array
    {
        return [
            [
                'type' => 'delivery_confirmation',
                'description' => 'Customer confirms delivery of the ordered part',
                'required' => true,
                'auto_release_after_days' => 7, // Auto-release if no dispute after 7 days
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number
                ]
            ],
            [
                'type' => 'quality_acceptance',
                'description' => 'Customer accepts the quality of the delivered part',
                'required' => false, // Optional - defaults to accepted if no complaint
                'auto_release_after_days' => 3, // Auto-accept if no complaint after 3 days
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number
                ]
            ],
            [
                'type' => 'dispute_resolution',
                'description' => 'Any disputes must be resolved before release',
                'required' => true,
                'auto_release_after_days' => null, // No auto-release for disputes
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number
                ]
            ]
        ];
    }

    /**
     * Fund escrow account with payment.
     */
    public function fundEscrow(int $escrowId, array $paymentData): array
    {
        try {
            Log::info('Funding escrow account', [
                'escrow_id' => $escrowId,
                'payment_id' => $paymentData['payment_id'] ?? null,
                'amount' => $paymentData['amount'] ?? null
            ]);

            $response = $this->paymentService->fundEscrow($escrowId, $paymentData);

            Log::info('Escrow funding result', [
                'escrow_id' => $escrowId,
                'success' => $response['success'],
                'message' => $response['message'] ?? null
            ]);

            return $response;

        } catch (Exception $e) {
            Log::error('Failed to fund escrow', [
                'escrow_id' => $escrowId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fund escrow: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Release escrow funds to merchant.
     */
    public function releaseEscrow(int $escrowId, string $reason = 'delivery_confirmed'): array
    {
        try {
            Log::info('Releasing escrow funds', [
                'escrow_id' => $escrowId,
                'reason' => $reason
            ]);

            $response = $this->paymentService->releaseEscrow($escrowId, [
                'reason' => $reason,
                'released_by' => 'system_automation',
                'released_at' => now()->toISOString()
            ]);

            Log::info('Escrow release result', [
                'escrow_id' => $escrowId,
                'success' => $response['success'],
                'reason' => $reason
            ]);

            return $response;

        } catch (Exception $e) {
            Log::error('Failed to release escrow', [
                'escrow_id' => $escrowId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to release escrow: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get escrow status for order.
     */
    public function getOrderEscrowStatus(int $orderId): array
    {
        try {
            return $this->paymentService->getEscrowByOrderId($orderId);
        } catch (Exception $e) {
            Log::error('Failed to get escrow status', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get escrow status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle delivery confirmation for escrow release.
     */
    public function handleDeliveryConfirmation(int $orderId, array $confirmationData): array
    {
        try {
            Log::info('Processing delivery confirmation for escrow', [
                'order_id' => $orderId,
                'confirmed_by' => $confirmationData['confirmed_by'] ?? null
            ]);

            // Get escrow for order
            $escrowResponse = $this->getOrderEscrowStatus($orderId);
            
            if (!$escrowResponse['success']) {
                throw new Exception('Could not find escrow for order: ' . $escrowResponse['message']);
            }

            $escrow = $escrowResponse['data'];

            // Release escrow funds
            $releaseResponse = $this->releaseEscrow($escrow['id'], 'delivery_confirmed');

            if ($releaseResponse['success']) {
                Log::info('Escrow released after delivery confirmation', [
                    'order_id' => $orderId,
                    'escrow_id' => $escrow['id']
                ]);
            }

            return $releaseResponse;

        } catch (Exception $e) {
            Log::error('Failed to handle delivery confirmation', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process delivery confirmation: ' . $e->getMessage()
            ];
        }
    }
}
