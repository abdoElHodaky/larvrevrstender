<?php

namespace App\Events;

use App\Models\CustomerProfile;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KYCVerificationCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public CustomerProfile $profile;
    public string $status;

    /**
     * Create a new event instance.
     */
    public function __construct(CustomerProfile $profile, string $status)
    {
        $this->profile = $profile;
        $this->status = $status;
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
            'verification_status' => $this->status,
            'is_verified' => $this->status === CustomerProfile::STATUS_VERIFIED,
            'completed_at' => now()->toISOString(),
            'event_type' => 'user.kyc.completed'
        ];
    }
}

