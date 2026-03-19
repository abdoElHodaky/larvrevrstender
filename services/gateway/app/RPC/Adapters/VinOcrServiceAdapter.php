<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * VinOcrServiceAdapter for Gateway Service
 * 
 * Provides HTTP-like interface for RPC calls to the VIN OCR service.
 * Gateway service needs VIN OCR operations for request routing and vehicle processing.
 */
class VinOcrServiceAdapter
{
    private $vinOcrRpc;

    public function __construct()
    {
        $this->vinOcrRpc = app('VinOcrRpc');
    }

    /**
     * Process VIN OCR
     */
    public function processVinOcr(array $ocrData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('processVinOcr', ['ocr_data' => $ocrData], $correlationId);
            
            $response = $this->vinOcrRpc->call('vinocr.processVinOcr', [
                'ocr_data' => $ocrData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('processVinOcr', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('processVinOcr', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get VIN information
     */
    public function getVinInfo(string $vin): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getVinInfo', ['vin' => $vin], $correlationId);
            
            $response = $this->vinOcrRpc->call('vinocr.getVinInfo', [
                'vin' => $vin
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getVinInfo', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getVinInfo', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Validate VIN
     */
    public function validateVin(string $vin): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('validateVin', ['vin' => $vin], $correlationId);
            
            $response = $this->vinOcrRpc->call('vinocr.validateVin', [
                'vin' => $vin
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('validateVin', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('validateVin', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $params, string $correlationId, string $status = 'start'): void
    {
        Log::info("Gateway VinOcrService RPC Call", [
            'method' => $method,
            'params' => $params,
            'correlation_id' => $correlationId,
            'status' => $status,
            'service' => 'vin-ocr-service',
            'caller' => 'gateway-service'
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("Gateway VinOcrService RPC Error", [
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $correlationId,
            'duration_ms' => $duration,
            'service' => 'vin-ocr-service',
            'caller' => 'gateway-service'
        ]);
    }
}
