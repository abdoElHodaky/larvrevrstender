<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_type',
        'event_data',
        'session_id',
        'ip_address',
        'user_agent',
        'created_at'
    ];

    protected $casts = [
        'event_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
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
        $dateFormat = match($groupBy) {
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
        'error_occurred' => 'Error Occurred'
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
            'payment_completed'
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
}

