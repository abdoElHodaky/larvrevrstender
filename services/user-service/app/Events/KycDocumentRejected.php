<?php

namespace App\Events;

use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KycDocumentRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;

    public KycDocument $document;

    public string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, KycDocument $document, string $reason)
    {
        $this->user = $user;
        $this->document = $document;
        $this->reason = $reason;
    }

    /**
     * Get the event payload for broadcasting or logging.
     */
    public function getPayload(): array
    {
        return [
            'event' => 'kyc.document.rejected',
            'user_id' => $this->user->id,
            'document_id' => $this->document->id,
            'document_type' => $this->document->document_type,
            'version' => $this->document->version,
            'rejection_reason' => $this->reason,
            'rejected_at' => now()->toISOString(),
        ];
    }
}
