<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserVerificationStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $profile;

    public $newStatus;

    public $previousStatus;

    /**
     * Create a new event instance.
     */
    public function __construct($profile, string $newStatus, ?string $previousStatus = null)
    {
        $this->profile = $profile;
        $this->newStatus = $newStatus;
        $this->previousStatus = $previousStatus ?? $profile->getOriginal('verification_status');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->profile->user_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->profile->user_id,
            'verification_status' => $this->newStatus,
            'previous_status' => $this->previousStatus,
            'updated_at' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'verification.status.changed';
    }
}
