<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for User Service
 * 
 * Provides RPC-based communication with the user service for
 * user management, authentication, and profile operations.
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
     * Get user details by ID
     *
     * @param int $userId User ID
     * @return array RPC response with user details
     */
    public function getUser(int $userId): array
    {
        return $this->call('user.get', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Get user by email
     *
     * @param string $email User email
     * @return array RPC response with user details
     */
    public function getUserByEmail(string $email): array
    {
        return $this->call('user.getByEmail', [
            'email' => $email,
        ]);
    }
    
    /**
     * Create new user
     *
     * @param array $userData User creation data
     * @return array RPC response with created user
     */
    public function createUser(array $userData): array
    {
        return $this->call('user.create', $userData);
    }
    
    /**
     * Update user details
     *
     * @param int $userId User ID
     * @param array $updateData Data to update
     * @return array RPC response
     */
    public function updateUser(int $userId, array $updateData): array
    {
        return $this->call('user.update', [
            'user_id' => $userId,
            'data' => $updateData,
        ]);
    }
    
    /**
     * Delete user
     *
     * @param int $userId User ID
     * @param string|null $reason Deletion reason
     * @return array RPC response
     */
    public function deleteUser(int $userId, ?string $reason = null): array
    {
        $params = ['user_id' => $userId];
        
        if ($reason) {
            $params['reason'] = $reason;
        }
        
        return $this->call('user.delete', $params);
    }
    
    /**
     * Get users with filtering and pagination
     *
     * @param array $filters Filters (status, role, date_range, etc.)
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @param string $orderBy Field to order by
     * @param string $orderDirection Order direction (asc/desc)
     * @return array RPC response with paginated users
     */
    public function getUsers(
        array $filters = [],
        int $limit = 20,
        int $offset = 0,
        string $orderBy = 'created_at',
        string $orderDirection = 'desc'
    ): array {
        return $this->call('user.list', [
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
            'order_by' => $orderBy,
            'order_direction' => $orderDirection,
        ]);
    }
    
    /**
     * Search users by criteria
     *
     * @param string $query Search query
     * @param array $filters Additional filters
     * @param int $limit Number of results
     * @param int $offset Pagination offset
     * @return array RPC response with search results
     */
    public function searchUsers(string $query, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        return $this->call('user.search', [
            'query' => $query,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Validate user credentials
     *
     * @param string $email User email
     * @param string $password User password
     * @return array RPC response with validation result
     */
    public function validateCredentials(string $email, string $password): array
    {
        return $this->call('user.validateCredentials', [
            'email' => $email,
            'password' => $password,
        ]);
    }
    
    /**
     * Update user password
     *
     * @param int $userId User ID
     * @param string $newPassword New password
     * @param string|null $currentPassword Current password for verification
     * @return array RPC response
     */
    public function updatePassword(int $userId, string $newPassword, ?string $currentPassword = null): array
    {
        $params = [
            'user_id' => $userId,
            'new_password' => $newPassword,
        ];
        
        if ($currentPassword) {
            $params['current_password'] = $currentPassword;
        }
        
        return $this->call('user.updatePassword', $params);
    }
    
    /**
     * Get user profile
     *
     * @param int $userId User ID
     * @return array RPC response with user profile
     */
    public function getUserProfile(int $userId): array
    {
        return $this->call('user.getProfile', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Update user profile
     *
     * @param int $userId User ID
     * @param array $profileData Profile data to update
     * @return array RPC response
     */
    public function updateUserProfile(int $userId, array $profileData): array
    {
        return $this->call('user.updateProfile', [
            'user_id' => $userId,
            'profile_data' => $profileData,
        ]);
    }
    
    /**
     * Get user preferences
     *
     * @param int $userId User ID
     * @return array RPC response with user preferences
     */
    public function getUserPreferences(int $userId): array
    {
        return $this->call('user.getPreferences', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Update user preferences
     *
     * @param int $userId User ID
     * @param array $preferences User preferences
     * @return array RPC response
     */
    public function updateUserPreferences(int $userId, array $preferences): array
    {
        return $this->call('user.updatePreferences', [
            'user_id' => $userId,
            'preferences' => $preferences,
        ]);
    }
    
    /**
     * Verify user email
     *
     * @param int $userId User ID
     * @param string $verificationToken Verification token
     * @return array RPC response
     */
    public function verifyEmail(int $userId, string $verificationToken): array
    {
        return $this->call('user.verifyEmail', [
            'user_id' => $userId,
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
        return $this->call('user.sendPasswordReset', [
            'email' => $email,
        ]);
    }
    
    /**
     * Reset password with token
     *
     * @param string $token Reset token
     * @param string $newPassword New password
     * @return array RPC response
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        return $this->call('user.resetPassword', [
            'token' => $token,
            'new_password' => $newPassword,
        ]);
    }
    
    /**
     * Get user activity log
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with activity log
     */
    public function getUserActivity(int $userId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->call('user.getActivity', [
            'user_id' => $userId,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Update user status
     *
     * @param int $userId User ID
     * @param string $status New status (active, inactive, suspended, etc.)
     * @param string|null $reason Reason for status change
     * @return array RPC response
     */
    public function updateUserStatus(int $userId, string $status, ?string $reason = null): array
    {
        $params = [
            'user_id' => $userId,
            'status' => $status,
        ];
        
        if ($reason) {
            $params['reason'] = $reason;
        }
        
        return $this->call('user.updateStatus', $params);
    }
    
    /**
     * Batch operation: Get multiple users
     *
     * @param array $userIds Array of user IDs
     * @return array Array of RPC responses
     */
    public function getMultipleUsers(array $userIds): array
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
}

