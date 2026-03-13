<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Shared\Traits\EloquentDatabaseFailover;

class BusinessMetric extends Model
{
    use HasFactory, EloquentDatabaseFailover;

    protected $fillable = [
        'metric_date',
        'metric_type',
        'value',
        'breakdown',
        'created_at',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'value' => 'decimal:2',
        'breakdown' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope for filtering by metric type
     */
    public function scopeMetricType($query, string $metricType)
    {
        return $query->where('metric_type', $metricType);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('metric_date', [$startDate, $endDate]);
    }

    /**
     * Scope for ordering by date
     */
    public function scopeOrderByDate($query, string $direction = 'asc')
    {
        return $query->orderBy('metric_date', $direction);
    }

    /**
     * Get metrics grouped by type
     */
    public function scopeGroupedByType($query)
    {
        return $query->selectRaw('metric_type, SUM(value) as total_value, COUNT(*) as count')
            ->groupBy('metric_type')
            ->orderBy('total_value', 'desc');
    }

    /**
     * Get latest metrics
     */
    public function scopeLatest($query, int $limit = 10)
    {
        return $query->orderBy('metric_date', 'desc')->limit($limit);
    }

    /**
     * Metric types constants
     */
    const METRIC_TYPES = [
        'orders' => 'Total Orders',
        'bids' => 'Total Bids',
        'revenue' => 'Revenue',
        'users' => 'Active Users',
        'conversion' => 'Conversion Rate',
        'avg_order_value' => 'Average Order Value',
        'customer_acquisition' => 'Customer Acquisition',
        'merchant_acquisition' => 'Merchant Acquisition',
        'order_completion_rate' => 'Order Completion Rate',
        'bid_success_rate' => 'Bid Success Rate',
    ];

    /**
     * Get formatted metric type name
     */
    public function getMetricTypeNameAttribute(): string
    {
        return self::METRIC_TYPES[$this->metric_type] ?? ucwords(str_replace('_', ' ', $this->metric_type));
    }

    /**
     * Get breakdown value by key
     */
    public function getBreakdownValue(string $key, $default = null)
    {
        return $this->breakdown[$key] ?? $default;
    }

    /**
     * Add breakdown data
     */
    public function addBreakdownData(string $key, $value): void
    {
        $breakdown = $this->breakdown ?? [];
        $breakdown[$key] = $value;
        $this->breakdown = $breakdown;
    }

    /**
     * Calculate percentage change from previous period
     */
    public function getPercentageChange(): ?float
    {
        $previousMetric = self::where('metric_type', $this->metric_type)
            ->where('metric_date', '<', $this->metric_date)
            ->orderBy('metric_date', 'desc')
            ->first();

        if (! $previousMetric || $previousMetric->value == 0) {
            return null;
        }

        return (($this->value - $previousMetric->value) / $previousMetric->value) * 100;
    }

    /**
     * Get trend direction
     */
    public function getTrendDirection(): string
    {
        $change = $this->getPercentageChange();

        if ($change === null) {
            return 'neutral';
        }

        if ($change > 0) {
            return 'up';
        } elseif ($change < 0) {
            return 'down';
        }

        return 'neutral';
    }

    /**
     * Format value based on metric type
     */
    public function getFormattedValueAttribute(): string
    {
        return match ($this->metric_type) {
            'revenue', 'avg_order_value' => number_format($this->value, 2).' SAR',
            'conversion', 'order_completion_rate', 'bid_success_rate' => number_format($this->value, 2).'%',
            default => number_format($this->value)
        };
    }

    /**
     * Get metrics safely with database failover protection
     * Modern PHP 8.3 & Laravel 12 implementation
     */
    public static function getMetricsSafely(string $metricType, Carbon $startDate, Carbon $endDate): \Illuminate\Database\Eloquent\Collection
    {
        $instance = new static();
        return $instance->executeFailsafeQuery('get_metrics_by_type_and_date', function() use ($metricType, $startDate, $endDate) {
            return static::metricType($metricType)
                ->dateRange($startDate, $endDate)
                ->orderByDate()
                ->get();
        });
    }

    /**
     * Get aggregated metrics safely using modern Laravel collection methods
     * PHP 8.3 match expressions with database failover protection
     */
    public static function getAggregatedMetricsSafely(string $metricType, string $aggregationType = 'sum'): mixed
    {
        $instance = new static();
        return $instance->executeFailsafeQuery('get_aggregated_metrics', function() use ($metricType, $aggregationType) {
            $query = static::metricType($metricType);
            
            return match($aggregationType) {
                'sum' => $query->sum('value'),
                'avg' => $query->avg('value'),
                'count' => $query->count(),
                'max' => $query->max('value'),
                'min' => $query->min('value'),
                default => throw new \InvalidArgumentException("Unsupported aggregation type: {$aggregationType}")
            };
        });
    }

    /**
     * Create metric safely with database failover protection
     * Modern PHP 8.3 typed parameters and return types
     */
    public static function createMetricSafely(
        string $metricType,
        float $value,
        Carbon $metricDate,
        ?array $breakdown = null
    ): static {
        $instance = new static();
        return $instance->createSafely([
            'metric_type' => $metricType,
            'value' => $value,
            'metric_date' => $metricDate,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Get trending metrics with failover protection
     * Uses Laravel collection methods for modern data processing
     */
    public static function getTrendingMetricsSafely(int $days = 30): \Illuminate\Support\Collection
    {
        $instance = new static();
        $startDate = now()->subDays($days);
        
        return $instance->executeFailsafeQuery('get_trending_metrics', function() use ($startDate) {
            return static::where('metric_date', '>=', $startDate)
                ->orderBy('metric_date')
                ->get()
                ->groupBy('metric_type')
                ->map(function($metrics) {
                    return $metrics->map(function($metric) {
                        return [
                            'date' => $metric->metric_date->format('Y-m-d'),
                            'value' => $metric->value,
                            'formatted_value' => $metric->formatted_value,
                            'trend' => $metric->getTrendDirection(),
                            'change_percentage' => $metric->getPercentageChange(),
                        ];
                    });
                });
        });
    }

    /**
     * Bulk update metrics safely with modern PHP 8.3 features
     */
    public static function bulkUpdateMetricsSafely(array $updates): int
    {
        $instance = new static();
        return $instance->executeFailsafeTransaction('bulk_update_metrics', function() use ($updates) {
            $affectedRows = 0;
            
            // Use Laravel collection methods for modern data processing
            collect($updates)->chunk(100)->each(function($chunk) use (&$affectedRows) {
                $chunk->each(function($update) use (&$affectedRows) {
                    $affected = static::where('id', $update['id'])
                        ->update(collect($update)->except('id')->toArray());
                    $affectedRows += $affected;
                });
            });
            
            return $affectedRows;
        });
    }

    /**
     * Get dashboard metrics with failover protection
     * Modern implementation using PHP 8.3 match expressions
     */
    public static function getDashboardMetricsSafely(string $period = 'month'): array
    {
        $instance = new static();
        
        $dateRange = match($period) {
            'day' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => throw new \InvalidArgumentException("Unsupported period: {$period}")
        };
        
        return $instance->executeFailsafeQuery('get_dashboard_metrics', function() use ($dateRange) {
            return static::whereBetween('metric_date', $dateRange)
                ->get()
                ->groupBy('metric_type')
                ->map(function($metrics, $type) {
                    $totalValue = $metrics->sum('value');
                    $avgValue = $metrics->avg('value');
                    $count = $metrics->count();
                    
                    return [
                        'type' => $type,
                        'name' => static::METRIC_TYPES[$type] ?? ucwords(str_replace('_', ' ', $type)),
                        'total_value' => $totalValue,
                        'average_value' => $avgValue,
                        'count' => $count,
                        'formatted_total' => match($type) {
                            'revenue', 'avg_order_value' => number_format($totalValue, 2) . ' SAR',
                            'conversion', 'order_completion_rate', 'bid_success_rate' => number_format($avgValue, 2) . '%',
                            default => number_format($totalValue)
                        },
                        'latest_value' => $metrics->sortByDesc('metric_date')->first()?->value,
                        'trend' => $metrics->sortByDesc('metric_date')->first()?->getTrendDirection(),
                    ];
                })
                ->values()
                ->toArray();
        });
    }
}
