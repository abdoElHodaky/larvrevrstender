<?php

namespace App\Events;

use App\Models\CustomerProfile;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserProfileUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CustomerProfile $profile;

    /**
     * Create a new event instance.
     */
    public function __construct(CustomerProfile $profile)
    {
        $this->profile = $profile;
    }

    /**
     * Get the event data for broadcasting
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->profile->user_id,
            'profile_id' => $this->profile->id,
            'company_name' => $this->profile->company_name,
            'industry' => $this->profile->industry,
            'company_size' => $this->profile->company_size,
            'verification_status' => $this->profile->verification_status,
            'is_verified' => $this->profile->isVerified(),
            'updated_at' => $this->profile->updated_at->toISOString(),
            'event_type' => 'user.profile.updated'
        ];
    }
}

