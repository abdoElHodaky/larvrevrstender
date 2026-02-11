<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VinOcrLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'vin_ocr_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'vehicle_id',
        'user_id',
        'original_image_path',
        'processed_image_path',
        'extracted_vin',
        'confidence_score',
        'ocr_metadata',
        'validation_result',
        'status',
        'processing_time_ms',
        'error_message',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'confidence_score' => 'decimal:4',
        'ocr_metadata' => 'array',
        'validation_result' => 'array',
        'processing_time_ms' => 'integer',
        'vehicle_id' => 'integer',
        'user_id' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        // Add any sensitive fields here if needed
    ];

    /**
     * Get the user that owns this OCR log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the vehicle associated with this OCR log.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Scope to get logs by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get successful OCR logs.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed')
                    ->whereNotNull('extracted_vin')
                    ->where('confidence_score', '>=', 0.7);
    }

    /**
     * Scope to get failed OCR logs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed')
                    ->orWhere(function($q) {
                        $q->where('status', 'completed')
                          ->where(function($subQ) {
                              $subQ->whereNull('extracted_vin')
                                   ->orWhere('confidence_score', '<', 0.7);
                          });
                    });
    }

    /**
     * Scope to get logs by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get logs by vehicle.
     */
    public function scopeByVehicle($query, int $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    /**
     * Scope to get logs with high confidence.
     */
    public function scopeHighConfidence($query, float $threshold = 0.9)
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    /**
     * Scope to get recent logs.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Check if the OCR was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed' &&
               !empty($this->extracted_vin) &&
               $this->confidence_score >= 0.7;
    }

    /**
     * Check if the OCR failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed' ||
               ($this->status === 'completed' && (
                   empty($this->extracted_vin) ||
                   $this->confidence_score < 0.7
               ));
    }

    /**
     * Check if the OCR is still processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if the extracted VIN is valid.
     */
    public function hasValidVin(): bool
    {
        return !empty($this->extracted_vin) &&
               isset($this->validation_result['valid']) &&
               $this->validation_result['valid'] === true;
    }

    /**
     * Get the formatted confidence score as percentage.
     */
    public function getConfidencePercentageAttribute(): string
    {
        return number_format($this->confidence_score * 100, 2) . '%';
    }

    /**
     * Get the formatted processing time.
     */
    public function getFormattedProcessingTimeAttribute(): string
    {
        if (!$this->processing_time_ms) {
            return 'Unknown';
        }

        if ($this->processing_time_ms < 1000) {
            return $this->processing_time_ms . 'ms';
        }

        return number_format($this->processing_time_ms / 1000, 2) . 's';
    }

    /**
     * Get the OCR engine used.
     */
    public function getOcrEngineAttribute(): ?string
    {
        return $this->ocr_metadata['ocr_engine'] ?? null;
    }

    /**
     * Get the preprocessing status.
     */
    public function getPreprocessingAppliedAttribute(): bool
    {
        return $this->ocr_metadata['preprocessing_applied'] ?? false;
    }

    /**
     * Get the manufacturer from validation result.
     */
    public function getManufacturerAttribute(): ?string
    {
        return $this->validation_result['details']['manufacturer']['name'] ?? null;
    }

    /**
     * Get the vehicle year from validation result.
     */
    public function getVehicleYearAttribute(): ?int
    {
        return $this->validation_result['details']['year']['year'] ?? null;
    }

    /**
     * Get validation errors.
     */
    public function getValidationErrorsAttribute(): array
    {
        return $this->validation_result['errors'] ?? [];
    }

    /**
     * Get the original image URL.
     */
    public function getOriginalImageUrlAttribute(): ?string
    {
        if (!$this->original_image_path) {
            return null;
        }

        // Assuming you have a storage URL helper
        return url('storage/' . $this->original_image_path);
    }

    /**
     * Get the processed image URL.
     */
    public function getProcessedImageUrlAttribute(): ?string
    {
        if (!$this->processed_image_path) {
            return null;
        }

        return url('storage/' . $this->processed_image_path);
    }

    /**
     * Get summary statistics for OCR logs.
     */
    public static function getStatistics(array $filters = []): array
    {
        $query = static::query();

        // Apply filters
        if (isset($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        if (isset($filters['vehicle_id'])) {
            $query->byVehicle($filters['vehicle_id']);
        }

        if (isset($filters['days'])) {
            $query->recent($filters['days']);
        }

        $total = $query->count();
        $successful = (clone $query)->successful()->count();
        $failed = (clone $query)->failed()->count();
        $processing = (clone $query)->byStatus('processing')->count();

        $avgConfidence = $query->whereNotNull('confidence_score')
                              ->avg('confidence_score');

        $avgProcessingTime = $query->whereNotNull('processing_time_ms')
                                  ->avg('processing_time_ms');

        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'processing' => $processing,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'average_confidence' => $avgConfidence ? round($avgConfidence, 4) : 0,
            'average_processing_time_ms' => $avgProcessingTime ? round($avgProcessingTime, 2) : 0,
        ];
    }

    /**
     * Clean up old OCR logs.
     */
    public static function cleanupOldLogs(int $daysToKeep = 90): int
    {
        return static::where('created_at', '<', now()->subDays($daysToKeep))
                    ->delete();
    }

    /**
     * Get the most recent successful OCR for a vehicle.
     */
    public static function getLatestSuccessfulForVehicle(int $vehicleId): ?self
    {
        return static::byVehicle($vehicleId)
                    ->successful()
                    ->latest()
                    ->first();
    }

    /**
     * Get OCR logs with duplicate VINs.
     */
    public static function getDuplicateVins(): array
    {
        return static::selectRaw('extracted_vin, COUNT(*) as count')
                    ->whereNotNull('extracted_vin')
                    ->groupBy('extracted_vin')
                    ->having('count', '>', 1)
                    ->orderBy('count', 'desc')
                    ->get()
                    ->toArray();
    }
}
