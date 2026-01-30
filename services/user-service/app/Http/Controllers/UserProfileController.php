<?php

namespace App\Http\Controllers;

use App\Models\CustomerProfile;
use App\Services\UserProfileService;
use App\Events\UserProfileUpdated;
use App\Events\KYCVerificationSubmitted;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class UserProfileController extends Controller
{
    public function __construct(
        private UserProfileService $userProfileService
    ) {}

    /**
     * Get user profile
     */
    public function show(Request $request): JsonResponse
    {
        $profile = $this->userProfileService->getProfile($request->user()->id);
        
        return response()->json([
            'success' => true,
            'data' => $profile
        ]);
    }

    /**
     * Create or update user profile
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'industry' => 'required|string|max:100',
            'company_size' => ['required', Rule::in([
                CustomerProfile::SIZE_STARTUP,
                CustomerProfile::SIZE_SMALL,
                CustomerProfile::SIZE_MEDIUM,
                CustomerProfile::SIZE_LARGE,
                CustomerProfile::SIZE_ENTERPRISE
            ])],
            'annual_budget' => 'nullable|numeric|min:0',
            'preferred_categories' => 'nullable|array',
            'preferred_categories.*' => 'string|max:100',
            'delivery_addresses' => 'nullable|array',
            'delivery_addresses.*.address' => 'required|string',
            'delivery_addresses.*.city' => 'required|string',
            'delivery_addresses.*.postal_code' => 'required|string',
            'delivery_addresses.*.is_primary' => 'boolean',
            'payment_terms' => 'nullable|string|max:500',
            'preferences' => 'nullable|array',
            'metadata' => 'nullable|array'
        ]);

        $profile = $this->userProfileService->createOrUpdateProfile(
            $request->user()->id,
            $validated
        );

        event(new UserProfileUpdated($profile));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $profile
        ]);
    }

    /**
     * Add delivery address
     */
    public function addDeliveryAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'is_primary' => 'boolean'
        ]);

        $profile = $this->userProfileService->addDeliveryAddress(
            $request->user()->id,
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Delivery address added successfully',
            'data' => $profile
        ]);
    }

    /**
     * Update preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => 'required|array',
            'preferences.notifications' => 'boolean',
            'preferences.email_updates' => 'boolean',
            'preferences.sms_updates' => 'boolean',
            'preferences.language' => 'string|in:en,ar',
            'preferences.currency' => 'string|in:SAR,USD,EUR'
        ]);

        $profile = $this->userProfileService->updatePreferences(
            $request->user()->id,
            $validated['preferences']
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully',
            'data' => $profile
        ]);
    }

    /**
     * Submit KYC verification documents
     */
    public function submitKYCVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'documents' => 'required|array',
            'documents.*.type' => 'required|string|in:commercial_register,tax_certificate,bank_statement,id_copy',
            'documents.*.file_path' => 'required|string',
            'documents.*.file_name' => 'required|string'
        ]);

        $profile = $this->userProfileService->submitKYCVerification(
            $request->user()->id,
            $validated['documents']
        );

        event(new KYCVerificationSubmitted($profile));

        return response()->json([
            'success' => true,
            'message' => 'KYC verification documents submitted successfully',
            'data' => $profile
        ]);
    }

    /**
     * Get verification status
     */
    public function verificationStatus(Request $request): JsonResponse
    {
        $profile = $this->userProfileService->getProfile($request->user()->id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'verification_status' => $profile->verification_status,
                'is_verified' => $profile->isVerified(),
                'verification_documents' => $profile->verification_documents,
                'submitted_at' => $profile->updated_at
            ]
        ]);
    }

    /**
     * Add preferred category
     */
    public function addPreferredCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|max:100'
        ]);

        $profile = $this->userProfileService->addPreferredCategory(
            $request->user()->id,
            $validated['category']
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferred category added successfully',
            'data' => $profile
        ]);
    }

    /**
     * Remove preferred category
     */
    public function removePreferredCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|max:100'
        ]);

        $profile = $this->userProfileService->removePreferredCategory(
            $request->user()->id,
            $validated['category']
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferred category removed successfully',
            'data' => $profile
        ]);
    }
}

