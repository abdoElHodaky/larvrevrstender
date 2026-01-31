<?php

namespace App\Services;

use App\Models\CustomerProfile;
use App\Events\UserProfileUpdated;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserProfileService
{
    /**
     * Get user profile by user ID
     */
    public function getProfile(int $userId): CustomerProfile
    {
        return CustomerProfile::where('user_id', $userId)->firstOrFail();
    }

    /**
     * Create or update user profile
     */
    public function createOrUpdateProfile(int $userId, array $data): CustomerProfile
    {
        $profile = CustomerProfile::updateOrCreate(
            ['user_id' => $userId],
            $data
        );

        return $profile->fresh();
    }

    /**
     * Add delivery address to profile
     */
    public function addDeliveryAddress(int $userId, array $addressData): CustomerProfile
    {
        $profile = $this->getProfile($userId);
        $profile->addDeliveryAddress($addressData);
        
        return $profile->fresh();
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(int $userId, array $preferences): CustomerProfile
    {
        $profile = $this->getProfile($userId);
        $profile->updatePreferences($preferences);
        
        event(new UserProfileUpdated($profile));
        
        return $profile->fresh();
    }

    /**
     * Submit KYC verification documents
     */
    public function submitKYCVerification(int $userId, array $documents): CustomerProfile
    {
        $profile = $this->getProfile($userId);
        
        $profile->update([
            'verification_documents' => $documents,
            'verification_status' => CustomerProfile::STATUS_PENDING
        ]);
        
        return $profile->fresh();
    }

    /**
     * Update verification status (admin function)
     */
    public function updateVerificationStatus(int $userId, string $status): CustomerProfile
    {
        $profile = $this->getProfile($userId);
        
        $profile->update([
            'verification_status' => $status
        ]);
        
        return $profile->fresh();
    }

    /**
     * Add preferred category
     */
    public function addPreferredCategory(int $userId, string $category): CustomerProfile
    {
        $profile = $this->getProfile($userId);
        $profile->addPreferredCategory($category);
        
        return $profile->fresh();
    }

    /**
     * Remove preferred category
     */
    public function removePreferredCategory(int $userId, string $category): CustomerProfile
    {
        $profile = $this->getProfile($userId);
        $profile->removePreferredCategory($category);
        
        return $profile->fresh();
    }

    /**
     * Check if user profile exists
     */
    public function profileExists(int $userId): bool
    {
        return CustomerProfile::where('user_id', $userId)->exists();
    }

    /**
     * Get profiles by verification status
     */
    public function getProfilesByVerificationStatus(string $status): \Illuminate\Database\Eloquent\Collection
    {
        return CustomerProfile::where('verification_status', $status)->get();
    }

    /**
     * Get profiles by industry
     */
    public function getProfilesByIndustry(string $industry): \Illuminate\Database\Eloquent\Collection
    {
        return CustomerProfile::byIndustry($industry)->get();
    }

    /**
     * Get profiles by company size
     */
    public function getProfilesByCompanySize(string $size): \Illuminate\Database\Eloquent\Collection
    {
        return CustomerProfile::byCompanySize($size)->get();
    }

    /**
     * Get verified profiles
     */
    public function getVerifiedProfiles(): \Illuminate\Database\Eloquent\Collection
    {
        return CustomerProfile::verified()->get();
    }

    /**
     * Create profile from user registration event
     */
    public function createProfileFromUserRegistration(int $userId, array $userData = []): CustomerProfile
    {
        $defaultData = [
            'user_id' => $userId,
            'verification_status' => CustomerProfile::STATUS_PENDING,
            'preferences' => [
                'notifications' => true,
                'email_updates' => true,
                'sms_updates' => false,
                'language' => 'en',
                'currency' => 'SAR'
            ]
        ];

        $profileData = array_merge($defaultData, $userData);

        return CustomerProfile::create($profileData);
    }
}

