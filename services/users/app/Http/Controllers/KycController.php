<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Shared\Services\FileUploadService;

class KycController extends Controller
{
    protected FileUploadService $fileUploadService;

    // KYC document types
    const DOCUMENT_TYPES = [
        'identity' => 'Identity Document',
        'passport' => 'Passport',
        'drivers_license' => 'Driver\'s License',
        'proof_of_address' => 'Proof of Address',
        'business_registration' => 'Business Registration',
        'tax_certificate' => 'Tax Certificate',
        'bank_statement' => 'Bank Statement',
        'utility_bill' => 'Utility Bill',
    ];

    // KYC status constants
    const STATUS_PENDING = 'pending';

    const STATUS_UNDER_REVIEW = 'under_review';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    const STATUS_RESUBMISSION_REQUIRED = 'resubmission_required';

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Upload KYC document
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document' => 'required|file|mimes:pdf,jpeg,png,jpg|max:10240', // 10MB max
            'document_type' => 'required|string|in:'.implode(',', array_keys(self::DOCUMENT_TYPES)),
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $document = $request->file('document');
            $documentType = $request->input('document_type');
            $description = $request->input('description');

            // Check if user already has this document type (for versioning)
            $existingDocument = DB::table('kyc_documents')
                ->where('user_id', $user->id)
                ->where('document_type', $documentType)
                ->where('status', '!=', 'deleted')
                ->orderBy('version', 'desc')
                ->first();

            $version = $existingDocument ? $existingDocument->version + 1 : 1;

            // Upload document to cloud storage
            $result = $this->fileUploadService->upload(
                $document,
                'user-service/kyc-documents/'.$user->id.'/'.$documentType,
                [
                    'optimize' => false, // Don't optimize documents to preserve quality
                    'encrypt' => true,   // Encrypt sensitive KYC documents
                ]
            );

            // Store document metadata in database
            $documentData = [
                'user_id' => $user->id,
                'document_type' => $documentType,
                'file_path' => $result['path'],
                'file_name' => $result['filename'],
                'original_name' => $document->getClientOriginalName(),
                'file_size' => $result['size'],
                'mime_type' => $document->getMimeType(),
                'storage_provider' => $result['provider'] ?? 's3',
                'url' => $result['url'],
                'description' => $description,
                'version' => $version,
                'status' => self::STATUS_PENDING,
                'encryption_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $documentId = DB::table('kyc_documents')->insertGetId($documentData);

            // Mark previous versions as superseded if this is a resubmission
            if ($existingDocument) {
                DB::table('kyc_documents')
                    ->where('user_id', $user->id)
                    ->where('document_type', $documentType)
                    ->where('id', '!=', $documentId)
                    ->update(['status' => 'superseded', 'updated_at' => now()]);
            }

            // Update user's KYC status if needed
            $this->updateUserKycStatus($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'document_id' => $documentId,
                    'document_type' => $documentType,
                    'document_type_label' => self::DOCUMENT_TYPES[$documentType],
                    'file_size' => $result['size'],
                    'version' => $version,
                    'status' => self::STATUS_PENDING,
                    'uploaded_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('KYC document upload failed: '.$e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'document_type' => $request->input('document_type'),
                'file_name' => $document->getClientOriginalName() ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Document upload failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get user's KYC documents
     */
    public function getDocuments(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $documents = DB::table('kyc_documents')
                ->where('user_id', $user->id)
                ->where('status', '!=', 'deleted')
                ->orderBy('document_type')
                ->orderBy('version', 'desc')
                ->get()
                ->groupBy('document_type')
                ->map(function ($docs) {
                    return $docs->first(); // Get latest version of each document type
                });

            $formattedDocuments = $documents->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type,
                    'document_type_label' => self::DOCUMENT_TYPES[$doc->document_type] ?? $doc->document_type,
                    'original_name' => $doc->original_name,
                    'file_size' => $doc->file_size,
                    'version' => $doc->version,
                    'status' => $doc->status,
                    'description' => $doc->description,
                    'uploaded_at' => $doc->created_at,
                    'verified_at' => $doc->verified_at,
                    'rejection_reason' => $doc->rejection_reason,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $formattedDocuments,
            ]);

        } catch (\Exception $e) {
            Log::error('Get KYC documents failed: '.$e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve documents',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get KYC status
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Get user's KYC status from user table or separate kyc_status table
            $kycStatus = DB::table('users')
                ->where('id', $user->id)
                ->value('kyc_status') ?? self::STATUS_PENDING;

            // Get document counts
            $documentCounts = DB::table('kyc_documents')
                ->where('user_id', $user->id)
                ->where('status', '!=', 'deleted')
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            // Get required document types (this could be configurable)
            $requiredDocuments = ['identity', 'proof_of_address'];
            $uploadedDocuments = DB::table('kyc_documents')
                ->where('user_id', $user->id)
                ->where('status', '!=', 'deleted')
                ->whereIn('document_type', $requiredDocuments)
                ->distinct()
                ->pluck('document_type')
                ->toArray();

            $missingDocuments = array_diff($requiredDocuments, $uploadedDocuments);

            return response()->json([
                'success' => true,
                'data' => [
                    'kyc_status' => $kycStatus,
                    'document_counts' => $documentCounts,
                    'required_documents' => $requiredDocuments,
                    'uploaded_documents' => $uploadedDocuments,
                    'missing_documents' => $missingDocuments,
                    'completion_percentage' => count($uploadedDocuments) / count($requiredDocuments) * 100,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get KYC status failed: '.$e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve KYC status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Submit KYC for review
     */
    public function submit(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if user has uploaded required documents
            $requiredDocuments = ['identity', 'proof_of_address'];
            $uploadedDocuments = DB::table('kyc_documents')
                ->where('user_id', $user->id)
                ->where('status', '!=', 'deleted')
                ->whereIn('document_type', $requiredDocuments)
                ->distinct()
                ->pluck('document_type')
                ->toArray();

            $missingDocuments = array_diff($requiredDocuments, $uploadedDocuments);

            if (! empty($missingDocuments)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required documents',
                    'missing_documents' => $missingDocuments,
                ], 422);
            }

            // Update user's KYC status to under review
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'kyc_status' => self::STATUS_UNDER_REVIEW,
                    'kyc_submitted_at' => now(),
                    'updated_at' => now(),
                ]);

            // Update all pending documents to under review
            DB::table('kyc_documents')
                ->where('user_id', $user->id)
                ->where('status', self::STATUS_PENDING)
                ->update([
                    'status' => self::STATUS_UNDER_REVIEW,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'KYC submitted for review successfully',
                'data' => [
                    'kyc_status' => self::STATUS_UNDER_REVIEW,
                    'submitted_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('KYC submission failed: '.$e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'KYC submission failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete a KYC document
     */
    public function deleteDocument(Request $request, int $documentId): JsonResponse
    {
        try {
            $user = $request->user();

            // Get the document
            $document = DB::table('kyc_documents')
                ->where('id', $documentId)
                ->where('user_id', $user->id)
                ->where('status', '!=', 'deleted')
                ->first();

            if (! $document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found',
                ], 404);
            }

            // Delete from cloud storage
            try {
                $this->fileUploadService->delete($document->file_path);
            } catch (\Exception $e) {
                Log::warning('Failed to delete KYC document from cloud storage: '.$e->getMessage(), [
                    'user_id' => $user->id,
                    'document_id' => $documentId,
                    'file_path' => $document->file_path,
                ]);
            }

            // Mark as deleted in database (soft delete for audit trail)
            DB::table('kyc_documents')
                ->where('id', $documentId)
                ->update([
                    'status' => 'deleted',
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);

            // Update user's KYC status
            $this->updateUserKycStatus($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('KYC document deletion failed: '.$e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'document_id' => $documentId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Document deletion failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update user's overall KYC status based on document statuses
     */
    private function updateUserKycStatus(int $userId): void
    {
        $documentStatuses = DB::table('kyc_documents')
            ->where('user_id', $userId)
            ->where('status', '!=', 'deleted')
            ->pluck('status')
            ->unique()
            ->toArray();

        $overallStatus = self::STATUS_PENDING;

        if (in_array(self::STATUS_REJECTED, $documentStatuses)) {
            $overallStatus = self::STATUS_REJECTED;
        } elseif (in_array(self::STATUS_RESUBMISSION_REQUIRED, $documentStatuses)) {
            $overallStatus = self::STATUS_RESUBMISSION_REQUIRED;
        } elseif (in_array(self::STATUS_UNDER_REVIEW, $documentStatuses)) {
            $overallStatus = self::STATUS_UNDER_REVIEW;
        } elseif (count($documentStatuses) === 1 && $documentStatuses[0] === self::STATUS_APPROVED) {
            $overallStatus = self::STATUS_APPROVED;
        }

        DB::table('users')
            ->where('id', $userId)
            ->update([
                'kyc_status' => $overallStatus,
                'updated_at' => now(),
            ]);
    }
}
