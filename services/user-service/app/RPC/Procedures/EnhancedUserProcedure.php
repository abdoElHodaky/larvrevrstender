<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\EnhancedUserService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Sajya\Server\Exceptions\RuntimeException;

class EnhancedUserProcedure extends BaseProcedure
{
    public function __construct(
        private EnhancedUserService $userService
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
     * Create or update user profile
     * 
     * @param array $params
     * @return array
     */
    public function createOrUpdateProfile(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:customer,merchant',
            'profile_data' => 'required|array',
        ]);

        return $this->executeWithLogging('User@createOrUpdateProfile', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->createOrUpdateProfile(
                $params['user_id'],
                $params['user_type'],
                $params['profile_data']
            );

            if (!$result['success']) {
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
     * Get user profile
     * 
     * @param array $params
     * @return array
     */
    public function getUserProfile(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:customer,merchant',
        ]);

        return $this->executeWithLogging('User@getUserProfile', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->getUserProfile(
                $params['user_id'],
                $params['user_type']
            );

            if (!$result['success']) {
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
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Update user preferences
     * 
     * @param array $params
     * @return array
     */
    public function updateUserPreferences(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:customer,merchant',
            'preferences' => 'required|array',
        ]);

        return $this->executeWithLogging('User@updateUserPreferences', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->updateUserPreferences(
                $params['user_id'],
                $params['user_type'],
                $params['preferences']
            );

            if (!$result['success']) {
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
     * Add address to user profile
     * 
     * @param array $params
     * @return array
     */
    public function addUserAddress(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:customer,merchant',
            'address_data' => 'required|array',
            'address_data.street_address' => 'required|string',
            'address_data.city' => 'required|string',
            'address_data.type' => 'sometimes|string|in:delivery,billing,business',
            'address_data.label' => 'sometimes|string|max:100',
            'address_data.country' => 'sometimes|string|size:2',
            'address_data.is_default' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('User@addUserAddress', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->addUserAddress(
                $params['user_id'],
                $params['user_type'],
                $params['address_data']
            );

            if (!$result['success']) {
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
     * Add vehicle to user profile (customers only)
     * 
     * @param array $params
     * @return array
     */
    public function addUserVehicle(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'vehicle_data' => 'required|array',
            'vehicle_data.vin' => 'required|string|size:17',
            'vehicle_data.make' => 'required|string|max:50',
            'vehicle_data.model' => 'required|string|max:50',
            'vehicle_data.year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'vehicle_data.color' => 'sometimes|string|max:30',
            'vehicle_data.license_plate' => 'sometimes|string|max:20',
            'vehicle_data.is_primary' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('User@addUserVehicle', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->addUserVehicle(
                $params['user_id'],
                $params['vehicle_data']
            );

            if (!$result['success']) {
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
     * 
     * @param array $params
     * @return array
     */
    public function submitKYCVerification(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:customer,merchant',
            'documents' => 'required|array',
            'documents.national_id' => 'required|array',
            'documents.national_id.file_path' => 'required|string',
            'documents.proof_of_address' => 'required|array',
            'documents.proof_of_address.file_path' => 'required|string',
        ]);

        return $this->executeWithLogging('User@submitKYCVerification', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->submitKYCVerification(
                $params['user_id'],
                $params['user_type'],
                $params['documents']
            );

            if (!$result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32006,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'verification_status' => $result['verification_status'],
                'message' => $result['message'],
                'submitted_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Update verification status (admin function)
     * 
     * @param array $params
     * @return array
     */
    public function updateVerificationStatus(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:customer,merchant',
            'status' => 'required|string|in:pending,approved,rejected,requires_review',
            'notes' => 'sometimes|string|max:1000',
        ]);

        return $this->executeWithLogging('User@updateVerificationStatus', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->updateVerificationStatus(
                $params['user_id'],
                $params['user_type'],
                $params['status'],
                $params['notes'] ?? null
            );

            if (!$result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32007,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'verification_status' => $result['verification_status'],
                'message' => $result['message'],
                'updated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get user activity summary
     * 
     * @param array $params
     * @return array
     */
    public function getUserActivitySummary(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
        ]);

        return $this->executeWithLogging('User@getUserActivitySummary', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->getUserActivitySummary($params['user_id']);

            if (!$result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32008,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'activity' => $result['activity'],
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Search users by criteria
     * 
     * @param array $params
     * @return array
     */
    public function searchUsers(array $params): array
    {
        $this->validate($params, [
            'criteria' => 'sometimes|array',
            'criteria.verification_status' => 'sometimes|string|in:pending,approved,rejected,requires_review',
            'criteria.city' => 'sometimes|string|max:100',
            'criteria.industry' => 'sometimes|string|max:100',
            'criteria.company_size' => 'sometimes|string|max:50',
            'criteria.page' => 'sometimes|integer|min:1',
            'criteria.per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        return $this->executeWithLogging('User@searchUsers', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for search operations
            $key = 'user_search:' . request()->ip();
            if (RateLimiter::tooManyAttempts($key, 30)) {
                throw $this->createRuntimeException(
                    'Too many search requests. Please try again later.',
                    -32009,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            RateLimiter::hit($key, 60); // 1 minute window

            $result = $this->userService->searchUsers($params['criteria'] ?? []);

            if (!$result['success']) {
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
                'searched_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get profile completion status
     * 
     * @param array $params
     * @return array
     */
    public function getProfileCompletionStatus(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:customer,merchant',
        ]);

        return $this->executeWithLogging('User@getProfileCompletionStatus', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->getUserProfile(
                $params['user_id'],
                $params['user_type']
            );

            if (!$result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32011,
                    ['user_id' => $params['user_id']]
                );
            }

            // Determine missing fields based on user type
            $profile = $result['profile'];
            $missingFields = [];
            
            if ($params['user_type'] === 'customer') {
                $requiredFields = [
                    'national_id' => 'National ID',
                    'national_address' => 'National Address',
                    'date_of_birth' => 'Date of Birth',
                    'occupation' => 'Occupation',
                    'default_location' => 'Default Location'
                ];
            } else {
                $requiredFields = [
                    'business_name' => 'Business Name',
                    'business_type' => 'Business Type',
                    'commercial_registration' => 'Commercial Registration',
                    'tax_number' => 'Tax Number',
                    'business_address' => 'Business Address',
                    'contact_person' => 'Contact Person'
                ];
            }

            foreach ($requiredFields as $field => $label) {
                if (empty($profile->$field)) {
                    $missingFields[] = [
                        'field' => $field,
                        'label' => $label,
                        'required' => true
                    ];
                }
            }

            return [
                'success' => true,
                'completion_percentage' => $result['completion_percentage'],
                'verification_status' => $result['verification_status'],
                'missing_fields' => $missingFields,
                'is_complete' => empty($missingFields),
                'checked_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Update user location
     * 
     * @param array $params
     * @return array
     */
    public function updateUserLocation(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:customer,merchant',
            'location' => 'required|array',
            'location.latitude' => 'required|numeric|between:-90,90',
            'location.longitude' => 'required|numeric|between:-180,180',
            'location.address' => 'sometimes|string|max:255',
            'location.city' => 'sometimes|string|max:100',
            'location.country' => 'sometimes|string|size:2',
        ]);

        return $this->executeWithLogging('User@updateUserLocation', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->createOrUpdateProfile(
                $params['user_id'],
                $params['user_type'],
                ['default_location' => $params['location']]
            );

            if (!$result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32012,
                    ['user_id' => $params['user_id']]
                );
            }

            return [
                'success' => true,
                'location' => $params['location'],
                'message' => 'Location updated successfully',
                'updated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get user preferences
     * 
     * @param array $params
     * @return array
     */
    public function getUserPreferences(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
            'user_type' => 'required|string|in:customer,merchant',
        ]);

        return $this->executeWithLogging('User@getUserPreferences', $this->sanitizeForLogging($params), function () use ($params) {
            $result = $this->userService->getUserProfile(
                $params['user_id'],
                $params['user_type']
            );

            if (!$result['success']) {
                throw $this->createRuntimeException(
                    $result['message'],
                    -32013,
                    ['user_id' => $params['user_id']]
                );
            }

            $preferences = $result['profile']->preferences ?? [];

            return [
                'success' => true,
                'preferences' => $preferences,
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }
}
