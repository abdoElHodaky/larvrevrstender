<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Analytics Report Model with PHP 8.3 + Laravel 12 optimizations
 */
class AnalyticsReport extends Model
{
    protected $table = 'analytics_reports';

    protected $fillable = [
        'type',
        'title',
        'status',
        'parameters',
        'file_path',
        'file_size',
        'completed_at'
    ];

    protected $casts = [
        'parameters' => 'array',
        'file_size' => 'integer',
        'completed_at' => 'datetime'
    ];

    /**
     * Scope for filtering by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for filtering by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for date range filtering.
     */
    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get formatted file size (PHP 8.3 optimized).
     */
    protected function formattedFileSize(): Attribute
    {
        return Attribute::make(
            get: fn() => match(true) {
                $this->file_size >= 1073741824 => round($this->file_size / 1073741824, 2) . ' GB',
                $this->file_size >= 1048576 => round($this->file_size / 1048576, 2) . ' MB',
                $this->file_size >= 1024 => round($this->file_size / 1024, 2) . ' KB',
                default => $this->file_size . ' bytes'
            }
        );
    }

    /**
     * Check if report is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed' && $this->completed_at !== null;
    }

    /**
     * Check if report is in progress.
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * Get reports by status using collection methods (PHP 8.3).
     */
    public static function getReportsByStatus(): array
    {
        return static::all()
            ->groupBy('status')
            ->map(fn($reports) => $reports->count())
            ->toArray();
    }

    /**
     * Bulk update report statuses (PHP 8.3 optimized).
     */
    public static function bulkUpdateStatus(array $reportIds, string $status): int
    {
        return static::whereIn('id', $reportIds)
            ->update(['status' => $status, 'updated_at' => now()]);
    }
}
