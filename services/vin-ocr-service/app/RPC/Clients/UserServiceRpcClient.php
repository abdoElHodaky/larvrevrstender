<?php

namespace App\RPC\Clients;

use Shared\RPC\BaseRpcClient;

/**
 * User Service RPC Client for VIN OCR Service
 *
 * Handles RPC communication with the User Service for VIN OCR-related
 * user management, customer validation, vehicle management, and
 * user profile operations.
 *
 * This client provides comprehensive user operations needed for
 * VIN OCR processing workflows including customer validation,
 * vehicle management, and user profile updates.
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
     * Get customer information for VIN processing
     *
     * @param int $customerId Customer ID
     * @return array Customer information
     */
    public function getCustomerForVinProcessing(int $customerId): array
    {
        return $this->call('user.get_customer_for_vin_processing', [
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Validate customer for VIN OCR service
     *
     * @param int $customerId Customer ID
     * @return array Validation result
     */
    public function validateCustomerForVinOcr(int $customerId): array
    {
        return $this->call('user.validate_customer_for_vin_ocr', [
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Add vehicle from VIN processing result
     *
     * @param int $customerId Customer ID
     * @param string $vin VIN number
     * @param array $vehicleData Vehicle information
     * @return array Vehicle addition result
     */
    public function addVehicleFromVin(int $customerId, string $vin, array $vehicleData): array
    {
        return $this->call('user.add_vehicle_from_vin', [
            'customer_id' => $customerId,
            'vin' => $vin,
            'vehicle_data' => $vehicleData,
        ]);
    }

    /**
     * Update vehicle information from VIN data
     *
     * @param int $vehicleId Vehicle ID
     * @param array $vinData VIN processing data
     * @return array Update result
     */
    public function updateVehicleFromVinData(int $vehicleId, array $vinData): array
    {
        return $this->call('user.update_vehicle_from_vin_data', [
            'vehicle_id' => $vehicleId,
            'vin_data' => $vinData,
        ]);
    }

    /**
     * Get customer vehicles
     *
     * @param int $customerId Customer ID
     * @return array Customer vehicles
     */
    public function getCustomerVehicles(int $customerId): array
    {
        return $this->call('user.get_customer_vehicles', [
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Check if VIN already exists for customer
     *
     * @param int $customerId Customer ID
     * @param string $vin VIN number
     * @return array VIN existence check result
     */
    public function checkVinExistsForCustomer(int $customerId, string $vin): array
    {
        return $this->call('user.check_vin_exists_for_customer', [
            'customer_id' => $customerId,
            'vin' => $vin,
        ]);
    }

    /**
     * Get customer VIN processing history
     *
     * @param int $customerId Customer ID
     * @return array VIN processing history
     */
    public function getCustomerVinProcessingHistory(int $customerId): array
    {
        return $this->call('user.get_customer_vin_processing_history', [
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Update customer VIN processing quota
     *
     * @param int $customerId Customer ID
     * @param int $quotaUsed Quota used
     * @return array Quota update result
     */
    public function updateCustomerVinProcessingQuota(int $customerId, int $quotaUsed): array
    {
        return $this->call('user.update_customer_vin_processing_quota', [
            'customer_id' => $customerId,
            'quota_used' => $quotaUsed,
        ]);
    }

    /**
     * Get customer VIN processing quota
     *
     * @param int $customerId Customer ID
     * @return array Customer quota information
     */
    public function getCustomerVinProcessingQuota(int $customerId): array
    {
        return $this->call('user.get_customer_vin_processing_quota', [
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Validate customer VIN processing permissions
     *
     * @param int $customerId Customer ID
     * @param string $operation Operation type
     * @return array Permission validation result
     */
    public function validateCustomerVinProcessingPermissions(int $customerId, string $operation): array
    {
        return $this->call('user.validate_customer_vin_processing_permissions', [
            'customer_id' => $customerId,
            'operation' => $operation,
        ]);
    }

    /**
     * Get customer subscription tier for VIN processing
     *
     * @param int $customerId Customer ID
     * @return array Customer subscription tier
     */
    public function getCustomerSubscriptionTier(int $customerId): array
    {
        return $this->call('user.get_customer_subscription_tier', [
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Record VIN processing activity for customer
     *
     * @param int $customerId Customer ID
     * @param array $activityData Activity details
     * @return array Activity recording result
     */
    public function recordVinProcessingActivity(int $customerId, array $activityData): array
    {
        return $this->call('user.record_vin_processing_activity', [
            'customer_id' => $customerId,
            'activity_data' => $activityData,
        ]);
    }

    /**
     * Get vehicle by VIN
     *
     * @param string $vin VIN number
     * @return array Vehicle information
     */
    public function getVehicleByVin(string $vin): array
    {
        return $this->call('user.get_vehicle_by_vin', [
            'vin' => $vin,
        ]);
    }

    /**
     * Update vehicle VIN verification status
     *
     * @param int $vehicleId Vehicle ID
     * @param bool $verified Verification status
     * @param array $verificationData Verification details
     * @return array Update result
     */
    public function updateVehicleVinVerificationStatus(int $vehicleId, bool $verified, array $verificationData): array
    {
        return $this->call('user.update_vehicle_vin_verification_status', [
            'vehicle_id' => $vehicleId,
            'verified' => $verified,
            'verification_data' => $verificationData,
        ]);
    }

    /**
     * Get customer preferences for VIN processing
     *
     * @param int $customerId Customer ID
     * @return array Customer VIN processing preferences
     */
    public function getCustomerVinProcessingPreferences(int $customerId): array
    {
        return $this->call('user.get_customer_vin_processing_preferences', [
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Update customer preferences for VIN processing
     *
     * @param int $customerId Customer ID
     * @param array $preferences VIN processing preferences
     * @return array Update result
     */
    public function updateCustomerVinProcessingPreferences(int $customerId, array $preferences): array
    {
        return $this->call('user.update_customer_vin_processing_preferences', [
            'customer_id' => $customerId,
            'preferences' => $preferences,
        ]);
    }

    /**
     * Get customer notification preferences for VIN processing
     *
     * @param int $customerId Customer ID
     * @return array Notification preferences
     */
    public function getCustomerVinNotificationPreferences(int $customerId): array
    {
        return $this->call('user.get_customer_vin_notification_preferences', [
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Create vehicle profile from VIN data
     *
     * @param int $customerId Customer ID
     * @param array $vinData VIN processing data
     * @return array Vehicle profile creation result
     */
    public function createVehicleProfileFromVinData(int $customerId, array $vinData): array
    {
        return $this->call('user.create_vehicle_profile_from_vin_data', [
            'customer_id' => $customerId,
            'vin_data' => $vinData,
        ]);
    }

    /**
     * Get batch customer information for VIN processing
     *
     * @param array $customerIds Array of customer IDs
     * @return array Batch customer information results
     */
    public function getBatchCustomersForVinProcessing(array $customerIds): array
    {
        $calls = [];
        foreach ($customerIds as $customerId) {
            $calls[] = [
                'method' => 'user.get_customer_for_vin_processing',
                'params' => ['customer_id' => $customerId],
                'id' => "customer_vin_processing_{$customerId}",
            ];
        }

        return $this->batchCall($calls);
    }

    /**
     * Validate batch customers for VIN OCR
     *
     * @param array $customerIds Array of customer IDs
     * @return array Batch validation results
     */
    public function validateBatchCustomersForVinOcr(array $customerIds): array
    {
        $calls = [];
        foreach ($customerIds as $customerId) {
            $calls[] = [
                'method' => 'user.validate_customer_for_vin_ocr',
                'params' => ['customer_id' => $customerId],
                'id' => "validate_customer_vin_{$customerId}",
            ];
        }

        return $this->batchCall($calls);
    }

    /**
     * Get customer VIN processing statistics
     *
     * @param int $customerId Customer ID
     * @return array Customer VIN processing statistics
     */
    public function getCustomerVinProcessingStatistics(int $customerId): array
    {
        return $this->call('user.get_customer_vin_processing_statistics', [
            'customer_id' => $customerId,
        ]);
    }
}

