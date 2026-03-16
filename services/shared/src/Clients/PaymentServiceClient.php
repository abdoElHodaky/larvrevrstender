<?php

declare(strict_types=1);

namespace Shared\Clients;

use Shared\RPC\AbstractRpcClient;
use Shared\RPC\Enums\ServiceType;
use Shared\RPC\ValueObjects\RpcRequest;
use Shared\RPC\ValueObjects\RpcResponse;

/**
 * Payment Service RPC Client - PHP 8.3 & Laravel 12 Implementation
 */
class PaymentServiceClient extends AbstractRpcClient
{
    public function __construct($httpClient, string $environment = 'local')
    {
        parent::__construct($httpClient, ServiceType::PAYMENT, $environment);
    }

    public function processPayment(array $paymentData): RpcResponse
    {
        $request = RpcRequest::post('/payments', $paymentData);
        return $this->call($request);
    }

    public function getPayment(int $paymentId): RpcResponse
    {
        $request = RpcRequest::get("/payments/{$paymentId}");
        return $this->call($request);
    }

    public function refundPayment(int $paymentId, float $amount, string $reason): RpcResponse
    {
        $request = RpcRequest::post("/payments/{$paymentId}/refund", [
            'amount' => $amount,
            'reason' => $reason,
        ]);
        return $this->call($request);
    }

    public function getPaymentMethods(int $userId): RpcResponse
    {
        $request = RpcRequest::get('/payment-methods', ['user_id' => $userId]);
        return $this->call($request);
    }

    public function addPaymentMethod(int $userId, array $methodData): RpcResponse
    {
        $request = RpcRequest::post('/payment-methods', array_merge($methodData, ['user_id' => $userId]));
        return $this->call($request);
    }

    public function validatePayment(array $paymentData): RpcResponse
    {
        $request = RpcRequest::post('/payments/validate', $paymentData);
        return $this->call($request);
    }
}
