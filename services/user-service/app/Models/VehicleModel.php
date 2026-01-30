<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleModel extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'brand_id',
        'name',
        'year_start',
        'year_end',
        'active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'active' => 'boolean',
        'year_start' => 'integer',
        'year_end' => 'integer',
    ];

    /**
     * Get the brand that owns the model.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the trims for the model.
     */
    public function trims(): HasMany
    {
        return $this->hasMany(Trim::class, 'model_id');
    }

    /**
     * Get vehicles for the model.
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'model_id');
    }

    /**
     * Scope for active models.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for models by brand.
     */
    public function scopeByBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    /**
     * Scope for models by year range.
     */
    public function scopeByYearRange($query, int $startYear, int $endYear = null)
    {
        $query->where('year_start', '<=', $startYear);
        
        if ($endYear) {
            $query->where(function ($q) use ($endYear) {
                $q->whereNull('year_end')
                  ->orWhere('year_end', '>=', $endYear);
            });
        }
        
        return $query;
    }

    /**
     * Scope for models available in specific year.
     */
    public function scopeAvailableInYear($query, int $year)
    {
        return $query->where('year_start', '<=', $year)
                    ->where(function ($q) use ($year) {
                        $q->whereNull('year_end')
                          ->orWhere('year_end', '>=', $year);
                    });
    }

    /**
     * Check if model is active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Check if model was available in specific year.
     */
    public function wasAvailableInYear(int $year): bool
    {
        return $this->year_start <= $year && 
               ($this->year_end === null || $this->year_end >= $year);
    }

    /**
     * Get the full model name with brand.
     */
    public function getFullNameAttribute(): string
    {
        return $this->brand->name . ' ' . $this->name;
    }

    /**
     * Get the year range display.
     */
    public function getYearRangeAttribute(): string
    {
        if ($this->year_end) {
            return $this->year_start . '-' . $this->year_end;
        }
        
        return $this->year_start . '-Present';
    }

    /**
     * Get trims count for the model.
     */
    public function getTrimsCountAttribute(): int
    {
        return $this->trims()->count();
    }
}

