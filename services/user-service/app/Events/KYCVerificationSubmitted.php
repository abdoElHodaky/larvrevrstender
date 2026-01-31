<?php

namespace App\Events;

use App\Models\CustomerProfile;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KYCVerificationSubmitted
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
            'verification_status' => $this->profile->verification_status,
            'documents_count' => count($this->profile->verification_documents ?? []),
            'submitted_at' => $this->profile->updated_at->toISOString(),
            'event_type' => 'user.kyc.submitted'
        ];
    }
}

