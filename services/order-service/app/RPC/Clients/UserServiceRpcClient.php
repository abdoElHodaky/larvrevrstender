<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * User Service RPC Client for Order Service
 *
 * Handles RPC communication with the User Service for order-related
 * user management, customer profiles, authentication checks, and
 * user validation operations.
 *
 * This client provides comprehensive user operations needed for
 * order processing workflows including customer management,
 * profile retrieval, and user validation.
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
     * Get user information for order processing
     *
     * @param int $userId User ID
     * @return array User information
     */
    public function getUserForOrder(int $userId): array
    {
        return $this->call('user.get_user_for_order', [
            'user_id' => $userId,
        ]);
    }

    /**
     * Get customer profile for order
     *
     * @param int $customerId Customer ID
     * @return array Customer profile information
     */
    public function getCustomerProfile(int $customerId): array
    {
        return $this->call('user.get_customer_profile', [
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Validate customer for order creation
     *
     * @param int $customerId Customer ID
     * @return array Validation result
     */
    public function validateCustomerForOrder(int $customerId): array
    {
        return $this->call('user.validate_customer_for_order', [
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Get merchant information for order
     *
     * @param int $merchantId Merchant ID
     * @return array Merchant information
     */
    public function getMerchantForOrder(int $merchantId): array
    {
        return $this->call('user.get_merchant_for_order', [
            'merchant_id' => $merchantId,
        ]);
    }

    /**
     * Validate merchant for order processing
     *
     * @param int $merchantId Merchant ID
     * @return array Validation result
     */
    public function validateMerchantForOrder(int $merchantId): array
    {
        return $this->call('user.validate_merchant_for_order', [
            'merchant_id' => $merchantId,
        ]);
    }

    /**
     * Get user preferences for order notifications
     *
     * @param int $userId User ID
     * @return array User notification preferences
     */
    public function getUserNotificationPreferences(int $userId): array
    {
        return $this->call('user.get_user_notification_preferences', [
            'user_id' => $userId,
        ]);
    }

    /**
     * Update user order history
     *
     * @param int $userId User ID
     * @param int $orderId Order ID
     * @param array $orderData Order information
     * @return array Update result
     */
    public function updateUserOrderHistory(int $userId, int $orderId, array $orderData): array
    {
        return $this->call('user.update_user_order_history', [
            'user_id' => $userId,
            'order_id' => $orderId,
            'order_data' => $orderData,
        ]);
    }

    /**
     * Get user shipping addresses
     *
     * @param int $userId User ID
     * @return array User shipping addresses
     */
    public function getUserShippingAddresses(int $userId): array
    {
        return $this->call('user.get_user_shipping_addresses', [
            'user_id' => $userId,
        ]);
    }

    /**
     * Validate user shipping address
     *
     * @param int $userId User ID
     * @param int $addressId Address ID
     * @return array Validation result
     */
    public function validateUserShippingAddress(int $userId, int $addressId): array
    {
        return $this->call('user.validate_user_shipping_address', [
            'user_id' => $userId,
            'address_id' => $addressId,
        ]);
    }

    /**
     * Get user billing information
     *
     * @param int $userId User ID
     * @return array User billing information
     */
    public function getUserBillingInfo(int $userId): array
    {
        return $this->call('user.get_user_billing_info', [
            'user_id' => $userId,
        ]);
    }

    /**
     * Check user authentication status
     *
     * @param int $userId User ID
     * @param string $token Authentication token
     * @return array Authentication status
     */
    public function checkUserAuthentication(int $userId, string $token): array
    {
        return $this->call('user.check_user_authentication', [
            'user_id' => $userId,
            'token' => $token,
        ]);
    }

    /**
     * Get user order permissions
     *
     * @param int $userId User ID
     * @param string $action Action to check (create, view, modify, cancel)
     * @return array Permission check result
     */
    public function checkUserOrderPermissions(int $userId, string $action): array
    {
        return $this->call('user.check_user_order_permissions', [
            'user_id' => $userId,
            'action' => $action,
        ]);
    }

    /**
     * Update user activity for order
     *
     * @param int $userId User ID
     * @param string $activity Activity type
     * @param array $activityData Activity details
     * @return array Activity update result
     */
    public function updateUserOrderActivity(int $userId, string $activity, array $activityData): array
    {
        return $this->call('user.update_user_order_activity', [
            'user_id' => $userId,
            'activity' => $activity,
            'activity_data' => $activityData,
        ]);
    }

    /**
     * Get user loyalty points for order
     *
     * @param int $userId User ID
     * @return array User loyalty points information
     */
    public function getUserLoyaltyPoints(int $userId): array
    {
        return $this->call('user.get_user_loyalty_points', [
            'user_id' => $userId,
        ]);
    }

    /**
     * Apply loyalty points to order
     *
     * @param int $userId User ID
     * @param int $orderId Order ID
     * @param int $points Points to apply
     * @return array Points application result
     */
    public function applyLoyaltyPointsToOrder(int $userId, int $orderId, int $points): array
    {
        return $this->call('user.apply_loyalty_points_to_order', [
            'user_id' => $userId,
            'order_id' => $orderId,
            'points' => $points,
        ]);
    }

    /**
     * Award loyalty points for order
     *
     * @param int $userId User ID
     * @param int $orderId Order ID
     * @param int $points Points to award
     * @param string $reason Reason for awarding points
     * @return array Points award result
     */
    public function awardLoyaltyPointsForOrder(int $userId, int $orderId, int $points, string $reason): array
    {
        return $this->call('user.award_loyalty_points_for_order', [
            'user_id' => $userId,
            'order_id' => $orderId,
            'points' => $points,
            'reason' => $reason,
        ]);
    }

    /**
     * Get user order statistics
     *
     * @param int $userId User ID
     * @return array User order statistics
     */
    public function getUserOrderStatistics(int $userId): array
    {
        return $this->call('user.get_user_order_statistics', [
            'user_id' => $userId,
        ]);
    }

    /**
     * Check user account status for orders
     *
     * @param int $userId User ID
     * @return array Account status information
     */
    public function checkUserAccountStatus(int $userId): array
    {
        return $this->call('user.check_user_account_status', [
            'user_id' => $userId,
        ]);
    }

    /**
     * Get user communication preferences
     *
     * @param int $userId User ID
     * @return array Communication preferences
     */
    public function getUserCommunicationPreferences(int $userId): array
    {
        return $this->call('user.get_user_communication_preferences', [
            'user_id' => $userId,
        ]);
    }

    /**
     * Validate user for order modification
     *
     * @param int $userId User ID
     * @param int $orderId Order ID
     * @return array Validation result
     */
    public function validateUserForOrderModification(int $userId, int $orderId): array
    {
        return $this->call('user.validate_user_for_order_modification', [
            'user_id' => $userId,
            'order_id' => $orderId,
        ]);
    }

    /**
     * Get multiple users for orders (batch operation)
     *
     * @param array $userIds Array of user IDs
     * @return array Batch user information results
     */
    public function getBatchUsersForOrders(array $userIds): array
    {
        $calls = [];
        foreach ($userIds as $userId) {
            $calls[] = [
                'method' => 'user.get_user_for_order',
                'params' => ['user_id' => $userId],
                'id' => "user_for_order_{$userId}",
            ];
        }

        return $this->batchCall($calls);
    }

    /**
     * Validate multiple customers for orders (batch operation)
     *
     * @param array $customerIds Array of customer IDs
     * @return array Batch validation results
     */
    public function validateBatchCustomersForOrders(array $customerIds): array
    {
        $calls = [];
        foreach ($customerIds as $customerId) {
            $calls[] = [
                'method' => 'user.validate_customer_for_order',
                'params' => ['customer_id' => $customerId],
                'id' => "validate_customer_{$customerId}",
            ];
        }

        return $this->batchCall($calls);
    }

    /**
     * Get user tier information for order pricing
     *
     * @param int $userId User ID
     * @return array User tier information
     */
    public function getUserTierForOrder(int $userId): array
    {
        return $this->call('user.get_user_tier_for_order', [
            'user_id' => $userId,
        ]);
    }
}
