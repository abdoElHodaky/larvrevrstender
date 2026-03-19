<?php

namespace App\Services\Contracts;

/**
 * User Service Contract
 * 
 * Defines the interface for user management services
 */
interface UserServiceInterface
{
    /**
     * Create or update user profile based on user type
     */
    public function createOrUpdateProfile(int $userId, string $userType, array $profileData): array;

    /**
     * Get user profile by user ID
     */
    public function getUserProfile(int $userId): array;

    /**
     * Update user verification status
     */
    public function updateVerificationStatus(int $userId, string $status, array $verificationData = []): array;

    /**
     * Get user verification status
     */
    public function getVerificationStatus(int $userId): array;

    /**
     * Update user preferences
     */
    public function updateUserPreferences(int $userId, array $preferences): array;

    /**
     * Get user preferences
     */
    public function getUserPreferences(int $userId): array;

    /**
     * Deactivate user account
     */
    public function deactivateUser(int $userId, string $reason = null): array;

    /**
     * Reactivate user account
     */
    public function reactivateUser(int $userId): array;
}
