<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\IncomingEntry;

/**
 * Enhanced correlation service for distributed tracing across microservices
 */
class CorrelationService
{
    private const CORRELATION_HEADER = 'X-Correlation-ID';
    private const TRACE_HEADER = 'X-Trace-ID';
    private const SPAN_HEADER = 'X-Span-ID';
    private const PARENT_SPAN_HEADER = 'X-Parent-Span-ID';
    private const SERVICE_HEADER = 'X-Service-Name';
    private const REQUEST_ID_HEADER = 'X-Request-ID';

    /**
     * Generate or extract correlation ID from request
     */
    public function getOrCreateCorrelationId(Request $request = null): string
    {
        if ($request) {
            $correlationId = $request->header(self::CORRELATION_HEADER);
            if ($correlationId) {
                return $correlationId;
            }
        }

        // Check if we have an active correlation in the current context
        $activeCorrelation = $this->getActiveCorrelation();
        if ($activeCorrelation) {
            return $activeCorrelation['correlation_id'];
        }

        // Generate new correlation ID
        return $this->generateCorrelationId();
    }

    /**
     * Generate a new correlation ID
     */
    public function generateCorrelationId(): string
    {
        return 'corr-' . uniqid() . '-' . bin2hex(random_bytes(8));
    }

    /**
     * Start a new correlation context
     */
    public function startCorrelation(
        string $correlationId,
        string $serviceName = 'order-service',
        string $operation = 'unknown',
        array $context = []
    ): array {
        $traceId = $this->generateTraceId($correlationId);
        $spanId = $this->generateSpanId($operation);
        $requestId = $this->generateRequestId();

        $correlationData = [
            'correlation_id' => $correlationId,
            'trace_id' => $traceId,
            'span_id' => $spanId,
            'parent_span_id' => null,
            'service_name' => $serviceName,
            'operation' => $operation,
            'request_id' => $requestId,
            'started_at' => microtime(true),
            'context' => $context,
            'child_spans' => [],
        ];

        // Store in cache for context propagation
        $this->storeCorrelationContext($correlationId, $correlationData);

        // Record in Telescope
        $this->recordCorrelationStart($correlationData);

        Log::info('Correlation started', [
            'correlation_id' => $correlationId,
            'trace_id' => $traceId,
            'service' => $serviceName,
            'operation' => $operation,
        ]);

        return $correlationData;
    }

    /**
     * Create a child span within existing correlation
     */
    public function createChildSpan(
        string $correlationId,
        string $operation,
        array $context = []
    ): array {
        $parentCorrelation = $this->getCorrelationContext($correlationId);
        
        if (!$parentCorrelation) {
            throw new \Exception("No correlation context found for ID: {$correlationId}");
        }

        $spanId = $this->generateSpanId($operation);
        
        $childSpan = [
            'correlation_id' => $correlationId,
            'trace_id' => $parentCorrelation['trace_id'],
            'span_id' => $spanId,
            'parent_span_id' => $parentCorrelation['span_id'],
            'service_name' => $parentCorrelation['service_name'],
            'operation' => $operation,
            'started_at' => microtime(true),
            'context' => $context,
        ];

        // Add to parent's child spans
        $parentCorrelation['child_spans'][] = $spanId;
        $this->storeCorrelationContext($correlationId, $parentCorrelation);

        // Store child span separately
        $this->storeSpanContext($correlationId, $spanId, $childSpan);

        // Record in Telescope
        $this->recordSpanStart($childSpan);

        return $childSpan;
    }

    /**
     * Complete a span
     */
    public function completeSpan(
        string $correlationId,
        string $spanId,
        bool $success = true,
        array $result = [],
        ?string $errorMessage = null
    ): void {
        $spanContext = $this->getSpanContext($correlationId, $spanId);
        
        if (!$spanContext) {
            Log::warning('Span context not found for completion', [
                'correlation_id' => $correlationId,
                'span_id' => $spanId,
            ]);
            return;
        }

        $endTime = microtime(true);
        $duration = $endTime - $spanContext['started_at'];

        $spanContext['completed_at'] = $endTime;
        $spanContext['duration_ms'] = round($duration * 1000, 2);
        $spanContext['success'] = $success;
        $spanContext['result'] = $result;
        $spanContext['error_message'] = $errorMessage;

        // Update span context
        $this->storeSpanContext($correlationId, $spanId, $spanContext);

        // Record completion in Telescope
        $this->recordSpanCompletion($spanContext);
    }

    /**
     * Complete correlation
     */
    public function completeCorrelation(
        string $correlationId,
        bool $success = true,
        array $finalResult = [],
        ?string $errorMessage = null
    ): void {
        $correlationData = $this->getCorrelationContext($correlationId);
        
        if (!$correlationData) {
            Log::warning('Correlation context not found for completion', [
                'correlation_id' => $correlationId,
            ]);
            return;
        }

        $endTime = microtime(true);
        $duration = $endTime - $correlationData['started_at'];

        $correlationData['completed_at'] = $endTime;
        $correlationData['total_duration_ms'] = round($duration * 1000, 2);
        $correlationData['success'] = $success;
        $correlationData['final_result'] = $finalResult;
        $correlationData['error_message'] = $errorMessage;

        // Record completion in Telescope
        $this->recordCorrelationCompletion($correlationData);

        // Clean up context
        $this->removeCorrelationContext($correlationId);

        Log::info('Correlation completed', [
            'correlation_id' => $correlationId,
            'trace_id' => $correlationData['trace_id'],
            'duration_ms' => $correlationData['total_duration_ms'],
            'success' => $success,
        ]);
    }

    /**
     * Get correlation headers for outgoing requests
     */
    public function getCorrelationHeaders(string $correlationId, ?string $spanId = null): array
    {
        $correlationData = $this->getCorrelationContext($correlationId);
        
        if (!$correlationData) {
            return [];
        }

        $headers = [
            self::CORRELATION_HEADER => $correlationId,
            self::TRACE_HEADER => $correlationData['trace_id'],
            self::SERVICE_HEADER => $correlationData['service_name'],
            self::REQUEST_ID_HEADER => $correlationData['request_id'],
        ];

        if ($spanId) {
            $headers[self::SPAN_HEADER] = $spanId;
            $headers[self::PARENT_SPAN_HEADER] = $correlationData['span_id'];
        } else {
            $headers[self::SPAN_HEADER] = $correlationData['span_id'];
        }

        return $headers;
    }

    /**
     * Extract correlation context from request headers
     */
    public function extractCorrelationFromRequest(Request $request): ?array
    {
        $correlationId = $request->header(self::CORRELATION_HEADER);
        $traceId = $request->header(self::TRACE_HEADER);
        $spanId = $request->header(self::SPAN_HEADER);
        $parentSpanId = $request->header(self::PARENT_SPAN_HEADER);
        $serviceName = $request->header(self::SERVICE_HEADER);
        $requestId = $request->header(self::REQUEST_ID_HEADER);

        if (!$correlationId) {
            return null;
        }

        return [
            'correlation_id' => $correlationId,
            'trace_id' => $traceId,
            'span_id' => $spanId,
            'parent_span_id' => $parentSpanId,
            'service_name' => $serviceName ?: 'unknown',
            'request_id' => $requestId,
            'extracted_from_request' => true,
        ];
    }

    /**
     * Record RPC call with correlation
     */
    public function recordRpcCall(
        string $correlationId,
        string $targetService,
        string $method,
        string $endpoint,
        array $requestData,
        array $responseData,
        float $duration,
        bool $success = true,
        ?string $errorMessage = null
    ): void {
        $correlationData = $this->getCorrelationContext($correlationId);
        
        if (!$correlationData) {
            return;
        }

        $rpcSpanId = $this->generateSpanId("rpc-{$targetService}-{$method}");

        // Record RPC call in Telescope
        Telescope::recordEntry(
            IncomingEntry::make([
                'type' => 'correlation_rpc',
                'family_hash' => $correlationData['trace_id'],
                'content' => [
                    'correlation_id' => $correlationId,
                    'trace_id' => $correlationData['trace_id'],
                    'span_id' => $rpcSpanId,
                    'parent_span_id' => $correlationData['span_id'],
                    'source_service' => $correlationData['service_name'],
                    'target_service' => $targetService,
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'request_data' => $requestData,
                    'response_data' => $responseData,
                    'duration_ms' => round($duration * 1000, 2),
                    'success' => $success,
                    'error_message' => $errorMessage,
                    'timestamp' => microtime(true),
                    'tags' => [
                        'correlation_id' => $correlationId,
                        'source_service' => $correlationData['service_name'],
                        'target_service' => $targetService,
                        'method' => $method,
                        'status' => $success ? 'success' : 'failure',
                    ],
                ],
            ])
        );
    }

    /**
     * Get active correlation from current context
     */
    private function getActiveCorrelation(): ?array
    {
        return app('correlation.context');
    }

    /**
     * Generate trace ID
     */
    private function generateTraceId(string $correlationId): string
    {
        return 'trace-' . substr($correlationId, 5) . '-' . uniqid();
    }

    /**
     * Generate span ID
     */
    private function generateSpanId(string $operation): string
    {
        return 'span-' . $operation . '-' . uniqid();
    }

    /**
     * Generate request ID
     */
    private function generateRequestId(): string
    {
        return 'req-' . uniqid() . '-' . bin2hex(random_bytes(4));
    }

    /**
     * Store correlation context
     */
    private function storeCorrelationContext(string $correlationId, array $data): void
    {
        Cache::put("correlation.{$correlationId}", $data, now()->addHours(24));
        
        // Store in app context for current request
        app()->instance('correlation.context', $data);
    }

    /**
     * Get correlation context
     */
    private function getCorrelationContext(string $correlationId): ?array
    {
        return Cache::get("correlation.{$correlationId}");
    }

    /**
     * Remove correlation context
     */
    private function removeCorrelationContext(string $correlationId): void
    {
        Cache::forget("correlation.{$correlationId}");
        
        // Clean up child spans
        $childSpans = Cache::get("correlation.{$correlationId}.spans", []);
        foreach ($childSpans as $spanId) {
            Cache::forget("correlation.{$correlationId}.span.{$spanId}");
        }
        Cache::forget("correlation.{$correlationId}.spans");
    }

    /**
     * Store span context
     */
    private function storeSpanContext(string $correlationId, string $spanId, array $data): void
    {
        Cache::put("correlation.{$correlationId}.span.{$spanId}", $data, now()->addHours(24));
        
        // Add to spans index
        $spans = Cache::get("correlation.{$correlationId}.spans", []);
        $spans[] = $spanId;
        Cache::put("correlation.{$correlationId}.spans", $spans, now()->addHours(24));
    }

    /**
     * Get span context
     */
    private function getSpanContext(string $correlationId, string $spanId): ?array
    {
        return Cache::get("correlation.{$correlationId}.span.{$spanId}");
    }

    /**
     * Record correlation start in Telescope
     */
    private function recordCorrelationStart(array $correlationData): void
    {
        Telescope::recordEntry(
            IncomingEntry::make([
                'type' => 'correlation',
                'family_hash' => $correlationData['trace_id'],
                'content' => [
                    'correlation_id' => $correlationData['correlation_id'],
                    'trace_id' => $correlationData['trace_id'],
                    'span_id' => $correlationData['span_id'],
                    'service_name' => $correlationData['service_name'],
                    'operation' => $correlationData['operation'],
                    'request_id' => $correlationData['request_id'],
                    'status' => 'started',
                    'started_at' => $correlationData['started_at'],
                    'context' => $correlationData['context'],
                    'tags' => [
                        'correlation_id' => $correlationData['correlation_id'],
                        'service' => $correlationData['service_name'],
                        'operation' => $correlationData['operation'],
                        'status' => 'started',
                    ],
                ],
            ])
        );
    }

    /**
     * Record span start in Telescope
     */
    private function recordSpanStart(array $spanData): void
    {
        Telescope::recordEntry(
            IncomingEntry::make([
                'type' => 'correlation_span',
                'family_hash' => $spanData['trace_id'],
                'content' => [
                    'correlation_id' => $spanData['correlation_id'],
                    'trace_id' => $spanData['trace_id'],
                    'span_id' => $spanData['span_id'],
                    'parent_span_id' => $spanData['parent_span_id'],
                    'service_name' => $spanData['service_name'],
                    'operation' => $spanData['operation'],
                    'status' => 'started',
                    'started_at' => $spanData['started_at'],
                    'context' => $spanData['context'],
                    'tags' => [
                        'correlation_id' => $spanData['correlation_id'],
                        'service' => $spanData['service_name'],
                        'operation' => $spanData['operation'],
                        'status' => 'started',
                    ],
                ],
            ])
        );
    }

    /**
     * Record span completion in Telescope
     */
    private function recordSpanCompletion(array $spanData): void
    {
        Telescope::recordEntry(
            IncomingEntry::make([
                'type' => 'correlation_span',
                'family_hash' => $spanData['trace_id'],
                'content' => [
                    'correlation_id' => $spanData['correlation_id'],
                    'trace_id' => $spanData['trace_id'],
                    'span_id' => $spanData['span_id'],
                    'parent_span_id' => $spanData['parent_span_id'],
                    'service_name' => $spanData['service_name'],
                    'operation' => $spanData['operation'],
                    'status' => $spanData['success'] ? 'completed' : 'failed',
                    'duration_ms' => $spanData['duration_ms'],
                    'result' => $spanData['result'],
                    'error_message' => $spanData['error_message'],
                    'completed_at' => $spanData['completed_at'],
                    'tags' => [
                        'correlation_id' => $spanData['correlation_id'],
                        'service' => $spanData['service_name'],
                        'operation' => $spanData['operation'],
                        'status' => $spanData['success'] ? 'success' : 'failure',
                    ],
                ],
            ])
        );
    }

    /**
     * Record correlation completion in Telescope
     */
    private function recordCorrelationCompletion(array $correlationData): void
    {
        Telescope::recordEntry(
            IncomingEntry::make([
                'type' => 'correlation',
                'family_hash' => $correlationData['trace_id'],
                'content' => [
                    'correlation_id' => $correlationData['correlation_id'],
                    'trace_id' => $correlationData['trace_id'],
                    'span_id' => $correlationData['span_id'],
                    'service_name' => $correlationData['service_name'],
                    'operation' => $correlationData['operation'],
                    'request_id' => $correlationData['request_id'],
                    'status' => $correlationData['success'] ? 'completed' : 'failed',
                    'total_duration_ms' => $correlationData['total_duration_ms'],
                    'child_spans_count' => count($correlationData['child_spans']),
                    'final_result' => $correlationData['final_result'],
                    'error_message' => $correlationData['error_message'],
                    'completed_at' => $correlationData['completed_at'],
                    'tags' => [
                        'correlation_id' => $correlationData['correlation_id'],
                        'service' => $correlationData['service_name'],
                        'operation' => $correlationData['operation'],
                        'status' => $correlationData['success'] ? 'success' : 'failure',
                    ],
                ],
            ])
        );
    }
}
