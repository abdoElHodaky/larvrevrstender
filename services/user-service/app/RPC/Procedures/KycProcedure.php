<?php

namespace App\RPC\Procedures;

use App\Models\KycDocument;
use App\RPC\BaseProcedure;
use App\Services\KycService;
use App\Services\UserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;

class KycProcedure extends BaseProcedure
{
    public function __construct(
        private UserService $userService,
        private KycService $kycService
    ) {}

    /**
     * Create a runtime exception conditionally based on Sajya availability
     */
    private function createRuntimeException(string $message, int $code = -32603, array $data = []): \Exception
    {
        if (class_exists('Sajya\Server\Exceptions\RuntimeException')) {
            return new \Sajya\Server\Exceptions\RuntimeException($message, $code, $data);
        }

        return new \Exception($message, $code);
    }

    /**
     * Upload KYC document via RPC
     */
    public function uploadDocument(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'document_type' => 'required|string|in:identity,passport,drivers_license,proof_of_address,business_registration,tax_certificate,bank_statement,utility_bill',
            'file_data' => 'required|string', // Base64 encoded file data
            'file_name' => 'required|string|max:255',
            'mime_type' => 'required|string|in:application/pdf,image/jpeg,image/png',
            'description' => 'sometimes|string|max:500',
        ]);

        return $this->executeWithLogging('KYC@uploadDocument', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for KYC uploads
            $key = 'kyc_upload:'.$params['user_id'];
            if (RateLimiter::tooManyAttempts($key, 10)) {
                throw $this->createRuntimeException(
                    'Too many KYC upload attempts. Please try again later.',
                    -32040,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            try {
                // Find user
                $user = $this->userService->getUserById($params['user_id']);
                if (! $user) {
                    throw $this->createRuntimeException(
                        'User not found',
                        -32041,
                        ['user_id' => $params['user_id']]
                    );
                }

                // Decode base64 file data
                $fileData = base64_decode($params['file_data']);
                if ($fileData === false) {
                    throw $this->createRuntimeException(
                        'Invalid file data encoding',
                        -32042,
                        ['user_id' => $params['user_id']]
                    );
                }

                // Create temporary file
                $tempPath = tempnam(sys_get_temp_dir(), 'kyc_');
                file_put_contents($tempPath, $fileData);

                // Create UploadedFile instance
                $uploadedFile = new UploadedFile(
                    $tempPath,
                    $params['file_name'],
                    $params['mime_type'],
                    null,
                    true
                );

                // Upload KYC document
                $document = $this->kycService->uploadDocument(
                    $user,
                    $uploadedFile,
                    $params['document_type'],
                    $params['description'] ?? null
                );

                // Clean up temp file
                unlink($tempPath);

                // Clear rate limiting on success
                RateLimiter::clear($key);

                return [
                    'success' => true,
                    'document' => [
                        'id' => $document->id,
                        'document_type' => $document->document_type,
                        'document_type_label' => $document->document_type_label,
                        'file_name' => $document->file_name,
                        'original_name' => $document->original_name,
                        'file_size' => $document->file_size,
                        'formatted_file_size' => $document->formatted_file_size,
                        'mime_type' => $document->mime_type,
                        'version' => $document->version,
                        'status' => $document->status,
                        'status_label' => $document->status_label,
                        'description' => $document->description,
                        'storage_provider' => $document->storage_provider,
                        'uploaded_at' => $document->created_at->toISOString(),
                    ],
                    'message' => 'KYC document uploaded successfully',
                    'uploaded_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                // Increment rate limiting on failure
                RateLimiter::hit($key, 300); // 5 minutes

                throw $this->createRuntimeException(
                    'KYC document upload failed: '.$e->getMessage(),
                    -32043,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Get user's KYC documents via RPC
     */
    public function getDocuments(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'document_type' => 'sometimes|string|in:identity,passport,drivers_license,proof_of_address,business_registration,tax_certificate,bank_statement,utility_bill',
            'status' => 'sometimes|string|in:pending,under_review,approved,rejected,resubmission_required,superseded',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        return $this->executeWithLogging('KYC@getDocuments', $params, function () use ($params) {
            try {
                // Find user
                $user = $this->userService->getUserById($params['user_id']);
                if (! $user) {
                    throw $this->createRuntimeException(
                        'User not found',
                        -32044,
                        ['user_id' => $params['user_id']]
                    );
                }

                // Get documents
                $documents = $this->kycService->getDocuments(
                    $user,
                    $params['document_type'] ?? null,
                    $params['status'] ?? null,
                    $params['per_page'] ?? 15,
                    $params['page'] ?? 1
                );

                return [
                    'success' => true,
                    'documents' => $documents->items(),
                    'pagination' => [
                        'current_page' => $documents->currentPage(),
                        'per_page' => $documents->perPage(),
                        'total' => $documents->total(),
                        'last_page' => $documents->lastPage(),
                        'has_more_pages' => $documents->hasMorePages(),
                    ],
                    'retrieved_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'Failed to retrieve KYC documents: '.$e->getMessage(),
                    -32045,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Delete KYC document via RPC
     */
    public function deleteDocument(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'document_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('KYC@deleteDocument', $params, function () use ($params) {
            try {
                // Find user
                $user = $this->userService->getUserById($params['user_id']);
                if (! $user) {
                    throw $this->createRuntimeException(
                        'User not found',
                        -32046,
                        ['user_id' => $params['user_id']]
                    );
                }

                // Delete document
                $deleted = $this->kycService->deleteDocument($user, $params['document_id']);

                if (! $deleted) {
                    throw $this->createRuntimeException(
                        'Failed to delete KYC document or document not found',
                        -32047,
                        ['user_id' => $params['user_id'], 'document_id' => $params['document_id']]
                    );
                }

                return [
                    'success' => true,
                    'message' => 'KYC document deleted successfully',
                    'deleted_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'KYC document deletion failed: '.$e->getMessage(),
                    -32048,
                    ['user_id' => $params['user_id'], 'document_id' => $params['document_id']]
                );
            }
        });
    }

    /**
     * Get comprehensive KYC status via RPC
     */
    public function getStatus(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('KYC@getStatus', $params, function () use ($params) {
            try {
                // Find user
                $user = $this->userService->getUserById($params['user_id']);
                if (! $user) {
                    throw $this->createRuntimeException(
                        'User not found',
                        -32049,
                        ['user_id' => $params['user_id']]
                    );
                }

                // Get KYC status
                $kycStatus = $this->kycService->getKycStatus($user);

                return [
                    'success' => true,
                    'kyc_status' => $kycStatus,
                    'retrieved_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'Failed to retrieve KYC status: '.$e->getMessage(),
                    -32050,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Submit KYC for review via RPC
     */
    public function submitForReview(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('KYC@submitForReview', $params, function () use ($params) {
            try {
                // Find user
                $user = $this->userService->getUserById($params['user_id']);
                if (! $user) {
                    throw $this->createRuntimeException(
                        'User not found',
                        -32051,
                        ['user_id' => $params['user_id']]
                    );
                }

                // Submit for review
                $submission = $this->kycService->submitForReview($user);

                return [
                    'success' => true,
                    'submission' => $submission,
                    'message' => 'KYC documents submitted for review successfully',
                    'submitted_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'KYC submission failed: '.$e->getMessage(),
                    -32052,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Admin approve KYC document via RPC
     */
    public function approveDocument(array $params): array
    {
        $this->validate($params, [
            'document_id' => 'required|integer|min:1',
            'admin_id' => 'required|integer|min:1',
            'notes' => 'sometimes|string|max:500',
        ]);

        return $this->executeWithLogging('KYC@approveDocument', $this->sanitizeForLogging($params), function () use ($params) {
            try {
                // Find document
                $document = KycDocument::find($params['document_id']);
                if (! $document) {
                    throw $this->createRuntimeException(
                        'KYC document not found',
                        -32053,
                        ['document_id' => $params['document_id']]
                    );
                }

                // Approve document
                $this->kycService->approveDocument($document, $params['notes'] ?? null);

                return [
                    'success' => true,
                    'document' => [
                        'id' => $document->id,
                        'document_type' => $document->document_type,
                        'status' => $document->status,
                        'status_label' => $document->status_label,
                        'verified_at' => $document->verified_at?->toISOString(),
                        'notes' => $params['notes'] ?? null,
                    ],
                    'message' => 'KYC document approved successfully',
                    'approved_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'KYC document approval failed: '.$e->getMessage(),
                    -32054,
                    ['document_id' => $params['document_id']]
                );
            }
        });
    }

    /**
     * Admin reject KYC document via RPC
     */
    public function rejectDocument(array $params): array
    {
        $this->validate($params, [
            'document_id' => 'required|integer|min:1',
            'admin_id' => 'required|integer|min:1',
            'rejection_reason' => 'required|string|max:500',
        ]);

        return $this->executeWithLogging('KYC@rejectDocument', $this->sanitizeForLogging($params), function () use ($params) {
            try {
                // Find document
                $document = KycDocument::find($params['document_id']);
                if (! $document) {
                    throw $this->createRuntimeException(
                        'KYC document not found',
                        -32055,
                        ['document_id' => $params['document_id']]
                    );
                }

                // Reject document
                $this->kycService->rejectDocument($document, $params['rejection_reason']);

                return [
                    'success' => true,
                    'document' => [
                        'id' => $document->id,
                        'document_type' => $document->document_type,
                        'status' => $document->status,
                        'status_label' => $document->status_label,
                        'rejection_reason' => $document->rejection_reason,
                        'rejected_at' => now()->toISOString(),
                    ],
                    'message' => 'KYC document rejected successfully',
                    'rejected_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'KYC document rejection failed: '.$e->getMessage(),
                    -32056,
                    ['document_id' => $params['document_id']]
                );
            }
        });
    }

    /**
     * Get KYC document types and statuses
     */
    public function getMetadata(array $params): array
    {
        return $this->executeWithLogging('KYC@getMetadata', $params, function () {
            return [
                'success' => true,
                'metadata' => [
                    'document_types' => KycDocument::getDocumentTypes(),
                    'statuses' => KycDocument::getStatuses(),
                    'required_documents' => $this->kycService->getRequiredDocumentTypes(),
                ],
                'retrieved_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get KYC statistics for admin dashboard
     */
    public function getStatistics(array $params): array
    {
        $this->validate($params, [
            'period' => 'sometimes|string|in:day,week,month,quarter,year',
            'admin_id' => 'required|integer|min:1', // Only admins can access statistics
        ]);

        return $this->executeWithLogging('KYC@getStatistics', $params, function () use ($params) {
            try {
                $period = $params['period'] ?? 'month';

                // Get statistics from the database
                $stats = [
                    'total_documents' => KycDocument::count(),
                    'pending_documents' => KycDocument::pending()->count(),
                    'under_review_documents' => KycDocument::underReview()->count(),
                    'approved_documents' => KycDocument::approved()->count(),
                    'rejected_documents' => KycDocument::rejected()->count(),
                    'documents_by_type' => KycDocument::selectRaw('document_type, count(*) as count')
                        ->groupBy('document_type')
                        ->pluck('count', 'document_type')
                        ->toArray(),
                    'recent_uploads' => KycDocument::where('created_at', '>=', now()->sub($period, 1))
                        ->count(),
                ];

                return [
                    'success' => true,
                    'statistics' => $stats,
                    'period' => $period,
                    'retrieved_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw $this->createRuntimeException(
                    'Failed to retrieve KYC statistics: '.$e->getMessage(),
                    -32057,
                    ['period' => $params['period'] ?? 'month']
                );
            }
        });
    }
}
