<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\UserService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Sajya\Server\Exceptions\RuntimeException;

class UserProcedure extends BaseProcedure
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Create new user
     * 
     * @param array $params
     * @return array
     */
    public function create(array $params): array
    {
        $this->validate($params, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'sometimes|string|max:20',
            'role' => 'sometimes|string|in:buyer,seller,admin',
            'company_name' => 'sometimes|string|max:255',
            'address' => 'sometimes|array',
            'preferences' => 'sometimes|array',
        ]);

        return $this->executeWithLogging('User@create', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for user creation
            $key = 'user_create:' . request()->ip();
            if (RateLimiter::tooManyAttempts($key, 10)) {
                throw new RuntimeException(
                    'Too many user creation attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            try {
                $user = $this->userService->createUser([
                    'name' => $params['name'],
                    'email' => $params['email'],
                    'phone' => $params['phone'] ?? null,
                    'role' => $params['role'] ?? 'buyer',
                    'company_name' => $params['company_name'] ?? null,
                    'address' => $params['address'] ?? null,
                    'preferences' => $params['preferences'] ?? [],
                ]);
                
                // Clear rate limiting on successful creation
                RateLimiter::clear($key);
                
                return [
                    'success' => true,
                    'user' => $user,
                    'created_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                // Increment rate limiting on failed creation
                RateLimiter::hit($key, 300); // 5 minutes
                
                throw new RuntimeException(
                    'User creation failed: ' . $e->getMessage(),
                    -32001,
                    ['email' => $params['email']]
                );
            }
        });
    }

    /**
     * Get user by ID
     * 
     * @param array $params
     * @return array
     */
    public function getById(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'include_preferences' => 'sometimes|boolean',
            'include_statistics' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('User@getById', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'user:' . $params['user_id'];
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $user = $this->userService->getUserById(
                    $params['user_id'],
                    $params['include_preferences'] ?? false,
                    $params['include_statistics'] ?? false
                );
                
                if (!$user) {
                    throw new RuntimeException(
                        'User not found',
                        -32001,
                        ['user_id' => $params['user_id']]
                    );
                }
                
                $result = [
                    'success' => true,
                    'user' => $user,
                    'retrieved_at' => now()->toISOString(),
                ];
                
                // Cache for 5 minutes
                Cache::put($cacheKey, $result, 300);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve user: ' . $e->getMessage(),
                    -32001,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Update user information
     * 
     * @param array $params
     * @return array
     */
    public function update(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'company_name' => 'sometimes|string|max:255',
            'address' => 'sometimes|array',
            'preferences' => 'sometimes|array',
            'status' => 'sometimes|string|in:active,inactive,suspended',
        ]);

        return $this->executeWithLogging('User@update', $this->sanitizeForLogging($params), function () use ($params) {
            try {
                $user = $this->userService->updateUser($params['user_id'], [
                    'name' => $params['name'] ?? null,
                    'phone' => $params['phone'] ?? null,
                    'company_name' => $params['company_name'] ?? null,
                    'address' => $params['address'] ?? null,
                    'preferences' => $params['preferences'] ?? null,
                    'status' => $params['status'] ?? null,
                ]);
                
                // Clear cache
                Cache::forget('user:' . $params['user_id']);
                
                return [
                    'success' => true,
                    'user' => $user,
                    'updated_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'User update failed: ' . $e->getMessage(),
                    -32002,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Get user profile with statistics
     * 
     * @param array $params
     * @return array
     */
    public function getProfile(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('User@getProfile', $params, function () use ($params) {
            try {
                $profile = $this->userService->getUserProfile($params['user_id']);
                
                return [
                    'success' => true,
                    'profile' => $profile,
                    'retrieved_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve user profile: ' . $e->getMessage(),
                    -32001,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Search users with filters
     * 
     * @param array $params
     * @return array
     */
    public function search(array $params): array
    {
        $this->validate($params, [
            'query' => 'sometimes|string|max:255',
            'role' => 'sometimes|string|in:buyer,seller,admin',
            'status' => 'sometimes|string|in:active,inactive,suspended',
            'company_name' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort_by' => 'sometimes|string|in:name,email,created_at,updated_at',
            'sort_order' => 'sometimes|string|in:asc,desc',
        ]);

        return $this->executeWithLogging('User@search', $params, function () use ($params) {
            try {
                $results = $this->userService->searchUsers([
                    'query' => $params['query'] ?? null,
                    'role' => $params['role'] ?? null,
                    'status' => $params['status'] ?? null,
                    'company_name' => $params['company_name'] ?? null,
                    'location' => $params['location'] ?? null,
                    'page' => $params['page'] ?? 1,
                    'per_page' => $params['per_page'] ?? 20,
                    'sort_by' => $params['sort_by'] ?? 'created_at',
                    'sort_order' => $params['sort_order'] ?? 'desc',
                ]);
                
                return [
                    'success' => true,
                    'users' => $results['data'],
                    'pagination' => $results['pagination'],
                    'searched_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'User search failed: ' . $e->getMessage(),
                    -32003,
                    ['search_params' => $params]
                );
            }
        });
    }

    /**
     * Get user statistics
     * 
     * @param array $params
     * @return array
     */
    public function getStatistics(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'period' => 'sometimes|string|in:week,month,quarter,year',
        ]);

        return $this->executeWithLogging('User@getStatistics', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'user_stats:' . $params['user_id'] . ':' . ($params['period'] ?? 'month');
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $statistics = $this->userService->getUserStatistics(
                    $params['user_id'],
                    $params['period'] ?? 'month'
                );
                
                $result = [
                    'success' => true,
                    'statistics' => $statistics,
                    'period' => $params['period'] ?? 'month',
                    'retrieved_at' => now()->toISOString(),
                ];
                
                // Cache for 1 hour
                Cache::put($cacheKey, $result, 3600);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve user statistics: ' . $e->getMessage(),
                    -32001,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Update user preferences
     * 
     * @param array $params
     * @return array
     */
    public function updatePreferences(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'preferences' => 'required|array',
            'preferences.notifications' => 'sometimes|array',
            'preferences.language' => 'sometimes|string|max:5',
            'preferences.timezone' => 'sometimes|string|max:50',
            'preferences.currency' => 'sometimes|string|max:3',
        ]);

        return $this->executeWithLogging('User@updatePreferences', $params, function () use ($params) {
            try {
                $user = $this->userService->updateUserPreferences(
                    $params['user_id'],
                    $params['preferences']
                );
                
                // Clear cache
                Cache::forget('user:' . $params['user_id']);
                
                return [
                    'success' => true,
                    'preferences' => $user['preferences'],
                    'updated_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to update user preferences: ' . $e->getMessage(),
                    -32002,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Deactivate user account
     * 
     * @param array $params
     * @return array
     */
    public function deactivate(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'reason' => 'sometimes|string|max:500',
        ]);

        return $this->executeWithLogging('User@deactivate', $params, function () use ($params) {
            try {
                $result = $this->userService->deactivateUser(
                    $params['user_id'],
                    $params['reason'] ?? null
                );
                
                // Clear cache
                Cache::forget('user:' . $params['user_id']);
                
                return [
                    'success' => true,
                    'message' => 'User account deactivated successfully',
                    'deactivated_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to deactivate user: ' . $e->getMessage(),
                    -32002,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Reactivate user account
     * 
     * @param array $params
     * @return array
     */
    public function reactivate(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('User@reactivate', $params, function () use ($params) {
            try {
                $result = $this->userService->reactivateUser($params['user_id']);
                
                // Clear cache
                Cache::forget('user:' . $params['user_id']);
                
                return [
                    'success' => true,
                    'message' => 'User account reactivated successfully',
                    'reactivated_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to reactivate user: ' . $e->getMessage(),
                    -32002,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }
}
