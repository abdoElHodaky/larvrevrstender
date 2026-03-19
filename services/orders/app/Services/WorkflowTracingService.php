<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\EntryType;

/**
 * Service for distributed tracing of workflow activities using Laravel Telescope
 */
class WorkflowTracingService
{
    /**
     * Start a workflow trace
     */
    public function startWorkflowTrace(
        string $workflowId,
        string $orderId,
        array $workflowData = []
    ): string {
        $traceId = $this->generateTraceId($workflowId);
        $spanId = $this->generateSpanId('workflow-root');

        // Record workflow start in Telescope
        Telescope::recordEvent(
            IncomingEntry::make([
                'name' => 'workflow.start',
                'content' => [
                    'trace_id' => $traceId,
                    'span_id' => $spanId,
                    'parent_span_id' => null,
                    'workflow_id' => $workflowId,
                    'order_id' => $orderId,
                    'operation' => 'workflow_start',
                    'status' => 'started',
                    'workflow_data' => $workflowData,
                    'started_at' => microtime(true),
                    'tags' => [
                        'workflow_id' => $workflowId,
                        'order_id' => $orderId,
                        'operation' => 'workflow_start',
                    ],
                ],
            ])
        );

        // Store trace context
        $this->storeTraceContext($workflowId, [
            'trace_id' => $traceId,
            'root_span_id' => $spanId,
            'workflow_id' => $workflowId,
            'order_id' => $orderId,
            'started_at' => microtime(true),
        ]);

        Log::info('Workflow trace started', [
            'trace_id' => $traceId,
            'workflow_id' => $workflowId,
            'order_id' => $orderId,
        ]);

        return $traceId;
    }

    /**
     * Start an activity trace
     */
    public function startActivityTrace(
        string $workflowId,
        string $activityName,
        array $activityData = []
    ): string {
        $traceContext = $this->getTraceContext($workflowId);
        $spanId = $this->generateSpanId($activityName);

        if (!$traceContext) {
            Log::warning('No trace context found for workflow', [
                'workflow_id' => $workflowId,
                'activity_name' => $activityName,
            ]);
            return $spanId;
        }

        // Record activity start in Telescope
        Telescope::recordEvent(
            IncomingEntry::make([
                'name' => 'workflow.activity.start',
                'content' => [
                    'trace_id' => $traceContext['trace_id'],
                    'span_id' => $spanId,
                    'parent_span_id' => $traceContext['root_span_id'],
                    'workflow_id' => $workflowId,
                    'order_id' => $traceContext['order_id'],
                    'activity_name' => $activityName,
                    'operation' => 'activity_start',
                    'status' => 'started',
                    'activity_data' => $activityData,
                    'started_at' => microtime(true),
                    'tags' => [
                        'workflow_id' => $workflowId,
                        'activity_name' => $activityName,
                        'operation' => 'activity_start',
                        'activity_type' => $this->getActivityType($activityName),
                    ],
                ],
            ])
        );

        // Store activity span context
        $this->storeActivitySpan($workflowId, $activityName, [
            'span_id' => $spanId,
            'activity_name' => $activityName,
            'started_at' => microtime(true),
        ]);

        return $spanId;
    }

    /**
     * Complete an activity trace
     */
    public function completeActivityTrace(
        string $workflowId,
        string $activityName,
        array $result,
        bool $success = true,
        ?string $errorMessage = null
    ): void {
        $traceContext = $this->getTraceContext($workflowId);
        $activitySpan = $this->getActivitySpan($workflowId, $activityName);

        if (!$traceContext || !$activitySpan) {
            Log::warning('No trace context or activity span found', [
                'workflow_id' => $workflowId,
                'activity_name' => $activityName,
            ]);
            return;
        }

        $endTime = microtime(true);
        $duration = $endTime - $activitySpan['started_at'];

        // Record activity completion in Telescope
        Telescope::recordEvent(
            IncomingEntry::make([
                'name' => 'workflow.activity.complete',
                'content' => [
                    'trace_id' => $traceContext['trace_id'],
                    'span_id' => $activitySpan['span_id'],
                    'parent_span_id' => $traceContext['root_span_id'],
                    'workflow_id' => $workflowId,
                    'order_id' => $traceContext['order_id'],
                    'activity_name' => $activityName,
                    'operation' => 'activity_complete',
                    'status' => $success ? 'completed' : 'failed',
                    'result' => $result,
                    'error_message' => $errorMessage,
                    'duration_ms' => round($duration * 1000, 2),
                    'completed_at' => $endTime,
                    'tags' => [
                        'workflow_id' => $workflowId,
                        'activity_name' => $activityName,
                        'operation' => 'activity_complete',
                        'activity_type' => $this->getActivityType($activityName),
                        'status' => $success ? 'success' : 'failure',
                    ],
                ],
            ])
        );

        // Clean up activity span
        $this->removeActivitySpan($workflowId, $activityName);
    }

    /**
     * Record RPC call trace
     */
    public function traceRpcCall(
        string $workflowId,
        string $activityName,
        string $service,
        string $method,
        array $requestData,
        array $responseData,
        float $duration,
        bool $success = true,
        ?string $errorMessage = null
    ): void {
        $traceContext = $this->getTraceContext($workflowId);
        $activitySpan = $this->getActivitySpan($workflowId, $activityName);

        if (!$traceContext) {
            return;
        }

        $rpcSpanId = $this->generateSpanId("rpc-{$service}-{$method}");

        // Record RPC call in Telescope
        Telescope::recordEvent(
            IncomingEntry::make([
                'name' => 'workflow.rpc',
                'content' => [
                    'trace_id' => $traceContext['trace_id'],
                    'span_id' => $rpcSpanId,
                    'parent_span_id' => $activitySpan['span_id'] ?? $traceContext['root_span_id'],
                    'workflow_id' => $workflowId,
                    'order_id' => $traceContext['order_id'],
                    'activity_name' => $activityName,
                    'service' => $service,
                    'method' => $method,
                    'operation' => 'rpc_call',
                    'status' => $success ? 'success' : 'failure',
                    'request_data' => $requestData,
                    'response_data' => $responseData,
                    'error_message' => $errorMessage,
                    'duration_ms' => round($duration * 1000, 2),
                    'timestamp' => microtime(true),
                    'tags' => [
                        'workflow_id' => $workflowId,
                        'activity_name' => $activityName,
                        'service' => $service,
                        'method' => $method,
                        'operation' => 'rpc_call',
                        'status' => $success ? 'success' : 'failure',
                    ],
                ],
            ])
        );
    }

    /**
     * Complete workflow trace
     */
    public function completeWorkflowTrace(
        string $workflowId,
        array $finalResult,
        bool $success = true,
        ?string $errorMessage = null
    ): void {
        $traceContext = $this->getTraceContext($workflowId);

        if (!$traceContext) {
            Log::warning('No trace context found for workflow completion', [
                'workflow_id' => $workflowId,
            ]);
            return;
        }

        $endTime = microtime(true);
        $duration = $endTime - $traceContext['started_at'];

        // Record workflow completion in Telescope
        Telescope::recordEvent(
            IncomingEntry::make([
                'name' => 'workflow.complete',
                'content' => [
                    'trace_id' => $traceContext['trace_id'],
                    'span_id' => $traceContext['root_span_id'],
                    'parent_span_id' => null,
                    'workflow_id' => $workflowId,
                    'order_id' => $traceContext['order_id'],
                    'operation' => 'workflow_complete',
                    'status' => $success ? 'completed' : 'failed',
                    'final_result' => $finalResult,
                    'error_message' => $errorMessage,
                    'total_duration_ms' => round($duration * 1000, 2),
                    'completed_at' => $endTime,
                    'tags' => [
                        'workflow_id' => $workflowId,
                        'order_id' => $traceContext['order_id'],
                        'operation' => 'workflow_complete',
                        'status' => $success ? 'success' : 'failure',
                    ],
                ],
            ])
        );

        // Clean up trace context
        $this->removeTraceContext($workflowId);

        Log::info('Workflow trace completed', [
            'trace_id' => $traceContext['trace_id'],
            'workflow_id' => $workflowId,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $success,
        ]);
    }

    /**
     * Get trace headers for RPC calls
     */
    public function getTraceHeaders(string $workflowId, string $activityName): array
    {
        $traceContext = $this->getTraceContext($workflowId);
        $activitySpan = $this->getActivitySpan($workflowId, $activityName);

        if (!$traceContext) {
            return [];
        }

        return [
            'X-Trace-ID' => $traceContext['trace_id'],
            'X-Workflow-ID' => $workflowId,
            'X-Span-ID' => $activitySpan['span_id'] ?? $traceContext['root_span_id'],
            'X-Parent-Span-ID' => $traceContext['root_span_id'],
            'X-Order-ID' => $traceContext['order_id'],
            'X-Activity-Name' => $activityName,
        ];
    }

    /**
     * Generate trace ID
     */
    private function generateTraceId(string $workflowId): string
    {
        return 'trace-' . $workflowId . '-' . uniqid();
    }

    /**
     * Generate span ID
     */
    private function generateSpanId(string $operation): string
    {
        return 'span-' . $operation . '-' . uniqid();
    }

    /**
     * Get activity type from activity name
     */
    private function getActivityType(string $activityName): string
    {
        if (str_contains($activityName, 'Payment')) {
            return 'payment';
        } elseif (str_contains($activityName, 'Inventory')) {
            return 'inventory';
        } elseif (str_contains($activityName, 'Shipping')) {
            return 'shipping';
        } else {
            return 'unknown';
        }
    }

    /**
     * Store trace context in cache
     */
    private function storeTraceContext(string $workflowId, array $context): void
    {
        cache()->put("workflow.trace.{$workflowId}", $context, now()->addHours(24));
    }

    /**
     * Get trace context from cache
     */
    private function getTraceContext(string $workflowId): ?array
    {
        return cache()->get("workflow.trace.{$workflowId}");
    }

    /**
     * Remove trace context from cache
     */
    private function removeTraceContext(string $workflowId): void
    {
        cache()->forget("workflow.trace.{$workflowId}");
    }

    /**
     * Store activity span context
     */
    private function storeActivitySpan(string $workflowId, string $activityName, array $span): void
    {
        cache()->put("workflow.span.{$workflowId}.{$activityName}", $span, now()->addHours(24));
    }

    /**
     * Get activity span context
     */
    private function getActivitySpan(string $workflowId, string $activityName): ?array
    {
        return cache()->get("workflow.span.{$workflowId}.{$activityName}");
    }

    /**
     * Remove activity span context
     */
    private function removeActivitySpan(string $workflowId, string $activityName): void
    {
        cache()->forget("workflow.span.{$workflowId}.{$activityName}");
    }
}
