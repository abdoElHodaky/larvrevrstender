<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BusinessMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric_date',
        'metric_type',
        'value',
        'breakdown',
        'created_at'
    ];

    protected $casts = [
        'metric_date' => 'date',
        'value' => 'decimal:2',
        'breakdown' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
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
        'bid_success_rate' => 'Bid Success Rate'
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

        if (!$previousMetric || $previousMetric->value == 0) {
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
        return match($this->metric_type) {
            'revenue', 'avg_order_value' => number_format($this->value, 2) . ' SAR',
            'conversion', 'order_completion_rate', 'bid_success_rate' => number_format($this->value, 2) . '%',
            default => number_format($this->value)
        };
    }
}

