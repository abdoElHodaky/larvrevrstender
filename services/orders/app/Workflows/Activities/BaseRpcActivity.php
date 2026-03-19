<?php

namespace App\Workflows\Activities;

use Shared\Procedures\CrossServiceProcedure;
use Workflow\Activity;
use Workflow\ActivityStub;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\CorrelationService;
use App\Services\WorkflowTracingService;

/**
 * Base RPC Activity for Laravel Workflow
 * 
 * Provides common functionality for workflow activities that interact
 * with RPC services through the existing Sajya infrastructure.
 */
abstract class BaseRpcActivity extends Activity
{
    protected CrossServiceProcedure $rpcClient;
    protected CorrelationService $correlationService;
    protected WorkflowTracingService $tracingService;
    
    public function __construct(
        int $index,
        string $now,
        \Workflow\Models\StoredWorkflow $storedWorkflow,
        ...$arguments
    ) {
        parent::__construct($index, $now, $storedWorkflow, ...$arguments);
        $this->rpcClient = new CrossServiceProcedure();
        $this->correlationService = app(CorrelationService::class);
        $this->tracingService = app(WorkflowTracingService::class);
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
        $correlationId = $this->getCorrelationId();
        $spanId = null;
        
        try {
            // Create child span for RPC call
            if ($correlationId) {
                $childSpan = $this->correlationService->createChildSpan(
                    $correlationId,
                    "rpc-{$service}-{$method}",
                    [
                        'service' => $service,
                        'method' => $method,
                        'activity' => static::class,
                    ]
                );
                $spanId = $childSpan['span_id'];
            }
            
            // Get correlation headers for RPC call
            $correlationHeaders = $correlationId 
                ? $this->correlationService->getCorrelationHeaders($correlationId, $spanId)
                : [];
            
            $sagaContext = [
                'data' => $data,
                'saga_id' => $this->getSagaId(),
                'correlation_id' => $correlationId,
                'activity_name' => static::class,
                'timestamp' => now()->toISOString(),
                'correlation_headers' => $correlationHeaders,
            ];
            
            Log::info("RPC call initiated", [
                'service' => $service,
                'method' => $method,
                'saga_id' => $this->getSagaId(),
                'correlation_id' => $correlationId,
                'span_id' => $spanId,
                'activity' => static::class
            ]);
            
            $result = $this->rpcClient->callRpc($service, $method, $sagaContext);
            
            $duration = microtime(true) - $startTime;
            $success = $result['success'] ?? false;
            
            // Complete span
            if ($correlationId && $spanId) {
                $this->correlationService->completeSpan(
                    $correlationId,
                    $spanId,
                    $success,
                    $result,
                    $success ? null : ($result['error'] ?? 'Unknown RPC error')
                );
            }
            
            // Record RPC call in correlation service
            if ($correlationId) {
                $this->correlationService->recordRpcCall(
                    $correlationId,
                    $service,
                    $method,
                    "rpc://{$service}/{$method}",
                    $data,
                    $result,
                    $duration,
                    $success,
                    $success ? null : ($result['error'] ?? 'Unknown RPC error')
                );
            }
            
            Log::info("RPC call completed", [
                'service' => $service,
                'method' => $method,
                'saga_id' => $this->getSagaId(),
                'correlation_id' => $correlationId,
                'span_id' => $spanId,
                'duration_ms' => round($duration * 1000, 2),
                'success' => $success
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            // Complete span with error
            if ($correlationId && $spanId) {
                $this->correlationService->completeSpan(
                    $correlationId,
                    $spanId,
                    false,
                    [],
                    $e->getMessage()
                );
            }
            
            // Record failed RPC call
            if ($correlationId) {
                $this->correlationService->recordRpcCall(
                    $correlationId,
                    $service,
                    $method,
                    "rpc://{$service}/{$method}",
                    $data,
                    [],
                    $duration,
                    false,
                    $e->getMessage()
                );
            }
            
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
     * Get correlation ID for tracing
     */
    protected function getCorrelationId(): string
    {
        return ActivityStub::getWorkflowId() . '-' . uniqid();
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
