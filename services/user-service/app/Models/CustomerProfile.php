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
        'date_of_birth',
        'gender',
        'occupation',
        'company_name',
        'industry',
        'company_size',
        'annual_revenue',
        'default_location',
        'preferences',
        'verification_status',
        'verification_documents',
        'verification_submitted_at',
        'verification_updated_at',
        'verification_notes',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'default_location' => 'array',
        'preferences' => 'array',
        'verification_documents' => 'array',
        'verification_submitted_at' => 'datetime',
        'verification_updated_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Verification status constants
     */
    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    const STATUS_REQUIRES_REVIEW = 'requires_review';

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

        if (! $location || ! isset($location['latitude'], $location['longitude'])) {
            return null;
        }

        return [
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
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
        return ! empty($this->national_id) && strlen($this->national_id) >= 10;
    }

    /**
     * Get formatted national address for ZATCA compliance.
     */
    public function getFormattedNationalAddress(): ?string
    {
        return $this->national_address;
    }

    /**
     * Get the addresses for the customer.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'user_id', 'user_id');
    }

    /**
     * Get the default address for the customer.
     */
    public function defaultAddress()
    {
        return $this->hasOne(Address::class, 'user_id', 'user_id')->where('is_default', true);
    }

    /**
     * Scope for filtering by verification status
     */
    public function scopeWithVerificationStatus($query, string $status)
    {
        return $query->where('verification_status', $status);
    }

    /**
     * Scope for verified profiles
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', self::STATUS_APPROVED);
    }

    /**
     * Scope for filtering by industry
     */
    public function scopeByIndustry($query, string $industry)
    {
        return $query->where('industry', $industry);
    }

    /**
     * Scope for filtering by company size
     */
    public function scopeByCompanySize($query, string $size)
    {
        return $query->where('company_size', $size);
    }

    /**
     * Check if profile is verified
     */
    public function isVerified(): bool
    {
        return $this->verification_status === self::STATUS_APPROVED;
    }

    /**
     * Check if profile is pending verification
     */
    public function isPendingVerification(): bool
    {
        return $this->verification_status === self::STATUS_PENDING;
    }

    /**
     * Add preferred category
     */
    public function addPreferredCategory(string $category): void
    {
        $preferences = $this->preferences ?? [];
        $categories = $preferences['preferred_categories'] ?? [];

        if (! in_array($category, $categories)) {
            $categories[] = $category;
            $preferences['preferred_categories'] = $categories;
            $this->update(['preferences' => $preferences]);
        }
    }

    /**
     * Remove preferred category
     */
    public function removePreferredCategory(string $category): void
    {
        $preferences = $this->preferences ?? [];
        $categories = $preferences['preferred_categories'] ?? [];

        $categories = array_filter($categories, fn ($cat) => $cat !== $category);
        $preferences['preferred_categories'] = array_values($categories);
        $this->update(['preferences' => $preferences]);
    }

    /**
     * Add delivery address
     */
    public function addDeliveryAddress(array $addressData): void
    {
        Address::create([
            'user_id' => $this->user_id,
            'type' => 'delivery',
            'street_address' => $addressData['street_address'],
            'city' => $addressData['city'],
            'state' => $addressData['state'] ?? null,
            'postal_code' => $addressData['postal_code'] ?? null,
            'country' => $addressData['country'] ?? 'SA',
            'is_default' => $addressData['is_default'] ?? false,
        ]);
    }

    /**
     * Get age from date of birth
     */
    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }

    /**
     * Get full name with company if available
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->user->name ?? 'Unknown User';

        return $this->company_name ? "{$name} ({$this->company_name})" : $name;
    }

    /**
     * Check if profile has complete KYC information
     */
    public function hasCompleteKYC(): bool
    {
        return ! empty($this->national_id)
            && ! empty($this->national_address)
            && ! empty($this->date_of_birth)
            && $this->isVerified();
    }

    /**
     * Get verification progress percentage
     */
    public function getVerificationProgressAttribute(): int
    {
        $requiredFields = [
            'national_id', 'national_address', 'date_of_birth',
            'occupation', 'default_location',
        ];

        $completedFields = 0;
        foreach ($requiredFields as $field) {
            if (! empty($this->$field)) {
                $completedFields++;
            }
        }

        return round(($completedFields / count($requiredFields)) * 100);
    }

    /**
     * User relationship
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
