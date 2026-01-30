<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notification Service
 * 
 * Handles communication with the notification service
 * for sending order-related notifications
 */
class NotificationService
{
    protected string $notificationServiceUrl;

    public function __construct()
    {
        $this->notificationServiceUrl = config('services.notification.url', env('NOTIFICATION_SERVICE_URL'));
    }

    /**
     * Send order created notification
     */
    public function sendOrderCreatedNotification(Order $order): void
    {
        $this->sendNotification([
            'type' => 'order_created',
            'user_id' => $order->customer_id,
            'title' => 'Order Created Successfully',
            'message' => "Your order #{$order->order_number} has been created successfully.",
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status
            ],
            'channels' => ['push', 'email']
        ]);
    }

    /**
     * Send order published notification
     */
    public function sendOrderPublishedNotification(Order $order): void
    {
        // Notify customer
        $this->sendNotification([
            'type' => 'order_published',
            'user_id' => $order->customer_id,
            'title' => 'Order Published',
            'message' => "Your order #{$order->order_number} is now live and merchants can start bidding.",
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'deadline' => $order->deadline?->toISOString()
            ],
            'channels' => ['push', 'email']
        ]);

        // Notify relevant merchants
        $this->notifyRelevantMerchants($order, 'new_order_available');
    }

    /**
     * Send order cancelled notification
     */
    public function sendOrderCancelledNotification(Order $order): void
    {
        $this->sendNotification([
            'type' => 'order_cancelled',
            'user_id' => $order->customer_id,
            'title' => 'Order Cancelled',
            'message' => "Your order #{$order->order_number} has been cancelled.",
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status
            ],
            'channels' => ['push', 'email']
        ]);

        // Notify merchants who bid on this order
        $this->notifyBiddingMerchants($order, 'order_cancelled');
    }

    /**
     * Send order notification to specific user
     */
    public function sendOrderNotification(User $user, Order $order, string $type): void
    {
        $messages = [
            'new_order' => [
                'title' => 'New Order Available',
                'message' => "A new order matching your services is available: {$order->title}"
            ],
            'bid_accepted' => [
                'title' => 'Bid Accepted',
                'message' => "Your bid on order #{$order->order_number} has been accepted!"
            ],
            'bid_rejected' => [
                'title' => 'Bid Not Selected',
                'message' => "Your bid on order #{$order->order_number} was not selected."
            ]
        ];

        $messageData = $messages[$type] ?? [
            'title' => 'Order Update',
            'message' => "Order #{$order->order_number} has been updated."
        ];

        $this->sendNotification([
            'type' => $type,
            'user_id' => $user->id,
            'title' => $messageData['title'],
            'message' => $messageData['message'],
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status
            ],
            'channels' => ['push', 'email']
        ]);
    }

    /**
     * Send notification via HTTP to notification service
     */
    protected function sendNotification(array $data): void
    {
        try {
            $response = Http::timeout(10)
                ->post("{$this->notificationServiceUrl}/api/notifications", $data);

            if (!$response->successful()) {
                Log::warning('Failed to send notification', [
                    'data' => $data,
                    'response' => $response->body(),
                    'status' => $response->status()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Notification service error', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify relevant merchants about new order
     */
    protected function notifyRelevantMerchants(Order $order, string $type): void
    {
        try {
            $response = Http::timeout(10)
                ->post("{$this->notificationServiceUrl}/api/notifications/merchants", [
                    'type' => $type,
                    'order_id' => $order->id,
                    'order_data' => [
                        'id' => $order->id,
                        'title' => $order->title,
                        'description' => $order->description,
                        'budget_max' => $order->budget_max,
                        'deadline' => $order->deadline?->toISOString(),
                        'urgent' => $order->urgent
                    ],
                    'filters' => [
                        'service_areas' => $order->delivery_location,
                        'specializations' => $order->part_details
                    ]
                ]);

            if (!$response->successful()) {
                Log::warning('Failed to notify merchants', [
                    'order_id' => $order->id,
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Merchant notification error', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notify merchants who bid on the order
     */
    protected function notifyBiddingMerchants(Order $order, string $type): void
    {
        try {
            $response = Http::timeout(10)
                ->post("{$this->notificationServiceUrl}/api/notifications/bidding-merchants", [
                    'type' => $type,
                    'order_id' => $order->id,
                    'message' => "Order #{$order->order_number} has been cancelled."
                ]);

            if (!$response->successful()) {
                Log::warning('Failed to notify bidding merchants', [
                    'order_id' => $order->id,
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Bidding merchant notification error', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
