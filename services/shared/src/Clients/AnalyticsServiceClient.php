<?php

declare(strict_types=1);

namespace Shared\Clients;

use Shared\RPC\AbstractRpcClient;
use Shared\RPC\Enums\ServiceType;
use Shared\RPC\ValueObjects\RpcRequest;
use Shared\RPC\ValueObjects\RpcResponse;

/**
 * Analytics Service RPC Client - PHP 8.3 & Laravel 12 Implementation
 */
class AnalyticsServiceClient extends AbstractRpcClient
{
    public function __construct($httpClient, string $environment = 'local')
    {
        parent::__construct($httpClient, ServiceType::ANALYTICS, $environment);
    }

    public function trackEvent(string $event, array $data): RpcResponse
    {
        $request = RpcRequest::post('/events', [
            'event' => $event,
            'data' => $data,
        ]);
        return $this->call($request);
    }

    public function getUserAnalytics(int $userId, string $period = '30d'): RpcResponse
    {
        $request = RpcRequest::get('/analytics/user', [
            'user_id' => $userId,
            'period' => $period,
        ]);
        return $this->call($request);
    }

    public function getBusinessMetrics(string $period = '30d'): RpcResponse
    {
        $request = RpcRequest::get('/analytics/business', ['period' => $period]);
        return $this->call($request);
    }

    public function generateReport(string $reportType, array $parameters): RpcResponse
    {
        $request = RpcRequest::post('/reports', [
            'type' => $reportType,
            'parameters' => $parameters,
        ]);
        return $this->call($request);
    }

    public function getReport(int $reportId): RpcResponse
    {
        $request = RpcRequest::get("/reports/{$reportId}");
        return $this->call($request);
    }
}
