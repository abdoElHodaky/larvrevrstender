<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * FailedJob Model for Laravel Queue Failed Jobs Table
 * 
 * Represents failed jobs in the Laravel queue system for Eloquent ORM operations
 * instead of raw DB::table queries.
 */
class FailedJob extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'failed_jobs';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'connection',
        'queue',
        'payload',
        'exception',
        'failed_at'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'failed_at' => 'datetime'
    ];

    /**
     * Scope for failed jobs in a specific queue
     */
    public function scopeInQueue($query, string $queueName)
    {
        return $query->where('queue', $queueName);
    }

    /**
     * Scope for failed jobs older than specified hours
     */
    public function scopeOlderThan($query, int $hours)
    {
        return $query->where('failed_at', '<', now()->subHours($hours));
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
     * Get the number of attempts from payload
     */
    protected function attempts(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->decoded_payload['attempts'] ?? 0
        );
    }

    /**
     * Get the max attempts from payload
     */
    protected function maxAttempts(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->decoded_payload['maxTries'] ?? 3
        );
    }

    /**
     * Check if job has exceeded max attempts
     */
    protected function hasExceededMaxAttempts(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attempts >= $this->max_attempts
        );
    }
}
