<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\CustomerProfile;
use App\Models\KycDocument;
use Shared\Jobs\BaseQueueJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * User Profile Validation Job with Laravel Fuse Circuit Breaker Protection
 * 
 * Validates user profiles, KYC documents, and customer information for compliance
 * and completeness. This is critical for user onboarding, regulatory compliance,
 * and maintaining platform trust and security standards.
 */
class ProcessUserProfileValidationJob extends BaseQueueJob
{
    public array $userIds;
    public array $validationTypes;
    public array $validationRules;
    public int $batchSize;
    public int $tries = 3;
    public int $timeout = 900; // 15 minutes for profile validation

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $userIds,
        array $validationTypes = [],
        array $validationRules = [],
        int $batchSize = 50
    ) {
        // Initialize parent with circuit breaker configuration
        parent::__construct();
        
        $this->userIds = $userIds;
        $this->validationTypes = $validationTypes ?: $this->getDefaultValidationTypes();
        $this->validationRules = array_merge($this->getDefaultValidationRules(), $validationRules);
        $this->batchSize = $batchSize;
        
        // Set queue based on batch size priority
        $this->onQueue($this->getQueueForBatchSize($batchSize));
        
        // Configure circuit breaker for user profile validation
        $this->configureCircuitBreaker([
            'service_name' => 'user_profile_validation',
            'failure_threshold' => 35, // 35% failure rate triggers circuit breaker
            'timeout' => 180, // 3 minutes timeout for validation operations
            'recovery_timeout' => 600, // 10 minutes before attempting recovery
            'tags' => [
                'service' => 'user-service',
                'job_type' => 'validation',
                'operation' => 'profile_validation',
                'priority' => 'high'
            ]
        ]);
    }

    /**
     * Execute the job with circuit breaker protection.
     */
    public function handle(): void
    {
        Log::info('Starting user profile validation with circuit breaker protection', [
            'user_count' => count($this->userIds),
            'validation_types' => $this->validationTypes,
            'batch_size' => $this->batchSize,
            'job_id' => $this->job?->getJobId(),
            'circuit_breaker_service' => 'user_profile_validation'
        ]);

        // Execute with circuit breaker protection
        $this->executeWithCircuitBreaker(function() {
            $results = [
                'processed' => 0,
                'validated' => 0,
                'failed' => 0,
                'requires_review' => 0,
                'errors' => []
            ];

            // Process users in chunks to manage memory
            $chunks = array_chunk($this->userIds, $this->batchSize);
            
            foreach ($chunks as $chunkIndex => $chunk) {
                Log::debug('Processing user validation chunk', [
                    'chunk_index' => $chunkIndex + 1,
                    'chunk_size' => count($chunk),
                    'total_chunks' => count($chunks)
                ]);

                $chunkResults = $this->processUserChunk($chunk);
                
                // Aggregate results
                $results['processed'] += $chunkResults['processed'];
                $results['validated'] += $chunkResults['validated'];
                $results['failed'] += $chunkResults['failed'];
                $results['requires_review'] += $chunkResults['requires_review'];
                $results['errors'] = array_merge($results['errors'], $chunkResults['errors']);
            }

            Log::info('User profile validation completed successfully', [
                'total_processed' => $results['processed'],
                'validated' => $results['validated'],
                'failed' => $results['failed'],
                'requires_review' => $results['requires_review'],
                'success_rate' => $results['processed'] > 0 ? 
                    round(($results['validated'] / $results['processed']) * 100, 2) : 0,
                'job_id' => $this->job?->getJobId()
            ]);

            return $results;
        }, function(\Exception $e) {
            // Circuit breaker failure handler
            Log::error('User profile validation failed with circuit breaker protection', [
                'user_count' => count($this->userIds),
                'validation_types' => $this->validationTypes,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job?->getJobId(),
            ]);

            throw $e;
        });
    }

    /**
     * Process a chunk of users
     */
    private function processUserChunk(array $userIds): array
    {
        $results = [
            'processed' => 0,
            'validated' => 0,
            'failed' => 0,
            'requires_review' => 0,
            'errors' => []
        ];

        // Load users with their profiles and documents
        $users = User::with(['customerProfile', 'kycDocuments'])
            ->whereIn('id', $userIds)
            ->get();

        foreach ($users as $user) {
            try {
                $validationResult = $this->validateUserProfile($user);
                
                $results['processed']++;
                
                match ($validationResult['status']) {
                    'validated' => $results['validated']++,
                    'requires_review' => $results['requires_review']++,
                    'failed' => [
                        $results['failed']++,
                        $results['errors'][] = [
                            'user_id' => $user->id,
                            'error' => $validationResult['message'] ?? 'Validation failed'
                        ]
                    ],
                    default => null
                };

            } catch (\Exception $e) {
                $results['processed']++;
                $results['failed']++;
                $results['errors'][] = [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ];

                Log::error('Failed to validate user profile', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return $results;
    }

    /**
     * Validate a single user profile
     */
    private function validateUserProfile(User $user): array
    {
        $startTime = microtime(true);
        $validationResults = [];
        $overallStatus = 'validated';
        $issues = [];

        Log::debug('Validating user profile', [
            'user_id' => $user->id,
            'email' => $user->email,
            'validation_types' => $this->validationTypes
        ]);

        foreach ($this->validationTypes as $validationType) {
            $result = $this->performValidationType($user, $validationType);
            $validationResults[$validationType] = $result;
            
            if (!$result['passed']) {
                $issues[] = $result['message'];
                
                if ($result['severity'] === 'critical') {
                    $overallStatus = 'failed';
                } elseif ($result['severity'] === 'warning' && $overallStatus !== 'failed') {
                    $overallStatus = 'requires_review';
                }
            }
        }

        $processingTime = (microtime(true) - $startTime) * 1000;

        // Update user profile validation status
        $this->updateValidationStatus($user, $overallStatus, $validationResults, $issues);

        Log::info('User profile validation completed', [
            'user_id' => $user->id,
            'status' => $overallStatus,
            'issues_count' => count($issues),
            'processing_time_ms' => round($processingTime)
        ]);

        return [
            'status' => $overallStatus,
            'message' => implode('; ', $issues),
            'validation_results' => $validationResults,
            'processing_time_ms' => round($processingTime)
        ];
    }

    /**
     * Perform a specific validation type
     */
    private function performValidationType(User $user, string $validationType): array
    {
        switch ($validationType) {
            case 'basic_profile':
                return $this->validateBasicProfile($user);
            
            case 'contact_verification':
                return $this->validateContactVerification($user);
            
            case 'customer_profile':
                return $this->validateCustomerProfile($user);
            
            case 'kyc_documents':
                return $this->validateKycDocuments($user);
            
            case 'profile_completeness':
                return $this->validateProfileCompleteness($user);
            
            case 'data_consistency':
                return $this->validateDataConsistency($user);
            
            default:
                return [
                    'passed' => false,
                    'severity' => 'warning',
                    'message' => "Unknown validation type: {$validationType}"
                ];
        }
    }

    /**
     * Validate basic profile information
     */
    private function validateBasicProfile(User $user): array
    {
        $issues = [];
        
        // Required fields validation
        if (empty($user->name)) {
            $issues[] = 'Name is required';
        }
        
        if (empty($user->email)) {
            $issues[] = 'Email is required';
        } elseif (!filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            $issues[] = 'Invalid email format';
        }
        
        if (empty($user->phone)) {
            $issues[] = 'Phone number is required';
        } elseif (!preg_match('/^\+?[1-9]\d{1,14}$/', $user->phone)) {
            $issues[] = 'Invalid phone number format';
        }
        
        // Status validation
        if (!in_array($user->status, ['active', 'pending', 'suspended'])) {
            $issues[] = 'Invalid user status';
        }

        return [
            'passed' => empty($issues),
            'severity' => empty($issues) ? 'info' : 'critical',
            'message' => implode(', ', $issues)
        ];
    }

    /**
     * Validate contact verification status
     */
    private function validateContactVerification(User $user): array
    {
        $issues = [];
        
        if (!$user->email_verified_at) {
            $issues[] = 'Email not verified';
        }
        
        if (!$user->phone_verified_at) {
            $issues[] = 'Phone not verified';
        }

        return [
            'passed' => empty($issues),
            'severity' => empty($issues) ? 'info' : 'warning',
            'message' => implode(', ', $issues)
        ];
    }

    /**
     * Validate customer profile information
     */
    private function validateCustomerProfile(User $user): array
    {
        $issues = [];
        $profile = $user->customerProfile;
        
        if (!$profile) {
            return [
                'passed' => false,
                'severity' => 'critical',
                'message' => 'Customer profile not found'
            ];
        }
        
        // Required profile fields
        if (empty($profile->national_id)) {
            $issues[] = 'National ID is required';
        }
        
        if (empty($profile->date_of_birth)) {
            $issues[] = 'Date of birth is required';
        } elseif ($profile->date_of_birth->age < 18) {
            $issues[] = 'User must be at least 18 years old';
        }
        
        if (empty($profile->gender)) {
            $issues[] = 'Gender is required';
        }
        
        // Verification status
        if ($profile->verification_status === CustomerProfile::STATUS_REJECTED) {
            $issues[] = 'Profile verification was rejected';
        }

        return [
            'passed' => empty($issues),
            'severity' => empty($issues) ? 'info' : 'warning',
            'message' => implode(', ', $issues)
        ];
    }

    /**
     * Validate KYC documents
     */
    private function validateKycDocuments(User $user): array
    {
        $issues = [];
        $documents = $user->kycDocuments;
        
        if ($documents->isEmpty()) {
            return [
                'passed' => false,
                'severity' => 'warning',
                'message' => 'No KYC documents uploaded'
            ];
        }
        
        // Required document types
        $requiredTypes = [
            KycDocument::TYPE_IDENTITY,
            KycDocument::TYPE_PROOF_OF_ADDRESS
        ];
        
        $uploadedTypes = $documents->pluck('document_type')->unique()->toArray();
        $missingTypes = array_diff($requiredTypes, $uploadedTypes);
        
        if (!empty($missingTypes)) {
            $issues[] = 'Missing required documents: ' . implode(', ', $missingTypes);
        }
        
        // Check document statuses
        $rejectedDocs = $documents->where('status', KycDocument::STATUS_REJECTED);
        if ($rejectedDocs->isNotEmpty()) {
            $issues[] = 'Some documents were rejected';
        }
        
        $pendingDocs = $documents->where('status', KycDocument::STATUS_PENDING);
        if ($pendingDocs->isNotEmpty()) {
            $issues[] = 'Some documents are still pending review';
        }

        return [
            'passed' => empty($issues),
            'severity' => empty($issues) ? 'info' : 'warning',
            'message' => implode(', ', $issues)
        ];
    }

    /**
     * Validate profile completeness
     */
    private function validateProfileCompleteness(User $user): array
    {
        $completeness = 0;
        $totalFields = 0;
        
        // Basic user fields
        $userFields = ['name', 'email', 'phone', 'email_verified_at', 'phone_verified_at'];
        foreach ($userFields as $field) {
            $totalFields++;
            if (!empty($user->$field)) {
                $completeness++;
            }
        }
        
        // Customer profile fields
        if ($user->customerProfile) {
            $profileFields = ['national_id', 'date_of_birth', 'gender', 'occupation'];
            foreach ($profileFields as $field) {
                $totalFields++;
                if (!empty($user->customerProfile->$field)) {
                    $completeness++;
                }
            }
        }
        
        $completenessPercentage = $totalFields > 0 ? ($completeness / $totalFields) * 100 : 0;
        $threshold = $this->validationRules['completeness_threshold'] ?? 80;
        
        return [
            'passed' => $completenessPercentage >= $threshold,
            'severity' => $completenessPercentage >= $threshold ? 'info' : 'warning',
            'message' => "Profile completeness: {$completenessPercentage}% (threshold: {$threshold}%)"
        ];
    }

    /**
     * Validate data consistency
     */
    private function validateDataConsistency(User $user): array
    {
        $issues = [];
        
        // Email consistency
        if ($user->email && $user->customerProfile) {
            // Check if email domain is consistent with profile data
            $emailDomain = substr(strrchr($user->email, "@"), 1);
            if (in_array($emailDomain, ['tempmail.com', '10minutemail.com'])) {
                $issues[] = 'Temporary email address detected';
            }
        }
        
        // Phone consistency
        if ($user->phone && $user->customerProfile && $user->customerProfile->national_address) {
            // Basic phone/address country consistency check
            $phoneCountryCode = substr($user->phone, 0, 3);
            // This is a simplified check - in real implementation, use proper phone validation library
        }
        
        // Age consistency with documents
        if ($user->customerProfile && $user->customerProfile->date_of_birth) {
            $age = $user->customerProfile->date_of_birth->age;
            if ($age < 18 || $age > 120) {
                $issues[] = 'Age appears to be invalid';
            }
        }

        return [
            'passed' => empty($issues),
            'severity' => empty($issues) ? 'info' : 'warning',
            'message' => implode(', ', $issues)
        ];
    }

    /**
     * Update validation status in database
     */
    private function updateValidationStatus(User $user, string $status, array $results, array $issues): void
    {
        try {
            // Update customer profile if exists
            if ($user->customerProfile) {
                $user->customerProfile->update([
                    'verification_status' => $this->mapStatusToVerificationStatus($status),
                    'verification_updated_at' => now(),
                    'verification_notes' => implode('; ', $issues),
                    'metadata' => array_merge(
                        $user->customerProfile->metadata ?? [],
                        [
                            'last_validation' => [
                                'timestamp' => now()->toISOString(),
                                'status' => $status,
                                'results' => $results,
                                'job_id' => $this->job?->getJobId()
                            ]
                        ]
                    )
                ]);
            }
            
            // Update user status if critical failure
            if ($status === 'failed') {
                $user->update(['status' => 'suspended']);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to update validation status', [
                'user_id' => $user->id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Map validation status to customer profile verification status
     */
    private function mapStatusToVerificationStatus(string $status): string
    {
        return match ($status) {
            'validated' => CustomerProfile::STATUS_APPROVED,
            'requires_review' => CustomerProfile::STATUS_REQUIRES_REVIEW,
            'failed' => CustomerProfile::STATUS_REJECTED,
            default => CustomerProfile::STATUS_PENDING,
        };
    }

    /**
     * Get default validation types
     */
    private function getDefaultValidationTypes(): array
    {
        return [
            'basic_profile',
            'contact_verification',
            'customer_profile',
            'kyc_documents',
            'profile_completeness',
            'data_consistency'
        ];
    }

    /**
     * Get default validation rules
     */
    private function getDefaultValidationRules(): array
    {
        return [
            'completeness_threshold' => 80, // 80% profile completeness required
            'min_age' => 18,
            'max_age' => 120,
            'required_documents' => [
                KycDocument::TYPE_IDENTITY,
                KycDocument::TYPE_PROOF_OF_ADDRESS
            ]
        ];
    }

    /**
     * Get queue name based on batch size
     */
    private function getQueueForBatchSize(int $batchSize): string
    {
        return match (true) {
            $batchSize >= 200 => 'user-validation-large',
            $batchSize >= 100 => 'user-validation-medium',
            $batchSize >= 50 => 'user-validation-small',
            default => 'user-validation-default',
        };
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('User profile validation job failed permanently', [
            'user_count' => count($this->userIds),
            'validation_types' => $this->validationTypes,
            'batch_size' => $this->batchSize,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job?->getJobId(),
        ]);

        // Could broadcast failure event for monitoring
        // broadcast(new \App\Events\User\ProfileValidationFailed(...));
    }
}
