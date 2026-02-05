<?php

namespace App\Events;

use App\Models\User;
use App\Models\UserAvatar;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AvatarUploaded
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
            'event' => 'avatar.uploaded',
            'user_id' => $this->user->id,
            'avatar_id' => $this->avatar->id,
            'file_size' => $this->avatar->file_size,
            'storage_provider' => $this->avatar->storage_provider,
            'uploaded_at' => $this->avatar->created_at->toISOString(),
        ];
    }
}

