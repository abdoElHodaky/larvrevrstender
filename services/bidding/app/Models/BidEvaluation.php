<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BidEvaluation Model
 * 
 * Represents the evaluation and scoring of bids in the winner selection process.
 * Supports multi-criteria evaluation with weighted scoring.
 */
class BidEvaluation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'bid_id',
        'auction_id',
        'evaluator_id',
        'price_score',
        'delivery_score',
        'quality_score',
        'supplier_score',
        'technical_score',
        'compliance_score',
        'composite_score',
        'rank',
        'evaluation_criteria',
        'score_breakdown',
        'evaluation_notes',
        'evaluation_status',
        'evaluated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price_score' => 'decimal:2',
        'delivery_score' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'supplier_score' => 'decimal:2',
        'technical_score' => 'decimal:2',
        'compliance_score' => 'decimal:2',
        'composite_score' => 'decimal:2',
        'rank' => 'integer',
        'evaluation_criteria' => 'array',
        'score_breakdown' => 'array',
        'evaluated_at' => 'datetime',
    ];

    /**
     * Default evaluation criteria weights
     */
    public const DEFAULT_CRITERIA_WEIGHTS = [
        'price' => 40.0,      // 40% weight for price competitiveness
        'delivery' => 20.0,   // 20% weight for delivery timeline
        'quality' => 15.0,    // 15% weight for quality specifications
        'supplier' => 15.0,   // 15% weight for supplier reputation
        'technical' => 5.0,   // 5% weight for technical compliance
        'compliance' => 5.0,  // 5% weight for regulatory compliance
    ];

    /**
     * Evaluation status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_APPROVED = 'approved';

    /**
     * Get the bid that this evaluation belongs to.
     */
    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    /**
     * Get the auction that this evaluation belongs to.
     */
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /**
     * Scope for completed evaluations.
     */
    public function scopeCompleted($query)
    {
        return $query->where('evaluation_status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for approved evaluations.
     */
    public function scopeApproved($query)
    {
        return $query->where('evaluation_status', self::STATUS_APPROVED);
    }

    /**
     * Scope for evaluations ordered by rank.
     */
    public function scopeByRank($query)
    {
        return $query->orderBy('rank', 'asc');
    }

    /**
     * Scope for evaluations ordered by composite score (highest first).
     */
    public function scopeByScore($query)
    {
        return $query->orderBy('composite_score', 'desc');
    }

    /**
     * Calculate composite score based on individual scores and weights.
     */
    public function calculateCompositeScore(array $weights = null): float
    {
        $weights = $weights ?? self::DEFAULT_CRITERIA_WEIGHTS;
        
        $totalWeight = array_sum($weights);
        if ($totalWeight == 0) {
            return 0.0;
        }

        $weightedScore = 
            ($this->price_score * ($weights['price'] ?? 0)) +
            ($this->delivery_score * ($weights['delivery'] ?? 0)) +
            ($this->quality_score * ($weights['quality'] ?? 0)) +
            ($this->supplier_score * ($weights['supplier'] ?? 0)) +
            ($this->technical_score * ($weights['technical'] ?? 0)) +
            ($this->compliance_score * ($weights['compliance'] ?? 0));

        return round($weightedScore / $totalWeight, 2);
    }

    /**
     * Update the composite score and save.
     */
    public function updateCompositeScore(array $weights = null): bool
    {
        $this->composite_score = $this->calculateCompositeScore($weights);
        return $this->save();
    }

    /**
     * Get detailed score breakdown.
     */
    public function getScoreBreakdown(): array
    {
        return [
            'individual_scores' => [
                'price' => $this->price_score,
                'delivery' => $this->delivery_score,
                'quality' => $this->quality_score,
                'supplier' => $this->supplier_score,
                'technical' => $this->technical_score,
                'compliance' => $this->compliance_score,
            ],
            'composite_score' => $this->composite_score,
            'rank' => $this->rank,
            'evaluation_criteria' => $this->evaluation_criteria,
            'evaluated_at' => $this->evaluated_at,
        ];
    }

    /**
     * Check if evaluation is complete.
     */
    public function isComplete(): bool
    {
        return in_array($this->evaluation_status, [
            self::STATUS_COMPLETED,
            self::STATUS_REVIEWED,
            self::STATUS_APPROVED
        ]);
    }

    /**
     * Check if evaluation is approved.
     */
    public function isApproved(): bool
    {
        return $this->evaluation_status === self::STATUS_APPROVED;
    }

    /**
     * Mark evaluation as completed.
     */
    public function markCompleted(): bool
    {
        $this->evaluation_status = self::STATUS_COMPLETED;
        $this->evaluated_at = now();
        return $this->save();
    }

    /**
     * Mark evaluation as approved.
     */
    public function markApproved(): bool
    {
        $this->evaluation_status = self::STATUS_APPROVED;
        return $this->save();
    }

    /**
     * Get the winner status for this evaluation.
     */
    public function isWinner(): bool
    {
        return $this->rank === 1 && $this->isApproved();
    }

    /**
     * Scope for winner evaluation (rank 1 and approved).
     */
    public function scopeWinner($query)
    {
        return $query->where('rank', 1)->approved();
    }

    /**
     * Get evaluation summary for display.
     */
    public function getSummary(): array
    {
        return [
            'bid_id' => $this->bid_id,
            'composite_score' => $this->composite_score,
            'rank' => $this->rank,
            'status' => $this->evaluation_status,
            'is_winner' => $this->isWinner(),
            'evaluated_at' => $this->evaluated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
