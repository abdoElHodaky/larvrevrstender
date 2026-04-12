<?php

declare(strict_types=1);

namespace Shared\RPC\Clients;

use Shared\RPC\AbstractRpcClient;
use Shared\RPC\Enums\ServiceType;
use Shared\RPC\ValueObjects\RpcRequest;
use Shared\RPC\ValueObjects\RpcResponse;

/**
 * Order Service RPC Client - PHP 8.3 & Laravel 12 Implementation
 */
class OrderServiceClient extends AbstractRpcClient
{
    public function __construct($httpClient, string $environment = 'local')
    {
        parent::__construct($httpClient, ServiceType::ORDER, $environment);
    }

    public function createOrder(array $orderData): RpcResponse
    {
        $request = RpcRequest::post('/orders', $orderData);
        return $this->call($request);
    }

    public function getOrder(int $orderId): RpcResponse
    {
        $request = RpcRequest::get("/orders/{$orderId}");
        return $this->call($request);
    }

    public function updateOrderStatus(int $orderId, string $status): RpcResponse
    {
        $request = RpcRequest::put("/orders/{$orderId}/status", ['status' => $status]);
        return $this->call($request);
    }

    public function getOrdersByUser(int $userId, int $page = 1, int $limit = 20): RpcResponse
    {
        $request = RpcRequest::get('/orders/by-user', [
            'user_id' => $userId,
            'page' => $page,
            'limit' => $limit,
        ]);
        return $this->call($request);
    }

    public function cancelOrder(int $orderId, string $reason): RpcResponse
    {
        $request = RpcRequest::post("/orders/{$orderId}/cancel", ['reason' => $reason]);
        return $this->call($request);
    }

    public function processOrderWorkflow(int $orderId, string $action, array $data = []): RpcResponse
    {
        $request = RpcRequest::post("/orders/{$orderId}/workflow", [
            'action' => $action,
            'data' => $data,
        ]);
        return $this->call($request);
    }
}
