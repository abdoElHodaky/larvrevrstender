<?php

namespace App\Listeners;

use App\Services\UserProfileService;
use Illuminate\Support\Facades\Log;

class HandleUserRegisteredFromAuth
{
    public function __construct(
        private UserProfileService $userProfileService
    ) {}

    /**
     * Handle the event from auth service.
     */
    public function handle($eventData): void
    {
        try {
            // Extract user data from the event
            $userData = is_array($eventData) ? $eventData : json_decode($eventData, true);
            
            if (!isset($userData['user_id'])) {
                Log::warning('User registered event missing user_id', ['data' => $userData]);
                return;
            }

            // Create a basic profile for the new user
            $profile = $this->userProfileService->createProfileFromUserRegistration(
                $userData['user_id'],
                [
                    'company_name' => $userData['company_name'] ?? null,
                    'industry' => $userData['industry'] ?? null,
                ]
            );

            Log::info('User profile created from auth service registration', [
                'user_id' => $userData['user_id'],
                'profile_id' => $profile->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle user registered event from auth service', [
                'error' => $e->getMessage(),
                'event_data' => $eventData
            ]);
        }
    }
}

