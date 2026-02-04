<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Bid;
use App\Models\PartRequest;
use App\Services\EnhancedOrderService;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Events\OrderCompleted;
use App\Events\OrderCancelled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Carbon\Carbon;

class EnhancedOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private EnhancedOrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = new EnhancedOrderService();
        Event::fake();
    }

    /** @test */
    public function it_can_create_order_from_winning_bid()
    {
        // Create test data
        $partRequest = PartRequest::factory()->create([
            'customer_id' => 1,
            'status' => 'bidding_closed',
            'part_name' => 'Brake Pads',
            'part_number' => 'BP-123',
            'quantity' => 2,
        ]);

        $bid = Bid::factory()->create([
            'part_request_id' => $partRequest->id,
            'merchant_id' => 2,
            'amount' => 500.00,
            'delivery_cost' => 50.00,
            'status' => 'accepted',
            'part_condition' => 'new',
            'warranty_period' => 12,
            'delivery_time' => 3,
        ]);

        $orderData = [
            'currency' => 'SAR',
            'delivery_method' => 'standard',
            'delivery_address' => [
                'street' => '123 King Fahd Road',
                'city' => 'Riyadh',
                'postal_code' => '12345',
            ],
            'payment_method' => 'credit_card',
            'notes' => ['Customer requested express delivery'],
        ];

        $result = $this->orderService->createOrderFromBid($bid->id, $orderData);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('order', $result);
        $this->assertEquals('Order created successfully', $result['message']);

        // Verify order was created in database
        $this->assertDatabaseHas('orders', [
            'part_request_id' => $partRequest->id,
            'winning_bid_id' => $bid->id,
            'customer_id' => $partRequest->customer_id,
            'merchant_id' => $bid->merchant_id,
            'part_cost' => 500.00,
            'delivery_cost' => 50.00,
            'currency' => 'SAR',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'delivery_method' => 'standard',
            'payment_method' => 'credit_card',
        ]);

        // Verify calculated totals
        $order = Order::where('winning_bid_id', $bid->id)->first();
        $this->assertEquals(500.00, $order->part_cost);
        $this->assertEquals(50.00, $order->delivery_cost);
        $this->assertEquals(82.50, $order->tax_amount); // 15% VAT on subtotal
        $this->assertEquals(27.50, $order->platform_fee); // 5% platform fee on subtotal
        $this->assertEquals(660.00, $order->total_amount); // 550 + 82.50 + 27.50

        // Verify order item was created
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'part_name' => 'Brake Pads',
            'part_number' => 'BP-123',
            'quantity' => 2,
            'unit_price' => 500.00,
            'total_price' => 500.00,
            'part_condition' => 'new',
            'warranty_period' => 12,
        ]);

        // Verify bid status was updated
        $bid->refresh();
        $this->assertEquals('accepted', $bid->status);

        // Verify part request status was updated
        $partRequest->refresh();
        $this->assertEquals('order_created', $partRequest->status);

        // Verify events were dispatched
        Event::assertDispatched(OrderCreated::class);
    }

    /** @test */
    public function it_validates_bid_before_creating_order()
    {
        $bid = Bid::factory()->create(['status' => 'pending']);

        $result = $this->orderService->createOrderFromBid($bid->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Bid must be accepted to create order', $result['message']);
    }

    /** @test */
    public function it_prevents_duplicate_orders_from_same_bid()
    {
        $partRequest = PartRequest::factory()->create(['status' => 'bidding_closed']);
        $bid = Bid::factory()->create([
            'part_request_id' => $partRequest->id,
            'status' => 'accepted',
        ]);

        // Create first order
        $this->orderService->createOrderFromBid($bid->id);

        // Try to create second order from same bid
        $result = $this->orderService->createOrderFromBid($bid->id);

        $this->assertFalse($result['success']);
        $this->assertEquals('Order already exists for this bid', $result['message']);
    }

    /** @test */
    public function it_can_get_order_details_with_caching()
    {
        $order = Order::factory()->create([
            'status' => Order::STATUS_PAYMENT_CONFIRMED,
            'total_amount' => 1000.00,
            'paid_at' => now(),
            'estimated_delivery' => now()->addDays(5),
        ]);

        $result = $this->orderService->getOrderDetails($order->id);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('order', $result);
        $this->assertArrayHasKey('payment_status', $result);
        $this->assertArrayHasKey('delivery_status', $result);
        $this->assertArrayHasKey('next_actions', $result);

        // Verify payment status
        $this->assertEquals('paid', $result['payment_status']['status']);
        $this->assertNotNull($result['payment_status']['paid_at']);

        // Verify delivery status
        $this->assertEquals('pending', $result['delivery_status']['status']);

        // Verify next actions
        $this->assertContains('start_processing', $result['next_actions']);

        // Verify caching
        $this->assertTrue(Cache::has("order_details:{$order->id}"));
    }

    /** @test */
    public function it_returns_error_for_non_existent_order()
    {
        $result = $this->orderService->getOrderDetails(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Order not found', $result['message']);
    }

    /** @test */
    public function it_can_update_order_status_with_validation()
    {
        $order = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        $statusData = [
            'payment_reference' => 'PAY-123456',
            'updated_by' => 1,
            'note' => 'Payment confirmed via credit card',
        ];

        $result = $this->orderService->updateOrderStatus(
            $order->id,
            Order::STATUS_PAYMENT_CONFIRMED,
            $statusData
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $result['previous_status']);
        $this->assertEquals(Order::STATUS_PAYMENT_CONFIRMED, $result['new_status']);

        // Verify order was updated
        $order->refresh();
        $this->assertEquals(Order::STATUS_PAYMENT_CONFIRMED, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertEquals('PAY-123456', $order->payment_reference);

        // Verify status history
        $this->assertNotEmpty($order->status_history);
        $latestHistory = end($order->status_history);
        $this->assertEquals(Order::STATUS_PAYMENT_CONFIRMED, $latestHistory['status']);
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $latestHistory['previous_status']);
        $this->assertEquals('Payment confirmed via credit card', $latestHistory['note']);

        // Verify events were dispatched
        Event::assertDispatched(OrderStatusChanged::class);
    }

    /** @test */
    public function it_validates_status_transitions()
    {
        $order = Order::factory()->create([
            'status' => Order::STATUS_COMPLETED,
        ]);

        $result = $this->orderService->updateOrderStatus(
            $order->id,
            Order::STATUS_PENDING_PAYMENT
        );

        $this->assertFalse($result['success']);
        $this->assertStringContains('Cannot transition from completed to pending_payment', $result['message']);
    }

    /** @test */
    public function it_can_search_orders_with_filters()
    {
        // Create test orders
        $customer1 = 1;
        $customer2 = 2;
        $merchant1 = 3;

        Order::factory()->create([
            'customer_id' => $customer1,
            'merchant_id' => $merchant1,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total_amount' => 500.00,
            'created_at' => now()->subDays(5),
        ]);

        Order::factory()->create([
            'customer_id' => $customer2,
            'merchant_id' => $merchant1,
            'status' => Order::STATUS_COMPLETED,
            'total_amount' => 1000.00,
            'created_at' => now()->subDays(2),
        ]);

        Order::factory()->create([
            'customer_id' => $customer1,
            'merchant_id' => $merchant1,
            'status' => Order::STATUS_SHIPPED,
            'total_amount' => 750.00,
            'created_at' => now()->subDay(),
        ]);

        // Test customer filter
        $criteria = ['customer_id' => $customer1];
        $result = $this->orderService->searchOrders($criteria);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['orders']);
        $this->assertEquals(2, $result['summary']['total_orders']);

        // Test status filter
        $criteria = ['status' => Order::STATUS_PENDING_PAYMENT];
        $result = $this->orderService->searchOrders($criteria);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['orders']);
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $result['orders'][0]->status);

        // Test amount range filter
        $criteria = ['amount_min' => 600, 'amount_max' => 800];
        $result = $this->orderService->searchOrders($criteria);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['orders']);
        $this->assertEquals(750.00, $result['orders'][0]->total_amount);

        // Test date range filter
        $criteria = [
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ];
        $result = $this->orderService->searchOrders($criteria);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['orders']);

        // Test pagination
        $criteria = ['per_page' => 1, 'page' => 1];
        $result = $this->orderService->searchOrders($criteria);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['orders']);
        $this->assertEquals(1, $result['pagination']['current_page']);
        $this->assertEquals(3, $result['pagination']['total']);
    }

    /** @test */
    public function it_can_get_order_analytics()
    {
        // Create test orders
        Order::factory()->create([
            'status' => Order::STATUS_COMPLETED,
            'total_amount' => 500.00,
            'payment_method' => 'credit_card',
            'delivery_method' => 'standard',
            'created_at' => now()->subMonth(),
        ]);

        Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total_amount' => 750.00,
            'payment_method' => 'bank_transfer',
            'delivery_method' => 'express',
            'created_at' => now()->subWeek(),
        ]);

        Order::factory()->create([
            'status' => Order::STATUS_COMPLETED,
            'total_amount' => 1000.00,
            'payment_method' => 'credit_card',
            'delivery_method' => 'standard',
            'created_at' => now()->subDay(),
        ]);

        $result = $this->orderService->getOrderAnalytics();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('analytics', $result);

        $analytics = $result['analytics'];
        $this->assertEquals(3, $analytics['total_orders']);
        $this->assertEquals(2250.00, $analytics['total_revenue']);
        $this->assertEquals(750.00, $analytics['average_order_value']);

        // Verify status breakdown
        $this->assertArrayHasKey('orders_by_status', $analytics);
        $this->assertEquals(2, $analytics['orders_by_status'][Order::STATUS_COMPLETED]);
        $this->assertEquals(1, $analytics['orders_by_status'][Order::STATUS_PENDING_PAYMENT]);

        // Verify payment methods breakdown
        $this->assertArrayHasKey('payment_methods', $analytics);
        $this->assertEquals(2, $analytics['payment_methods']['credit_card']);
        $this->assertEquals(1, $analytics['payment_methods']['bank_transfer']);

        // Verify delivery methods breakdown
        $this->assertArrayHasKey('delivery_methods', $analytics);
        $this->assertEquals(2, $analytics['delivery_methods']['standard']);
        $this->assertEquals(1, $analytics['delivery_methods']['express']);
    }

    /** @test */
    public function it_can_cancel_order_with_validation()
    {
        $order = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total_amount' => 500.00,
        ]);

        $result = $this->orderService->cancelOrder($order->id, 'Customer requested cancellation', 1);

        $this->assertTrue($result['success']);

        // Verify order status was updated
        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);

        // Verify status history
        $latestHistory = end($order->status_history);
        $this->assertEquals(Order::STATUS_CANCELLED, $latestHistory['status']);
        $this->assertEquals('Customer requested cancellation', $latestHistory['metadata']['cancellation_reason']);

        // Verify events were dispatched
        Event::assertDispatched(OrderStatusChanged::class);
        Event::assertDispatched(OrderCancelled::class);
    }

    /** @test */
    public function it_prevents_cancellation_of_non_cancellable_orders()
    {
        $order = Order::factory()->create([
            'status' => Order::STATUS_COMPLETED,
        ]);

        $result = $this->orderService->cancelOrder($order->id, 'Test cancellation', 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Order cannot be cancelled in current status', $result['message']);
    }

    /** @test */
    public function it_calculates_estimated_delivery_correctly()
    {
        $partRequest = PartRequest::factory()->create(['status' => 'bidding_closed']);
        $bid = Bid::factory()->create([
            'part_request_id' => $partRequest->id,
            'status' => 'accepted',
        ]);

        // Test different delivery methods
        $testCases = [
            'express' => 1,
            'fast' => 2,
            'standard' => 5,
            'economy' => 10,
        ];

        foreach ($testCases as $method => $expectedDays) {
            $orderData = ['delivery_method' => $method];
            $result = $this->orderService->createOrderFromBid($bid->id, $orderData);

            $this->assertTrue($result['success']);
            
            $order = $result['order'];
            $expectedDate = now()->addDays($expectedDays)->toDateString();
            $actualDate = $order->estimated_delivery->toDateString();
            
            $this->assertEquals($expectedDate, $actualDate, "Failed for delivery method: {$method}");

            // Clean up for next iteration
            $order->delete();
        }
    }

    /** @test */
    public function it_detects_payment_overdue_status()
    {
        $order = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_due_at' => now()->subHours(2),
            'paid_at' => null,
        ]);

        $result = $this->orderService->getOrderDetails($order->id);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['order']->is_payment_overdue);
        $this->assertEquals('overdue', $result['payment_status']['status']);
        $this->assertGreaterThan(0, $result['payment_status']['overdue_days']);
    }

    /** @test */
    public function it_identifies_orders_requiring_action()
    {
        // Create orders in different statuses
        $pendingPaymentOrder = Order::factory()->create([
            'customer_id' => 1,
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        $processingOrder = Order::factory()->create([
            'merchant_id' => 2,
            'status' => Order::STATUS_PROCESSING,
        ]);

        $completedOrder = Order::factory()->create([
            'customer_id' => 1,
            'status' => Order::STATUS_COMPLETED,
        ]);

        // Test customer perspective
        $criteria = ['customer_id' => 1];
        $result = $this->orderService->searchOrders($criteria);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['orders']);

        // Verify pending payment order requires customer action
        $pendingOrder = collect($result['orders'])->firstWhere('id', $pendingPaymentOrder->id);
        $this->assertNotNull($pendingOrder);

        // Test merchant perspective
        $criteria = ['merchant_id' => 2];
        $result = $this->orderService->searchOrders($criteria);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['orders']);

        // Verify processing order requires merchant action
        $merchantOrder = $result['orders'][0];
        $this->assertEquals(Order::STATUS_PROCESSING, $merchantOrder->status);
    }

    /** @test */
    public function it_clears_cache_after_order_updates()
    {
        $order = Order::factory()->create();

        // First call to populate cache
        $this->orderService->getOrderDetails($order->id);
        $this->assertTrue(Cache::has("order_details:{$order->id}"));

        // Update order status should clear cache
        $this->orderService->updateOrderStatus($order->id, Order::STATUS_PAYMENT_CONFIRMED);
        $this->assertFalse(Cache::has("order_details:{$order->id}"));
    }

    /** @test */
    public function it_handles_order_completion_workflow()
    {
        $order = Order::factory()->create([
            'status' => Order::STATUS_DELIVERED,
        ]);

        $result = $this->orderService->updateOrderStatus(
            $order->id,
            Order::STATUS_COMPLETED,
            ['updated_by' => 1, 'note' => 'Order completed successfully']
        );

        $this->assertTrue($result['success']);

        // Verify order was marked as completed
        $order->refresh();
        $this->assertEquals(Order::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->completed_at);

        // Verify completion event was dispatched
        Event::assertDispatched(OrderCompleted::class);
    }

    /** @test */
    public function it_tracks_order_status_history()
    {
        $order = Order::factory()->create([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'status_history' => [
                [
                    'status' => Order::STATUS_PENDING_PAYMENT,
                    'timestamp' => now()->subHour()->toISOString(),
                    'note' => 'Order created',
                    'updated_by' => 'system',
                ]
            ],
        ]);

        // Update status multiple times
        $this->orderService->updateOrderStatus($order->id, Order::STATUS_PAYMENT_CONFIRMED, [
            'updated_by' => 1,
            'note' => 'Payment confirmed',
        ]);

        $this->orderService->updateOrderStatus($order->id, Order::STATUS_PROCESSING, [
            'updated_by' => 2,
            'note' => 'Order processing started',
        ]);

        $order->refresh();
        $this->assertCount(3, $order->status_history);

        // Verify history is properly structured
        $history = $order->status_history;
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $history[0]['status']);
        $this->assertEquals(Order::STATUS_PAYMENT_CONFIRMED, $history[1]['status']);
        $this->assertEquals(Order::STATUS_PROCESSING, $history[2]['status']);

        // Verify previous status tracking
        $this->assertEquals(Order::STATUS_PENDING_PAYMENT, $history[1]['previous_status']);
        $this->assertEquals(Order::STATUS_PAYMENT_CONFIRMED, $history[2]['previous_status']);
    }
}
