<?php

declare(strict_types=1);

namespace Shared\RPC\Clients;

use Shared\RPC\AbstractRpcClient;
use Shared\RPC\Enums\ServiceType;
use Shared\RPC\ValueObjects\RpcRequest;
use Shared\RPC\ValueObjects\RpcResponse;

/**
 * Notification Service RPC Client - PHP 8.3 & Laravel 12 Implementation
 */
class NotificationServiceClient extends AbstractRpcClient
{
    public function __construct($httpClient, string $environment = 'local')
    {
        parent::__construct($httpClient, ServiceType::NOTIFICATION, $environment);
    }

    public function sendNotification(array $notificationData): RpcResponse
    {
        $request = RpcRequest::post('/notifications', $notificationData);
        return $this->call($request);
    }

    public function getNotifications(int $userId, int $page = 1, int $limit = 20): RpcResponse
    {
        $request = RpcRequest::get('/notifications', [
            'user_id' => $userId,
            'page' => $page,
            'limit' => $limit,
        ]);
        return $this->call($request);
    }

    public function markAsRead(int $notificationId): RpcResponse
    {
        $request = RpcRequest::put("/notifications/{$notificationId}/read");
        return $this->call($request);
    }

    public function subscribeToPush(int $userId, array $subscriptionData): RpcResponse
    {
        $request = RpcRequest::post('/push-subscriptions', array_merge($subscriptionData, ['user_id' => $userId]));
        return $this->call($request);
    }

    public function sendBulkNotification(array $userIds, array $notificationData): RpcResponse
    {
        $request = RpcRequest::post('/notifications/bulk', [
            'user_ids' => $userIds,
            'notification' => $notificationData,
        ]);
        return $this->call($request);
    }
}
