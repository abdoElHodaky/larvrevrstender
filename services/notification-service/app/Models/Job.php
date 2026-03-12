<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Job Model for Laravel Queue Jobs Table
 * 
 * Represents jobs in the Laravel queue system for Eloquent ORM operations
 * instead of raw DB::table queries.
 */
class Job extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'jobs';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'queue',
        'payload',
        'attempts',
        'reserved_at',
        'available_at',
        'created_at',
        'priority'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'reserved_at' => 'timestamp',
        'available_at' => 'timestamp', 
        'created_at' => 'timestamp',
        'attempts' => 'integer',
        'priority' => 'integer'
    ];

    /**
     * Scope for jobs in a specific queue
     */
    public function scopeInQueue($query, string $queueName)
    {
        return $query->where('queue', $queueName);
    }

    /**
     * Scope for stuck jobs (reserved but not completed)
     */
    public function scopeStuck($query, int $timeoutMinutes = 30)
    {
        return $query->whereNotNull('reserved_at')
                    ->where('reserved_at', '<', now()->subMinutes($timeoutMinutes)->timestamp);
    }

    /**
     * Scope for available jobs (not reserved)
     */
    public function scopeAvailable($query)
    {
        return $query->whereNull('reserved_at')
                    ->where('available_at', '<=', now()->timestamp);
    }

    /**
     * Scope for expired jobs
     */
    public function scopeExpired($query, int $expirationHours = 24)
    {
        return $query->where('created_at', '<', now()->subHours($expirationHours)->timestamp);
    }

    /**
     * Get the decoded payload
     */
    protected function decodedPayload(): Attribute
    {
        return Attribute::make(
            get: fn () => json_decode($this->payload, true)
        );
    }

    /**
     * Get the job class name from payload
     */
    protected function jobClass(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->decoded_payload['displayName'] ?? ''
        );
    }

    /**
     * Get job age in hours
     */
    protected function ageInHours(): Attribute
    {
        return Attribute::make(
            get: fn () => now()->diffInHours(\Carbon\Carbon::createFromTimestamp($this->created_at))
        );
    }
}
