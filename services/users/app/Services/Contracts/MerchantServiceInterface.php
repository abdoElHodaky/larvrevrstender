<?php

namespace App\Services\Contracts;

use App\Models\MerchantProfile;
use Illuminate\Support\Collection;

/**
 * Merchant Service Contract
 * 
 * Defines the interface for merchant management services
 */
interface MerchantServiceInterface
{
    /**
     * Create merchant profile
     */
    public function createMerchantProfile(int $userId, array $profileData): MerchantProfile;

    /**
     * Update merchant profile
     */
    public function updateMerchantProfile(int $merchantId, array $profileData): MerchantProfile;

    /**
     * Get merchant profile by ID
     */
    public function getMerchantProfile(int $merchantId): MerchantProfile;

    /**
     * Get merchant profile by user ID
     */
    public function getMerchantProfileByUserId(int $userId): ?MerchantProfile;

    /**
     * Update merchant verification status
     */
    public function updateVerificationStatus(int $merchantId, string $status, array $verificationData = []): array;

    /**
     * Get merchant verification status
     */
    public function getVerificationStatus(int $merchantId): array;

    /**
     * Update merchant business information
     */
    public function updateBusinessInfo(int $merchantId, array $businessData): array;

    /**
     * Get merchant business information
     */
    public function getBusinessInfo(int $merchantId): array;

    /**
     * Update merchant payment settings
     */
    public function updatePaymentSettings(int $merchantId, array $paymentSettings): array;

    /**
     * Get merchant payment settings
     */
    public function getPaymentSettings(int $merchantId): array;

    /**
     * Suspend merchant account
     */
    public function suspendMerchant(int $merchantId, string $reason = null): array;

    /**
     * Reactivate merchant account
     */
    public function reactivateMerchant(int $merchantId): array;
}
