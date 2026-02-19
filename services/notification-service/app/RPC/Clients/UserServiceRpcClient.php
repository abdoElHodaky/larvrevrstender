<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for User Service (from Notification Service)
 * 
 * Provides RPC-based communication with the user service for
 * user data retrieval and notification preferences.
 */
class UserServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('user-service', [
            'timeout' => 20,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Get user details for notifications
     *
     * @param int $userId User ID
     * @return array RPC response with user data
     */
    public function getUser(int $userId): array
    {
        return $this->call('user.get', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Get multiple users for bulk notifications
     *
     * @param array $userIds Array of user IDs
     * @return array RPC response with users data
     */
    public function getMultipleUsers(array $userIds): array
    {
        return $this->call('user.getMultiple', [
            'user_ids' => $userIds,
        ]);
    }
    
    /**
     * Get user notification preferences
     *
     * @param int $userId User ID
     * @return array RPC response with notification preferences
     */
    public function getUserNotificationPreferences(int $userId): array
    {
        return $this->call('user.getNotificationPreferences', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Get user contact information
     *
     * @param int $userId User ID
     * @return array RPC response with contact info
     */
    public function getUserContactInfo(int $userId): array
    {
        return $this->call('user.getContactInfo', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Check if user is active
     *
     * @param int $userId User ID
     * @return array RPC response with user status
     */
    public function isUserActive(int $userId): array
    {
        return $this->call('user.isActive', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Get user timezone for notification scheduling
     *
     * @param int $userId User ID
     * @return array RPC response with timezone info
     */
    public function getUserTimezone(int $userId): array
    {
        return $this->call('user.getTimezone', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Get user language preference
     *
     * @param int $userId User ID
     * @return array RPC response with language preference
     */
    public function getUserLanguage(int $userId): array
    {
        return $this->call('user.getLanguage', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Update user notification delivery status
     *
     * @param int $userId User ID
     * @param array $deliveryData Delivery status data
     * @return array RPC response
     */
    public function updateNotificationDeliveryStatus(int $userId, array $deliveryData): array
    {
        return $this->call('user.updateNotificationDeliveryStatus', [
            'user_id' => $userId,
            'delivery_data' => $deliveryData,
        ]);
    }
    
    /**
     * Batch operation: Get multiple users' notification preferences
     *
     * @param array $userIds Array of user IDs
     * @return array Array of RPC responses
     */
    public function batchGetNotificationPreferences(array $userIds): array
    {
        $calls = [];
        foreach ($userIds as $userId) {
            $calls[] = [
                'method' => 'user.getNotificationPreferences',
                'params' => ['user_id' => $userId],
            ];
        }
        
        return $this->batchCall($calls);
    }
    
    /**
     * Batch operation: Get multiple users' contact info
     *
     * @param array $userIds Array of user IDs
     * @return array Array of RPC responses
     */
    public function batchGetContactInfo(array $userIds): array
    {
        $calls = [];
        foreach ($userIds as $userId) {
            $calls[] = [
                'method' => 'user.getContactInfo',
                'params' => ['user_id' => $userId],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

