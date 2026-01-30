<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'business_name',
        'business_license',
        'tax_number',
        'specializations',
        'rating',
        'total_reviews',
        'verified',
        'verification_documents',
        'business_hours',
        'service_areas',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'specializations' => 'array',
        'verification_documents' => 'array',
        'business_hours' => 'array',
        'service_areas' => 'array',
        'rating' => 'decimal:2',
        'verified' => 'boolean',
        'total_reviews' => 'integer',
    ];

    /**
     * Scope for verified merchants.
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    /**
     * Scope for merchants by specialization.
     */
    public function scopeBySpecialization($query, string $specialization)
    {
        return $query->whereJsonContains('specializations', $specialization);
    }

    /**
     * Scope for merchants by rating.
     */
    public function scopeByMinRating($query, float $minRating)
    {
        return $query->where('rating', '>=', $minRating);
    }

    /**
     * Scope for merchants serving specific area.
     */
    public function scopeServingArea($query, string $area)
    {
        return $query->whereJsonContains('service_areas', $area);
    }

    /**
     * Check if merchant is verified.
     */
    public function isVerified(): bool
    {
        return $this->verified;
    }

    /**
     * Check if merchant specializes in a category.
     */
    public function specializesIn(string $category): bool
    {
        $specializations = $this->specializations ?? [];
        return in_array($category, $specializations);
    }

    /**
     * Add specialization.
     */
    public function addSpecialization(string $specialization): void
    {
        $specializations = $this->specializations ?? [];
        
        if (!in_array($specialization, $specializations)) {
            $specializations[] = $specialization;
            $this->update(['specializations' => $specializations]);
        }
    }

    /**
     * Check if merchant serves an area.
     */
    public function servesArea(string $area): bool
    {
        $serviceAreas = $this->service_areas ?? [];
        return in_array($area, $serviceAreas);
    }

    /**
     * Add service area.
     */
    public function addServiceArea(string $area): void
    {
        $serviceAreas = $this->service_areas ?? [];
        
        if (!in_array($area, $serviceAreas)) {
            $serviceAreas[] = $area;
            $this->update(['service_areas' => $serviceAreas]);
        }
    }

    /**
     * Update rating based on new review.
     */
    public function updateRating(float $newRating): void
    {
        $totalReviews = $this->total_reviews;
        $currentRating = $this->rating;
        
        $newTotalReviews = $totalReviews + 1;
        $newAverageRating = (($currentRating * $totalReviews) + $newRating) / $newTotalReviews;
        
        $this->update([
            'rating' => round($newAverageRating, 2),
            'total_reviews' => $newTotalReviews
        ]);
    }

    /**
     * Check if merchant has ZATCA-compliant tax number.
     */
    public function hasValidTaxNumber(): bool
    {
        return !empty($this->tax_number) && strlen($this->tax_number) >= 10;
    }

    /**
     * Get business hours for a specific day.
     */
    public function getBusinessHours(string $day): ?array
    {
        $hours = $this->business_hours ?? [];
        return $hours[strtolower($day)] ?? null;
    }

    /**
     * Check if merchant is open at specific time.
     */
    public function isOpenAt(string $day, string $time): bool
    {
        $hours = $this->getBusinessHours($day);
        
        if (!$hours || !isset($hours['open'], $hours['close'])) {
            return false;
        }
        
        return $time >= $hours['open'] && $time <= $hours['close'];
    }

    /**
     * Get formatted rating display.
     */
    public function getFormattedRatingAttribute(): string
    {
        return number_format($this->rating, 1) . '/5.0 (' . $this->total_reviews . ' reviews)';
    }
}

