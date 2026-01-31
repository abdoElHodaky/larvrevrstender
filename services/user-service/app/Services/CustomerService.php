<?php

namespace App\Services;

use App\Models\CustomerProfile;
use App\Events\CustomerProfileUpdated;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class CustomerService
{
    /**
     * Get customer profile by user ID.
     */
    public function getProfile(int $userId): CustomerProfile
    {
        return CustomerProfile::where('user_id', $userId)->firstOrFail();
    }

    /**
     * Create customer profile.
     */
    public function createProfile(array $data): CustomerProfile
    {
        $profile = CustomerProfile::create($data);
        
        event(new CustomerProfileUpdated($profile));
        
        return $profile;
    }

    /**
     * Update customer profile.
     */
    public function updateProfile(int $userId, array $data): CustomerProfile
    {
        $profile = $this->getProfile($userId);
        $profile->update($data);
        
        event(new CustomerProfileUpdated($profile));
        
        return $profile->fresh();
    }

    /**
     * Update customer preferences.
     */
    public function updatePreferences(int $userId, array $preferences): CustomerProfile
    {
        $profile = $this->getProfile($userId);
        $profile->updatePreferences($preferences);
        
        event(new CustomerProfileUpdated($profile));
        
        return $profile->fresh();
    }

    /**
     * Set default location for customer.
     */
    public function setDefaultLocation(int $userId, array $location): CustomerProfile
    {
        $profile = $this->getProfile($userId);
        
        $profile->update(['default_location' => $location]);
        
        event(new CustomerProfileUpdated($profile));
        
        return $profile->fresh();
    }

    /**
     * Update national ID for ZATCA compliance.
     */
    public function updateNationalId(int $userId, string $nationalId): CustomerProfile
    {
        $profile = $this->getProfile($userId);
        
        $profile->update(['national_id' => $nationalId]);
        
        event(new CustomerProfileUpdated($profile));
        
        return $profile->fresh();
    }

    /**
     * Update national address for ZATCA compliance.
     */
    public function updateNationalAddress(int $userId, string $nationalAddress): CustomerProfile
    {
        $profile = $this->getProfile($userId);
        
        $profile->update(['national_address' => $nationalAddress]);
        
        event(new CustomerProfileUpdated($profile));
        
        return $profile->fresh();
    }

    /**
     * Check if customer profile exists.
     */
    public function profileExists(int $userId): bool
    {
        return CustomerProfile::where('user_id', $userId)->exists();
    }

    /**
     * Get customer with vehicles.
     */
    public function getCustomerWithVehicles(int $userId): CustomerProfile
    {
        return CustomerProfile::with(['vehicles.brand', 'vehicles.vehicleModel', 'vehicles.trim'])
                             ->where('user_id', $userId)
                             ->firstOrFail();
    }

    /**
     * Get customer's primary vehicle.
     */
    public function getPrimaryVehicle(int $userId): ?object
    {
        $profile = $this->getProfile($userId);
        return $profile->primaryVehicle;
    }

    /**
     * Validate customer for ZATCA compliance.
     */
    public function validateForZATCA(int $userId): array
    {
        $profile = $this->getProfile($userId);
        
        $errors = [];
        
        if (!$profile->hasValidNationalId()) {
            $errors[] = 'Valid national ID is required for ZATCA compliance';
        }
        
        if (!$profile->national_address) {
            $errors[] = 'National address is required for ZATCA compliance';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get customers by location proximity.
     */
    public function getCustomersByLocation(float $latitude, float $longitude, float $radiusKm = 50): Collection
    {
        // This would typically use a spatial database query
        // For now, we'll return customers with default locations
        return CustomerProfile::whereNotNull('default_location')->get()
                             ->filter(function ($customer) use ($latitude, $longitude, $radiusKm) {
                                 $coords = $customer->getLocationCoordinates();
                                 if (!$coords) {
                                     return false;
                                 }
                                 
                                 // Simple distance calculation (Haversine formula would be more accurate)
                                 $distance = sqrt(
                                     pow($coords['latitude'] - $latitude, 2) + 
                                     pow($coords['longitude'] - $longitude, 2)
                                 ) * 111; // Rough km conversion
                                 
                                 return $distance <= $radiusKm;
                             });
    }

    /**
     * Create profile from user registration.
     */
    public function createProfileFromRegistration(int $userId, array $userData = []): CustomerProfile
    {
        $defaultData = [
            'user_id' => $userId,
            'preferences' => [
                'notifications' => true,
                'email_updates' => true,
                'sms_updates' => false,
                'language' => 'ar', // Arabic default for Saudi market
                'currency' => 'SAR'
            ]
        ];

        $profileData = array_merge($defaultData, $userData);

        return $this->createProfile($profileData);
    }

    /**
     * Delete customer profile.
     */
    public function deleteProfile(int $userId): bool
    {
        $profile = $this->getProfile($userId);
        
        // Delete associated vehicles first
        $profile->vehicles()->delete();
        
        return $profile->delete();
    }
}

