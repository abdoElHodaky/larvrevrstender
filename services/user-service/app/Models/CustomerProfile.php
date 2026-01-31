<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'national_id',
        'national_address',
        'default_location',
        'preferences',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'default_location' => 'array',
        'preferences' => 'array',
    ];

    /**
     * Get the vehicles for the customer.
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'customer_id');
    }

    /**
     * Get the primary vehicle for the customer.
     */
    public function primaryVehicle()
    {
        return $this->hasOne(Vehicle::class, 'customer_id')->where('is_primary', true);
    }

    /**
     * Get the default location coordinates.
     */
    public function getLocationCoordinates(): ?array
    {
        $location = $this->default_location;
        
        if (!$location || !isset($location['latitude'], $location['longitude'])) {
            return null;
        }
        
        return [
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude']
        ];
    }

    /**
     * Update preferences.
     */
    public function updatePreferences(array $preferences): void
    {
        $currentPreferences = $this->preferences ?? [];
        $updatedPreferences = array_merge($currentPreferences, $preferences);
        
        $this->update(['preferences' => $updatedPreferences]);
    }

    /**
     * Get preference value.
     */
    public function getPreference(string $key, $default = null)
    {
        return $this->preferences[$key] ?? $default;
    }

    /**
     * Check if customer has ZATCA-compliant national ID.
     */
    public function hasValidNationalId(): bool
    {
        return !empty($this->national_id) && strlen($this->national_id) >= 10;
    }

    /**
     * Get formatted national address for ZATCA compliance.
     */
    public function getFormattedNationalAddress(): ?string
    {
        return $this->national_address;
    }
}

