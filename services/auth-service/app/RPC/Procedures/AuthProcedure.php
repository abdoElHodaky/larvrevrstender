<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\RPC\Procedures\Micro\SessionValidationProcedure;
use App\RPC\Procedures\Micro\SessionManagementProcedure;
use App\RPC\Procedures\Micro\SessionSecurityProcedure;
use App\RPC\Procedures\Micro\SessionAnalyticsProcedure;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ActivityController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthProcedure extends BaseProcedure
{
    use SessionValidationProcedure, SessionManagementProcedure, SessionSecurityProcedure, SessionAnalyticsProcedure;
    /**
     * Validate authentication token
     * 
     * @param array $params
     * @return array
     */
    public function validateToken(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'token' => 'required|string',
            ]);

            $controller = new AuthController();
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
     * 
     * @param array $params
     * @return array
     */
    public function getUserPermissions(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
            ]);

            $controller = new AuthController();
            $result = $controller->getUserPermissions($params['user_id']);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Check specific permission for user
     * 
     * @param array $params
     * @return array
     */
    public function checkPermission(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'permission' => 'required|string',
            ]);

            $controller = new AuthController();
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
     * 
     * @param array $params
     * @return array
     */
    public function getUserRoles(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
            ]);

            $controller = new AuthController();
            $result = $controller->getUserRoles($params['user_id']);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Check specific role for user
     * 
     * @param array $params
     * @return array
     */
    public function checkRole(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'role' => 'required|string',
            ]);

            $controller = new AuthController();
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
     * 
     * @param array $params
     * @return array
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

            $controller = new AuthController();
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
     * 
     * @param array $params
     * @return array
     */
    public function invalidateSession(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'session_id' => 'required|string',
            ]);

            $controller = new AuthController();
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
     * 
     * @param array $params
     * @return array
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

            $controller = new AuthController();
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
     * 
     * @param array $params
     * @return array
     */
    public function getUser(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
            ]);

            $controller = new UserController();
            $result = $controller->show($params['user_id']);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Refresh authentication token
     * 
     * @param array $params
     * @return array
     */
    public function refreshToken(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'refresh_token' => 'required|string',
            ]);

            $controller = new AuthController();
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
     * 
     * @param array $params
     * @return array
     */
    public function logout(array $params): array
    {
        $startTime = microtime(true);
        
        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
            ]);

            $controller = new AuthController();
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
     * 
     * @param array $params
     * @return array
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

            $controller = new ActivityController();
            $result = $controller->getUserActivities($params['user_id']);
            
            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            
            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }
}
