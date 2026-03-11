<?php

namespace App\Jobs;

use Shared\Jobs\BaseQueueJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Application Log Rotation Job with Laravel Fuse Circuit Breaker Protection
 * 
 * Manages log file rotation, compression, and cleanup to prevent disk space issues
 * and maintain system performance. Critical for operational stability and
 * preventing storage-related outages across all microservices.
 */
class RotateApplicationLogsJob extends BaseQueueJob
{
    public array $logTypes;
    public array $rotationOptions;
    public int $tries = 3;
    public int $timeout = 1800; // 30 minutes for log operations

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $logTypes = [],
        array $rotationOptions = []
    ) {
        parent::__construct();
        
        $this->logTypes = $logTypes ?: $this->getDefaultLogTypes();
        $this->rotationOptions = array_merge($this->getDefaultRotationOptions(), $rotationOptions);
        
        // Set queue for log operations
        $this->onQueue('log-rotation');
        
        // Configure circuit breaker for log rotation
        $this->configureCircuitBreaker([
            'service_name' => 'log_rotation',
            'failure_threshold' => 30, // 30% failure rate triggers circuit breaker
            'timeout' => 600, // 10 minutes timeout for log operations
            'recovery_timeout' => 900, // 15 minutes before attempting recovery
            'tags' => [
                'service' => 'shared-service',
                'job_type' => 'maintenance',
                'operation' => 'log_rotation',
                'priority' => 'medium'
            ]
        ]);
    }

    /**
     * Execute the job with circuit breaker protection.
     */
    public function handle(): void
    {
        Log::info('Starting log rotation with circuit breaker protection', [
            'log_types' => $this->logTypes,
            'rotation_options' => $this->rotationOptions,
            'job_id' => $this->job?->getJobId(),
            'circuit_breaker_service' => 'log_rotation'
        ]);

        $this->executeWithCircuitBreaker(function() {
            $results = [
                'log_types_processed' => 0,
                'files_rotated' => 0,
                'files_compressed' => 0,
                'files_deleted' => 0,
                'space_freed_mb' => 0,
                'processing_time_ms' => 0,
                'errors' => []
            ];

            $startTime = microtime(true);

            foreach ($this->logTypes as $logType) {
                try {
                    $rotationResult = $this->rotateLogType($logType);
                    
                    $results['log_types_processed']++;
                    $results['files_rotated'] += $rotationResult['files_rotated'];
                    $results['files_compressed'] += $rotationResult['files_compressed'];
                    $results['files_deleted'] += $rotationResult['files_deleted'];
                    $results['space_freed_mb'] += $rotationResult['space_freed_mb'];
                    
                    Log::debug('Log type rotated successfully', [
                        'log_type' => $logType,
                        'files_rotated' => $rotationResult['files_rotated'],
                        'space_freed_mb' => $rotationResult['space_freed_mb']
                    ]);
                    
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'log_type' => $logType,
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('Failed to rotate log type', [
                        'log_type' => $logType,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $results['processing_time_ms'] = round((microtime(true) - $startTime) * 1000);

            Log::info('Log rotation completed successfully', [
                'log_types_processed' => $results['log_types_processed'],
                'total_files_rotated' => $results['files_rotated'],
                'total_space_freed_mb' => $results['space_freed_mb'],
                'processing_time_ms' => $results['processing_time_ms'],
                'job_id' => $this->job?->getJobId()
            ]);

            return $results;
        }, function(\Exception $e) {
            Log::error('Log rotation failed with circuit breaker protection', [
                'log_types' => $this->logTypes,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job?->getJobId(),
            ]);

            throw $e;
        });
    }

    /**
     * Rotate logs for a specific type
     */
    private function rotateLogType(string $logType): array
    {
        $filesRotated = 0;
        $filesCompressed = 0;
        $filesDeleted = 0;
        $spaceFreedMb = 0;

        Log::debug('Starting log rotation for type', [
            'log_type' => $logType,
            'rotation_options' => $this->rotationOptions
        ]);

        return match ($logType) {
            'application_logs' => $this->rotateApplicationLogs(),
            'access_logs' => $this->rotateAccessLogs(),
            'error_logs' => $this->rotateErrorLogs(),
            'audit_logs' => $this->rotateAuditLogs(),
            'performance_logs' => $this->rotatePerformanceLogs(),
            'security_logs' => $this->rotateSecurityLogs(),
            default => throw new \InvalidArgumentException("Unknown log type: {$logType}")
        };
    }

    /**
     * Rotate application logs
     */
    private function rotateApplicationLogs(): array
    {
        $logPath = storage_path('logs');
        $pattern = 'laravel*.log';
        
        return $this->performLogRotation($logPath, $pattern, [
            'max_age_days' => $this->rotationOptions['application_logs_retention_days'],
            'max_size_mb' => $this->rotationOptions['max_file_size_mb'],
            'compress_after_days' => $this->rotationOptions['compress_after_days']
        ]);
    }

    /**
     * Rotate access logs
     */
    private function rotateAccessLogs(): array
    {
        $logPath = storage_path('logs/access');
        $pattern = 'access*.log';
        
        return $this->performLogRotation($logPath, $pattern, [
            'max_age_days' => $this->rotationOptions['access_logs_retention_days'],
            'max_size_mb' => $this->rotationOptions['max_file_size_mb'],
            'compress_after_days' => $this->rotationOptions['compress_after_days']
        ]);
    }

    /**
     * Rotate error logs
     */
    private function rotateErrorLogs(): array
    {
        $logPath = storage_path('logs/errors');
        $pattern = 'error*.log';
        
        return $this->performLogRotation($logPath, $pattern, [
            'max_age_days' => $this->rotationOptions['error_logs_retention_days'],
            'max_size_mb' => $this->rotationOptions['max_file_size_mb'],
            'compress_after_days' => $this->rotationOptions['compress_after_days']
        ]);
    }

    /**
     * Rotate audit logs
     */
    private function rotateAuditLogs(): array
    {
        $logPath = storage_path('logs/audit');
        $pattern = 'audit*.log';
        
        return $this->performLogRotation($logPath, $pattern, [
            'max_age_days' => $this->rotationOptions['audit_logs_retention_days'],
            'max_size_mb' => $this->rotationOptions['max_file_size_mb'],
            'compress_after_days' => $this->rotationOptions['compress_after_days']
        ]);
    }

    /**
     * Rotate performance logs
     */
    private function rotatePerformanceLogs(): array
    {
        $logPath = storage_path('logs/performance');
        $pattern = 'performance*.log';
        
        return $this->performLogRotation($logPath, $pattern, [
            'max_age_days' => $this->rotationOptions['performance_logs_retention_days'],
            'max_size_mb' => $this->rotationOptions['max_file_size_mb'],
            'compress_after_days' => $this->rotationOptions['compress_after_days']
        ]);
    }

    /**
     * Rotate security logs
     */
    private function rotateSecurityLogs(): array
    {
        $logPath = storage_path('logs/security');
        $pattern = 'security*.log';
        
        return $this->performLogRotation($logPath, $pattern, [
            'max_age_days' => $this->rotationOptions['security_logs_retention_days'],
            'max_size_mb' => $this->rotationOptions['max_file_size_mb'],
            'compress_after_days' => $this->rotationOptions['compress_after_days']
        ]);
    }

    /**
     * Perform log rotation for a specific path and pattern
     */
    private function performLogRotation(string $logPath, string $pattern, array $options): array
    {
        $filesRotated = 0;
        $filesCompressed = 0;
        $filesDeleted = 0;
        $spaceFreedMb = 0;

        if (!File::exists($logPath)) {
            File::makeDirectory($logPath, 0755, true);
            return [
                'files_rotated' => 0,
                'files_compressed' => 0,
                'files_deleted' => 0,
                'space_freed_mb' => 0
            ];
        }

        $logFiles = File::glob($logPath . '/' . $pattern);
        $cutoffDate = now()->subDays($options['max_age_days']);
        $compressDate = now()->subDays($options['compress_after_days']);

        foreach ($logFiles as $logFile) {
            try {
                $fileInfo = new \SplFileInfo($logFile);
                $fileModTime = Carbon::createFromTimestamp($fileInfo->getMTime());
                $fileSizeMb = $fileInfo->getSize() / 1024 / 1024;

                // Delete old files
                if ($fileModTime->lt($cutoffDate)) {
                    $spaceFreedMb += $fileSizeMb;
                    File::delete($logFile);
                    $filesDeleted++;
                    
                    Log::debug('Deleted old log file', [
                        'file' => $logFile,
                        'age_days' => $fileModTime->diffInDays(now()),
                        'size_mb' => round($fileSizeMb, 2)
                    ]);
                    continue;
                }

                // Compress files older than compress threshold
                if ($fileModTime->lt($compressDate) && !str_ends_with($logFile, '.gz')) {
                    $compressedFile = $this->compressLogFile($logFile);
                    if ($compressedFile) {
                        $filesCompressed++;
                        $originalSize = $fileSizeMb;
                        $compressedSize = filesize($compressedFile) / 1024 / 1024;
                        $spaceFreedMb += ($originalSize - $compressedSize);
                        
                        Log::debug('Compressed log file', [
                            'original_file' => $logFile,
                            'compressed_file' => $compressedFile,
                            'original_size_mb' => round($originalSize, 2),
                            'compressed_size_mb' => round($compressedSize, 2),
                            'compression_ratio' => round(($compressedSize / $originalSize) * 100, 1) . '%'
                        ]);
                    }
                }

                // Rotate large files
                if ($fileSizeMb > $options['max_size_mb']) {
                    $rotatedFile = $this->rotateLogFile($logFile);
                    if ($rotatedFile) {
                        $filesRotated++;
                        
                        Log::debug('Rotated large log file', [
                            'original_file' => $logFile,
                            'rotated_file' => $rotatedFile,
                            'size_mb' => round($fileSizeMb, 2)
                        ]);
                    }
                }

            } catch (\Exception $e) {
                Log::warning('Failed to process log file', [
                    'file' => $logFile,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'files_rotated' => $filesRotated,
            'files_compressed' => $filesCompressed,
            'files_deleted' => $filesDeleted,
            'space_freed_mb' => round($spaceFreedMb, 2)
        ];
    }

    /**
     * Compress a log file using gzip
     */
    private function compressLogFile(string $logFile): ?string
    {
        try {
            $compressedFile = $logFile . '.gz';
            
            // Read original file
            $originalContent = File::get($logFile);
            
            // Compress content
            $compressedContent = gzencode($originalContent, 9);
            
            // Write compressed file
            File::put($compressedFile, $compressedContent);
            
            // Delete original file
            File::delete($logFile);
            
            return $compressedFile;
            
        } catch (\Exception $e) {
            Log::error('Failed to compress log file', [
                'file' => $logFile,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Rotate a log file by renaming it with timestamp
     */
    private function rotateLogFile(string $logFile): ?string
    {
        try {
            $pathInfo = pathinfo($logFile);
            $timestamp = now()->format('Y-m-d_H-i-s');
            $rotatedFile = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_' . $timestamp . '.' . $pathInfo['extension'];
            
            // Rename the file
            File::move($logFile, $rotatedFile);
            
            // Create new empty log file
            File::put($logFile, '');
            
            return $rotatedFile;
            
        } catch (\Exception $e) {
            Log::error('Failed to rotate log file', [
                'file' => $logFile,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Get default log types
     */
    private function getDefaultLogTypes(): array
    {
        return [
            'application_logs',
            'access_logs',
            'error_logs',
            'audit_logs'
        ];
    }

    /**
     * Get default rotation options
     */
    private function getDefaultRotationOptions(): array
    {
        return [
            'application_logs_retention_days' => 30,
            'access_logs_retention_days' => 90,
            'error_logs_retention_days' => 60,
            'audit_logs_retention_days' => 365, // Keep audit logs longer for compliance
            'performance_logs_retention_days' => 14,
            'security_logs_retention_days' => 180,
            'max_file_size_mb' => 100,
            'compress_after_days' => 7,
            'enable_compression' => true,
            'compression_level' => 9
        ];
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Log rotation job failed permanently', [
            'log_types' => $this->logTypes,
            'rotation_options' => $this->rotationOptions,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job?->getJobId(),
        ]);

        // Could broadcast failure event for monitoring
        // broadcast(new \App\Events\Logs\LogRotationFailed(...));
    }
}
