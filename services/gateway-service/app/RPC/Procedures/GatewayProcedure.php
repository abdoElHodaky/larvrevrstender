<?php

namespace App\RPC\Procedures;

use App\Services\GatewayService;
use Sajya\Server\Procedure;

class GatewayProcedure extends Procedure
{
    /**
     * The name of the procedure that will be
     * displayed and taken into account in the search
     */
    public static string $name = 'gateway';

    protected GatewayService $gatewayService;

    public function __construct(GatewayService $gatewayService)
    {
        $this->gatewayService = $gatewayService;
    }

    /**
     * Route request to appropriate service
     */
    public function route(string $service, string $method, array $params = []): array
    {
        return $this->gatewayService->routeRequest($service, $method, $params);
    }

    /**
     * Health check endpoint
     */
    public function health(): array
    {
        return $this->gatewayService->healthCheck();
    }
}
