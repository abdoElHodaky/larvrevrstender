<?php

namespace App\Events;

use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KycDocumentDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;

    public KycDocument $document;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, KycDocument $document)
    {
        $this->user = $user;
        $this->document = $document;
    }

    /**
     * Get the event payload for broadcasting or logging.
     */
    public function getPayload(): array
    {
        return [
            'event' => 'kyc.document.deleted',
            'user_id' => $this->user->id,
            'document_id' => $this->document->id,
            'document_type' => $this->document->document_type,
            'version' => $this->document->version,
            'deleted_at' => now()->toISOString(),
        ];
    }
}
