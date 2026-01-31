<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trim extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'model_id',
        'name',
        'engine_type',
        'transmission_type',
        'fuel_type',
        'body_style',
    ];

    /**
     * Get the vehicle model that owns the trim.
     */
    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    /**
     * Get vehicles for the trim.
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'trim_id');
    }

    /**
     * Scope for trims by model.
     */
    public function scopeByModel($query, int $modelId)
    {
        return $query->where('model_id', $modelId);
    }

    /**
     * Scope for trims by engine type.
     */
    public function scopeByEngineType($query, string $engineType)
    {
        return $query->where('engine_type', $engineType);
    }

    /**
     * Scope for trims by transmission type.
     */
    public function scopeByTransmissionType($query, string $transmissionType)
    {
        return $query->where('transmission_type', $transmissionType);
    }

    /**
     * Scope for trims by fuel type.
     */
    public function scopeByFuelType($query, string $fuelType)
    {
        return $query->where('fuel_type', $fuelType);
    }

    /**
     * Scope for trims by body style.
     */
    public function scopeByBodyStyle($query, string $bodyStyle)
    {
        return $query->where('body_style', $bodyStyle);
    }

    /**
     * Get the full trim name with model and brand.
     */
    public function getFullNameAttribute(): string
    {
        return $this->vehicleModel->brand->name . ' ' . 
               $this->vehicleModel->name . ' ' . 
               $this->name;
    }

    /**
     * Get trim specifications as array.
     */
    public function getSpecificationsAttribute(): array
    {
        return [
            'engine_type' => $this->engine_type,
            'transmission_type' => $this->transmission_type,
            'fuel_type' => $this->fuel_type,
            'body_style' => $this->body_style,
        ];
    }

    /**
     * Check if trim matches specifications.
     */
    public function matchesSpecifications(array $specs): bool
    {
        foreach ($specs as $key => $value) {
            if ($this->$key !== $value) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get formatted specifications display.
     */
    public function getFormattedSpecsAttribute(): string
    {
        $specs = [];
        
        if ($this->engine_type) {
            $specs[] = $this->engine_type;
        }
        
        if ($this->transmission_type) {
            $specs[] = $this->transmission_type;
        }
        
        if ($this->fuel_type) {
            $specs[] = $this->fuel_type;
        }
        
        if ($this->body_style) {
            $specs[] = $this->body_style;
        }
        
        return implode(' • ', $specs);
    }
}

