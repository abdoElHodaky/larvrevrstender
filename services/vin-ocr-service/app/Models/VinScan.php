<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VinScan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'image_path',
        'original_filename',
        'vin_number',
        'confidence_score',
        'status',
        'processed_at',
        'user_id',
        'processing_time_ms',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'confidence_score' => 'decimal:4',
        'processed_at' => 'datetime',
        'processing_time_ms' => 'integer',
    ];

    /**
     * Get the OCR results for this scan.
     */
    public function ocrResults(): HasMany
    {
        return $this->hasMany(OcrResult::class);
    }

    /**
     * Check if the scan was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed' && 
               !empty($this->vin_number) && 
               $this->confidence_score >= 0.8;
    }

    /**
     * Get the formatted VIN number.
     */
    public function getFormattedVinAttribute(): string
    {
        return strtoupper(str_replace(['-', ' '], '', $this->vin_number ?? ''));
    }
}
