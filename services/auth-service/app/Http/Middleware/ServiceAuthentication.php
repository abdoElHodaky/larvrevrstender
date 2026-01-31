<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ServiceAuthentication
{
    /**
     * Handle an incoming request for inter-service communication.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if request is from an internal service
        $serviceName = $request->header('X-Service-Name');
        $requestId = $request->header('X-Request-ID');
        
        // List of allowed internal services
        $allowedServices = [
            'Auth Service',
            'User Service', 
            'Bidding Service',
            'Order Service',
            'Payment Service',
            'Analytics Service',
            'VIN OCR Service',
            'Notification Service'
        ];
        
        // If no service name header, this is an external request - require Sanctum auth
        if (!$serviceName) {
            return $next($request);
        }
        
        // Validate service name
        if (!in_array($serviceName, $allowedServices)) {
            return response()->json([
                'error' => 'Unauthorized service',
                'message' => 'Service not recognized'
            ], 401);
        }
        
        // Add service context to request
        $request->attributes->set('service_name', $serviceName);
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('is_internal_request', true);
        
        return $next($request);
    }
}
