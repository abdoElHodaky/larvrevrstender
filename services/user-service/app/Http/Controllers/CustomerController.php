<?php

namespace App\Http\Controllers;

use App\Services\CustomerService;
use App\Http\Requests\CreateCustomerProfileRequest;
use App\Http\Requests\UpdateCustomerProfileRequest;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Http\Resources\CustomerProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    private CustomerService $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * Get customer profile.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $profile = $this->customerService->getProfile($userId);
            
            return response()->json([
                'success' => true,
                'data' => new CustomerProfileResource($profile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found'
            ], 404);
        }
    }

    /**
     * Create customer profile.
     */
    public function store(CreateCustomerProfileRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $data = array_merge($request->validated(), ['user_id' => $userId]);
            
            $profile = $this->customerService->createProfile($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Customer profile created successfully',
                'data' => new CustomerProfileResource($profile)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update customer profile.
     */
    public function update(UpdateCustomerProfileRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $profile = $this->customerService->updateProfile($userId, $request->validated());
            
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => new CustomerProfileResource($profile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update customer preferences.
     */
    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $profile = $this->customerService->updatePreferences($userId, $request->validated());
            
            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'data' => new CustomerProfileResource($profile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set default location.
     */
    public function setLocation(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
        ]);

        try {
            $userId = $request->user()->id;
            $location = $request->only(['latitude', 'longitude', 'address', 'city', 'region']);
            
            $profile = $this->customerService->setDefaultLocation($userId, $location);
            
            return response()->json([
                'success' => true,
                'message' => 'Default location updated successfully',
                'data' => new CustomerProfileResource($profile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update national ID for ZATCA compliance.
     */
    public function updateNationalId(Request $request): JsonResponse
    {
        $request->validate([
            'national_id' => 'required|string|min:10|max:20'
        ]);

        try {
            $userId = $request->user()->id;
            $profile = $this->customerService->updateNationalId($userId, $request->national_id);
            
            return response()->json([
                'success' => true,
                'message' => 'National ID updated successfully',
                'data' => new CustomerProfileResource($profile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update national ID: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update national address for ZATCA compliance.
     */
    public function updateNationalAddress(Request $request): JsonResponse
    {
        $request->validate([
            'national_address' => 'required|string|max:1000'
        ]);

        try {
            $userId = $request->user()->id;
            $profile = $this->customerService->updateNationalAddress($userId, $request->national_address);
            
            return response()->json([
                'success' => true,
                'message' => 'National address updated successfully',
                'data' => new CustomerProfileResource($profile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update national address: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer with vehicles.
     */
    public function showWithVehicles(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $customer = $this->customerService->getCustomerWithVehicles($userId);
            
            return response()->json([
                'success' => true,
                'data' => new CustomerProfileResource($customer->load('vehicles'))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }
    }

    /**
     * Get primary vehicle.
     */
    public function primaryVehicle(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $vehicle = $this->customerService->getPrimaryVehicle($userId);
            
            if (!$vehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'No primary vehicle found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $vehicle
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get primary vehicle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate ZATCA compliance.
     */
    public function validateZatca(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $validation = $this->customerService->validateForZATCA($userId);
            
            return response()->json([
                'success' => true,
                'data' => $validation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate ZATCA compliance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete customer profile.
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $deleted = $this->customerService->deleteProfile($userId);
            
            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Profile deleted successfully'
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile: ' . $e->getMessage()
            ], 500);
        }
    }
}

