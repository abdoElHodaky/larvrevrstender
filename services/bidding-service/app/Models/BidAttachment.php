<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BidAttachment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'bid_attachments';

    /**
     * Attachment type constants.
     */
    const TYPE_DOCUMENT = 'document';

    const TYPE_IMAGE = 'image';

    const TYPE_CERTIFICATE = 'certificate';

    const TYPE_PROOF_OF_FUNDS = 'proof_of_funds';

    const TYPE_TECHNICAL_SPEC = 'technical_spec';

    const TYPE_OTHER = 'other';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'bid_id',
        'attachment_type',
        'file_path',
        'file_name',
        'original_name',
        'file_size',
        'mime_type',
        'storage_provider',
        'url',
        'description',
        'is_confidential',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'file_size' => 'integer',
        'is_confidential' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'file_path', // Hide internal storage path for security
    ];

    /**
     * Get all available attachment types.
     */
    public static function getAttachmentTypes(): array
    {
        return [
            self::TYPE_DOCUMENT => 'Document',
            self::TYPE_IMAGE => 'Image',
            self::TYPE_CERTIFICATE => 'Certificate',
            self::TYPE_PROOF_OF_FUNDS => 'Proof of Funds',
            self::TYPE_TECHNICAL_SPEC => 'Technical Specification',
            self::TYPE_OTHER => 'Other',
        ];
    }

    /**
     * Get the bid that owns the attachment.
     */
    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    /**
     * Get the human-readable attachment type.
     */
    public function getAttachmentTypeLabelAttribute(): string
    {
        return self::getAttachmentTypes()[$this->attachment_type] ?? 'Unknown';
    }

    /**
     * Get the formatted file size in human readable format.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Get the CDN URL for the attachment.
     */
    public function getCdnUrlAttribute(): string
    {
        if (str_contains($this->url, 'cdn.')) {
            return $this->url;
        }

        $cdnDomain = config('filesystems.cdn_domain', 'https://cdn.reversetender.com');

        return $cdnDomain.'/'.ltrim($this->file_path, '/');
    }

    /**
     * Get the file extension from mime type.
     */
    public function getFileExtensionAttribute(): string
    {
        $mimeToExt = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'text/plain' => 'txt',
        ];

        return $mimeToExt[$this->mime_type] ?? 'unknown';
    }

    /**
     * Check if the attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the attachment is a document.
     */
    public function isDocument(): bool
    {
        return str_starts_with($this->mime_type, 'application/') ||
               str_starts_with($this->mime_type, 'text/');
    }

    /**
     * Check if the attachment is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Scope to get attachments by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('attachment_type', $type);
    }

    /**
     * Scope to get confidential attachments.
     */
    public function scopeConfidential($query)
    {
        return $query->where('is_confidential', true);
    }

    /**
     * Scope to get public attachments.
     */
    public function scopePublic($query)
    {
        return $query->where('is_confidential', false);
    }

    /**
     * Scope to get attachments by storage provider.
     */
    public function scopeByProvider($query, string $provider)
    {
        return $query->where('storage_provider', $provider);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Set default attachment type
        static::creating(function ($attachment) {
            if (empty($attachment->attachment_type)) {
                $attachment->attachment_type = self::TYPE_DOCUMENT;
            }
        });

        // Automatically delete from cloud storage when model is deleted
        static::deleting(function ($attachment) {
            if ($attachment->isForceDeleting()) {
                try {
                    app(\Shared\Services\FileUploadService::class)->delete($attachment->file_path);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        'Failed to delete bid attachment from cloud storage during model deletion',
                        [
                            'attachment_id' => $attachment->id,
                            'file_path' => $attachment->file_path,
                            'error' => $e->getMessage(),
                        ]
                    );
                }
            }
        });
    }
}
