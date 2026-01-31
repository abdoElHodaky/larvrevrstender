<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'customer_id',
        'brand_id',
        'model_id',
        'trim_id',
        'year',
        'vin',
        'is_primary',
        'custom_name',
        'mileage',
        'engine_type',
        'transmission_type',
        'fuel_type',
        'body_style',
        'vin_confidence',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'year' => 'integer',
        'mileage' => 'integer',
        'vin_confidence' => 'decimal:2',
    ];

    /**
     * Get the customer that owns the vehicle.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_id');
    }

    /**
     * Get the brand of the vehicle.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the model of the vehicle.
     */
    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    /**
     * Get the trim of the vehicle.
     */
    public function trim(): BelongsTo
    {
        return $this->belongsTo(Trim::class);
    }

    /**
     * Scope for primary vehicles.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope for vehicles by customer.
     */
    public function scopeByCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope for vehicles by brand.
     */
    public function scopeByBrand($query, int $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    /**
     * Scope for vehicles by year range.
     */
    public function scopeByYearRange($query, int $startYear, int $endYear = null)
    {
        $query->where('year', '>=', $startYear);
        
        if ($endYear) {
            $query->where('year', '<=', $endYear);
        }
        
        return $query;
    }

    /**
     * Scope for vehicles with VIN.
     */
    public function scopeWithVin($query)
    {
        return $query->whereNotNull('vin');
    }

    /**
     * Scope for vehicles with high VIN confidence.
     */
    public function scopeHighVinConfidence($query, float $minConfidence = 0.8)
    {
        return $query->where('vin_confidence', '>=', $minConfidence);
    }

    /**
     * Check if vehicle is primary.
     */
    public function isPrimary(): bool
    {
        return $this->is_primary;
    }

    /**
     * Set as primary vehicle (and unset others).
     */
    public function setAsPrimary(): void
    {
        // Unset other primary vehicles for this customer
        Vehicle::where('customer_id', $this->customer_id)
               ->where('id', '!=', $this->id)
               ->update(['is_primary' => false]);
        
        // Set this vehicle as primary
        $this->update(['is_primary' => true]);
    }

    /**
     * Validate VIN using Luhn algorithm.
     */
    public function validateVin(): bool
    {
        if (!$this->vin || strlen($this->vin) !== 17) {
            return false;
        }
        
        // Basic VIN validation (simplified)
        return preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $this->vin);
    }

    /**
     * Get display name for the vehicle.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->custom_name) {
            return $this->custom_name;
        }
        
        $name = $this->year . ' ' . $this->brand->name . ' ' . $this->vehicleModel->name;
        
        if ($this->trim) {
            $name .= ' ' . $this->trim->name;
        }
        
        return $name;
    }

    /**
     * Get full vehicle specifications.
     */
    public function getSpecificationsAttribute(): array
    {
        return [
            'year' => $this->year,
            'brand' => $this->brand->name,
            'model' => $this->vehicleModel->name,
            'trim' => $this->trim?->name,
            'engine_type' => $this->engine_type,
            'transmission_type' => $this->transmission_type,
            'fuel_type' => $this->fuel_type,
            'body_style' => $this->body_style,
            'mileage' => $this->mileage,
            'vin' => $this->vin,
        ];
    }

    /**
     * Get VIN confidence level description.
     */
    public function getVinConfidenceLevelAttribute(): string
    {
        $confidence = $this->vin_confidence;
        
        if ($confidence >= 0.9) {
            return 'Very High';
        } elseif ($confidence >= 0.8) {
            return 'High';
        } elseif ($confidence >= 0.7) {
            return 'Medium';
        } elseif ($confidence >= 0.5) {
            return 'Low';
        } else {
            return 'Very Low';
        }
    }

    /**
     * Check if VIN was extracted with high confidence.
     */
    public function hasHighVinConfidence(): bool
    {
        return $this->vin_confidence >= 0.8;
    }

    /**
     * Get formatted mileage display.
     */
    public function getFormattedMileageAttribute(): string
    {
        if (!$this->mileage) {
            return 'Not specified';
        }
        
        return number_format($this->mileage) . ' km';
    }
}

