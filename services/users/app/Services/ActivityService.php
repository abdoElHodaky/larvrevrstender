<?php

namespace App\Services;

use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use Shared\Core\BaseService;

class ActivityService extends BaseService
{
    /**
     * Log an activity for a user or model
     */
    public function logActivity(
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        array $properties = [],
        ?string $logName = 'default'
    ): Activity {
        try {
            $activity = activity($logName)
                ->performedOn($subject)
                ->causedBy($causer)
                ->withProperties($properties)
                ->log($description);

            Log::info('Activity logged', [
                'activity_id' => $activity->id,
                'description' => $description,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject?->id,
                'causer_type' => $causer ? get_class($causer) : null,
                'causer_id' => $causer?->id,
                'log_name' => $logName
            ]);

            return $activity;
        } catch (\Exception $e) {
            Log::error('Failed to log activity', [
                'description' => $description,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get activities for a specific user
     */
    public function getUserActivities(
        int $userId,
        array $filters = [],
        int $perPage = 15,
        int $page = 1
    ): array {
        try {
            $query = Activity::query()
                ->where('causer_id', $userId)
                ->where('causer_type', 'App\\Models\\User');

            // Apply filters
            if (!empty($filters['log_name'])) {
                $query->where('log_name', $filters['log_name']);
            }

            if (!empty($filters['subject_type'])) {
                $query->where('subject_type', $filters['subject_type']);
            }

            if (!empty($filters['description'])) {
                $query->where('description', 'like', '%' . $filters['description'] . '%');
            }

            if (!empty($filters['date_from'])) {
                $query->where('created_at', '>=', Carbon::parse($filters['date_from']));
            }

            if (!empty($filters['date_to'])) {
                $query->where('created_at', '<=', Carbon::parse($filters['date_to']));
            }

            // Order by most recent first
            $query->orderBy('created_at', 'desc');

            // Get paginated results
            $activities = $query->paginate($perPage, ['*'], 'page', $page);

            return [
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
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get user activities', [
                'user_id' => $userId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to retrieve user activities',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get activity statistics for a user
     */
    public function getUserActivityStats(int $userId, int $days = 30): array
    {
        try {
            $fromDate = Carbon::now()->subDays($days);

            $stats = Activity::where('causer_id', $userId)
                ->where('causer_type', 'App\\Models\\User')
                ->where('created_at', '>=', $fromDate)
                ->selectRaw('
                    COUNT(*) as total_activities,
                    COUNT(DISTINCT log_name) as unique_log_names,
                    COUNT(DISTINCT subject_type) as unique_subject_types,
                    DATE(created_at) as activity_date
                ')
                ->groupBy('activity_date')
                ->orderBy('activity_date', 'desc')
                ->get();

            $totalActivities = Activity::where('causer_id', $userId)
                ->where('causer_type', 'App\\Models\\User')
                ->where('created_at', '>=', $fromDate)
                ->count();

            $topLogNames = Activity::where('causer_id', $userId)
                ->where('causer_type', 'App\\Models\\User')
                ->where('created_at', '>=', $fromDate)
                ->selectRaw('log_name, COUNT(*) as count')
                ->groupBy('log_name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            return [
                'success' => true,
                'data' => [
                    'period_days' => $days,
                    'total_activities' => $totalActivities,
                    'daily_stats' => $stats,
                    'top_log_names' => $topLogNames,
                    'generated_at' => Carbon::now()->toISOString()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get user activity stats', [
                'user_id' => $userId,
                'days' => $days,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to retrieve activity statistics',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get activity by ID
     */
    public function getActivity(int $activityId): array
    {
        try {
            $activity = Activity::with(['subject', 'causer'])->find($activityId);

            if (!$activity) {
                return [
                    'success' => false,
                    'error' => 'Activity not found',
                    'message' => "Activity with ID {$activityId} does not exist"
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'activity' => $activity,
                    'subject' => $activity->subject,
                    'causer' => $activity->causer,
                    'properties' => $activity->properties
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get activity', [
                'activity_id' => $activityId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to retrieve activity',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete activity (soft delete or hard delete based on configuration)
     */
    public function deleteActivity(int $activityId, int $requestingUserId): array
    {
        try {
            $activity = Activity::find($activityId);

            if (!$activity) {
                return [
                    'success' => false,
                    'error' => 'Activity not found',
                    'message' => "Activity with ID {$activityId} does not exist"
                ];
            }

            // Check if the requesting user owns this activity
            if ($activity->causer_id !== $requestingUserId) {
                return [
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => 'You can only delete your own activities'
                ];
            }

            $activity->delete();

            Log::info('Activity deleted', [
                'activity_id' => $activityId,
                'deleted_by' => $requestingUserId
            ]);

            return [
                'success' => true,
                'message' => 'Activity deleted successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete activity', [
                'activity_id' => $activityId,
                'requesting_user_id' => $requestingUserId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to delete activity',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get activities for a specific subject (model)
     */
    public function getSubjectActivities(
        string $subjectType,
        int $subjectId,
        array $filters = [],
        int $perPage = 15,
        int $page = 1
    ): array {
        try {
            $query = Activity::query()
                ->where('subject_type', $subjectType)
                ->where('subject_id', $subjectId);

            // Apply filters
            if (!empty($filters['log_name'])) {
                $query->where('log_name', $filters['log_name']);
            }

            if (!empty($filters['causer_id'])) {
                $query->where('causer_id', $filters['causer_id']);
            }

            if (!empty($filters['description'])) {
                $query->where('description', 'like', '%' . $filters['description'] . '%');
            }

            if (!empty($filters['date_from'])) {
                $query->where('created_at', '>=', Carbon::parse($filters['date_from']));
            }

            if (!empty($filters['date_to'])) {
                $query->where('created_at', '<=', Carbon::parse($filters['date_to']));
            }

            // Order by most recent first
            $query->orderBy('created_at', 'desc');

            // Get paginated results
            $activities = $query->with(['causer'])->paginate($perPage, ['*'], 'page', $page);

            return [
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
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get subject activities', [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to retrieve subject activities',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Bulk log activities
     */
    public function bulkLogActivities(array $activities): array
    {
        try {
            $loggedActivities = [];
            $errors = [];

            foreach ($activities as $index => $activityData) {
                try {
                    $activity = $this->logActivity(
                        $activityData['description'],
                        $activityData['subject'] ?? null,
                        $activityData['causer'] ?? null,
                        $activityData['properties'] ?? [],
                        $activityData['log_name'] ?? 'default'
                    );
                    $loggedActivities[] = $activity;
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'data' => $activityData
                    ];
                }
            }

            return [
                'success' => empty($errors),
                'data' => [
                    'logged_count' => count($loggedActivities),
                    'error_count' => count($errors),
                    'activities' => $loggedActivities,
                    'errors' => $errors
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to bulk log activities', [
                'activities_count' => count($activities),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to bulk log activities',
                'message' => $e->getMessage()
            ];
        }
    }
}
