<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * UserServiceAdapter for Order Service
 * 
 * Provides HTTP-like interface for RPC calls to the user service.
 * Order service needs user operations for vehicle validation and customer data.
 */
class UserServiceAdapter
{
    private $userRpc;

    public function __construct()
    {
        $this->userRpc = app('UserRpc');
    }

    /**
     * Validate vehicle ownership
     */
    public function validateVehicleOwnership(int $vehicleId, int $customerId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('validateVehicleOwnership', ['vehicle_id' => $vehicleId, 'customer_id' => $customerId], $correlationId);
            
            $response = $this->userRpc->call('user.validateVehicleOwnership', [
                'vehicle_id' => $vehicleId,
                'customer_id' => $customerId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('validateVehicleOwnership', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('validateVehicleOwnership', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get vehicle details
     */
    public function getVehicleDetails(int $vehicleId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getVehicleDetails', ['vehicle_id' => $vehicleId], $correlationId);
            
            $response = $this->userRpc->call('user.getVehicleDetails', [
                'vehicle_id' => $vehicleId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getVehicleDetails', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getVehicleDetails', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get customer vehicles
     */
    public function getCustomerVehicles(int $customerId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getCustomerVehicles', ['customer_id' => $customerId], $correlationId);
            
            $response = $this->userRpc->call('user.getCustomerVehicles', [
                'customer_id' => $customerId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getCustomerVehicles', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getCustomerVehicles', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get vehicle specifications by VIN
     */
    public function getVehicleSpecsByVin(string $vin): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getVehicleSpecsByVin', ['vin' => $vin], $correlationId);
            
            $response = $this->userRpc->call('user.getVehicleSpecsByVin', [
                'vin' => $vin
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getVehicleSpecsByVin', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getVehicleSpecsByVin', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $params, string $correlationId, string $status = 'start'): void
    {
        Log::info("Order UserService RPC Call", [
            'method' => $method,
            'params' => $params,
            'correlation_id' => $correlationId,
            'status' => $status,
            'service' => 'user-service',
            'caller' => 'order-service'
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("Order UserService RPC Error", [
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $correlationId,
            'duration_ms' => $duration,
            'service' => 'user-service',
            'caller' => 'order-service'
        ]);
    }
}
