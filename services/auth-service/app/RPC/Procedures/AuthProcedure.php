<?php

namespace App\RPC\Procedures;

use App\Http\Controllers\AuthController;
use App\Services\Shared\ActivityRpcService;
use Illuminate\Support\Facades\Http;
use App\RPC\BaseProcedure;
use App\RPC\Procedures\Micro\SessionAnalyticsProcedure;
use App\RPC\Procedures\Micro\SessionManagementProcedure;
use App\RPC\Procedures\Micro\SessionSecurityProcedure;
use App\RPC\Procedures\Micro\SessionValidationProcedure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthProcedure extends BaseProcedure
{
    use SessionAnalyticsProcedure, SessionManagementProcedure, SessionSecurityProcedure, SessionValidationProcedure;

    public function __construct(
        private ActivityRpcService $activityRpcService
    ) {}

    /**
     * Validate authentication token
     */
    public function validateToken(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'token' => 'required|string',
            ]);

            $controller = new AuthController;
            $request = new Request(['token' => $params['token']]);

            $result = $controller->validateToken($request);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return [
                'valid' => $result->getData()->valid ?? false,
                'user_id' => $result->getData()->user_id ?? null,
                'expires_at' => $result->getData()->expires_at ?? null,
            ];
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
            ]);

            $controller = new AuthController;
            $result = $controller->getUserPermissions($params['user_id']);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Check specific permission for user
     */
    public function checkPermission(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'permission' => 'required|string',
            ]);

            $controller = new AuthController;
            $request = new Request([
                'user_id' => $params['user_id'],
                'permission' => $params['permission'],
            ]);

            $result = $controller->checkPermission($request);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return [
                'has_permission' => $result->getData()->has_permission ?? false,
            ];
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get user roles
     */
    public function getUserRoles(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
            ]);

            $controller = new AuthController;
            $result = $controller->getUserRoles($params['user_id']);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Check specific role for user
     */
    public function checkRole(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'role' => 'required|string',
            ]);

            $controller = new AuthController;
            $request = new Request([
                'user_id' => $params['user_id'],
                'role' => $params['role'],
            ]);

            $result = $controller->checkRole($request);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return [
                'has_role' => $result->getData()->has_role ?? false,
            ];
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Create session for user
     */
    public function createSession(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'device_info' => 'nullable|array',
                'ip_address' => 'nullable|string',
            ]);

            $controller = new AuthController;
            $request = new Request($params);

            $result = $controller->createSession($request);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Invalidate session
     */
    public function invalidateSession(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'session_id' => 'required|string',
            ]);

            $controller = new AuthController;
            $result = $controller->invalidateSession($params['session_id']);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return [
                'success' => $result->getStatusCode() === 200,
            ];
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Log user activity
     */
    public function logActivity(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'action' => 'required|string',
                'description' => 'nullable|string',
                'metadata' => 'nullable|array',
            ]);

            $controller = new AuthController;
            $request = new Request($params);

            $result = $controller->logActivity($request);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return [
                'success' => $result->getStatusCode() === 201,
                'activity_id' => $result->getData()->id ?? null,
            ];
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get user information
     */
    public function getUser(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
            ]);

            // Call user-service RPC to get user information
            $userServiceUrl = config('services.user_service.url', 'http://user-service:8000');
            $response = Http::timeout(30)->post($userServiceUrl . '/rpc', [
                'jsonrpc' => '2.0',
                'method' => 'user.getUser',
                'params' => ['user_id' => $params['user_id']],
                'id' => uniqid()
            ]);

            if (!$response->successful()) {
                throw new \Exception("Failed to get user from user-service: " . $response->status());
            }

            $data = $response->json();
            if (isset($data['error'])) {
                throw new \Exception("User service error: " . $data['error']['message']);
            }

            $result = $data['result'] ?? [];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result;
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Refresh authentication token
     */
    public function refreshToken(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'refresh_token' => 'required|string',
            ]);

            $controller = new AuthController;
            $request = new Request(['refresh_token' => $params['refresh_token']]);

            $result = $controller->refresh($request);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Logout user (invalidate all sessions)
     */
    public function logout(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
            ]);

            $controller = new AuthController;
            $request = new Request(['user_id' => $params['user_id']]);

            $result = $controller->logout($request);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return [
                'success' => $result->getStatusCode() === 200,
            ];
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
                'user_id' => 'required|integer',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            $result = $this->activityRpcService->getUserActivities(
                $params['user_id'],
                [],
                $params['limit'] ?? 15,
                1
            );

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result;
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }
}
