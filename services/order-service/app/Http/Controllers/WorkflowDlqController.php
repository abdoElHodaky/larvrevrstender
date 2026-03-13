<?php

namespace App\Http\Controllers;

use App\Services\WorkflowDeadLetterQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for Dead Letter Queue management endpoints
 */
class WorkflowDlqController extends Controller
{
    public function __construct(
        protected WorkflowDeadLetterQueue$dlqService
    ) {
    }

    /**
     * Get DLQ statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $statistics = $this->dlqService->getStatistics();
            
            return response()->json([
                'success' => true,
                'data' => $statistics,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get DLQ statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve DLQ statistics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get manual intervention queue
     */
    public function getManualInterventionQueue(): JsonResponse
    {
        try {
            $queue = $this->dlqService->getManualInterventionQueue();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'queue' => $queue,
                    'count' => count($queue),
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get manual intervention queue', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve manual intervention queue',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry a failed activity
     */
    public function retryFailedActivity(string $failureId): JsonResponse
    {
        try {
            $result = $this->dlqService->retryFailedActivity($failureId);
            
            if ($result) {
                Log::info('Activity retry initiated', [
                    'failure_id' => $failureId,
                    'result' => $result,
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Activity retry initiated successfully',
                    'data' => $result,
                    'timestamp' => now()->toISOString(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to initiate activity retry',
                    'message' => 'Activity may not be eligible for retry or failure ID not found',
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('Failed to retry activity', [
                'failure_id' => $failureId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to retry activity',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve manual intervention
     */
    public function resolveManualIntervention(string $failureId, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'resolution_status' => 'required|in:success,failure',
                'resolution_notes' => 'nullable|string|max:1000',
                'resolver_id' => 'nullable|string|max:255',
            ]);
            
            $result = $this->dlqService->resolveManualIntervention(
                $failureId,
                $validated['resolution_status'] === 'success',
                $validated['resolution_notes'] ?? '',
                $validated['resolver_id'] ?? 'system'
            );
            
            if ($result) {
                Log::info('Manual intervention resolved', [
                    'failure_id' => $failureId,
                    'resolution_status' => $validated['resolution_status'],
                    'resolver_id' => $validated['resolver_id'] ?? 'system',
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Manual intervention resolved successfully',
                    'data' => $result,
                    'timestamp' => now()->toISOString(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to resolve manual intervention',
                    'message' => 'Failure ID not found or already resolved',
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('Failed to resolve manual intervention', [
                'failure_id' => $failureId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to resolve manual intervention',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process retry queue
     */
    public function processRetryQueue(): JsonResponse
    {
        try {
            $results = $this->dlqService->processRetryQueue();
            
            Log::info('Retry queue processed', [
                'processed_count' => count($results),
                'results' => $results,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Retry queue processed successfully',
                'data' => [
                    'processed_count' => count($results),
                    'results' => $results,
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process retry queue', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to process retry queue',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
