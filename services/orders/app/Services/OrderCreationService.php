<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Order\OrderItem;
use App\Events\OrderCreated;
use App\RPC\Adapters\AuctionServiceAdapter;
use App\RPC\Adapters\BiddingServiceAdapter;
use App\RPC\Adapters\PaymentServiceAdapter;
use App\RPC\Adapters\NotificationServiceAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Order Creation Service
 * 
 * Handles automatic order creation from winning bids.
 * Manages order initialization, pricing calculations, and workflow setup.
 */
class OrderCreationService
{
    private AuctionServiceAdapter $auctionService;
    private BiddingServiceAdapter $biddingService;
    private PaymentServiceAdapter $paymentService;
    private NotificationServiceAdapter $notificationService;

    public function __construct(
        AuctionServiceAdapter $auctionService,
        BiddingServiceAdapter $biddingService,
        PaymentServiceAdapter $paymentService,
        NotificationServiceAdapter $notificationService
    ) {
        $this->auctionService = $auctionService;
        $this->biddingService = $biddingService;
        $this->paymentService = $paymentService;
        $this->notificationService = $notificationService;
    }

    /**
     * Create order from winning bid.
     */
    public function createFromWinningBid(int $winningBidId, int $auctionId): OrderCreationResult
    {
        try {
            DB::beginTransaction();

            Log::info('Starting order creation from winning bid', [
                'bid_id' => $winningBidId,
                'auction_id' => $auctionId
            ]);

            // Step 1: Retrieve winning bid and auction data
            $bidData = $this->biddingService->getBidDetails($winningBidId);
            $auctionData = $this->auctionService->getAuctionDetails($auctionId);

            if (!$bidData || !$auctionData) {
                return new OrderCreationResult(
                    success: false,
                    message: 'Failed to retrieve bid or auction data',
                    order: null
                );
            }

            // Step 2: Validate order creation prerequisites
            $validationErrors = $this->validateOrderCreationPrerequisites($bidData, $auctionData);
            if (!empty($validationErrors)) {
                return new OrderCreationResult(
                    success: false,
                    message: 'Order creation validation failed: ' . implode(', ', $validationErrors),
                    order: null
                );
            }

            // Step 3: Calculate order totals
            $pricing = $this->calculateOrderPricing($bidData, $auctionData);

            // Step 4: Create order record
            $order = $this->createOrderRecord([
                'winning_bid_id' => $winningBidId,
                'auction_id' => $auctionId,
                'customer_id' => $auctionData['created_by'],
                'merchant_id' => $bidData['user_id'],
                'pricing' => $pricing,
                'auction_data' => $auctionData,
            ]);

            if (!$order) {
                return new OrderCreationResult(
                    success: false,
                    message: 'Failed to create order record',
                    order: null
                );
            }

            // Step 5: Initialize order workflow
            $this->initializeOrderWorkflow($order);

            // Step 6: Send notifications
            $this->sendOrderCreationNotifications($order, $bidData, $auctionData);

            // Step 7: Fire OrderCreated event to trigger payment workflow
            event(new OrderCreated($order, [
                'bid_data' => $bidData,
                'auction_data' => $auctionData,
                'pricing' => $pricing,
                'created_via' => 'winner_selection_automation'
            ]));

            DB::commit();

            Log::info('Order created successfully from winning bid', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'bid_id' => $winningBidId,
                'auction_id' => $auctionId,
                'total_amount' => $order->total_amount,
                'event_fired' => 'OrderCreated'
            ]);

            return new OrderCreationResult(
                success: true,
                message: 'Order created successfully',
                order: $order
            );

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Order creation failed', [
                'bid_id' => $winningBidId,
                'auction_id' => $auctionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new OrderCreationResult(
                success: false,
                message: 'Order creation failed: ' . $e->getMessage(),
                order: null
            );
        }
    }

    /**
     * Validate order creation prerequisites.
     */
    private function validateOrderCreationPrerequisites(array $bidData, array $auctionData): array
    {
        $errors = [];

        // Validate bid amount
        if (empty($bidData['amount']) || $bidData['amount'] <= 0) {
            $errors[] = 'Invalid bid amount';
        }

        // Validate bid status
        if ($bidData['status'] !== 'accepted') {
            $errors[] = 'Bid must have accepted status';
        }

        // Validate auction status
        if ($auctionData['status'] !== 'completed') {
            $errors[] = 'Auction must be completed';
        }

        // Validate user IDs
        if (empty($bidData['user_id']) || empty($auctionData['created_by'])) {
            $errors[] = 'Missing user information';
        }

        // Check if order already exists for this auction
        if (Order::where('winning_bid_id', $bidData['id'])->exists()) {
            $errors[] = 'Order already exists for this winning bid';
        }

        return $errors;
    }

    /**
     * Calculate complete order pricing breakdown.
     */
    private function calculateOrderPricing(array $bidData, array $auctionData): array
    {
        // Base part cost from winning bid
        $partCost = $bidData['amount'];

        // Calculate delivery cost (could be from bid metadata or fixed)
        $deliveryCost = $this->calculateDeliveryCost($bidData, $auctionData);

        // Calculate tax (ZATCA for Saudi Arabia)
        $subtotal = $partCost + $deliveryCost;
        $taxAmount = $this->calculateTaxAmount($subtotal, $auctionData);

        // Calculate platform fee (typically 3-5% of part cost)
        $platformFee = $this->calculatePlatformFee($partCost);

        // Total amount
        $totalAmount = $subtotal + $taxAmount + $platformFee;

        return [
            'part_cost' => number_format($partCost, 2, '.', ''),
            'delivery_cost' => number_format($deliveryCost, 2, '.', ''),
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'tax_amount' => number_format($taxAmount, 2, '.', ''),
            'platform_fee' => number_format($platformFee, 2, '.', ''),
            'total_amount' => number_format($totalAmount, 2, '.', ''),
            'currency' => 'SAR', // Default to Saudi Riyal
        ];
    }

    /**
     * Calculate delivery cost.
     */
    private function calculateDeliveryCost(array $bidData, array $auctionData): float
    {
        // Check if delivery cost specified in bid
        if (isset($bidData['metadata']['delivery_cost'])) {
            return (float) $bidData['metadata']['delivery_cost'];
        }

        // Check if included in bid amount
        if ($bidData['metadata']['delivery_included'] ?? false) {
            return 0.0;
        }

        // Calculate based on delivery distance/location
        $deliveryAddress = $auctionData['delivery_address'] ?? [];
        
        // Default delivery cost tiers
        return match($deliveryAddress['city'] ?? null) {
            'Riyadh' => 50.0,
            'Jeddah', 'Dammam' => 75.0,
            default => 100.0,
        };
    }

    /**
     * Calculate tax amount (ZATCA compliance).
     */
    private function calculateTaxAmount(float $subtotal, array $auctionData): float
    {
        // Default VAT rate in Saudi Arabia: 15%
        $vatRate = 0.15;

        // Check for custom tax configuration
        if (isset($auctionData['metadata']['tax_rate'])) {
            $vatRate = (float) $auctionData['metadata']['tax_rate'];
        }

        return round($subtotal * $vatRate, 2);
    }

    /**
     * Calculate platform fee.
     */
    private function calculatePlatformFee(float $partCost): float
    {
        // Default platform fee: 3% of part cost
        $feePercentage = 0.03;

        // Could be configurable per account or transaction type
        return round($partCost * $feePercentage, 2);
    }

    /**
     * Create order record in database.
     */
    private function createOrderRecord(array $orderData): ?Order
    {
        $order = Order::create([
            'order_number' => $this->generateOrderNumber(),
            'winning_bid_id' => $orderData['winning_bid_id'],
            'customer_id' => $orderData['customer_id'],
            'merchant_id' => $orderData['merchant_id'],
            'part_cost' => $orderData['pricing']['part_cost'],
            'delivery_cost' => $orderData['pricing']['delivery_cost'],
            'tax_amount' => $orderData['pricing']['tax_amount'],
            'platform_fee' => $orderData['pricing']['platform_fee'],
            'total_amount' => $orderData['pricing']['total_amount'],
            'currency' => $orderData['pricing']['currency'],
            'status' => Order::STATUS_DRAFT,
            'delivery_address' => $orderData['auction_data']['delivery_address'] ?? [],
            'notes' => $orderData['auction_data']['description'] ?? null,
            'metadata' => [
                'auction_id' => $orderData['auction_id'],
                'auction_title' => $orderData['auction_data']['title'],
                'created_from_winning_bid' => true,
                'creation_timestamp' => now()->toIso8601String(),
            ],
        ]);

        return $order->fresh();
    }

    /**
     * Generate unique order number.
     */
    private function generateOrderNumber(): string
    {
        // Format: ORD-YYYYMMDD-XXXXX
        $date = now()->format('Ymd');
        $random = str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $orderNumber = "ORD-{$date}-{$random}";

        // Ensure uniqueness
        while (Order::where('order_number', $orderNumber)->exists()) {
            $random = str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
            $orderNumber = "ORD-{$date}-{$random}";
        }

        return $orderNumber;
    }

    /**
     * Initialize order workflow state machine.
     */
    private function initializeOrderWorkflow(Order $order): void
    {
        // Set initial state to AwaitingPayment
        $order->transitionTo(Order::STATE_AWAITING_PAYMENT);

        // Set payment due date (e.g., 7 days from order creation)
        $order->update([
            'payment_due_at' => now()->addDays(7),
            'status_history' => [
                [
                    'status' => Order::STATUS_DRAFT,
                    'timestamp' => now()->toIso8601String(),
                    'reason' => 'Order created from winning bid',
                ],
                [
                    'status' => Order::STATUS_AWAITING_PAYMENT,
                    'timestamp' => now()->toIso8601String(),
                    'reason' => 'Order initialized and awaiting payment',
                ],
            ],
        ]);

        Log::info('Order workflow initialized', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'initial_state' => Order::STATE_AWAITING_PAYMENT,
            'payment_due_at' => $order->payment_due_at,
        ]);
    }

    /**
     * Send notifications for order creation.
     */
    private function sendOrderCreationNotifications(Order $order, array $bidData, array $auctionData): void
    {
        try {
            // Notify customer (auction creator) of order creation
            $this->notificationService->sendNotification([
                'type' => 'order_created',
                'user_id' => $order->customer_id,
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'merchant_id' => $order->merchant_id,
                    'payment_due_at' => $order->payment_due_at->toIso8601String(),
                ]
            ]);

            // Notify merchant (winning supplier) of order placement
            $this->notificationService->sendNotification([
                'type' => 'order_placed',
                'user_id' => $order->merchant_id,
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_amount' => $order->part_cost,
                    'delivery_address' => $order->delivery_address,
                    'customer_id' => $order->customer_id,
                ]
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to send order creation notifications', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get order details.
     */
    public function getOrderDetails(int $orderId): ?array
    {
        $order = Order::find($orderId);

        if (!$order) {
            return null;
        }

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_id' => $order->customer_id,
            'merchant_id' => $order->merchant_id,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'currency' => $order->currency,
            'pricing' => [
                'part_cost' => $order->part_cost,
                'delivery_cost' => $order->delivery_cost,
                'tax_amount' => $order->tax_amount,
                'platform_fee' => $order->platform_fee,
            ],
            'delivery_address' => $order->delivery_address,
            'payment_due_at' => $order->payment_due_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}

/**
 * Order Creation Result DTO
 */
class OrderCreationResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?Order $order
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'order' => $this->order ? [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'total_amount' => $this->order->total_amount,
                'status' => $this->order->status,
            ] : null,
        ];
    }
}
