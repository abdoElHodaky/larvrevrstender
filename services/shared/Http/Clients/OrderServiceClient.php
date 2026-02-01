<?php

namespace Shared\Http\Clients;

class OrderServiceClient extends BaseServiceClient
{
    public function createOrder(array $orderData): ?array
    {
        $response = $this->post('/orders', $orderData);
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    public function getOrder(string $orderId): ?array
    {
        $response = $this->get("/orders/{$orderId}");
        return $this->isSuccessful($response) ? $this->decodeJsonResponse($response) : null;
    }

    public function updateOrderStatus(string $orderId, string $status): bool
    {
        $response = $this->put("/orders/{$orderId}/status", ['status' => $status]);
        return $this->isSuccessful($response);
    }
}
