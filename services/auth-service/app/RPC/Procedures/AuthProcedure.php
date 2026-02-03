<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\AuthService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Sajya\Server\Exceptions\RuntimeException;

class AuthProcedure extends BaseProcedure
{
    public function __construct(
        private AuthService $authService
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
     * Authenticate user credentials
     * 
     * @param array $params
     * @return array
     */
    public function authenticate(array $params): array
    {
        $this->validate($params, [
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|max:255',
            'remember' => 'sometimes|boolean',
            'device_name' => 'sometimes|string|max:255',
        ]);

        return $this->executeWithLogging('Auth@authenticate', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting
            $key = 'auth_attempt:' . $params['email'];
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw $this->createRuntimeException(
                    'Too many authentication attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            try {
                $result = $this->authService->authenticate(
                    $params['email'],
                    $params['password'],
                    $params['remember'] ?? false,
                    $params['device_name'] ?? 'Unknown Device'
                );
                
                // Clear rate limiting on successful authentication
                RateLimiter::clear($key);
                
                return [
                    'success' => true,
                    'user' => $result['user'],
                    'token' => $result['token'],
                    'expires_at' => $result['expires_at'],
                    'authenticated_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                // Increment rate limiting on failed authentication
                RateLimiter::hit($key, 300); // 5 minutes
                
                throw $this->createRuntimeException(
                    'Authentication failed',
                    -32001,
                    ['email' => $params['email'], 'reason' => 'Invalid credentials']
                );
            }
        });
    }

    /**
     * Verify JWT token
     * 
     * @param array $params
     * @return array
     */
    public function verifyToken(array $params): array
    {
        $this->validate($params, [
            'token' => 'required|string',
            'check_permissions' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Auth@verifyToken', $this->sanitizeForLogging($params), function () use ($params) {
            // Check cache first for performance
            $cacheKey = 'token_verification:' . hash('sha256', $params['token']);
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null && $cached['valid']) {
                return $cached;
            }

            try {
                $result = $this->authService->verifyToken(
                    $params['token'],
                    $params['check_permissions'] ?? false
                );
                
                // Cache valid tokens for 5 minutes
                if ($result['valid']) {
                    Cache::put($cacheKey, $result, 300);
                }
                
                return [
                    'valid' => $result['valid'],
                    'user' => $result['user'] ?? null,
                    'permissions' => $result['permissions'] ?? [],
                    'expires_at' => $result['expires_at'] ?? null,
                    'verified_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                return [
                    'valid' => false,
                    'error' => 'Token verification failed',
                    'verified_at' => now()->toISOString(),
                ];
            }
        });
    }

    /**
     * Refresh JWT token
     * 
     * @param array $params
     * @return array
     */
    public function refreshToken(array $params): array
    {
        $this->validate($params, [
            'refresh_token' => 'required|string',
            'device_name' => 'sometimes|string|max:255',
        ]);

        return $this->executeWithLogging('Auth@refreshToken', $this->sanitizeForLogging($params), function () use ($params) {
            try {
                $result = $this->authService->refreshToken(
                    $params['refresh_token'],
                    $params['device_name'] ?? 'Unknown Device'
                );
                
                return [
                    'success' => true,
                    'token' => $result['token'],
                    'refresh_token' => $result['refresh_token'],
                    'expires_at' => $result['expires_at'],
                    'refreshed_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'Token refresh failed',
                    -32003,
                    ['reason' => 'Invalid or expired refresh token']
                );
            }
        });
    }

    /**
     * Logout user (invalidate token)
     * 
     * @param array $params
     * @return array
     */
    public function logout(array $params): array
    {
        $this->validate($params, [
            'token' => 'required|string',
            'all_devices' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Auth@logout', $this->sanitizeForLogging($params), function () use ($params) {
            try {
                $result = $this->authService->logout(
                    $params['token'],
                    $params['all_devices'] ?? false
                );
                
                // Clear token from cache
                $cacheKey = 'token_verification:' . hash('sha256', $params['token']);
                Cache::forget($cacheKey);
                
                return [
                    'success' => true,
                    'message' => $params['all_devices'] ?? false 
                        ? 'Logged out from all devices' 
                        : 'Logged out successfully',
                    'logged_out_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'Logout failed',
                    -32004,
                    ['reason' => 'Invalid token or user not found']
                );
            }
        });
    }

    /**
     * Register new user
     * 
     * @param array $params
     * @return array
     */
    public function register(array $params): array
    {
        $this->validate($params, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8|max:255|confirmed',
            'password_confirmation' => 'required|string',
            'device_name' => 'sometimes|string|max:255',
        ]);

        return $this->executeWithLogging('Auth@register', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for registration
            $key = 'register_attempt:' . request()->ip();
            if (RateLimiter::tooManyAttempts($key, 3)) {
                throw $this->createRuntimeException(
                    'Too many registration attempts. Please try again later.',
                    -32008,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            try {
                $result = $this->authService->register([
                    'name' => $params['name'],
                    'email' => $params['email'],
                    'password' => $params['password'],
                ], $params['device_name'] ?? 'Unknown Device');
                
                // Clear rate limiting on successful registration
                RateLimiter::clear($key);
                
                return [
                    'success' => true,
                    'user' => $result['user'],
                    'token' => $result['token'],
                    'expires_at' => $result['expires_at'],
                    'registered_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                // Increment rate limiting on failed registration
                RateLimiter::hit($key, 600); // 10 minutes
                
                throw $this->createRuntimeException(
                    'Registration failed: ' . $e->getMessage(),
                    -32009,
                    ['email' => $params['email']]
                );
            }
        });
    }

    /**
     * Change user password
     * 
     * @param array $params
     * @return array
     */
    public function changePassword(array $params): array
    {
        $this->validate($params, [
            'token' => 'required|string',
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|max:255|confirmed',
            'new_password_confirmation' => 'required|string',
        ]);

        return $this->executeWithLogging('Auth@changePassword', $this->sanitizeForLogging($params), function () use ($params) {
            try {
                $result = $this->authService->changePassword(
                    $params['token'],
                    $params['current_password'],
                    $params['new_password']
                );
                
                return [
                    'success' => true,
                    'message' => 'Password changed successfully',
                    'changed_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'Password change failed: ' . $e->getMessage(),
                    -32010,
                    ['reason' => 'Invalid current password or token']
                );
            }
        });
    }

    /**
     * Get user profile from token
     * 
     * @param array $params
     * @return array
     */
    public function getProfile(array $params): array
    {
        $this->validate($params, [
            'token' => 'required|string',
        ]);

        return $this->executeWithLogging('Auth@getProfile', $this->sanitizeForLogging($params), function () use ($params) {
            try {
                $result = $this->authService->getProfile($params['token']);
                
                return [
                    'success' => true,
                    'user' => $result['user'],
                    'permissions' => $result['permissions'] ?? [],
                    'retrieved_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'Profile retrieval failed',
                    -32011,
                    ['reason' => 'Invalid token or user not found']
                );
            }
        });
    }
}
