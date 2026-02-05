<?php

namespace App\Events;

use App\Models\User;
use App\Models\KycDocument;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KycDocumentUploaded
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
            'event' => 'kyc.document.uploaded',
            'user_id' => $this->user->id,
            'document_id' => $this->document->id,
            'document_type' => $this->document->document_type,
            'version' => $this->document->version,
            'status' => $this->document->status,
            'file_size' => $this->document->file_size,
            'uploaded_at' => $this->document->created_at->toISOString(),
        ];
    }
}

