<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shared\Traits\EloquentDatabaseFailover;

class UserAnalytic extends Model
{
    use HasFactory, EloquentDatabaseFailover;

    protected $fillable = [
        'user_id',
        'event_type',
        'event_data',
        'session_id',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the analytic event
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for filtering by event type
     */
    public function scopeEventType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for filtering by user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for filtering by session
     */
    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Get events grouped by type
     */
    public function scopeGroupedByType($query)
    {
        return $query->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->orderBy('count', 'desc');
    }

    /**
     * Get events grouped by date
     */
    public function scopeGroupedByDate($query, string $groupBy = 'day')
    {
        $dateFormat = match ($groupBy) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d'
        };

        return $query->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as date, COUNT(*) as count")
            ->groupBy('date')
            ->orderBy('date');
    }

    /**
     * Get unique users count
     */
    public function scopeUniqueUsers($query)
    {
        return $query->distinct('user_id')->count('user_id');
    }

    /**
     * Get unique sessions count
     */
    public function scopeUniqueSessions($query)
    {
        return $query->distinct('session_id')->count('session_id');
    }

    /**
     * Common event types constants
     */
    const EVENT_TYPES = [
        'user_registration' => 'User Registration',
        'user_login' => 'User Login',
        'user_logout' => 'User Logout',
        'order_created' => 'Order Created',
        'order_published' => 'Order Published',
        'bid_placed' => 'Bid Placed',
        'bid_updated' => 'Bid Updated',
        'bid_awarded' => 'Bid Awarded',
        'payment_initiated' => 'Payment Initiated',
        'payment_completed' => 'Payment Completed',
        'profile_updated' => 'Profile Updated',
        'search_performed' => 'Search Performed',
        'page_view' => 'Page View',
        'button_click' => 'Button Click',
        'form_submission' => 'Form Submission',
        'error_occurred' => 'Error Occurred',
    ];

    /**
     * Get formatted event type name
     */
    public function getEventTypeNameAttribute(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? ucwords(str_replace('_', ' ', $this->event_type));
    }

    /**
     * Check if event is a conversion event
     */
    public function isConversionEvent(): bool
    {
        $conversionEvents = [
            'user_registration',
            'order_created',
            'bid_placed',
            'payment_completed',
        ];

        return in_array($this->event_type, $conversionEvents);
    }

    /**
     * Get event data value by key
     */
    public function getEventDataValue(string $key, $default = null)
    {
        return $this->event_data[$key] ?? $default;
    }

    /**
     * Add event data
     */
    public function addEventData(string $key, $value): void
    {
        $eventData = $this->event_data ?? [];
        $eventData[$key] = $value;
        $this->event_data = $eventData;
    }

    /**
     * Track user event safely with database failover protection
     * Modern PHP 8.3 typed parameters and Laravel 12 features
     */
    public static function trackEventSafely(
        int $userId,
        string $eventType,
        array $eventData = [],
        ?string $sessionId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): static {
        $instance = new static();
        return $instance->createSafely([
            'user_id' => $userId,
            'event_type' => $eventType,
            'event_data' => $eventData,
            'session_id' => $sessionId ?? session()->getId(),
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
        ]);
    }

    /**
     * Get user analytics safely with modern collection methods
     * PHP 8.3 match expressions for period handling
     */
    public static function getUserAnalyticsSafely(int $userId, string $period = 'month'): \Illuminate\Support\Collection
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
        
        return $instance->executeFailsafeQuery('get_user_analytics', function() use ($userId, $dateRange) {
            return static::forUser($userId)
                ->whereBetween('created_at', $dateRange)
                ->get()
                ->groupBy('event_type')
                ->map(function($events, $eventType) {
                    return [
                        'event_type' => $eventType,
                        'event_name' => static::EVENT_TYPES[$eventType] ?? ucwords(str_replace('_', ' ', $eventType)),
                        'count' => $events->count(),
                        'is_conversion' => $events->first()?->isConversionEvent() ?? false,
                        'latest_event' => $events->sortByDesc('created_at')->first()?->created_at?->toISOString(),
                        'sessions' => $events->pluck('session_id')->unique()->count(),
                    ];
                })
                ->values();
        });
    }

    /**
     * Get conversion funnel data safely with failover protection
     * Modern Laravel collection methods for data processing
     */
    public static function getConversionFunnelSafely(array $funnelSteps, Carbon $startDate, Carbon $endDate): array
    {
        $instance = new static();
        
        return $instance->executeFailsafeQuery('get_conversion_funnel', function() use ($funnelSteps, $startDate, $endDate) {
            $funnelData = [];
            $previousStepUsers = null;
            
            foreach ($funnelSteps as $index => $step) {
                $stepUsers = static::eventType($step)
                    ->dateRange($startDate, $endDate)
                    ->distinct('user_id')
                    ->pluck('user_id');
                
                $stepCount = $stepUsers->count();
                $conversionRate = $previousStepUsers ? 
                    ($stepCount / $previousStepUsers->count()) * 100 : 100;
                
                $funnelData[] = [
                    'step' => $index + 1,
                    'event_type' => $step,
                    'event_name' => static::EVENT_TYPES[$step] ?? ucwords(str_replace('_', ' ', $step)),
                    'users' => $stepCount,
                    'conversion_rate' => round($conversionRate, 2),
                    'drop_off_rate' => round(100 - $conversionRate, 2),
                ];
                
                $previousStepUsers = $stepUsers;
            }
            
            return $funnelData;
        });
    }

    /**
     * Get user engagement metrics safely
     * Modern PHP 8.3 features with database failover protection
     */
    public static function getEngagementMetricsSafely(Carbon $startDate, Carbon $endDate): array
    {
        $instance = new static();
        
        return $instance->executeFailsafeQuery('get_engagement_metrics', function() use ($startDate, $endDate) {
            $analytics = static::dateRange($startDate, $endDate)->get();
            
            return [
                'total_events' => $analytics->count(),
                'unique_users' => $analytics->pluck('user_id')->unique()->count(),
                'unique_sessions' => $analytics->pluck('session_id')->unique()->count(),
                'events_by_type' => $analytics->groupBy('event_type')
                    ->map(fn($events) => $events->count())
                    ->sortDesc()
                    ->toArray(),
                'conversion_events' => $analytics->filter(fn($event) => $event->isConversionEvent())->count(),
                'conversion_rate' => $analytics->isNotEmpty() ? 
                    round(($analytics->filter(fn($event) => $event->isConversionEvent())->count() / $analytics->count()) * 100, 2) : 0,
                'daily_breakdown' => $analytics->groupBy(fn($event) => $event->created_at->format('Y-m-d'))
                    ->map(function($dailyEvents) {
                        return [
                            'events' => $dailyEvents->count(),
                            'users' => $dailyEvents->pluck('user_id')->unique()->count(),
                            'sessions' => $dailyEvents->pluck('session_id')->unique()->count(),
                        ];
                    })
                    ->toArray(),
            ];
        });
    }

    /**
     * Get user journey safely with modern collection methods
     * PHP 8.3 typed parameters and return types
     */
    public static function getUserJourneySafely(int $userId, ?string $sessionId = null): \Illuminate\Support\Collection
    {
        $instance = new static();
        
        return $instance->executeFailsafeQuery('get_user_journey', function() use ($userId, $sessionId) {
            $query = static::forUser($userId);
            
            if ($sessionId) {
                $query->forSession($sessionId);
            }
            
            return $query->orderBy('created_at')
                ->get()
                ->map(function($event) {
                    return [
                        'id' => $event->id,
                        'event_type' => $event->event_type,
                        'event_name' => $event->event_type_name,
                        'timestamp' => $event->created_at->toISOString(),
                        'session_id' => $event->session_id,
                        'event_data' => $event->event_data,
                        'is_conversion' => $event->isConversionEvent(),
                        'ip_address' => $event->ip_address,
                        'user_agent' => $event->user_agent,
                    ];
                });
        });
    }

    /**
     * Bulk track events safely with modern PHP 8.3 features
     * Uses Laravel collection methods for efficient processing
     */
    public static function bulkTrackEventsSafely(array $events): bool
    {
        $instance = new static();
        
        return $instance->executeFailsafeTransaction('bulk_track_events', function() use ($events) {
            // Process events in chunks for better performance
            $processedEvents = collect($events)
                ->map(function($event) {
                    return [
                        'user_id' => $event['user_id'],
                        'event_type' => $event['event_type'],
                        'event_data' => json_encode($event['event_data'] ?? []),
                        'session_id' => $event['session_id'] ?? session()->getId(),
                        'ip_address' => $event['ip_address'] ?? request()->ip(),
                        'user_agent' => $event['user_agent'] ?? request()->userAgent(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })
                ->chunk(500); // Process in chunks of 500
            
            $success = true;
            $processedEvents->each(function($chunk) use (&$success) {
                $result = static::insert($chunk->toArray());
                if (!$result) {
                    $success = false;
                }
            });
            
            return $success;
        });
    }

    /**
     * Get real-time analytics dashboard data safely
     * Modern implementation with PHP 8.3 match expressions
     */
    public static function getRealTimeDashboardSafely(): array
    {
        $instance = new static();
        
        return $instance->executeFailsafeQuery('get_realtime_dashboard', function() {
            $last24Hours = now()->subDay();
            $lastHour = now()->subHour();
            
            $recent = static::where('created_at', '>=', $last24Hours)->get();
            $hourly = static::where('created_at', '>=', $lastHour)->get();
            
            return [
                'last_24_hours' => [
                    'total_events' => $recent->count(),
                    'unique_users' => $recent->pluck('user_id')->unique()->count(),
                    'unique_sessions' => $recent->pluck('session_id')->unique()->count(),
                    'conversion_events' => $recent->filter(fn($event) => $event->isConversionEvent())->count(),
                ],
                'last_hour' => [
                    'total_events' => $hourly->count(),
                    'unique_users' => $hourly->pluck('user_id')->unique()->count(),
                    'unique_sessions' => $hourly->pluck('session_id')->unique()->count(),
                    'conversion_events' => $hourly->filter(fn($event) => $event->isConversionEvent())->count(),
                ],
                'top_events_24h' => $recent->groupBy('event_type')
                    ->map(fn($events) => $events->count())
                    ->sortDesc()
                    ->take(10)
                    ->map(function($count, $eventType) {
                        return [
                            'event_type' => $eventType,
                            'event_name' => static::EVENT_TYPES[$eventType] ?? ucwords(str_replace('_', ' ', $eventType)),
                            'count' => $count,
                        ];
                    })
                    ->values()
                    ->toArray(),
                'hourly_breakdown' => $recent->groupBy(fn($event) => $event->created_at->format('H:00'))
                    ->map(fn($events) => $events->count())
                    ->sortKeys()
                    ->toArray(),
            ];
        });
    }
}
