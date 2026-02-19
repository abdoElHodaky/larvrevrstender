<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Auth Service
 * 
 * Provides RPC-based communication with the auth service for
 * authentication, authorization, and session management.
 */
class AuthServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('auth-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Authenticate user with credentials
     *
     * @param string $email User email
     * @param string $password User password
     * @param array $options Additional authentication options
     * @return array RPC response with authentication result
     */
    public function authenticate(string $email, string $password, array $options = []): array
    {
        return $this->call('auth.authenticate', [
            'email' => $email,
            'password' => $password,
            'options' => $options,
        ]);
    }
    
    /**
     * Validate authentication token
     *
     * @param string $token Authentication token
     * @return array RPC response with token validation result
     */
    public function validateToken(string $token): array
    {
        return $this->call('auth.validateToken', [
            'token' => $token,
        ]);
    }
    
    /**
     * Refresh authentication token
     *
     * @param string $refreshToken Refresh token
     * @return array RPC response with new tokens
     */
    public function refreshToken(string $refreshToken): array
    {
        return $this->call('auth.refreshToken', [
            'refresh_token' => $refreshToken,
        ]);
    }
    
    /**
     * Logout user and invalidate tokens
     *
     * @param string $token Authentication token
     * @return array RPC response
     */
    public function logout(string $token): array
    {
        return $this->call('auth.logout', [
            'token' => $token,
        ]);
    }
    
    /**
     * Register new user
     *
     * @param array $userData User registration data
     * @return array RPC response with registration result
     */
    public function register(array $userData): array
    {
        return $this->call('auth.register', $userData);
    }
    
    /**
     * Verify user email with token
     *
     * @param string $verificationToken Email verification token
     * @return array RPC response
     */
    public function verifyEmail(string $verificationToken): array
    {
        return $this->call('auth.verifyEmail', [
            'verification_token' => $verificationToken,
        ]);
    }
    
    /**
     * Send password reset email
     *
     * @param string $email User email
     * @return array RPC response
     */
    public function sendPasswordReset(string $email): array
    {
        return $this->call('auth.sendPasswordReset', [
            'email' => $email,
        ]);
    }
    
    /**
     * Reset password with token
     *
     * @param string $resetToken Password reset token
     * @param string $newPassword New password
     * @return array RPC response
     */
    public function resetPassword(string $resetToken, string $newPassword): array
    {
        return $this->call('auth.resetPassword', [
            'reset_token' => $resetToken,
            'new_password' => $newPassword,
        ]);
    }
    
    /**
     * Change user password
     *
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return array RPC response
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        return $this->call('auth.changePassword', [
            'user_id' => $userId,
            'current_password' => $currentPassword,
            'new_password' => $newPassword,
        ]);
    }
    
    /**
     * Get user permissions
     *
     * @param int $userId User ID
     * @return array RPC response with user permissions
     */
    public function getUserPermissions(int $userId): array
    {
        return $this->call('auth.getUserPermissions', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Check if user has permission
     *
     * @param int $userId User ID
     * @param string $permission Permission to check
     * @return array RPC response with permission check result
     */
    public function hasPermission(int $userId, string $permission): array
    {
        return $this->call('auth.hasPermission', [
            'user_id' => $userId,
            'permission' => $permission,
        ]);
    }
    
    /**
     * Check if user has role
     *
     * @param int $userId User ID
     * @param string $role Role to check
     * @return array RPC response with role check result
     */
    public function hasRole(int $userId, string $role): array
    {
        return $this->call('auth.hasRole', [
            'user_id' => $userId,
            'role' => $role,
        ]);
    }
    
    /**
     * Assign role to user
     *
     * @param int $userId User ID
     * @param string $role Role to assign
     * @return array RPC response
     */
    public function assignRole(int $userId, string $role): array
    {
        return $this->call('auth.assignRole', [
            'user_id' => $userId,
            'role' => $role,
        ]);
    }
    
    /**
     * Remove role from user
     *
     * @param int $userId User ID
     * @param string $role Role to remove
     * @return array RPC response
     */
    public function removeRole(int $userId, string $role): array
    {
        return $this->call('auth.removeRole', [
            'user_id' => $userId,
            'role' => $role,
        ]);
    }
    
    /**
     * Get user roles
     *
     * @param int $userId User ID
     * @return array RPC response with user roles
     */
    public function getUserRoles(int $userId): array
    {
        return $this->call('auth.getUserRoles', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Enable two-factor authentication
     *
     * @param int $userId User ID
     * @param array $twoFactorData Two-factor authentication data
     * @return array RPC response
     */
    public function enableTwoFactor(int $userId, array $twoFactorData): array
    {
        return $this->call('auth.enableTwoFactor', [
            'user_id' => $userId,
            'two_factor_data' => $twoFactorData,
        ]);
    }
    
    /**
     * Disable two-factor authentication
     *
     * @param int $userId User ID
     * @param string $verificationCode Verification code
     * @return array RPC response
     */
    public function disableTwoFactor(int $userId, string $verificationCode): array
    {
        return $this->call('auth.disableTwoFactor', [
            'user_id' => $userId,
            'verification_code' => $verificationCode,
        ]);
    }
    
    /**
     * Verify two-factor authentication code
     *
     * @param int $userId User ID
     * @param string $code Two-factor code
     * @return array RPC response
     */
    public function verifyTwoFactor(int $userId, string $code): array
    {
        return $this->call('auth.verifyTwoFactor', [
            'user_id' => $userId,
            'code' => $code,
        ]);
    }
    
    /**
     * Get user sessions
     *
     * @param int $userId User ID
     * @return array RPC response with user sessions
     */
    public function getUserSessions(int $userId): array
    {
        return $this->call('auth.getUserSessions', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Revoke user session
     *
     * @param string $sessionId Session ID
     * @return array RPC response
     */
    public function revokeSession(string $sessionId): array
    {
        return $this->call('auth.revokeSession', [
            'session_id' => $sessionId,
        ]);
    }
    
    /**
     * Revoke all user sessions
     *
     * @param int $userId User ID
     * @param string|null $exceptSessionId Session ID to keep active
     * @return array RPC response
     */
    public function revokeAllSessions(int $userId, ?string $exceptSessionId = null): array
    {
        $params = ['user_id' => $userId];
        
        if ($exceptSessionId) {
            $params['except_session_id'] = $exceptSessionId;
        }
        
        return $this->call('auth.revokeAllSessions', $params);
    }
    
    /**
     * Get authentication logs for user
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with auth logs
     */
    public function getAuthLogs(int $userId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->call('auth.getAuthLogs', [
            'user_id' => $userId,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
}

