<?php

namespace App\Http\Controllers;

use App\Services\ActivityService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ActivityController extends Controller
{
    public function __construct(
        private ActivityService $activityService
    ) {}

    /**
     * Log an activity
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'description' => 'required|string|max:255',
                'causer_id' => 'sometimes|integer|min:1',
                'subject_type' => 'sometimes|string|max:255',
                'subject_id' => 'sometimes|integer|min:1',
                'properties' => 'sometimes|array',
                'log_name' => 'sometimes|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            $causer = null;
            if (!empty($data['causer_id'])) {
                $causer = User::find($data['causer_id']);
                if (!$causer) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Causer user not found'
                    ], 404);
                }
            }

            $subject = null;
            if (!empty($data['subject_type']) && !empty($data['subject_id'])) {
                if (class_exists($data['subject_type'])) {
                    $subject = $data['subject_type']::find($data['subject_id']);
                }
            }

            $activity = $this->activityService->logActivity(
                $data['description'],
                $subject,
                $causer,
                $data['properties'] ?? [],
                $data['log_name'] ?? 'default'
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'activity_id' => $activity->id,
                    'description' => $activity->description,
                    'log_name' => $activity->log_name,
                    'created_at' => $activity->created_at->toISOString()
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to log activity via REST', [
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to log activity',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user activities
     */
    public function getUserActivities(Request $request, int $userId): JsonResponse
    {
        try {
            $validator = Validator::make(array_merge($request->all(), ['user_id' => $userId]), [
                'user_id' => 'required|integer|min:1',
                'log_name' => 'sometimes|string|max:100',
                'subject_type' => 'sometimes|string|max:255',
                'description' => 'sometimes|string|max:255',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Check if user exists
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found'
                ], 404);
            }

            $filters = array_filter([
                'log_name' => $data['log_name'] ?? null,
                'subject_type' => $data['subject_type'] ?? null,
                'description' => $data['description'] ?? null,
                'date_from' => $data['date_from'] ?? null,
                'date_to' => $data['date_to'] ?? null,
            ]);

            $result = $this->activityService->getUserActivities(
                $userId,
                $filters,
                $data['per_page'] ?? 15,
                $data['page'] ?? 1
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to get user activities via REST', [
                'user_id' => $userId,
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve user activities',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user activity statistics
     */
    public function getUserActivityStats(Request $request, int $userId): JsonResponse
    {
        try {
            $validator = Validator::make(array_merge($request->all(), ['user_id' => $userId]), [
                'user_id' => 'required|integer|min:1',
                'days' => 'sometimes|integer|min:1|max:365'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Check if user exists
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found'
                ], 404);
            }

            $result = $this->activityService->getUserActivityStats(
                $userId,
                $data['days'] ?? 30
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to get user activity stats via REST', [
                'user_id' => $userId,
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve activity statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity by ID
     */
    public function show(int $activityId): JsonResponse
    {
        try {
            $result = $this->activityService->getActivity($activityId);
            
            if (!$result['success']) {
                return response()->json($result, 404);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to get activity via REST', [
                'activity_id' => $activityId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve activity',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete activity
     */
    public function destroy(Request $request, int $activityId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'requesting_user_id' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Check if requesting user exists
            $user = User::find($data['requesting_user_id']);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Requesting user not found'
                ], 404);
            }

            $result = $this->activityService->deleteActivity(
                $activityId,
                $data['requesting_user_id']
            );

            if (!$result['success']) {
                $statusCode = str_contains($result['error'], 'not found') ? 404 : 
                             (str_contains($result['error'], 'Unauthorized') ? 403 : 500);
                return response()->json($result, $statusCode);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to delete activity via REST', [
                'activity_id' => $activityId,
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete activity',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subject activities
     */
    public function getSubjectActivities(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'subject_type' => 'required|string|max:255',
                'subject_id' => 'required|integer|min:1',
                'log_name' => 'sometimes|string|max:100',
                'causer_id' => 'sometimes|integer|min:1',
                'description' => 'sometimes|string|max:255',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            $filters = array_filter([
                'log_name' => $data['log_name'] ?? null,
                'causer_id' => $data['causer_id'] ?? null,
                'description' => $data['description'] ?? null,
                'date_from' => $data['date_from'] ?? null,
                'date_to' => $data['date_to'] ?? null,
            ]);

            $result = $this->activityService->getSubjectActivities(
                $data['subject_type'],
                $data['subject_id'],
                $filters,
                $data['per_page'] ?? 15,
                $data['page'] ?? 1
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to get subject activities via REST', [
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve subject activities',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk log activities
     */
    public function bulkStore(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'activities' => 'required|array|min:1|max:50',
                'activities.*.description' => 'required|string|max:255',
                'activities.*.causer_id' => 'sometimes|integer|min:1',
                'activities.*.subject_type' => 'sometimes|string|max:255',
                'activities.*.subject_id' => 'sometimes|integer|min:1',
                'activities.*.properties' => 'sometimes|array',
                'activities.*.log_name' => 'sometimes|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Process activities to resolve models
            $processedActivities = [];
            foreach ($data['activities'] as $activityData) {
                $causer = null;
                if (!empty($activityData['causer_id'])) {
                    $causer = User::find($activityData['causer_id']);
                }

                $subject = null;
                if (!empty($activityData['subject_type']) && !empty($activityData['subject_id'])) {
                    if (class_exists($activityData['subject_type'])) {
                        $subject = $activityData['subject_type']::find($activityData['subject_id']);
                    }
                }

                $processedActivities[] = [
                    'description' => $activityData['description'],
                    'causer' => $causer,
                    'subject' => $subject,
                    'properties' => $activityData['properties'] ?? [],
                    'log_name' => $activityData['log_name'] ?? 'default'
                ];
            }

            $result = $this->activityService->bulkLogActivities($processedActivities);

            return response()->json($result, $result['success'] ? 201 : 500);
        } catch (\Exception $e) {
            Log::error('Failed to bulk log activities via REST', [
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to bulk log activities',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all activities (admin endpoint)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'log_name' => 'sometimes|string|max:100',
                'subject_type' => 'sometimes|string|max:255',
                'causer_id' => 'sometimes|integer|min:1',
                'description' => 'sometimes|string|max:255',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // This is a simplified version - in production you'd want proper admin authorization
            $query = \Spatie\Activitylog\Models\Activity::query();

            // Apply filters
            if (!empty($data['log_name'])) {
                $query->where('log_name', $data['log_name']);
            }

            if (!empty($data['subject_type'])) {
                $query->where('subject_type', $data['subject_type']);
            }

            if (!empty($data['causer_id'])) {
                $query->where('causer_id', $data['causer_id']);
            }

            if (!empty($data['description'])) {
                $query->where('description', 'like', '%' . $data['description'] . '%');
            }

            if (!empty($data['date_from'])) {
                $query->where('created_at', '>=', $data['date_from']);
            }

            if (!empty($data['date_to'])) {
                $query->where('created_at', '<=', $data['date_to']);
            }

            // Order by most recent first
            $query->orderBy('created_at', 'desc');

            // Get paginated results
            $activities = $query->with(['subject', 'causer'])->paginate(
                $data['per_page'] ?? 15,
                ['*'],
                'page',
                $data['page'] ?? 1
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'activities' => $activities->items(),
                    'pagination' => [
                        'current_page' => $activities->currentPage(),
                        'per_page' => $activities->perPage(),
                        'total' => $activities->total(),
                        'last_page' => $activities->lastPage(),
                        'has_more_pages' => $activities->hasMorePages()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list activities via REST', [
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve activities',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
