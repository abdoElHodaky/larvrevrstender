<?php

namespace App\Events;

use App\Models\User;
use App\Models\KycDocument;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KycDocumentApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public KycDocument $document;
    public ?string $notes;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, KycDocument $document, ?string $notes = null)
    {
        $this->user = $user;
        $this->document = $document;
        $this->notes = $notes;
    }

    /**
     * Get the event payload for broadcasting or logging.
     */
    public function getPayload(): array
    {
        return [
            'event' => 'kyc.document.approved',
            'user_id' => $this->user->id,
            'document_id' => $this->document->id,
            'document_type' => $this->document->document_type,
            'version' => $this->document->version,
            'approved_at' => $this->document->verified_at->toISOString(),
            'notes' => $this->notes,
        ];
    }
}

