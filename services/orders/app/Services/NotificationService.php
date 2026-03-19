<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Services\Contracts\NotificationServiceInterface;
use App\RPC\Adapters\NotificationServiceAdapter;
use Illuminate\Support\Facades\Log;

/**
 * Notification Service
 *
 * Handles communication with the notification service
 * for sending order-related notifications via RPC
 */
class NotificationService implements NotificationServiceInterface
{
    protected NotificationServiceAdapter $notificationAdapter;

    public function __construct(NotificationServiceAdapter $notificationAdapter)
    {
        $this->notificationAdapter = $notificationAdapter;
    }

    /**
     * Send order created notification
     */
    public function sendOrderCreatedNotification(Order $order): void
    {
        try {
            $this->notificationAdapter->sendOrderCreatedNotification([
                'id' => $order->id,
                'customer_id' => $order->customer_id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send order created notification via RPC', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send order published notification
     */
    public function sendOrderPublishedNotification(Order $order): void
    {
        try {
            // Notify customer
            $this->notificationAdapter->sendOrderPublishedNotification([
                'id' => $order->id,
                'customer_id' => $order->customer_id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ]);

            // Notify relevant merchants
            $this->notifyRelevantMerchants($order, 'new_order_available');
        } catch (\Exception $e) {
            Log::error('Failed to send order published notification via RPC', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
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
                'status' => $order->status,
            ],
            'channels' => ['push', 'email'],
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
                'message' => "A new order matching your services is available: {$order->title}",
            ],
            'bid_accepted' => [
                'title' => 'Bid Accepted',
                'message' => "Your bid on order #{$order->order_number} has been accepted!",
            ],
            'bid_rejected' => [
                'title' => 'Bid Not Selected',
                'message' => "Your bid on order #{$order->order_number} was not selected.",
            ],
        ];

        $messageData = $messages[$type] ?? [
            'title' => 'Order Update',
            'message' => "Order #{$order->order_number} has been updated.",
        ];

        $this->sendNotification([
            'type' => $type,
            'user_id' => $user->id,
            'title' => $messageData['title'],
            'message' => $messageData['message'],
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
            ],
            'channels' => ['push', 'email'],
        ]);
    }

    /**
     * Send notification via RPC to notification service
     */
    protected function sendNotification(array $data): void
    {
        try {
            $result = $this->notificationAdapter->sendNotification($data);
            
            if (!$result) {
                Log::warning('Failed to send notification via RPC', [
                    'data' => $data,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Notification service RPC error', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify relevant merchants about new order
     */
    protected function notifyRelevantMerchants(Order $order, string $type): void
    {
        try {
            $result = $this->notificationAdapter->sendNotification([
                'type' => $type,
                'order_id' => $order->id,
                'order_data' => [
                    'id' => $order->id,
                    'title' => $order->title,
                    'description' => $order->description,
                    'budget_max' => $order->budget_max,
                    'deadline' => $order->deadline?->toISOString(),
                    'urgent' => $order->urgent,
                ],
                'filters' => [
                    'service_areas' => $order->delivery_location,
                    'specializations' => $order->part_details,
                ],
                'target' => 'merchants',
            ]);

            if (!$result) {
                Log::warning('Failed to notify merchants via RPC', [
                    'order_id' => $order->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Merchant notification RPC error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
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
                    'message' => "Order #{$order->order_number} has been cancelled.",
                ]);

            if (! $response->successful()) {
                Log::warning('Failed to notify bidding merchants', [
                    'order_id' => $order->id,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Bidding merchant notification error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send bulk notification to multiple users
     */
    public function sendBulkNotification(array $userIds, array $notificationData): void
    {
        try {
            $response = Http::timeout(10)
                ->post("{$this->notificationServiceUrl}/api/notifications/bulk", [
                    'user_ids' => $userIds,
                    'notification_data' => $notificationData,
                ]);

            if (! $response->successful()) {
                Log::warning('Failed to send bulk notification', [
                    'user_ids' => $userIds,
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Bulk notification error', [
                'user_ids' => $userIds,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get notification delivery status
     */
    public function getDeliveryStatus(string $notificationId): array
    {
        try {
            $response = Http::timeout(10)
                ->get("{$this->notificationServiceUrl}/api/notifications/{$notificationId}/status");

            if ($response->successful()) {
                return $response->json();
            }

            return ['status' => 'unknown', 'error' => 'Failed to get status'];
        } catch (\Exception $e) {
            Log::error('Failed to get notification status', [
                'notification_id' => $notificationId,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }
}
