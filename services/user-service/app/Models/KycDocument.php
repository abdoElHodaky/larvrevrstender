<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class KycDocument extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'kyc_documents';

    /**
     * Document type constants.
     */
    const TYPE_IDENTITY = 'identity';
    const TYPE_PASSPORT = 'passport';
    const TYPE_DRIVERS_LICENSE = 'drivers_license';
    const TYPE_PROOF_OF_ADDRESS = 'proof_of_address';
    const TYPE_BUSINESS_REGISTRATION = 'business_registration';
    const TYPE_TAX_CERTIFICATE = 'tax_certificate';
    const TYPE_BANK_STATEMENT = 'bank_statement';
    const TYPE_UTILITY_BILL = 'utility_bill';

    /**
     * Status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_RESUBMISSION_REQUIRED = 'resubmission_required';
    const STATUS_SUPERSEDED = 'superseded';
    const STATUS_DELETED = 'deleted';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'document_type',
        'file_path',
        'file_name',
        'original_name',
        'file_size',
        'mime_type',
        'storage_provider',
        'url',
        'description',
        'version',
        'status',
        'encryption_enabled',
        'verified_at',
        'rejection_reason',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'file_size' => 'integer',
        'version' => 'integer',
        'encryption_enabled' => 'boolean',
        'verified_at' => 'datetime',
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
     * Get all available document types.
     */
    public static function getDocumentTypes(): array
    {
        return [
            self::TYPE_IDENTITY => 'Identity Document',
            self::TYPE_PASSPORT => 'Passport',
            self::TYPE_DRIVERS_LICENSE => 'Driver\'s License',
            self::TYPE_PROOF_OF_ADDRESS => 'Proof of Address',
            self::TYPE_BUSINESS_REGISTRATION => 'Business Registration',
            self::TYPE_TAX_CERTIFICATE => 'Tax Certificate',
            self::TYPE_BANK_STATEMENT => 'Bank Statement',
            self::TYPE_UTILITY_BILL => 'Utility Bill',
        ];
    }

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending Review',
            self::STATUS_UNDER_REVIEW => 'Under Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_RESUBMISSION_REQUIRED => 'Resubmission Required',
            self::STATUS_SUPERSEDED => 'Superseded',
            self::STATUS_DELETED => 'Deleted',
        ];
    }

    /**
     * Get the user that owns the document.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the human-readable document type.
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        return self::getDocumentTypes()[$this->document_type] ?? 'Unknown';
    }

    /**
     * Get the human-readable status.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? 'Unknown';
    }

    /**
     * Get the formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if the document is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the document is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if the document is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the document is under review.
     */
    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    /**
     * Check if resubmission is required.
     */
    public function requiresResubmission(): bool
    {
        return $this->status === self::STATUS_RESUBMISSION_REQUIRED;
    }

    /**
     * Check if the document can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return !in_array($this->status, [self::STATUS_APPROVED, self::STATUS_UNDER_REVIEW]);
    }

    /**
     * Get the CDN URL for the document.
     */
    public function getCdnUrlAttribute(): string
    {
        if (str_contains($this->url, 'cdn.')) {
            return $this->url;
        }

        $cdnDomain = config('filesystems.cdn_domain', 'https://cdn.reversetender.com');
        return $cdnDomain . '/' . ltrim($this->file_path, '/');
    }

    /**
     * Scope to get documents by type.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope to get documents by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get approved documents.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope to get pending documents.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get documents under review.
     */
    public function scopeUnderReview(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNDER_REVIEW);
    }

    /**
     * Scope to get rejected documents.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope to get latest version of each document type for a user.
     */
    public function scopeLatestVersions(Builder $query): Builder
    {
        return $query->whereIn('id', function ($subQuery) {
            $subQuery->selectRaw('MAX(id)')
                ->from('kyc_documents')
                ->where('status', '!=', self::STATUS_DELETED)
                ->groupBy(['user_id', 'document_type']);
        });
    }

    /**
     * Scope to get active documents (not deleted or superseded).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_DELETED, self::STATUS_SUPERSEDED]);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Set default status
        static::creating(function ($document) {
            if (empty($document->status)) {
                $document->status = self::STATUS_PENDING;
            }
            if (empty($document->version)) {
                $document->version = 1;
            }
        });

        // Automatically delete from cloud storage when model is force deleted
        static::deleting(function ($document) {
            if ($document->isForceDeleting()) {
                try {
                    app(\Shared\Services\FileUploadService::class)->delete($document->file_path);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        'Failed to delete KYC document from cloud storage during model deletion',
                        [
                            'document_id' => $document->id,
                            'file_path' => $document->file_path,
                            'error' => $e->getMessage()
                        ]
                    );
                }
            }
        });
    }
}

