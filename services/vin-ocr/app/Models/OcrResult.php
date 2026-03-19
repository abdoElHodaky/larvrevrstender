<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrResult extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'vin_scan_id',
        'detected_text',
        'confidence_score',
        'bounding_box',
        'character_position',
        'validation_status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'confidence_score' => 'decimal:4',
        'bounding_box' => 'array',
        'character_position' => 'integer',
    ];

    /**
     * Get the VIN scan that owns this result.
     */
    public function vinScan(): BelongsTo
    {
        return $this->belongsTo(VinScan::class);
    }

    /**
     * Check if this result is high confidence.
     */
    public function isHighConfidence(): bool
    {
        return $this->confidence_score >= 0.9;
    }

    /**
     * Get the cleaned detected text.
     */
    public function getCleanedTextAttribute(): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($this->detected_text ?? ''));
    }
}
