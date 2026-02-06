<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class KycSubmittedForReview
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;

    public Collection $documents;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, Collection $documents)
    {
        $this->user = $user;
        $this->documents = $documents;
    }

    /**
     * Get the event payload for broadcasting or logging.
     */
    public function getPayload(): array
    {
        return [
            'event' => 'kyc.submitted_for_review',
            'user_id' => $this->user->id,
            'document_count' => $this->documents->count(),
            'document_types' => $this->documents->pluck('document_type')->toArray(),
            'submitted_at' => now()->toISOString(),
        ];
    }
}
