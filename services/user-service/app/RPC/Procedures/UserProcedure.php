<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\KycService;
use App\Services\ProfileService;
use App\Services\UserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Sajya\Server\Exceptions\RuntimeException;

class UserProcedure extends BaseProcedure
{
    public function __construct(
        private UserService $userService,
        private ProfileService $profileService,
        private KycService $kycService
    ) {}

    /**
     * Create a runtime exception conditionally based on Sajya availability
     */
    private function createRuntimeException(string $message, int $code = -32603, array $data = []): \Exception
    {
        if (class_exists('Sajya\Server\Exceptions\RuntimeException')) {
            return new \Sajya\Server\Exceptions\RuntimeException($message, $code, $data);
        }

        return new \Exception($message, $code);
    }

    /**
     * Create new user
     */
    public function create(array $params): array
    {
        $this->validate($params, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'phone' => 'sometimes|string|max:20',
            'role' => 'sometimes|string|in:buyer,seller,admin',
            'company_name' => 'sometimes|string|max:255',
            'address' => 'sometimes|array',
            'preferences' => 'sometimes|array',
        ]);

        return $this->executeWithLogging('User@create', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for user creation
            $key = 'user_create:'.request()->ip();
            if (RateLimiter::tooManyAttempts($key, 10)) {
                throw new RuntimeException(
                    'Too many user creation attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            try {
                $user = $this->userService->createUser([
                    'name' => $params['name'],
                    'email' => $params['email'],
                    'phone' => $params['phone'] ?? null,
                    'role' => $params['role'] ?? 'buyer',
                    'company_name' => $params['company_name'] ?? null,
                    'address' => $params['address'] ?? null,
                    'preferences' => $params['preferences'] ?? [],
                ]);

                // Clear rate limiting on successful creation
                RateLimiter::clear($key);

                return [
                    'success' => true,
                    'user' => $user,
                    'created_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                // Increment rate limiting on failed creation
                RateLimiter::hit($key, 300); // 5 minutes

                throw new RuntimeException(
                    'User creation failed: '.$e->getMessage(),
                    -32001,
                    ['email' => $params['email']]
                );
            }
        });
    }

    /**
     * Get user by ID
     */
    public function getById(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'include_preferences' => 'sometimes|boolean',
            'include_statistics' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('User@getById', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'user:'.$params['user_id'];
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }

            try {
                $user = $this->userService->getUserById(
                    $params['user_id'],
                    $params['include_preferences'] ?? false,
                    $params['include_statistics'] ?? false
                );

                if (! $user) {
                    throw new RuntimeException(
                        'User not found',
                        -32001,
                        ['user_id' => $params['user_id']]
                    );
                }

                $result = [
                    'success' => true,
                    'user' => $user,
                    'retrieved_at' => now()->toISOString(),
                ];

                // Cache for 5 minutes
                Cache::put($cacheKey, $result, 300);

                return $result;

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve user: '.$e->getMessage(),
                    -32001,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Update user information
     */
    public function update(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'company_name' => 'sometimes|string|max:255',
            'address' => 'sometimes|array',
            'preferences' => 'sometimes|array',
            'status' => 'sometimes|string|in:active,inactive,suspended',
        ]);

        return $this->executeWithLogging('User@update', $this->sanitizeForLogging($params), function () use ($params) {
            try {
                $user = $this->userService->updateUser($params['user_id'], [
                    'name' => $params['name'] ?? null,
                    'phone' => $params['phone'] ?? null,
                    'company_name' => $params['company_name'] ?? null,
                    'address' => $params['address'] ?? null,
                    'preferences' => $params['preferences'] ?? null,
                    'status' => $params['status'] ?? null,
                ]);

                // Clear cache
                Cache::forget('user:'.$params['user_id']);

                return [
                    'success' => true,
                    'user' => $user,
                    'updated_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'User update failed: '.$e->getMessage(),
                    -32002,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Get user profile with statistics
     */
    public function getProfile(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('User@getProfile', $params, function () use ($params) {
            try {
                $profile = $this->userService->getUserProfile($params['user_id']);

                return [
                    'success' => true,
                    'profile' => $profile,
                    'retrieved_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve user profile: '.$e->getMessage(),
                    -32001,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Search users with filters
     */
    public function search(array $params): array
    {
        $this->validate($params, [
            'query' => 'sometimes|string|max:255',
            'role' => 'sometimes|string|in:buyer,seller,admin',
            'status' => 'sometimes|string|in:active,inactive,suspended',
            'company_name' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort_by' => 'sometimes|string|in:name,email,created_at,updated_at',
            'sort_order' => 'sometimes|string|in:asc,desc',
        ]);

        return $this->executeWithLogging('User@search', $params, function () use ($params) {
            try {
                $results = $this->userService->searchUsers([
                    'query' => $params['query'] ?? null,
                    'role' => $params['role'] ?? null,
                    'status' => $params['status'] ?? null,
                    'company_name' => $params['company_name'] ?? null,
                    'location' => $params['location'] ?? null,
                    'page' => $params['page'] ?? 1,
                    'per_page' => $params['per_page'] ?? 20,
                    'sort_by' => $params['sort_by'] ?? 'created_at',
                    'sort_order' => $params['sort_order'] ?? 'desc',
                ]);

                return [
                    'success' => true,
                    'users' => $results['data'],
                    'pagination' => $results['pagination'],
                    'searched_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'User search failed: '.$e->getMessage(),
                    -32003,
                    ['search_params' => $params]
                );
            }
        });
    }

    /**
     * Get user statistics
     */
    public function getStatistics(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'period' => 'sometimes|string|in:week,month,quarter,year',
        ]);

        return $this->executeWithLogging('User@getStatistics', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'user_stats:'.$params['user_id'].':'.($params['period'] ?? 'month');
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }

            try {
                $statistics = $this->userService->getUserStatistics(
                    $params['user_id'],
                    $params['period'] ?? 'month'
                );

                $result = [
                    'success' => true,
                    'statistics' => $statistics,
                    'period' => $params['period'] ?? 'month',
                    'retrieved_at' => now()->toISOString(),
                ];

                // Cache for 1 hour
                Cache::put($cacheKey, $result, 3600);

                return $result;

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve user statistics: '.$e->getMessage(),
                    -32001,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'preferences' => 'required|array',
            'preferences.notifications' => 'sometimes|array',
            'preferences.language' => 'sometimes|string|max:5',
            'preferences.timezone' => 'sometimes|string|max:50',
            'preferences.currency' => 'sometimes|string|max:3',
        ]);

        return $this->executeWithLogging('User@updatePreferences', $params, function () use ($params) {
            try {
                $user = $this->userService->updateUserPreferences(
                    $params['user_id'],
                    $params['preferences']
                );

                // Clear cache
                Cache::forget('user:'.$params['user_id']);

                return [
                    'success' => true,
                    'preferences' => $user['preferences'],
                    'updated_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to update user preferences: '.$e->getMessage(),
                    -32002,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Deactivate user account
     */
    public function deactivate(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'reason' => 'sometimes|string|max:500',
        ]);

        return $this->executeWithLogging('User@deactivate', $params, function () use ($params) {
            try {
                $result = $this->userService->deactivateUser(
                    $params['user_id'],
                    $params['reason'] ?? null
                );

                // Clear cache
                Cache::forget('user:'.$params['user_id']);

                return [
                    'success' => true,
                    'message' => 'User account deactivated successfully',
                    'deactivated_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to deactivate user: '.$e->getMessage(),
                    -32002,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Reactivate user account
     */
    public function reactivate(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('User@reactivate', $params, function () use ($params) {
            try {
                $result = $this->userService->reactivateUser($params['user_id']);

                // Clear cache
                Cache::forget('user:'.$params['user_id']);

                return [
                    'success' => true,
                    'message' => 'User account reactivated successfully',
                    'reactivated_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to reactivate user: '.$e->getMessage(),
                    -32002,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    // ========================================
    // ENHANCED USER MANAGEMENT METHODS
    // ========================================

    /**
     * Create or update user profile
     */
    public function createOrUpdateProfile(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'profile_data' => 'required|array',
            'profile_data.first_name' => 'sometimes|string|max:100',
            'profile_data.last_name' => 'sometimes|string|max:100',
            'profile_data.phone' => 'sometimes|string|max:20',
            'profile_data.date_of_birth' => 'sometimes|date',
            'profile_data.gender' => 'sometimes|string|in:male,female,other',
            'profile_data.nationality' => 'sometimes|string|max:100',
            'profile_data.id_number' => 'sometimes|string|max:50',
            'profile_data.id_type' => 'sometimes|string|in:national_id,passport,iqama',
            'profile_data.company_name' => 'sometimes|string|max:200',
            'profile_data.company_registration' => 'sometimes|string|max:100',
            'profile_data.tax_number' => 'sometimes|string|max:50',
            'profile_data.business_type' => 'sometimes|string|in:individual,company,government',
            'profile_data.profile_image' => 'sometimes|string|max:500',
            'profile_data.bio' => 'sometimes|string|max:1000',
            'profile_data.website' => 'sometimes|url|max:200',
            'profile_data.social_links' => 'sometimes|array',
            'profile_data.metadata' => 'sometimes|array',
        ]);

        return $this->executeWithLogging('User@createOrUpdateProfile', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->createOrUpdateProfile(
                $params['user_id'],
                $params['profile_data']
            );

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32001,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'profile' => $result['profile'],
                'message' => $result['message'],
                'updated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get user profile with verification status
     */
    public function getUserProfile(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
        ]);

        return $this->executeWithLogging('User@getUserProfile', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->getUserProfile($params['user_id']);

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32002,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'profile' => $result['profile'],
                'verification_status' => $result['verification_status'],
                'completion_percentage' => $result['completion_percentage'],
                'missing_fields' => $result['missing_fields'],
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Update user preferences
     */
    public function updateUserPreferences(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'preferences' => 'required|array',
            'preferences.language' => 'sometimes|string|in:ar,en',
            'preferences.currency' => 'sometimes|string|in:SAR,USD,EUR',
            'preferences.timezone' => 'sometimes|string|max:50',
            'preferences.notifications' => 'sometimes|array',
            'preferences.notifications.email_enabled' => 'sometimes|boolean',
            'preferences.notifications.sms_enabled' => 'sometimes|boolean',
            'preferences.notifications.push_enabled' => 'sometimes|boolean',
            'preferences.notifications.marketing_enabled' => 'sometimes|boolean',
            'preferences.privacy' => 'sometimes|array',
            'preferences.privacy.profile_visibility' => 'sometimes|string|in:public,private,contacts',
            'preferences.privacy.show_online_status' => 'sometimes|boolean',
            'preferences.privacy.allow_contact' => 'sometimes|boolean',
            'preferences.display' => 'sometimes|array',
            'preferences.display.theme' => 'sometimes|string|in:light,dark,auto',
            'preferences.display.items_per_page' => 'sometimes|integer|min:10|max:100',
        ]);

        return $this->executeWithLogging('User@updateUserPreferences', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->updateUserPreferences(
                $params['user_id'],
                $params['preferences']
            );

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32003,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'preferences' => $result['preferences'],
                'message' => $result['message'],
                'updated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Add user address
     */
    public function addUserAddress(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'address_data' => 'required|array',
            'address_data.type' => 'required|string|in:home,work,billing,shipping,other',
            'address_data.label' => 'sometimes|string|max:100',
            'address_data.street_address' => 'required|string|max:200',
            'address_data.building_number' => 'sometimes|string|max:20',
            'address_data.apartment_number' => 'sometimes|string|max:20',
            'address_data.district' => 'required|string|max:100',
            'address_data.city' => 'required|string|max:100',
            'address_data.region' => 'required|string|max:100',
            'address_data.postal_code' => 'required|string|max:20',
            'address_data.country' => 'required|string|max:100',
            'address_data.latitude' => 'sometimes|numeric|between:-90,90',
            'address_data.longitude' => 'sometimes|numeric|between:-180,180',
            'address_data.is_default' => 'sometimes|boolean',
            'address_data.delivery_instructions' => 'sometimes|string|max:500',
        ]);

        return $this->executeWithLogging('User@addUserAddress', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->addUserAddress(
                $params['user_id'],
                $params['address_data']
            );

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32004,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'address' => $result['address'],
                'message' => $result['message'],
                'created_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Add user vehicle
     */
    public function addUserVehicle(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'vehicle_data' => 'required|array',
            'vehicle_data.make' => 'required|string|max:100',
            'vehicle_data.model' => 'required|string|max:100',
            'vehicle_data.year' => 'required|integer|min:1900|max:'.(date('Y') + 1),
            'vehicle_data.vin' => 'sometimes|string|size:17',
            'vehicle_data.license_plate' => 'sometimes|string|max:20',
            'vehicle_data.color' => 'sometimes|string|max:50',
            'vehicle_data.engine_size' => 'sometimes|string|max:20',
            'vehicle_data.fuel_type' => 'sometimes|string|in:gasoline,diesel,hybrid,electric,other',
            'vehicle_data.transmission' => 'sometimes|string|in:manual,automatic,cvt',
            'vehicle_data.mileage' => 'sometimes|integer|min:0',
            'vehicle_data.is_primary' => 'sometimes|boolean',
            'vehicle_data.notes' => 'sometimes|string|max:500',
            'vehicle_data.images' => 'sometimes|array',
            'vehicle_data.images.*' => 'string|max:500',
        ]);

        return $this->executeWithLogging('User@addUserVehicle', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->addUserVehicle(
                $params['user_id'],
                $params['vehicle_data']
            );

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32005,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'vehicle' => $result['vehicle'],
                'message' => $result['message'],
                'created_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Submit KYC verification documents
     */
    public function submitKYCVerification(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'kyc_data' => 'required|array',
            'kyc_data.document_type' => 'required|string|in:national_id,passport,iqama,driving_license',
            'kyc_data.document_number' => 'required|string|max:100',
            'kyc_data.document_front_image' => 'required|string|max:500',
            'kyc_data.document_back_image' => 'sometimes|string|max:500',
            'kyc_data.selfie_image' => 'required|string|max:500',
            'kyc_data.expiry_date' => 'sometimes|date|after:today',
            'kyc_data.issuing_country' => 'required|string|max:100',
            'kyc_data.additional_documents' => 'sometimes|array',
            'kyc_data.additional_documents.*' => 'string|max:500',
        ]);

        return $this->executeWithLogging('User@submitKYCVerification', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->submitKYCVerification(
                $params['user_id'],
                $params['kyc_data']
            );

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32006,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'kyc_submission' => $result['kyc_submission'],
                'message' => $result['message'],
                'submitted_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Update verification status (admin only)
     */
    public function updateVerificationStatus(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'verification_type' => 'required|string|in:email,phone,identity,business',
            'status' => 'required|string|in:pending,verified,rejected',
            'admin_id' => 'required|integer',
            'notes' => 'sometimes|string|max:500',
            'rejection_reason' => 'sometimes|string|max:500',
        ]);

        return $this->executeWithLogging('User@updateVerificationStatus', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->updateVerificationStatus(
                $params['user_id'],
                $params['verification_type'],
                $params['status'],
                $params['admin_id'],
                $params['notes'] ?? null,
                $params['rejection_reason'] ?? null
            );

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32007,
                    ['user_id' => $params['user_id'], 'verification_type' => $params['verification_type']]
                );
            }

            return [
                'success' => true,
                'verification' => $result['verification'],
                'message' => $result['message'],
                'updated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get user activity summary
     */
    public function getUserActivitySummary(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'period' => 'sometimes|string|in:week,month,quarter,year',
        ]);

        return $this->executeWithLogging('User@getUserActivitySummary', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->getUserActivitySummary(
                $params['user_id'],
                $params['period'] ?? 'month'
            );

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32008,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'activity_summary' => $result['activity_summary'],
                'period' => $params['period'] ?? 'month',
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Search users with advanced filtering
     */
    public function searchUsers(array $params): array
    {
        $this->validate($params, [
            'criteria' => 'sometimes|array',
            'criteria.name' => 'sometimes|string|max:100',
            'criteria.email' => 'sometimes|string|max:100',
            'criteria.phone' => 'sometimes|string|max:20',
            'criteria.role' => 'sometimes|string|in:customer,merchant,admin',
            'criteria.verification_status' => 'sometimes|string|in:verified,unverified,pending',
            'criteria.status' => 'sometimes|string|in:active,inactive,suspended',
            'criteria.registration_date_from' => 'sometimes|date',
            'criteria.registration_date_to' => 'sometimes|date',
            'criteria.last_activity_from' => 'sometimes|date',
            'criteria.last_activity_to' => 'sometimes|date',
            'criteria.city' => 'sometimes|string|max:100',
            'criteria.region' => 'sometimes|string|max:100',
            'criteria.country' => 'sometimes|string|max:100',
            'criteria.sort_by' => 'sometimes|string|in:name,email,created_at,last_activity',
            'criteria.sort_direction' => 'sometimes|string|in:asc,desc',
            'criteria.page' => 'sometimes|integer|min:1',
            'criteria.per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        return $this->executeWithLogging('User@searchUsers', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for search operations
            $key = 'user_search:'.request()->ip();
            if (RateLimiter::tooManyAttempts($key, 60)) {
                throw $this->createRuntimeException(
                    'Too many search requests. Please try again later.',
                    -32009,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            RateLimiter::hit($key, 60); // 1 minute window

            $result = $this->userService->searchUsers($params['criteria'] ?? []);

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32010,
                    ['criteria' => $params['criteria'] ?? []]
                );
            }

            return [
                'success' => true,
                'users' => $result['users'],
                'pagination' => $result['pagination'],
                'summary' => $result['summary'],
                'searched_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get profile completion status
     */
    public function getProfileCompletionStatus(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
        ]);

        return $this->executeWithLogging('User@getProfileCompletionStatus', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->getProfileCompletionStatus($params['user_id']);

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32011,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'completion_status' => $result['completion_status'],
                'completion_percentage' => $result['completion_percentage'],
                'missing_fields' => $result['missing_fields'],
                'next_steps' => $result['next_steps'],
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Update user location
     */
    public function updateUserLocation(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'location_data' => 'required|array',
            'location_data.latitude' => 'required|numeric|between:-90,90',
            'location_data.longitude' => 'required|numeric|between:-180,180',
            'location_data.accuracy' => 'sometimes|numeric|min:0',
            'location_data.address' => 'sometimes|string|max:200',
            'location_data.city' => 'sometimes|string|max:100',
            'location_data.region' => 'sometimes|string|max:100',
            'location_data.country' => 'sometimes|string|max:100',
        ]);

        return $this->executeWithLogging('User@updateUserLocation', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->updateUserLocation(
                $params['user_id'],
                $params['location_data']
            );

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32012,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'location' => $result['location'],
                'message' => $result['message'],
                'updated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get user preferences
     */
    public function getUserPreferences(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
        ]);

        return $this->executeWithLogging('User@getUserPreferences', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->getUserPreferences($params['user_id']);

            if (! $result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32013,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'preferences' => $result['preferences'],
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }

    // ========================================
    // AVATAR MANAGEMENT RPC METHODS
    // ========================================

    /**
     * Upload user avatar via RPC
     */
    public function uploadAvatar(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'file_data' => 'required|string', // Base64 encoded file data
            'file_name' => 'required|string|max:255',
            'mime_type' => 'required|string|in:image/jpeg,image/png,image/gif,image/webp',
            'options' => 'sometimes|array',
            'options.max_width' => 'sometimes|integer|min:100|max:2048',
            'options.max_height' => 'sometimes|integer|min:100|max:2048',
            'options.quality' => 'sometimes|integer|min:50|max:100',
        ]);

        return $this->executeWithLogging('User@uploadAvatar', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for avatar uploads
            $key = 'avatar_upload:'.$params['user_id'];
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw $this->createRuntimeException(
                    'Too many avatar upload attempts. Please try again later.',
                    -32020,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            try {
                // Find user
                $user = $this->userService->getUserById($params['user_id']);
                if (! $user) {
                    throw $this->createRuntimeException(
                        'User not found',
                        -32021,
                        ['user_id' => $params['user_id']]
                    );
                }

                // Decode base64 file data
                $fileData = base64_decode($params['file_data']);
                if ($fileData === false) {
                    throw $this->createRuntimeException(
                        'Invalid file data encoding',
                        -32022,
                        ['user_id' => $params['user_id']]
                    );
                }

                // Create temporary file
                $tempPath = tempnam(sys_get_temp_dir(), 'avatar_');
                file_put_contents($tempPath, $fileData);

                // Create UploadedFile instance
                $uploadedFile = new UploadedFile(
                    $tempPath,
                    $params['file_name'],
                    $params['mime_type'],
                    null,
                    true
                );

                // Upload avatar
                $avatar = $this->profileService->uploadAvatar(
                    $user,
                    $uploadedFile,
                    $params['options'] ?? []
                );

                // Clean up temp file
                unlink($tempPath);

                // Clear rate limiting on success
                RateLimiter::clear($key);

                return [
                    'success' => true,
                    'avatar' => [
                        'id' => $avatar->id,
                        'url' => $avatar->url,
                        'cdn_url' => $avatar->cdn_url,
                        'file_name' => $avatar->file_name,
                        'file_size' => $avatar->file_size,
                        'formatted_file_size' => $avatar->formatted_file_size,
                        'mime_type' => $avatar->mime_type,
                        'storage_provider' => $avatar->storage_provider,
                        'uploaded_at' => $avatar->created_at->toISOString(),
                    ],
                    'message' => 'Avatar uploaded successfully',
                    'uploaded_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                // Increment rate limiting on failure
                RateLimiter::hit($key, 300); // 5 minutes

                throw $this->createRuntimeException(
                    'Avatar upload failed: '.$e->getMessage(),
                    -32023,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Delete user avatar via RPC
     */
    public function deleteAvatar(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('User@deleteAvatar', $params, function () use ($params) {
            try {
                // Find user
                $user = $this->userService->getUserById($params['user_id']);
                if (! $user) {
                    throw $this->createRuntimeException(
                        'User not found',
                        -32024,
                        ['user_id' => $params['user_id']]
                    );
                }

                // Check if user has avatar
                if (! $user->hasAvatar()) {
                    throw $this->createRuntimeException(
                        'User has no avatar to delete',
                        -32025,
                        ['user_id' => $params['user_id']]
                    );
                }

                // Delete avatar
                $deleted = $this->profileService->deleteAvatar($user);

                if (! $deleted) {
                    throw $this->createRuntimeException(
                        'Failed to delete avatar',
                        -32026,
                        ['user_id' => $params['user_id']]
                    );
                }

                return [
                    'success' => true,
                    'message' => 'Avatar deleted successfully',
                    'deleted_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'Avatar deletion failed: '.$e->getMessage(),
                    -32027,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Get user avatar info via RPC
     */
    public function getAvatar(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('User@getAvatar', $params, function () use ($params) {
            try {
                // Find user
                $user = $this->userService->getUserById($params['user_id']);
                if (! $user) {
                    throw $this->createRuntimeException(
                        'User not found',
                        -32028,
                        ['user_id' => $params['user_id']]
                    );
                }

                // Get avatar
                $avatar = $this->profileService->getAvatar($user);

                if (! $avatar) {
                    return [
                        'success' => true,
                        'avatar' => null,
                        'message' => 'User has no avatar',
                        'retrieved_at' => now()->toISOString(),
                    ];
                }

                return [
                    'success' => true,
                    'avatar' => [
                        'id' => $avatar->id,
                        'url' => $avatar->url,
                        'cdn_url' => $avatar->cdn_url,
                        'file_name' => $avatar->file_name,
                        'original_name' => $avatar->original_name,
                        'file_size' => $avatar->file_size,
                        'formatted_file_size' => $avatar->formatted_file_size,
                        'mime_type' => $avatar->mime_type,
                        'storage_provider' => $avatar->storage_provider,
                        'uploaded_at' => $avatar->created_at->toISOString(),
                        'updated_at' => $avatar->updated_at->toISOString(),
                    ],
                    'retrieved_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'Failed to retrieve avatar: '.$e->getMessage(),
                    -32029,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Get user avatar URL via RPC
     */
    public function getAvatarUrl(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'default_url' => 'sometimes|string|url',
        ]);

        return $this->executeWithLogging('User@getAvatarUrl', $params, function () use ($params) {
            try {
                // Find user
                $user = $this->userService->getUserById($params['user_id']);
                if (! $user) {
                    throw $this->createRuntimeException(
                        'User not found',
                        -32030,
                        ['user_id' => $params['user_id']]
                    );
                }

                // Get avatar URL
                $avatarUrl = $this->profileService->getAvatarUrl(
                    $user,
                    $params['default_url'] ?? null
                );

                return [
                    'success' => true,
                    'avatar_url' => $avatarUrl,
                    'has_avatar' => $user->hasAvatar(),
                    'retrieved_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'Failed to retrieve avatar URL: '.$e->getMessage(),
                    -32031,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }
}
