<?php

namespace App\Listeners;

use App\Events\UserProfileUpdated;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class BroadcastUserProfileUpdated
{
    /**
     * Handle the event.
     */
    public function handle(UserProfileUpdated $event): void
    {
        try {
            $eventData = [
                'service' => 'user-service',
                'event' => 'user.profile.updated',
                'data' => $event->broadcastWith(),
                'timestamp' => now()->toISOString()
            ];

            // Broadcast to Redis for other services to consume
            Redis::publish('user-service.profile-updated', json_encode($eventData));
            
            Log::info('User profile updated event broadcasted', [
                'user_id' => $event->profile->user_id,
                'profile_id' => $event->profile->id
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to broadcast user profile updated event', [
                'error' => $e->getMessage(),
                'user_id' => $event->profile->user_id
            ]);
        }
    }
}

