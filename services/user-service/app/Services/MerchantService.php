<?php

namespace App\Services;

use App\Models\MerchantProfile;
use App\Events\MerchantProfileUpdated;
use App\Events\MerchantVerificationStatusChanged;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class MerchantService
{
    /**
     * Get merchant profile by user ID.
     */
    public function getProfile(int $userId): MerchantProfile
    {
        return MerchantProfile::where('user_id', $userId)->firstOrFail();
    }

    /**
     * Create merchant profile.
     */
    public function createProfile(array $data): MerchantProfile
    {
        $profile = MerchantProfile::create($data);
        
        event(new MerchantProfileUpdated($profile));
        
        return $profile;
    }

    /**
     * Update merchant profile.
     */
    public function updateProfile(int $userId, array $data): MerchantProfile
    {
        $profile = $this->getProfile($userId);
        $profile->update($data);
        
        event(new MerchantProfileUpdated($profile));
        
        return $profile->fresh();
    }

    /**
     * Update merchant verification status.
     */
    public function updateVerificationStatus(int $userId, bool $verified, array $documents = []): MerchantProfile
    {
        $profile = $this->getProfile($userId);
        
        $oldStatus = $profile->verified;
        
        $profile->update([
            'verified' => $verified,
            'verification_documents' => $documents ?: $profile->verification_documents
        ]);
        
        if ($oldStatus !== $verified) {
            event(new MerchantVerificationStatusChanged($profile, $oldStatus, $verified));
        }
        
        return $profile->fresh();
    }

    /**
     * Add specialization to merchant.
     */
    public function addSpecialization(int $userId, string $specialization): MerchantProfile
    {
        $profile = $this->getProfile($userId);
        $profile->addSpecialization($specialization);
        
        event(new MerchantProfileUpdated($profile));
        
        return $profile->fresh();
    }

    /**
     * Add service area to merchant.
     */
    public function addServiceArea(int $userId, string $area): MerchantProfile
    {
        $profile = $this->getProfile($userId);
        $profile->addServiceArea($area);
        
        event(new MerchantProfileUpdated($profile));
        
        return $profile->fresh();
    }

    /**
     * Update business hours.
     */
    public function updateBusinessHours(int $userId, array $businessHours): MerchantProfile
    {
        $profile = $this->getProfile($userId);
        
        $profile->update(['business_hours' => $businessHours]);
        
        event(new MerchantProfileUpdated($profile));
        
        return $profile->fresh();
    }

    /**
     * Update merchant rating.
     */
    public function addReview(int $userId, float $rating): MerchantProfile
    {
        $profile = $this->getProfile($userId);
        $profile->updateRating($rating);
        
        event(new MerchantProfileUpdated($profile));
        
        return $profile->fresh();
    }

    /**
     * Get verified merchants.
     */
    public function getVerifiedMerchants(): Collection
    {
        return MerchantProfile::verified()->get();
    }

    /**
     * Get merchants by specialization.
     */
    public function getMerchantsBySpecialization(string $specialization): Collection
    {
        return MerchantProfile::bySpecialization($specialization)->get();
    }

    /**
     * Get merchants by minimum rating.
     */
    public function getMerchantsByRating(float $minRating): Collection
    {
        return MerchantProfile::byMinRating($minRating)->get();
    }

    /**
     * Get merchants serving specific area.
     */
    public function getMerchantsServingArea(string $area): Collection
    {
        return MerchantProfile::servingArea($area)->get();
    }

    /**
     * Search merchants with filters.
     */
    public function searchMerchants(array $filters): Collection
    {
        $query = MerchantProfile::query();
        
        if (isset($filters['verified']) && $filters['verified']) {
            $query->verified();
        }
        
        if (isset($filters['specialization'])) {
            $query->bySpecialization($filters['specialization']);
        }
        
        if (isset($filters['min_rating'])) {
            $query->byMinRating($filters['min_rating']);
        }
        
        if (isset($filters['service_area'])) {
            $query->servingArea($filters['service_area']);
        }
        
        if (isset($filters['business_name'])) {
            $query->where('business_name', 'like', '%' . $filters['business_name'] . '%');
        }
        
        return $query->get();
    }

    /**
     * Check if merchant profile exists.
     */
    public function profileExists(int $userId): bool
    {
        return MerchantProfile::where('user_id', $userId)->exists();
    }

    /**
     * Validate merchant for ZATCA compliance.
     */
    public function validateForZATCA(int $userId): array
    {
        $profile = $this->getProfile($userId);
        
        $errors = [];
        
        if (!$profile->hasValidTaxNumber()) {
            $errors[] = 'Valid tax number is required for ZATCA compliance';
        }
        
        if (!$profile->business_name) {
            $errors[] = 'Business name is required for ZATCA compliance';
        }
        
        if (!$profile->verified) {
            $errors[] = 'Merchant must be verified for ZATCA compliance';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Check if merchant is available at specific time.
     */
    public function isAvailableAt(int $userId, string $day, string $time): bool
    {
        $profile = $this->getProfile($userId);
        return $profile->isOpenAt($day, $time);
    }

    /**
     * Get merchant statistics.
     */
    public function getMerchantStats(int $userId): array
    {
        $profile = $this->getProfile($userId);
        
        return [
            'rating' => $profile->rating,
            'total_reviews' => $profile->total_reviews,
            'verified' => $profile->verified,
            'specializations_count' => count($profile->specializations ?? []),
            'service_areas_count' => count($profile->service_areas ?? []),
            'has_business_hours' => !empty($profile->business_hours),
            'zatca_compliant' => $this->validateForZATCA($userId)['valid']
        ];
    }

    /**
     * Create profile from merchant registration.
     */
    public function createProfileFromRegistration(int $userId, array $merchantData): MerchantProfile
    {
        $defaultData = [
            'user_id' => $userId,
            'rating' => 0.00,
            'total_reviews' => 0,
            'verified' => false,
            'specializations' => [],
            'service_areas' => [],
        ];

        $profileData = array_merge($defaultData, $merchantData);

        return $this->createProfile($profileData);
    }

    /**
     * Delete merchant profile.
     */
    public function deleteProfile(int $userId): bool
    {
        $profile = $this->getProfile($userId);
        return $profile->delete();
    }
}

