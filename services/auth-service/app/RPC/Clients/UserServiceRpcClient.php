<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for User Service (Auth Context)
 * 
 * Provides RPC-based communication with the user service for
 * authentication-related user operations and data management.
 */
class UserServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('user-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Get user details for authentication
     *
     * @param int $userId User ID
     * @return array RPC response with user details
     */
    public function getUserForAuth(int $userId): array
    {
        return $this->call('user.get', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Get user by email for authentication
     *
     * @param string $email User email
     * @return array RPC response with user details
     */
    public function getUserByEmailForAuth(string $email): array
    {
        return $this->call('user.getByEmail', [
            'email' => $email,
        ]);
    }
    
    /**
     * Create new user during registration
     *
     * @param array $userData User creation data
     * @return array RPC response with created user
     */
    public function createUserForAuth(array $userData): array
    {
        return $this->call('user.create', $userData);
    }
    
    /**
     * Update user authentication data
     *
     * @param int $userId User ID
     * @param array $authData Authentication data to update
     * @return array RPC response
     */
    public function updateUserAuthData(int $userId, array $authData): array
    {
        return $this->call('user.updateAuthData', [
            'user_id' => $userId,
            'auth_data' => $authData,
        ]);
    }
    
    /**
     * Update user password hash
     *
     * @param int $userId User ID
     * @param string $passwordHash New password hash
     * @return array RPC response
     */
    public function updateUserPasswordHash(int $userId, string $passwordHash): array
    {
        return $this->call('user.updatePasswordHash', [
            'user_id' => $userId,
            'password_hash' => $passwordHash,
        ]);
    }
    
    /**
     * Update user email verification status
     *
     * @param int $userId User ID
     * @param bool $verified Verification status
     * @param string|null $verifiedAt Verification timestamp
     * @return array RPC response
     */
    public function updateEmailVerificationStatus(int $userId, bool $verified, ?string $verifiedAt = null): array
    {
        return $this->call('user.updateEmailVerification', [
            'user_id' => $userId,
            'verified' => $verified,
            'verified_at' => $verifiedAt,
        ]);
    }
    
    /**
     * Update user last login information
     *
     * @param int $userId User ID
     * @param array $loginData Login data (timestamp, IP, user agent, etc.)
     * @return array RPC response
     */
    public function updateUserLastLogin(int $userId, array $loginData): array
    {
        return $this->call('user.updateLastLogin', [
            'user_id' => $userId,
            'login_data' => $loginData,
        ]);
    }
    
    /**
     * Update user security settings
     *
     * @param int $userId User ID
     * @param array $securitySettings Security settings
     * @return array RPC response
     */
    public function updateUserSecuritySettings(int $userId, array $securitySettings): array
    {
        return $this->call('user.updateSecuritySettings', [
            'user_id' => $userId,
            'security_settings' => $securitySettings,
        ]);
    }
    
    /**
     * Lock user account
     *
     * @param int $userId User ID
     * @param string $reason Lock reason
     * @param string|null $lockedUntil Lock expiration time
     * @return array RPC response
     */
    public function lockUserAccount(int $userId, string $reason, ?string $lockedUntil = null): array
    {
        return $this->call('user.lockAccount', [
            'user_id' => $userId,
            'reason' => $reason,
            'locked_until' => $lockedUntil,
        ]);
    }
    
    /**
     * Unlock user account
     *
     * @param int $userId User ID
     * @param string $reason Unlock reason
     * @return array RPC response
     */
    public function unlockUserAccount(int $userId, string $reason): array
    {
        return $this->call('user.unlockAccount', [
            'user_id' => $userId,
            'reason' => $reason,
        ]);
    }
    
    /**
     * Update user two-factor authentication settings
     *
     * @param int $userId User ID
     * @param array $twoFactorSettings Two-factor authentication settings
     * @return array RPC response
     */
    public function updateUserTwoFactorSettings(int $userId, array $twoFactorSettings): array
    {
        return $this->call('user.updateTwoFactorSettings', [
            'user_id' => $userId,
            'two_factor_settings' => $twoFactorSettings,
        ]);
    }
    
    /**
     * Get user security profile
     *
     * @param int $userId User ID
     * @return array RPC response with security profile
     */
    public function getUserSecurityProfile(int $userId): array
    {
        return $this->call('user.getSecurityProfile', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Record user authentication attempt
     *
     * @param int $userId User ID
     * @param array $attemptData Authentication attempt data
     * @return array RPC response
     */
    public function recordAuthAttempt(int $userId, array $attemptData): array
    {
        return $this->call('user.recordAuthAttempt', [
            'user_id' => $userId,
            'attempt_data' => $attemptData,
        ]);
    }
    
    /**
     * Get user authentication history
     *
     * @param int $userId User ID
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with auth history
     */
    public function getUserAuthHistory(int $userId, int $limit = 50, int $offset = 0): array
    {
        return $this->call('user.getAuthHistory', [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Check if user account is locked
     *
     * @param int $userId User ID
     * @return array RPC response with lock status
     */
    public function isUserAccountLocked(int $userId): array
    {
        return $this->call('user.isAccountLocked', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Validate user account status for authentication
     *
     * @param int $userId User ID
     * @return array RPC response with account validation
     */
    public function validateUserAccountStatus(int $userId): array
    {
        return $this->call('user.validateAccountStatus', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Update user role assignments
     *
     * @param int $userId User ID
     * @param array $roles Array of role assignments
     * @return array RPC response
     */
    public function updateUserRoles(int $userId, array $roles): array
    {
        return $this->call('user.updateRoles', [
            'user_id' => $userId,
            'roles' => $roles,
        ]);
    }
    
    /**
     * Get user permissions from user service
     *
     * @param int $userId User ID
     * @return array RPC response with user permissions
     */
    public function getUserPermissions(int $userId): array
    {
        return $this->call('user.getPermissions', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Batch operation: Get multiple users for authentication
     *
     * @param array $userIds Array of user IDs
     * @return array Array of RPC responses
     */
    public function getBatchUsersForAuth(array $userIds): array
    {
        $calls = [];
        foreach ($userIds as $userId) {
            $calls[] = [
                'method' => 'user.get',
                'params' => ['user_id' => $userId],
            ];
        }
        
        return $this->batchCall($calls);
    }
    
    /**
     * Batch operation: Validate multiple user account statuses
     *
     * @param array $userIds Array of user IDs
     * @return array Array of RPC responses
     */
    public function validateBatchUserAccountStatuses(array $userIds): array
    {
        $calls = [];
        foreach ($userIds as $userId) {
            $calls[] = [
                'method' => 'user.validateAccountStatus',
                'params' => ['user_id' => $userId],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

