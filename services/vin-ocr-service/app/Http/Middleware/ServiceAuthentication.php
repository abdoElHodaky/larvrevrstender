<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ServiceAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        $serviceName = $request->header('X-Service-Name');
        $requestId = $request->header('X-Request-ID');

        $allowedServices = [
            'Auth Service',
            'User Service',
            'Bidding Service',
            'Order Service',
            'Payment Service',
            'Analytics Service',
            'VIN OCR Service',
            'Notification Service',
        ];

        if (! $serviceName) {
            return $next($request);
        }

        if (! in_array($serviceName, $allowedServices)) {
            return response()->json([
                'error' => 'Unauthorized service',
                'message' => 'Service not recognized',
            ], 401);
        }

        $request->attributes->set('service_name', $serviceName);
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('is_internal_request', true);

        return $next($request);
    }
}

