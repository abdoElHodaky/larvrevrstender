<?php

namespace App\Workflows\Activities;

use Shared\Procedures\CrossServiceProcedure;
use Workflow\Activity;
use Workflow\ActivityStub;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Base RPC Activity for Laravel Workflow in Payment Service
 * 
 * Provides common functionality for workflow activities that interact
 * with RPC services through the existing Sajya infrastructure.
 */
abstract class BaseRpcActivity extends Activity
{
    protected CrossServiceProcedure $rpcClient;
    
    public function __construct()
    {
        $this->rpcClient = new CrossServiceProcedure();
    }
    
    /**
     * Execute RPC call with saga context
     *
     * @param string $service Target service name
     * @param string $method RPC method to call
     * @param array $data Request data
     * @return array RPC response
     * @throws Exception
     */
    protected function callRpc(string $service, string $method, array $data): array
    {
        $startTime = microtime(true);
        
        try {
            $sagaContext = [
                'data' => $data,
                'saga_id' => $this->getSagaId(),
                'activity_name' => static::class,
                'timestamp' => now()->toISOString(),
            ];
            
            Log::info("RPC call initiated", [
                'service' => $service,
                'method' => $method,
                'saga_id' => $this->getSagaId(),
                'activity' => static::class
            ]);
            
            $result = $this->rpcClient->callRpc($service, $method, $sagaContext);
            
            $duration = microtime(true) - $startTime;
            $success = $result['success'] ?? false;
            
            Log::info("RPC call completed", [
                'service' => $service,
                'method' => $method,
                'saga_id' => $this->getSagaId(),
                'duration_ms' => round($duration * 1000, 2),
                'success' => $success
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->logError($e, $service, $method);
            throw new Exception("RPC call failed: {$e->getMessage()}", $e->getCode(), $e);
        }
    }
    
    /**
     * Get saga ID from workflow context
     */
    protected function getSagaId(): ?string
    {
        return ActivityStub::getWorkflowId();
    }
    
    /**
     * Log error with context
     */
    protected function logError(Exception $e, string $service = null, string $method = null): void
    {
        Log::error("RPC Activity Error", [
            'activity' => static::class,
            'saga_id' => $this->getSagaId(),
            'service' => $service,
            'method' => $method,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    
    /**
     * Validate required data fields
     */
    protected function validateData(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Required field '{$field}' is missing from activity data");
            }
        }
    }
    
    /**
     * Create success response
     */
    protected function successResponse(array $data = []): array
    {
        return [
            'success' => true,
            'data' => $data,
            'saga_id' => $this->getSagaId(),
            'timestamp' => now()->toISOString()
        ];
    }
    
    /**
     * Create error response
     */
    protected function errorResponse(string $message, array $details = []): array
    {
        return [
            'success' => false,
            'error' => $message,
            'details' => $details,
            'saga_id' => $this->getSagaId(),
            'timestamp' => now()->toISOString()
        ];
    }
}
