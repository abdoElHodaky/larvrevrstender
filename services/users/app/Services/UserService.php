<?php

namespace App\Services;

use App\Events\UserProfileUpdated;
use App\Events\UserVerificationStatusChanged;
use App\Models\Address;
use App\Models\CustomerProfile;
use App\Models\MerchantProfile;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Shared\Core\BaseService;
use App\Services\Contracts\UserServiceInterface;

class UserService extends BaseService implements UserServiceInterface
{
    /**
     * Create or update user profile based on user type
     */
    public function createOrUpdateProfile(int $userId, string $userType, array $profileData): array
    {
        try {
            DB::beginTransaction();

            $profile = match ($userType) {
                'customer' => $this->createOrUpdateCustomerProfile($userId, $profileData),
                'merchant' => $this->createOrUpdateMerchantProfile($userId, $profileData),
                default => throw new \Exception('Invalid user type')
            };

            DB::commit();

            // Clear profile cache
            Cache::forget("user_profile:{$userId}");

            Log::info('User profile created/updated', [
                'user_id' => $userId,
                'user_type' => $userType,
                'profile_id' => $profile->id,
            ]);

            return [
                'success' => true,
                'profile' => $profile,
                'message' => 'Profile updated successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Profile creation/update failed', [
                'user_id' => $userId,
                'user_type' => $userType,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get user profile with caching
     */
    public function getUserProfile(int $userId, string $userType): array
    {
        try {
            $cacheKey = "user_profile:{$userId}";

            $profile = Cache::remember($cacheKey, 3600, function () use ($userId, $userType) {
                return match ($userType) {
                    'customer' => CustomerProfile::where('user_id', $userId)->first(),
                    'merchant' => MerchantProfile::where('user_id', $userId)->first(),
                    default => null
                };
            });

            if (! $profile) {
                return [
                    'success' => false,
                    'message' => 'Profile not found',
                ];
            }

            // Load relationships
            $profile->load(['addresses', 'vehicles']);

            return [
                'success' => true,
                'profile' => $profile,
                'verification_status' => $profile->verification_status ?? 'pending',
                'completion_percentage' => $this->calculateProfileCompletion($profile),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get user profile', [
                'user_id' => $userId,
                'user_type' => $userType,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve profile',
            ];
        }
    }

    /**
     * Update user preferences
     */
    public function updateUserPreferences(int $userId, string $userType, array $preferences): array
    {
        try {
            $profile = $this->getProfileByUserType($userId, $userType);

            if (! $profile) {
                return [
                    'success' => false,
                    'message' => 'Profile not found',
                ];
            }

            // Validate preferences
            $validatedPreferences = $this->validatePreferences($preferences);

            // Update preferences
            $currentPreferences = $profile->preferences ?? [];
            $updatedPreferences = array_merge($currentPreferences, $validatedPreferences);

            $profile->update(['preferences' => $updatedPreferences]);

            // Clear cache
            Cache::forget("user_profile:{$userId}");

            event(new UserProfileUpdated($profile));

            Log::info('User preferences updated', [
                'user_id' => $userId,
                'preferences' => array_keys($validatedPreferences),
            ]);

            return [
                'success' => true,
                'preferences' => $updatedPreferences,
                'message' => 'Preferences updated successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to update preferences', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add address to user profile
     */
    public function addUserAddress(int $userId, string $userType, array $addressData): array
    {
        try {
            DB::beginTransaction();

            $profile = $this->getProfileByUserType($userId, $userType);

            if (! $profile) {
                return [
                    'success' => false,
                    'message' => 'Profile not found',
                ];
            }

            // Validate address data
            $this->validateAddressData($addressData);

            // Create address
            $address = Address::create([
                'user_id' => $userId,
                'type' => $addressData['type'] ?? 'delivery',
                'label' => $addressData['label'] ?? 'Home',
                'street_address' => $addressData['street_address'],
                'city' => $addressData['city'],
                'state' => $addressData['state'] ?? null,
                'postal_code' => $addressData['postal_code'] ?? null,
                'country' => $addressData['country'] ?? 'SA',
                'latitude' => $addressData['latitude'] ?? null,
                'longitude' => $addressData['longitude'] ?? null,
                'is_default' => $addressData['is_default'] ?? false,
                'metadata' => $addressData['metadata'] ?? [],
            ]);

            // If this is set as default, unset other defaults
            if ($address->is_default) {
                Address::where('user_id', $userId)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            DB::commit();

            // Clear cache
            Cache::forget("user_profile:{$userId}");

            Log::info('Address added to user profile', [
                'user_id' => $userId,
                'address_id' => $address->id,
                'type' => $address->type,
            ]);

            return [
                'success' => true,
                'address' => $address,
                'message' => 'Address added successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to add address', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add vehicle to user profile
     */
    public function addUserVehicle(int $userId, array $vehicleData): array
    {
        try {
            DB::beginTransaction();

            $profile = CustomerProfile::where('user_id', $userId)->first();

            if (! $profile) {
                return [
                    'success' => false,
                    'message' => 'Customer profile not found',
                ];
            }

            // Validate vehicle data
            $this->validateVehicleData($vehicleData);

            // Create vehicle
            $vehicle = Vehicle::create([
                'customer_id' => $profile->id,
                'vin' => $vehicleData['vin'],
                'make' => $vehicleData['make'],
                'model' => $vehicleData['model'],
                'year' => $vehicleData['year'],
                'color' => $vehicleData['color'] ?? null,
                'license_plate' => $vehicleData['license_plate'] ?? null,
                'engine_type' => $vehicleData['engine_type'] ?? null,
                'transmission' => $vehicleData['transmission'] ?? null,
                'mileage' => $vehicleData['mileage'] ?? null,
                'is_primary' => $vehicleData['is_primary'] ?? false,
                'metadata' => $vehicleData['metadata'] ?? [],
            ]);

            // If this is set as primary, unset other primary vehicles
            if ($vehicle->is_primary) {
                Vehicle::where('customer_id', $profile->id)
                    ->where('id', '!=', $vehicle->id)
                    ->update(['is_primary' => false]);
            }

            DB::commit();

            // Clear cache
            Cache::forget("user_profile:{$userId}");

            Log::info('Vehicle added to user profile', [
                'user_id' => $userId,
                'vehicle_id' => $vehicle->id,
                'vin' => $vehicle->vin,
            ]);

            return [
                'success' => true,
                'vehicle' => $vehicle,
                'message' => 'Vehicle added successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to add vehicle', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Submit KYC verification documents
     */
    public function submitKYCVerification(int $userId, string $userType, array $documents): array
    {
        try {
            DB::beginTransaction();

            $profile = $this->getProfileByUserType($userId, $userType);

            if (! $profile) {
                return [
                    'success' => false,
                    'message' => 'Profile not found',
                ];
            }

            // Validate documents
            $this->validateKYCDocuments($documents);

            // Update profile with verification documents
            $profile->update([
                'verification_documents' => $documents,
                'verification_status' => 'pending',
                'verification_submitted_at' => now(),
            ]);

            DB::commit();

            // Clear cache
            Cache::forget("user_profile:{$userId}");

            event(new UserVerificationStatusChanged($profile, 'pending'));

            Log::info('KYC verification submitted', [
                'user_id' => $userId,
                'user_type' => $userType,
                'documents_count' => count($documents),
            ]);

            return [
                'success' => true,
                'verification_status' => 'pending',
                'message' => 'KYC verification submitted successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('KYC verification submission failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update verification status (admin function)
     */
    public function updateVerificationStatus(int $userId, string $userType, string $status, ?string $notes = null): array
    {
        try {
            $profile = $this->getProfileByUserType($userId, $userType);

            if (! $profile) {
                return [
                    'success' => false,
                    'message' => 'Profile not found',
                ];
            }

            $validStatuses = ['pending', 'approved', 'rejected', 'requires_review'];
            if (! in_array($status, $validStatuses)) {
                return [
                    'success' => false,
                    'message' => 'Invalid verification status',
                ];
            }

            $profile->update([
                'verification_status' => $status,
                'verification_notes' => $notes,
                'verification_updated_at' => now(),
            ]);

            // Clear cache
            Cache::forget("user_profile:{$userId}");

            event(new UserVerificationStatusChanged($profile, $status));

            Log::info('Verification status updated', [
                'user_id' => $userId,
                'user_type' => $userType,
                'status' => $status,
                'notes' => $notes,
            ]);

            return [
                'success' => true,
                'verification_status' => $status,
                'message' => 'Verification status updated successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Failed to update verification status', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get user activity summary
     */
    public function getUserActivitySummary(int $userId): array
    {
        try {
            $cacheKey = "user_activity:{$userId}";

            $activity = Cache::remember($cacheKey, 1800, function () {
                // This would integrate with other services to get activity data
                return [
                    'orders_count' => 0, // Would call Order Service
                    'bids_count' => 0,   // Would call Bidding Service
                    'last_login' => null, // Would get from Auth Service
                    'profile_completion' => 0,
                    'verification_status' => 'pending',
                ];
            });

            return [
                'success' => true,
                'activity' => $activity,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get user activity', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve activity summary',
            ];
        }
    }

    /**
     * Search users by criteria
     */
    public function searchUsers(array $criteria): array
    {
        try {
            $query = CustomerProfile::query();

            // Apply filters
            if (isset($criteria['verification_status'])) {
                $query->where('verification_status', $criteria['verification_status']);
            }

            if (isset($criteria['city'])) {
                $query->whereHas('addresses', function ($q) use ($criteria) {
                    $q->where('city', $criteria['city']);
                });
            }

            if (isset($criteria['industry'])) {
                $query->where('industry', $criteria['industry']);
            }

            if (isset($criteria['company_size'])) {
                $query->where('company_size', $criteria['company_size']);
            }

            // Pagination
            $page = $criteria['page'] ?? 1;
            $perPage = min($criteria['per_page'] ?? 20, 100);

            $results = $query->with(['addresses', 'vehicles'])
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'success' => true,
                'users' => $results->items(),
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('User search failed', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Search failed',
            ];
        }
    }

    /**
     * Create or update customer profile
     */
    private function createOrUpdateCustomerProfile(int $userId, array $profileData): CustomerProfile
    {
        return CustomerProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'national_id' => $profileData['national_id'] ?? null,
                'national_address' => $profileData['national_address'] ?? null,
                'date_of_birth' => $profileData['date_of_birth'] ?? null,
                'gender' => $profileData['gender'] ?? null,
                'occupation' => $profileData['occupation'] ?? null,
                'company_name' => $profileData['company_name'] ?? null,
                'industry' => $profileData['industry'] ?? null,
                'company_size' => $profileData['company_size'] ?? null,
                'annual_revenue' => $profileData['annual_revenue'] ?? null,
                'default_location' => $profileData['default_location'] ?? null,
                'preferences' => $profileData['preferences'] ?? $this->getDefaultCustomerPreferences(),
                'verification_status' => $profileData['verification_status'] ?? 'pending',
                'metadata' => $profileData['metadata'] ?? [],
            ]
        );
    }

    /**
     * Create or update merchant profile
     */
    private function createOrUpdateMerchantProfile(int $userId, array $profileData): MerchantProfile
    {
        return MerchantProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'business_name' => $profileData['business_name'] ?? null,
                'business_type' => $profileData['business_type'] ?? null,
                'commercial_registration' => $profileData['commercial_registration'] ?? null,
                'tax_number' => $profileData['tax_number'] ?? null,
                'business_address' => $profileData['business_address'] ?? null,
                'contact_person' => $profileData['contact_person'] ?? null,
                'contact_phone' => $profileData['contact_phone'] ?? null,
                'contact_email' => $profileData['contact_email'] ?? null,
                'business_description' => $profileData['business_description'] ?? null,
                'service_areas' => $profileData['service_areas'] ?? [],
                'specializations' => $profileData['specializations'] ?? [],
                'certifications' => $profileData['certifications'] ?? [],
                'operating_hours' => $profileData['operating_hours'] ?? [],
                'payment_terms' => $profileData['payment_terms'] ?? null,
                'minimum_order_value' => $profileData['minimum_order_value'] ?? null,
                'delivery_options' => $profileData['delivery_options'] ?? [],
                'preferences' => $profileData['preferences'] ?? $this->getDefaultMerchantPreferences(),
                'verification_status' => $profileData['verification_status'] ?? 'pending',
                'metadata' => $profileData['metadata'] ?? [],
            ]
        );
    }

    /**
     * Get profile by user type
     */
    private function getProfileByUserType(int $userId, string $userType)
    {
        return match ($userType) {
            'customer' => CustomerProfile::where('user_id', $userId)->first(),
            'merchant' => MerchantProfile::where('user_id', $userId)->first(),
            default => null
        };
    }

    /**
     * Calculate profile completion percentage
     */
    private function calculateProfileCompletion($profile): int
    {
        if (! $profile) {
            return 0;
        }

        $requiredFields = match (get_class($profile)) {
            CustomerProfile::class => [
                'national_id', 'national_address', 'date_of_birth',
                'occupation', 'default_location',
            ],
            MerchantProfile::class => [
                'business_name', 'business_type', 'commercial_registration',
                'tax_number', 'business_address', 'contact_person',
            ],
            default => []
        };

        $completedFields = 0;
        foreach ($requiredFields as $field) {
            if (! empty($profile->$field)) {
                $completedFields++;
            }
        }

        return count($requiredFields) > 0
            ? round(($completedFields / count($requiredFields)) * 100)
            : 0;
    }

    /**
     * Validate preferences
     */
    private function validatePreferences(array $preferences): array
    {
        $validPreferences = [];
        $allowedKeys = [
            'language', 'currency', 'timezone', 'notifications',
            'email_updates', 'sms_updates', 'push_notifications',
            'marketing_consent', 'data_sharing_consent',
        ];

        foreach ($preferences as $key => $value) {
            if (in_array($key, $allowedKeys)) {
                $validPreferences[$key] = $value;
            }
        }

        return $validPreferences;
    }

    /**
     * Validate address data
     */
    private function validateAddressData(array $addressData): void
    {
        $required = ['street_address', 'city'];

        foreach ($required as $field) {
            if (empty($addressData[$field])) {
                throw new \Exception("Field {$field} is required");
            }
        }

        if (isset($addressData['type']) && ! in_array($addressData['type'], ['delivery', 'billing', 'business'])) {
            throw new \Exception('Invalid address type');
        }
    }

    /**
     * Validate vehicle data
     */
    private function validateVehicleData(array $vehicleData): void
    {
        $required = ['vin', 'make', 'model', 'year'];

        foreach ($required as $field) {
            if (empty($vehicleData[$field])) {
                throw new \Exception("Field {$field} is required");
            }
        }

        // Validate VIN format (basic validation)
        if (! preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vehicleData['vin'])) {
            throw new \Exception('Invalid VIN format');
        }

        // Validate year
        $currentYear = date('Y');
        if ($vehicleData['year'] < 1900 || $vehicleData['year'] > $currentYear + 1) {
            throw new \Exception('Invalid vehicle year');
        }
    }

    /**
     * Validate KYC documents
     */
    private function validateKYCDocuments(array $documents): void
    {
        $requiredDocuments = ['national_id', 'proof_of_address'];

        foreach ($requiredDocuments as $docType) {
            if (! isset($documents[$docType])) {
                throw new \Exception("Document {$docType} is required");
            }

            if (empty($documents[$docType]['file_path'])) {
                throw new \Exception("File path for {$docType} is required");
            }
        }
    }

    /**
     * Get default customer preferences
     */
    private function getDefaultCustomerPreferences(): array
    {
        return [
            'language' => 'ar',
            'currency' => 'SAR',
            'timezone' => 'Asia/Riyadh',
            'notifications' => true,
            'email_updates' => true,
            'sms_updates' => false,
            'push_notifications' => true,
            'marketing_consent' => false,
            'data_sharing_consent' => false,
        ];
    }

    /**
     * Get default merchant preferences
     */
    private function getDefaultMerchantPreferences(): array
    {
        return [
            'language' => 'ar',
            'currency' => 'SAR',
            'timezone' => 'Asia/Riyadh',
            'notifications' => true,
            'email_updates' => true,
            'sms_updates' => true,
            'push_notifications' => true,
            'order_notifications' => true,
            'bid_notifications' => true,
            'payment_notifications' => true,
            'marketing_consent' => false,
            'data_sharing_consent' => false,
        ];
    }
}
