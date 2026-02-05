<?php

namespace App\Events;

use App\Models\User;
use App\Models\UserAvatar;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AvatarDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public UserAvatar $avatar;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, UserAvatar $avatar)
    {
        $this->user = $user;
        $this->avatar = $avatar;
    }

    /**
     * Get the event payload for broadcasting or logging.
     */
    public function getPayload(): array
    {
        return [
            'event' => 'avatar.deleted',
            'user_id' => $this->user->id,
            'avatar_id' => $this->avatar->id,
            'deleted_at' => now()->toISOString(),
        ];
    }
}

