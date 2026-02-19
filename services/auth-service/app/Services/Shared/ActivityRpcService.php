<?php

namespace App\Services\Shared;

use App\RPC\Adapters\UserServiceAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Shared service for calling activity RPC procedures from other services via RPC adapters
 * This can be used by auth-service, gateway-service, etc. to access activity functionality
 */
class ActivityRpcService
{
    private UserServiceAdapter $userAdapter;

    public function __construct(UserServiceAdapter $userAdapter)
    {
        $this->userAdapter = $userAdapter;
    }

    /**
     * Make RPC call to user-service activity procedures via adapter
     */
    private function makeRpcCall(string $method, array $params = []): array
    {
        try {
            // Map activity methods to adapter methods
            switch ($method) {
                case 'activity.logActivity':
                case 'activity.bulkLogActivities':
                    $result = $this->userAdapter->createActivity($params);
                    break;
                case 'activity.getUserActivities':
                case 'activity.getUserActivityStats':
                case 'activity.getActivity':
                case 'activity.getSubjectActivities':
                    $result = $this->userAdapter->getUserActivities($params['user_id'] ?? 0, $params);
                    break;
                default:
                    // For unmapped methods, try generic activity creation
                    $result = $this->userAdapter->createActivity($params);
                    break;
            }

            if ($result) {
                return $result;
            }

            return [
                'success' => false,
                'error' => 'RPC call failed',
                'message' => 'No result from adapter'
            ];
        } catch (\Exception $e) {
            Log::error('Activity RPC call failed via adapter', [
                'method' => $method,
                'params' => $params,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'RPC call failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Log an activity via RPC
     */
    public function logActivity(
        string $description,
        ?int $causerId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $properties = [],
        string $logName = 'default'
    ): array {
        return $this->makeRpcCall('activity.logActivity', [
            'description' => $description,
            'causer_id' => $causerId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => $properties,
            'log_name' => $logName
        ]);
    }

    /**
     * Get user activities via RPC
     */
    public function getUserActivities(
        int $userId,
        array $filters = [],
        int $perPage = 15,
        int $page = 1
    ): array {
        // Cache key for user activities
        $cacheKey = 'shared_user_activities:' . $userId . ':' . md5(serialize([$filters, $perPage, $page]));
        
        return Cache::remember($cacheKey, 300, function () use ($userId, $filters, $perPage, $page) {
            return $this->makeRpcCall('activity.getUserActivities', [
                'user_id' => $userId,
                'filters' => $filters,
                'per_page' => $perPage,
                'page' => $page
            ]);
        });
    }

    /**
     * Get user activity statistics via RPC
     */
    public function getUserActivityStats(int $userId, int $days = 30): array
    {
        // Cache key for user activity stats
        $cacheKey = 'shared_user_activity_stats:' . $userId . ':' . $days;
        
        return Cache::remember($cacheKey, 600, function () use ($userId, $days) {
            return $this->makeRpcCall('activity.getUserActivityStats', [
                'user_id' => $userId,
                'days' => $days
            ]);
        });
    }

    /**
     * Get activity by ID via RPC
     */
    public function getActivity(int $activityId): array
    {
        // Cache key for activity
        $cacheKey = 'shared_activity:' . $activityId;
        
        return Cache::remember($cacheKey, 300, function () use ($activityId) {
            return $this->makeRpcCall('activity.getActivity', [
                'activity_id' => $activityId
            ]);
        });
    }

    /**
     * Delete activity via RPC
     */
    public function deleteActivity(int $activityId, int $requestingUserId): array
    {
        $result = $this->makeRpcCall('activity.deleteActivity', [
            'activity_id' => $activityId,
            'requesting_user_id' => $requestingUserId
        ]);

        // Clear related caches on successful deletion
        if ($result['success'] ?? false) {
            Cache::forget('shared_activity:' . $activityId);
            Cache::forget('shared_user_activities:' . $requestingUserId . ':*');
        }

        return $result;
    }

    /**
     * Get subject activities via RPC
     */
    public function getSubjectActivities(
        string $subjectType,
        int $subjectId,
        array $filters = [],
        int $perPage = 15,
        int $page = 1
    ): array {
        // Cache key for subject activities
        $cacheKey = 'shared_subject_activities:' . $subjectType . ':' . $subjectId . ':' . md5(serialize([$filters, $perPage, $page]));
        
        return Cache::remember($cacheKey, 300, function () use ($subjectType, $subjectId, $filters, $perPage, $page) {
            return $this->makeRpcCall('activity.getSubjectActivities', [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'filters' => $filters,
                'per_page' => $perPage,
                'page' => $page
            ]);
        });
    }

    /**
     * Bulk log activities via RPC
     */
    public function bulkLogActivities(array $activities): array
    {
        return $this->makeRpcCall('activity.bulkLogActivities', [
            'activities' => $activities
        ]);
    }

    /**
     * Helper method to log user login activity
     */
    public function logUserLogin(int $userId, array $properties = []): array
    {
        return $this->logActivity(
            'User logged in',
            $userId,
            'App\\Models\\User',
            $userId,
            array_merge([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toISOString()
            ], $properties),
            'auth'
        );
    }

    /**
     * Helper method to log user logout activity
     */
    public function logUserLogout(int $userId, array $properties = []): array
    {
        return $this->logActivity(
            'User logged out',
            $userId,
            'App\\Models\\User',
            $userId,
            array_merge([
                'ip_address' => request()->ip(),
                'timestamp' => now()->toISOString()
            ], $properties),
            'auth'
        );
    }

    /**
     * Helper method to log password change activity
     */
    public function logPasswordChange(int $userId, array $properties = []): array
    {
        return $this->logActivity(
            'User changed password',
            $userId,
            'App\\Models\\User',
            $userId,
            array_merge([
                'ip_address' => request()->ip(),
                'timestamp' => now()->toISOString()
            ], $properties),
            'auth'
        );
    }

    /**
     * Helper method to log profile update activity
     */
    public function logProfileUpdate(int $userId, array $changes = [], array $properties = []): array
    {
        return $this->logActivity(
            'User updated profile',
            $userId,
            'App\\Models\\User',
            $userId,
            array_merge([
                'changes' => $changes,
                'ip_address' => request()->ip(),
                'timestamp' => now()->toISOString()
            ], $properties),
            'profile'
        );
    }

    /**
     * Helper method to log failed login attempt
     */
    public function logFailedLogin(string $email, array $properties = []): array
    {
        return $this->logActivity(
            'Failed login attempt',
            null,
            null,
            null,
            array_merge([
                'email' => $email,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toISOString()
            ], $properties),
            'auth'
        );
    }

    /**
     * Helper method to log account lockout
     */
    public function logAccountLockout(int $userId, array $properties = []): array
    {
        return $this->logActivity(
            'Account locked due to failed login attempts',
            null,
            'App\\Models\\User',
            $userId,
            array_merge([
                'ip_address' => request()->ip(),
                'timestamp' => now()->toISOString()
            ], $properties),
            'security'
        );
    }

    /**
     * Helper method to log permission changes
     */
    public function logPermissionChange(int $userId, int $targetUserId, array $changes = [], array $properties = []): array
    {
        return $this->logActivity(
            'User permissions modified',
            $userId,
            'App\\Models\\User',
            $targetUserId,
            array_merge([
                'changes' => $changes,
                'modified_by' => $userId,
                'timestamp' => now()->toISOString()
            ], $properties),
            'permissions'
        );
    }
}
