<?php

namespace App\Http\Controllers;

use App\Services\CorrelationService;
use App\Services\WorkflowTracingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * WorkflowCorrelationController
 * 
 * Handles correlation and distributed tracing operations for workflow monitoring
 * and debugging across microservices.
 */
class WorkflowCorrelationController extends Controller
{
    protected CorrelationService $correlationService;
    protected WorkflowTracingService $tracingService;

    public function __construct(
        CorrelationService $correlationService,
        WorkflowTracingService $tracingService
    ) {
        $this->correlationService = $correlationService;
        $this->tracingService = $tracingService;
    }

    /**
     * Get complete trace for a correlation ID
     * 
     * @param string $correlationId
     * @param Request $request
     * @return JsonResponse
     */
    public function getTrace(string $correlationId, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'include_spans' => 'nullable|boolean',
                'include_rpc_calls' => 'nullable|boolean',
                'include_metadata' => 'nullable|boolean',
                'format' => 'nullable|string|in:json,jaeger,zipkin'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $options = [
                'include_spans' => $request->input('include_spans', true),
                'include_rpc_calls' => $request->input('include_rpc_calls', true),
                'include_metadata' => $request->input('include_metadata', false),
                'format' => $request->input('format', 'json')
            ];

            $trace = $this->tracingService->getCompleteTrace($correlationId, $options);

            if (!$trace) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trace not found for correlation ID',
                    'correlation_id' => $correlationId
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $trace,
                'correlation_id' => $correlationId,
                'metadata' => [
                    'total_spans' => $trace['span_count'] ?? 0,
                    'total_rpc_calls' => $trace['rpc_call_count'] ?? 0,
                    'duration_ms' => $trace['total_duration_ms'] ?? 0,
                    'status' => $trace['status'] ?? 'unknown'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get trace', [
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get trace',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get spans for a correlation ID
     * 
     * @param string $correlationId
     * @param Request $request
     * @return JsonResponse
     */
    public function getSpans(string $correlationId, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'service_name' => 'nullable|string',
                'operation_name' => 'nullable|string',
                'status' => 'nullable|string|in:success,error,timeout',
                'min_duration_ms' => 'nullable|integer|min:0',
                'max_duration_ms' => 'nullable|integer|min:0',
                'limit' => 'nullable|integer|min:1|max:1000',
                'offset' => 'nullable|integer|min:0',
                'sort_by' => 'nullable|string|in:start_time,duration,service_name',
                'sort_order' => 'nullable|string|in:asc,desc'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $filters = [
                'service_name' => $request->input('service_name'),
                'operation_name' => $request->input('operation_name'),
                'status' => $request->input('status'),
                'min_duration_ms' => $request->input('min_duration_ms'),
                'max_duration_ms' => $request->input('max_duration_ms'),
                'limit' => $request->input('limit', 100),
                'offset' => $request->input('offset', 0),
                'sort_by' => $request->input('sort_by', 'start_time'),
                'sort_order' => $request->input('sort_order', 'asc')
            ];

            $spans = $this->tracingService->getSpans($correlationId, $filters);

            return response()->json([
                'success' => true,
                'data' => $spans,
                'correlation_id' => $correlationId,
                'filters' => $filters,
                'pagination' => [
                    'limit' => $filters['limit'],
                    'offset' => $filters['offset'],
                    'total' => count($spans)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get spans', [
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get spans',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get RPC calls for a correlation ID
     * 
     * @param string $correlationId
     * @param Request $request
     * @return JsonResponse
     */
    public function getRpcCalls(string $correlationId, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'source_service' => 'nullable|string',
                'target_service' => 'nullable|string',
                'method' => 'nullable|string',
                'status' => 'nullable|string|in:success,error,timeout',
                'min_duration_ms' => 'nullable|integer|min:0',
                'max_duration_ms' => 'nullable|integer|min:0',
                'limit' => 'nullable|integer|min:1|max:1000',
                'offset' => 'nullable|integer|min:0',
                'include_payload' => 'nullable|boolean',
                'include_response' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $filters = [
                'source_service' => $request->input('source_service'),
                'target_service' => $request->input('target_service'),
                'method' => $request->input('method'),
                'status' => $request->input('status'),
                'min_duration_ms' => $request->input('min_duration_ms'),
                'max_duration_ms' => $request->input('max_duration_ms'),
                'limit' => $request->input('limit', 100),
                'offset' => $request->input('offset', 0),
                'include_payload' => $request->input('include_payload', false),
                'include_response' => $request->input('include_response', false)
            ];

            $rpcCalls = $this->tracingService->getRpcCalls($correlationId, $filters);

            return response()->json([
                'success' => true,
                'data' => $rpcCalls,
                'correlation_id' => $correlationId,
                'filters' => $filters,
                'pagination' => [
                    'limit' => $filters['limit'],
                    'offset' => $filters['offset'],
                    'total' => count($rpcCalls)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get RPC calls', [
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get RPC calls',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Propagate correlation context to downstream services
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function propagateContext(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'correlation_id' => 'nullable|string',
                'parent_span_id' => 'nullable|string',
                'service_name' => 'required|string',
                'operation_name' => 'required|string',
                'metadata' => 'nullable|array',
                'baggage' => 'nullable|array',
                'sampling_priority' => 'nullable|integer|in:0,1',
                'trace_flags' => 'nullable|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate or use existing correlation ID
            $correlationId = $request->input('correlation_id') 
                ?: $this->correlationService->generateCorrelationId();

            $contextData = [
                'correlation_id' => $correlationId,
                'parent_span_id' => $request->input('parent_span_id'),
                'service_name' => $request->input('service_name'),
                'operation_name' => $request->input('operation_name'),
                'metadata' => $request->input('metadata', []),
                'baggage' => $request->input('baggage', []),
                'sampling_priority' => $request->input('sampling_priority', 1),
                'trace_flags' => $request->input('trace_flags', 1),
                'timestamp' => now()->toISOString()
            ];

            // Create new span for this operation
            $spanId = $this->tracingService->startSpan(
                $correlationId,
                $contextData['service_name'],
                $contextData['operation_name'],
                $contextData['parent_span_id'],
                $contextData['metadata']
            );

            // Generate propagation headers
            $headers = $this->correlationService->injectHeaders($contextData);

            // Store context for future reference
            $this->correlationService->storeContext($correlationId, $contextData);

            return response()->json([
                'success' => true,
                'message' => 'Context propagated successfully',
                'data' => [
                    'correlation_id' => $correlationId,
                    'span_id' => $spanId,
                    'context' => $contextData,
                    'propagation_headers' => $headers,
                    'instructions' => [
                        'include_headers' => 'Include the propagation_headers in downstream HTTP requests',
                        'span_management' => 'Call finish_span when operation completes',
                        'error_handling' => 'Call record_error if operation fails'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to propagate context', [
                'service_name' => $request->input('service_name'),
                'operation_name' => $request->input('operation_name'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to propagate context',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get correlation statistics and health metrics
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getCorrelationStats(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'time_range' => 'nullable|string|in:1h,6h,24h,7d,30d',
                'service_name' => 'nullable|string',
                'include_details' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $timeRange = $request->input('time_range', '24h');
            $serviceName = $request->input('service_name');
            $includeDetails = $request->input('include_details', false);

            $stats = $this->correlationService->getCorrelationStatistics(
                $timeRange,
                $serviceName,
                $includeDetails
            );

            return response()->json([
                'success' => true,
                'data' => $stats,
                'time_range' => $timeRange,
                'service_filter' => $serviceName,
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get correlation statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get correlation statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
