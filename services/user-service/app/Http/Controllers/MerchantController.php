<?php

namespace App\Http\Controllers;

use App\Services\MerchantService;
use App\Http\Requests\CreateMerchantProfileRequest;
use App\Http\Requests\UpdateMerchantProfileRequest;
use App\Http\Resources\MerchantProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    private MerchantService $merchantService;

    public function __construct(MerchantService $merchantService)
    {
        $this->merchantService = $merchantService;
    }

    /**
     * Get merchant profile.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $profile = $this->merchantService->getProfile($userId);
            
            return response()->json([
                'success' => true,
                'data' => new MerchantProfileResource($profile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found'
            ], 404);
        }
    }

    /**
     * Create merchant profile.
     */
    public function store(CreateMerchantProfileRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $data = array_merge($request->validated(), ['user_id' => $userId]);
            
            $profile = $this->merchantService->createProfile($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Merchant profile created successfully',
                'data' => new MerchantProfileResource($profile)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update merchant profile.
     */
    public function update(UpdateMerchantProfileRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $profile = $this->merchantService->updateProfile($userId, $request->validated());
            
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => new MerchantProfileResource($profile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add specialization.
     */
    public function addSpecialization(Request $request): JsonResponse
    {
        $request->validate([
            'specialization' => 'required|string|max:100'
        ]);

        try {
            $userId = $request->user()->id;
            $profile = $this->merchantService->addSpecialization($userId, $request->specialization);
            
            return response()->json([
                'success' => true,
                'message' => 'Specialization added successfully',
                'data' => new MerchantProfileResource($profile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add specialization: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add service area.
     */
    public function addServiceArea(Request $request): JsonResponse
    {
        $request->validate([
            'area' => 'required|string|max:100'
        ]);

        try {
            $userId = $request->user()->id;
            $profile = $this->merchantService->addServiceArea($userId, $request->area);
            
            return response()->json([
                'success' => true,
                'message' => 'Service area added successfully',
                'data' => new MerchantProfileResource($profile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add service area: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update business hours.
     */
    public function updateBusinessHours(Request $request): JsonResponse
    {
        $request->validate([
            'business_hours' => 'required|array',
            'business_hours.*.day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'business_hours.*.open' => 'required|string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/',
            'business_hours.*.close' => 'required|string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/',
        ]);

        try {
            $userId = $request->user()->id;
            
            // Convert array to associative array with day as key
            $businessHours = [];
            foreach ($request->business_hours as $hours) {
                $businessHours[$hours['day']] = [
                    'open' => $hours['open'],
                    'close' => $hours['close']
                ];
            }
            
            $profile = $this->merchantService->updateBusinessHours($userId, $businessHours);
            
            return response()->json([
                'success' => true,
                'message' => 'Business hours updated successfully',
                'data' => new MerchantProfileResource($profile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update business hours: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add review/rating.
     */
    public function addReview(Request $request): JsonResponse
    {
        $request->validate([
            'rating' => 'required|numeric|min:1|max:5'
        ]);

        try {
            $userId = $request->user()->id;
            $profile = $this->merchantService->addReview($userId, $request->rating);
            
            return response()->json([
                'success' => true,
                'message' => 'Review added successfully',
                'data' => new MerchantProfileResource($profile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add review: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get verified merchants.
     */
    public function verified(): JsonResponse
    {
        try {
            $merchants = $this->merchantService->getVerifiedMerchants();
            
            return response()->json([
                'success' => true,
                'data' => MerchantProfileResource::collection($merchants)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get verified merchants: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search merchants.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'verified' => 'nullable|boolean',
            'specialization' => 'nullable|string|max:100',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'service_area' => 'nullable|string|max:100',
            'business_name' => 'nullable|string|max:255',
        ]);

        try {
            $filters = $request->only(['verified', 'specialization', 'min_rating', 'service_area', 'business_name']);
            $merchants = $this->merchantService->searchMerchants($filters);
            
            return response()->json([
                'success' => true,
                'data' => MerchantProfileResource::collection($merchants)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search merchants: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check availability.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'time' => 'required|string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/',
        ]);

        try {
            $userId = $request->user()->id;
            $available = $this->merchantService->isAvailableAt($userId, $request->day, $request->time);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'available' => $available,
                    'day' => $request->day,
                    'time' => $request->time
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check availability: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get merchant statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $stats = $this->merchantService->getMerchantStats($userId);
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get merchant statistics: ' . $e->getMessage()
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
            $validation = $this->merchantService->validateForZATCA($userId);
            
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
     * Delete merchant profile.
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $deleted = $this->merchantService->deleteProfile($userId);
            
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

