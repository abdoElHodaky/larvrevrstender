<?php

namespace App\Services;

use App\Models\User;
use App\Models\KycDocument;
use Shared\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;

class KycService
{
    protected FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Upload KYC document.
     */
    public function uploadDocument(
        User $user, 
        UploadedFile $file, 
        string $documentType, 
        ?string $description = null
    ): KycDocument {
        // Validate inputs
        $this->validateDocumentUpload($file, $documentType);

        DB::beginTransaction();
        try {
            // Check for existing document and determine version
            $version = $this->getNextVersion($user, $documentType);

            // Upload to cloud storage with encryption
            $result = $this->fileUploadService->upload(
                $file,
                'user-service/kyc-documents/' . $user->id . '/' . $documentType,
                [
                    'optimize' => false, // Don't optimize documents to preserve quality
                    'encrypt' => true,   // Encrypt sensitive KYC documents
                ]
            );

            // Create document record
            $document = KycDocument::create([
                'user_id' => $user->id,
                'document_type' => $documentType,
                'file_path' => $result['path'],
                'file_name' => $result['filename'],
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $result['size'],
                'mime_type' => $file->getMimeType(),
                'storage_provider' => $result['provider'] ?? 's3',
                'url' => $result['url'],
                'description' => $description,
                'version' => $version,
                'status' => KycDocument::STATUS_PENDING,
                'encryption_enabled' => true,
            ]);

            // Mark previous versions as superseded
            $this->supersedePreviousVersions($user, $documentType, $document->id);

            // Update user's overall KYC status
            $this->updateUserKycStatus($user);

            DB::commit();

            // Fire document uploaded event
            event(new \App\Events\KycDocumentUploaded($user, $document));

            Log::info('KYC document uploaded successfully', [
                'user_id' => $user->id,
                'document_id' => $document->id,
                'document_type' => $documentType,
                'version' => $version,
                'file_size' => $result['size'],
            ]);

            return $document;

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Cleanup uploaded file
            if (isset($result['path'])) {
                try {
                    $this->fileUploadService->delete($result['path']);
                } catch (\Exception $cleanupException) {
                    Log::warning('Failed to cleanup uploaded file after KYC document creation failure', [
                        'file_path' => $result['path'],
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }

            Log::error('KYC document upload failed', [
                'user_id' => $user->id,
                'document_type' => $documentType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get user's KYC documents with pagination and filtering.
     */
    public function getDocuments(
        User $user, 
        ?string $documentType = null, 
        ?string $status = null,
        int $perPage = 15,
        int $page = 1
    ): LengthAwarePaginator {
        $query = $user->kycDocuments()->active()->latest();

        if ($documentType) {
            $query->byType($documentType);
        }

        if ($status) {
            $query->byStatus($status);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Delete KYC document (soft delete).
     */
    public function deleteDocument(User $user, int $documentId): bool
    {
        $document = $user->kycDocuments()->findOrFail($documentId);

        // Check if document can be deleted
        if (!$document->canBeDeleted()) {
            throw new \InvalidArgumentException('Cannot delete approved or documents under review');
        }

        DB::beginTransaction();
        try {
            // Soft delete the document
            $document->update(['status' => KycDocument::STATUS_DELETED]);

            // Update user's overall KYC status
            $this->updateUserKycStatus($user);

            DB::commit();

            // Fire document deleted event
            event(new \App\Events\KycDocumentDeleted($user, $document));

            Log::info('KYC document deleted successfully', [
                'user_id' => $user->id,
                'document_id' => $document->id,
                'document_type' => $document->document_type,
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('KYC document deletion failed', [
                'user_id' => $user->id,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get user's KYC status overview.
     */
    public function getKycStatus(User $user): array
    {
        $documents = $user->kycDocuments()->active()->get();
        $requiredDocuments = $this->getRequiredDocumentTypes();
        
        // Count documents by status
        $statusCounts = [
            'total' => $documents->count(),
            'pending' => $documents->where('status', KycDocument::STATUS_PENDING)->count(),
            'under_review' => $documents->where('status', KycDocument::STATUS_UNDER_REVIEW)->count(),
            'approved' => $documents->where('status', KycDocument::STATUS_APPROVED)->count(),
            'rejected' => $documents->where('status', KycDocument::STATUS_REJECTED)->count(),
            'resubmission_required' => $documents->where('status', KycDocument::STATUS_RESUBMISSION_REQUIRED)->count(),
        ];

        // Get submitted and missing document types
        $submittedTypes = $documents->pluck('document_type')->unique()->toArray();
        $missingTypes = array_diff($requiredDocuments, $submittedTypes);

        // Determine overall status
        $overallStatus = $user->kyc_status;
        
        // Get submission and verification dates
        $submittedAt = $documents->where('status', '!=', KycDocument::STATUS_PENDING)->min('updated_at');
        $verifiedAt = $user->isKycApproved() ? $documents->where('status', KycDocument::STATUS_APPROVED)->max('verified_at') : null;

        return [
            'overall_status' => $overallStatus,
            'completion_percentage' => $user->kyc_completion_percentage,
            'submitted_at' => $submittedAt,
            'verified_at' => $verifiedAt,
            'document_counts' => $statusCounts,
            'required_documents' => $requiredDocuments,
            'submitted_documents' => $submittedTypes,
            'missing_documents' => $missingTypes,
            'next_steps' => $this->getNextSteps($user, $overallStatus, $missingTypes),
        ];
    }

    /**
     * Submit KYC documents for review.
     */
    public function submitForReview(User $user): array
    {
        $pendingDocuments = $user->kycDocuments()->pending()->get();

        if ($pendingDocuments->isEmpty()) {
            throw new \InvalidArgumentException('No pending documents found to submit for review');
        }

        DB::beginTransaction();
        try {
            // Update all pending documents to under review
            $user->kycDocuments()
                ->pending()
                ->update([
                    'status' => KycDocument::STATUS_UNDER_REVIEW,
                    'updated_at' => now(),
                ]);

            // Update user's overall KYC status
            $this->updateUserKycStatus($user);

            DB::commit();

            // Fire KYC submitted event
            event(new \App\Events\KycSubmittedForReview($user, $pendingDocuments));

            Log::info('KYC documents submitted for review', [
                'user_id' => $user->id,
                'document_count' => $pendingDocuments->count(),
            ]);

            return [
                'submitted_at' => now(),
                'documents_submitted' => $pendingDocuments->count(),
                'estimated_review_time' => '2-3 business days',
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('KYC submission for review failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Approve KYC document (admin function).
     */
    public function approveDocument(KycDocument $document, ?string $notes = null): bool
    {
        DB::beginTransaction();
        try {
            $document->update([
                'status' => KycDocument::STATUS_APPROVED,
                'verified_at' => now(),
                'rejection_reason' => null, // Clear any previous rejection reason
            ]);

            // Update user's overall KYC status
            $this->updateUserKycStatus($document->user);

            DB::commit();

            // Fire document approved event
            event(new \App\Events\KycDocumentApproved($document->user, $document, $notes));

            Log::info('KYC document approved', [
                'user_id' => $document->user_id,
                'document_id' => $document->id,
                'document_type' => $document->document_type,
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject KYC document (admin function).
     */
    public function rejectDocument(KycDocument $document, string $reason): bool
    {
        DB::beginTransaction();
        try {
            $document->update([
                'status' => KycDocument::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'verified_at' => null,
            ]);

            // Update user's overall KYC status
            $this->updateUserKycStatus($document->user);

            DB::commit();

            // Fire document rejected event
            event(new \App\Events\KycDocumentRejected($document->user, $document, $reason));

            Log::info('KYC document rejected', [
                'user_id' => $document->user_id,
                'document_id' => $document->id,
                'document_type' => $document->document_type,
                'reason' => $reason,
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get required document types.
     */
    protected function getRequiredDocumentTypes(): array
    {
        return config('kyc.required_documents', ['identity', 'proof_of_address']);
    }

    /**
     * Get next version number for document type.
     */
    protected function getNextVersion(User $user, string $documentType): int
    {
        $latestVersion = $user->kycDocuments()
            ->byType($documentType)
            ->where('status', '!=', KycDocument::STATUS_DELETED)
            ->max('version');

        return $latestVersion ? $latestVersion + 1 : 1;
    }

    /**
     * Mark previous versions as superseded.
     */
    protected function supersedePreviousVersions(User $user, string $documentType, int $excludeId): void
    {
        $user->kycDocuments()
            ->byType($documentType)
            ->where('id', '!=', $excludeId)
            ->where('status', '!=', KycDocument::STATUS_DELETED)
            ->update([
                'status' => KycDocument::STATUS_SUPERSEDED,
                'updated_at' => now(),
            ]);
    }

    /**
     * Update user's overall KYC status.
     */
    protected function updateUserKycStatus(User $user): void
    {
        // This could update a kyc_status field on users table if it exists
        // For now, the status is calculated dynamically via the User model
        
        // Optionally store last KYC update timestamp
        if (DB::getSchemaBuilder()->hasColumn('users', 'kyc_updated_at')) {
            $user->update(['kyc_updated_at' => now()]);
        }
    }

    /**
     * Get next steps for user based on KYC status.
     */
    protected function getNextSteps(User $user, string $overallStatus, array $missingTypes): array
    {
        switch ($overallStatus) {
            case 'not_started':
                return ['Upload required documents: ' . implode(', ', $this->getRequiredDocumentTypes())];
            
            case 'pending':
                if (!empty($missingTypes)) {
                    return ['Upload missing documents: ' . implode(', ', $missingTypes)];
                }
                return ['Submit documents for review'];
            
            case 'under_review':
                return ['Wait for document review to complete'];
            
            case 'rejected':
                $rejectedDocs = $user->kycDocuments()->rejected()->pluck('document_type')->toArray();
                return ['Resubmit rejected documents: ' . implode(', ', $rejectedDocs)];
            
            case 'approved':
                return ['KYC verification complete'];
            
            default:
                return ['Contact support for assistance'];
        }
    }

    /**
     * Validate document upload.
     */
    protected function validateDocumentUpload(UploadedFile $file, string $documentType): void
    {
        $validator = Validator::make([
            'document' => $file,
            'document_type' => $documentType,
        ], [
            'document' => [
                'required',
                'file',
                'mimes:pdf,jpeg,png,jpg',
                'max:10240', // 10MB max
            ],
            'document_type' => [
                'required',
                'string',
                'in:' . implode(',', array_keys(KycDocument::getDocumentTypes())),
            ],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}

