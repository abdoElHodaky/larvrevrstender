<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\ActivityService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Sajya\Server\Exceptions\RuntimeException;

class ActivityProcedure extends BaseProcedure
{
    public function __construct(
        private ActivityService $activityService
    ) {}

    /**
     * Create a runtime exception conditionally based on Sajya availability
     */
    private function createRuntimeException(string $message, int $code = -32603, array $data = []): \Exception
    {
        if (class_exists('Sajya\Server\Exceptions\RuntimeException')) {
            return new \Sajya\Server\Exceptions\RuntimeException($message, $code, $data);
        }

        return new \Exception($message, $code);
    }

    /**
     * Log an activity
     */
    public function logActivity(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'description' => 'required|string|max:255',
                'causer_id' => 'sometimes|integer|min:1',
                'subject_type' => 'sometimes|string|max:255',
                'subject_id' => 'sometimes|integer|min:1',
                'properties' => 'sometimes|array',
                'log_name' => 'sometimes|string|max:100'
            ]);

            // Rate limiting
            $key = 'activity_log:' . ($params['causer_id'] ?? 'anonymous');
            if (RateLimiter::tooManyAttempts($key, 100)) {
                throw $this->createRuntimeException('Too many activity log attempts. Please try again later.', -32429);
            }

            RateLimiter::hit($key, 60); // 100 attempts per minute

            $causer = null;
            if (!empty($params['causer_id'])) {
                $causer = User::find($params['causer_id']);
                if (!$causer) {
                    throw $this->createRuntimeException('Causer user not found', -32404);
                }
            }

            $subject = null;
            if (!empty($params['subject_type']) && !empty($params['subject_id'])) {
                // Try to resolve the subject model
                if (class_exists($params['subject_type'])) {
                    $subject = $params['subject_type']::find($params['subject_id']);
                }
            }

            $activity = $this->activityService->logActivity(
                $params['description'],
                $subject,
                $causer,
                $params['properties'] ?? [],
                $params['log_name'] ?? 'default'
            );

            $result = [
                'success' => true,
                'data' => [
                    'activity_id' => $activity->id,
                    'description' => $activity->description,
                    'log_name' => $activity->log_name,
                    'created_at' => $activity->created_at->toISOString()
                ]
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result;
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get user activities
     */
    public function getUserActivities(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer|min:1',
                'filters' => 'sometimes|array',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1'
            ]);

            // Rate limiting
            $key = 'get_user_activities:' . $params['user_id'];
            if (RateLimiter::tooManyAttempts($key, 60)) {
                throw $this->createRuntimeException('Too many requests. Please try again later.', -32429);
            }

            RateLimiter::hit($key, 60); // 60 attempts per minute

            // Check if user exists
            $user = User::find($params['user_id']);
            if (!$user) {
                throw $this->createRuntimeException('User not found', -32404);
            }

            // Cache key for user activities
            $cacheKey = 'user_activities:' . $params['user_id'] . ':' . md5(serialize($params));
            
            $result = Cache::remember($cacheKey, 300, function () use ($params) {
                return $this->activityService->getUserActivities(
                    $params['user_id'],
                    $params['filters'] ?? [],
                    $params['per_page'] ?? 15,
                    $params['page'] ?? 1
                );
            });

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result;
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get user activity statistics
     */
    public function getUserActivityStats(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer|min:1',
                'days' => 'sometimes|integer|min:1|max:365'
            ]);

            // Rate limiting
            $key = 'get_user_activity_stats:' . $params['user_id'];
            if (RateLimiter::tooManyAttempts($key, 30)) {
                throw $this->createRuntimeException('Too many requests. Please try again later.', -32429);
            }

            RateLimiter::hit($key, 60); // 30 attempts per minute

            // Check if user exists
            $user = User::find($params['user_id']);
            if (!$user) {
                throw $this->createRuntimeException('User not found', -32404);
            }

            // Cache key for user activity stats
            $cacheKey = 'user_activity_stats:' . $params['user_id'] . ':' . ($params['days'] ?? 30);
            
            $result = Cache::remember($cacheKey, 600, function () use ($params) {
                return $this->activityService->getUserActivityStats(
                    $params['user_id'],
                    $params['days'] ?? 30
                );
            });

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result;
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get activity by ID
     */
    public function getActivity(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'activity_id' => 'required|integer|min:1'
            ]);

            // Rate limiting
            $key = 'get_activity:' . $params['activity_id'];
            if (RateLimiter::tooManyAttempts($key, 60)) {
                throw $this->createRuntimeException('Too many requests. Please try again later.', -32429);
            }

            RateLimiter::hit($key, 60); // 60 attempts per minute

            // Cache key for activity
            $cacheKey = 'activity:' . $params['activity_id'];
            
            $result = Cache::remember($cacheKey, 300, function () use ($params) {
                return $this->activityService->getActivity($params['activity_id']);
            });

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result;
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Delete activity
     */
    public function deleteActivity(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'activity_id' => 'required|integer|min:1',
                'requesting_user_id' => 'required|integer|min:1'
            ]);

            // Rate limiting
            $key = 'delete_activity:' . $params['requesting_user_id'];
            if (RateLimiter::tooManyAttempts($key, 10)) {
                throw $this->createRuntimeException('Too many delete attempts. Please try again later.', -32429);
            }

            RateLimiter::hit($key, 60); // 10 attempts per minute

            // Check if requesting user exists
            $user = User::find($params['requesting_user_id']);
            if (!$user) {
                throw $this->createRuntimeException('Requesting user not found', -32404);
            }

            $result = $this->activityService->deleteActivity(
                $params['activity_id'],
                $params['requesting_user_id']
            );

            // Clear related caches
            Cache::forget('activity:' . $params['activity_id']);
            Cache::forget('user_activities:' . $params['requesting_user_id'] . ':*');

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result;
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get subject activities
     */
    public function getSubjectActivities(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'subject_type' => 'required|string|max:255',
                'subject_id' => 'required|integer|min:1',
                'filters' => 'sometimes|array',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1'
            ]);

            // Rate limiting
            $key = 'get_subject_activities:' . $params['subject_type'] . ':' . $params['subject_id'];
            if (RateLimiter::tooManyAttempts($key, 60)) {
                throw $this->createRuntimeException('Too many requests. Please try again later.', -32429);
            }

            RateLimiter::hit($key, 60); // 60 attempts per minute

            // Cache key for subject activities
            $cacheKey = 'subject_activities:' . $params['subject_type'] . ':' . $params['subject_id'] . ':' . md5(serialize($params));
            
            $result = Cache::remember($cacheKey, 300, function () use ($params) {
                return $this->activityService->getSubjectActivities(
                    $params['subject_type'],
                    $params['subject_id'],
                    $params['filters'] ?? [],
                    $params['per_page'] ?? 15,
                    $params['page'] ?? 1
                );
            });

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result;
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Bulk log activities
     */
    public function bulkLogActivities(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'activities' => 'required|array|min:1|max:50',
                'activities.*.description' => 'required|string|max:255',
                'activities.*.causer_id' => 'sometimes|integer|min:1',
                'activities.*.subject_type' => 'sometimes|string|max:255',
                'activities.*.subject_id' => 'sometimes|integer|min:1',
                'activities.*.properties' => 'sometimes|array',
                'activities.*.log_name' => 'sometimes|string|max:100'
            ]);

            // Rate limiting
            $key = 'bulk_log_activities:' . ($params['activities'][0]['causer_id'] ?? 'anonymous');
            if (RateLimiter::tooManyAttempts($key, 10)) {
                throw $this->createRuntimeException('Too many bulk log attempts. Please try again later.', -32429);
            }

            RateLimiter::hit($key, 300); // 10 attempts per 5 minutes

            // Process activities to resolve models
            $processedActivities = [];
            foreach ($params['activities'] as $activityData) {
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

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result;
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }
}
