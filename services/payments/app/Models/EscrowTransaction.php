<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscrowTransaction extends Model
{
    use HasFactory;

    // Transaction type constants
    const TYPE_HOLD = 'hold';
    const TYPE_RELEASE = 'release';
    const TYPE_PARTIAL_RELEASE = 'partial_release';
    const TYPE_DISPUTE = 'dispute';
    const TYPE_CANCEL = 'cancel';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'escrow_id',
        'type',
        'amount',
        'reason',
        'processed_by',
        'processed_at',
        'external_reference',
        'metadata'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the escrow that owns this transaction.
     */
    public function escrow(): BelongsTo
    {
        return $this->belongsTo(Escrow::class);
    }

    /**
     * Scope for transactions by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for hold transactions.
     */
    public function scopeHolds($query)
    {
        return $query->where('type', self::TYPE_HOLD);
    }

    /**
     * Scope for release transactions.
     */
    public function scopeReleases($query)
    {
        return $query->whereIn('type', [self::TYPE_RELEASE, self::TYPE_PARTIAL_RELEASE]);
    }

    /**
     * Scope for dispute transactions.
     */
    public function scopeDisputes($query)
    {
        return $query->where('type', self::TYPE_DISPUTE);
    }

    /**
     * Scope for recent transactions.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('processed_at', '>=', now()->subDays($days));
    }

    /**
     * Check if this is a release transaction.
     */
    public function isRelease(): bool
    {
        return in_array($this->type, [self::TYPE_RELEASE, self::TYPE_PARTIAL_RELEASE]);
    }

    /**
     * Check if this is a hold transaction.
     */
    public function isHold(): bool
    {
        return $this->type === self::TYPE_HOLD;
    }

    /**
     * Check if this is a dispute transaction.
     */
    public function isDispute(): bool
    {
        return $this->type === self::TYPE_DISPUTE;
    }
}

