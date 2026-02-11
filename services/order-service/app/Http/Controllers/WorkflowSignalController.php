<?php

namespace App\Http\Controllers;

use App\Services\WorkflowSignalHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * WorkflowSignalController
 * 
 * Handles workflow signal management operations including pause, resume,
 * intervention, and external signal processing.
 */
class WorkflowSignalController extends Controller
{
    protected WorkflowSignalHandler $signalHandler;

    public function __construct(WorkflowSignalHandler $signalHandler)
    {
        $this->signalHandler = $signalHandler;
    }

    /**
     * Pause a workflow
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function pauseWorkflow(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'workflow_id' => 'required|string',
                'reason' => 'required|string|max:500',
                'user_id' => 'nullable|integer',
                'metadata' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->signalHandler->pauseWorkflow(
                $request->input('workflow_id'),
                $request->input('reason'),
                $request->input('user_id'),
                $request->input('metadata', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Workflow paused successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to pause workflow', [
                'workflow_id' => $request->input('workflow_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to pause workflow',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resume a paused workflow
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function resumeWorkflow(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'workflow_id' => 'required|string',
                'reason' => 'required|string|max:500',
                'user_id' => 'nullable|integer',
                'metadata' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->signalHandler->resumeWorkflow(
                $request->input('workflow_id'),
                $request->input('reason'),
                $request->input('user_id'),
                $request->input('metadata', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Workflow resumed successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to resume workflow', [
                'workflow_id' => $request->input('workflow_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resume workflow',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request manual intervention for a workflow
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function requestIntervention(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'workflow_id' => 'required|string',
                'intervention_type' => 'required|string|in:manual_review,data_correction,approval_required,escalation',
                'reason' => 'required|string|max:1000',
                'priority' => 'required|string|in:low,medium,high,critical',
                'assigned_to' => 'nullable|integer',
                'metadata' => 'nullable|array',
                'context' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->signalHandler->requestManualIntervention(
                $request->input('workflow_id'),
                $request->input('intervention_type'),
                $request->input('reason'),
                $request->input('priority'),
                $request->input('assigned_to'),
                $request->input('metadata', []),
                $request->input('context', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Manual intervention requested successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to request manual intervention', [
                'workflow_id' => $request->input('workflow_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to request manual intervention',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send external signal to workflow
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function sendExternalSignal(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'workflow_id' => 'required|string',
                'signal_name' => 'required|string|max:100',
                'signal_data' => 'nullable|array',
                'source' => 'required|string|max:100',
                'priority' => 'nullable|string|in:low,medium,high,critical',
                'metadata' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->signalHandler->sendExternalSignal(
                $request->input('workflow_id'),
                $request->input('signal_name'),
                $request->input('signal_data', []),
                $request->input('source'),
                $request->input('priority', 'medium'),
                $request->input('metadata', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'External signal sent successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send external signal', [
                'workflow_id' => $request->input('workflow_id'),
                'signal_name' => $request->input('signal_name'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send external signal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get signals for a specific workflow
     * 
     * @param string $workflowId
     * @param Request $request
     * @return JsonResponse
     */
    public function getWorkflowSignals(string $workflowId, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'nullable|string|in:pending,processed,failed',
                'signal_type' => 'nullable|string',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $filters = [
                'status' => $request->input('status'),
                'signal_type' => $request->input('signal_type'),
                'limit' => $request->input('limit', 50),
                'offset' => $request->input('offset', 0)
            ];

            $result = $this->signalHandler->getWorkflowSignals($workflowId, $filters);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get workflow signals', [
                'workflow_id' => $workflowId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get workflow signals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active signals across workflows
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getActiveSignals(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'signal_type' => 'nullable|string',
                'priority' => 'nullable|string|in:low,medium,high,critical',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $filters = [
                'signal_type' => $request->input('signal_type'),
                'priority' => $request->input('priority'),
                'limit' => $request->input('limit', 50),
                'offset' => $request->input('offset', 0)
            ];

            $result = $this->signalHandler->getActiveSignals($filters);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get active signals', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get active signals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete/cancel a signal
     * 
     * @param string $signalId
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteSignal(string $signalId, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
                'user_id' => 'nullable|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->signalHandler->deleteSignal(
                $signalId,
                $request->input('reason'),
                $request->input('user_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Signal deleted successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete signal', [
                'signal_id' => $signalId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete signal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
