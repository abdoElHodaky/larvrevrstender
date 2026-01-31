<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'logo_url',
        'active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Get the vehicle models for the brand.
     */
    public function vehicleModels(): HasMany
    {
        return $this->hasMany(VehicleModel::class);
    }

    /**
     * Get active vehicle models for the brand.
     */
    public function activeVehicleModels(): HasMany
    {
        return $this->hasMany(VehicleModel::class)->where('active', true);
    }

    /**
     * Get vehicles for the brand.
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Scope for active brands.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for brands by name search.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('name', 'like', '%' . $search . '%');
    }

    /**
     * Check if brand is active.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Get the brand logo URL or default.
     */
    public function getLogoUrlAttribute($value): string
    {
        return $value ?: '/images/brands/default-brand-logo.png';
    }

    /**
     * Get models count for the brand.
     */
    public function getModelsCountAttribute(): int
    {
        return $this->vehicleModels()->count();
    }

    /**
     * Get active models count for the brand.
     */
    public function getActiveModelsCountAttribute(): int
    {
        return $this->activeVehicleModels()->count();
    }
}

