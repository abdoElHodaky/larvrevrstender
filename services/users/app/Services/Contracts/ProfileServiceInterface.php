<?php

namespace App\Services\Contracts;

/**
 * Profile Service Contract
 * 
 * Defines the interface for profile management services
 */
interface ProfileServiceInterface
{
    /**
     * Get user profile data
     */
    public function getUserProfileData(int $userId): array;

    /**
     * Update user profile data
     */
    public function updateUserProfileData(int $userId, array $profileData): array;

    /**
     * Get profile completion status
     */
    public function getProfileCompletionStatus(int $userId): array;

    /**
     * Validate profile data
     */
    public function validateProfileData(array $profileData, string $userType): array;

    /**
     * Upload profile image
     */
    public function uploadProfileImage(int $userId, $imageFile): array;

    /**
     * Delete profile image
     */
    public function deleteProfileImage(int $userId): array;

    /**
     * Get profile activity history
     */
    public function getProfileActivityHistory(int $userId): array;

    /**
     * Update profile privacy settings
     */
    public function updatePrivacySettings(int $userId, array $privacySettings): array;

    /**
     * Get profile privacy settings
     */
    public function getPrivacySettings(int $userId): array;
}
