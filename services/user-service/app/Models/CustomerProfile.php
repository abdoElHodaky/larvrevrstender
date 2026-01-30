<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerProfile extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'company_name',
        'industry',
        'company_size',
        'annual_budget',
        'preferred_categories',
        'delivery_addresses',
        'payment_terms',
        'verification_status',
        'verification_documents',
        'preferences',
        'metadata'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'preferred_categories' => 'array',
        'delivery_addresses' => 'array',
        'verification_documents' => 'array',
        'preferences' => 'array',
        'metadata' => 'array',
        'annual_budget' => 'decimal:2',
    ];

    /**
     * Verification statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_VERIFIED = 'verified';
    const STATUS_REJECTED = 'rejected';

    /**
     * Company sizes
     */
    const SIZE_STARTUP = 'startup';
    const SIZE_SMALL = 'small';
    const SIZE_MEDIUM = 'medium';
    const SIZE_LARGE = 'large';
    const SIZE_ENTERPRISE = 'enterprise';

    /**
     * Get the user that owns the profile
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for verified profiles
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', self::STATUS_VERIFIED);
    }

    /**
     * Scope for profiles by industry
     */
    public function scopeByIndustry($query, string $industry)
    {
        return $query->where('industry', $industry);
    }

    /**
     * Scope for profiles by company size
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
        return $this->verification_status === self::STATUS_VERIFIED;
    }

    /**
     * Get primary delivery address
     */
    public function getPrimaryDeliveryAddress(): ?array
    {
        $addresses = $this->delivery_addresses ?? [];
        
        foreach ($addresses as $address) {
            if ($address['is_primary'] ?? false) {
                return $address;
            }
        }
        
        return $addresses[0] ?? null;
    }

    /**
     * Add delivery address
     */
    public function addDeliveryAddress(array $address): void
    {
        $addresses = $this->delivery_addresses ?? [];
        
        // If this is the first address, make it primary
        if (empty($addresses)) {
            $address['is_primary'] = true;
        }
        
        $addresses[] = $address;
        $this->update(['delivery_addresses' => $addresses]);
    }

    /**
     * Update preferences
     */
    public function updatePreferences(array $preferences): void
    {
        $currentPreferences = $this->preferences ?? [];
        $updatedPreferences = array_merge($currentPreferences, $preferences);
        
        $this->update(['preferences' => $updatedPreferences]);
    }

    /**
     * Get preference value
     */
    public function getPreference(string $key, $default = null)
    {
        return $this->preferences[$key] ?? $default;
    }

    /**
     * Check if category is preferred
     */
    public function prefersCategory(string $category): bool
    {
        $categories = $this->preferred_categories ?? [];
        return in_array($category, $categories);
    }

    /**
     * Add preferred category
     */
    public function addPreferredCategory(string $category): void
    {
        $categories = $this->preferred_categories ?? [];
        
        if (!in_array($category, $categories)) {
            $categories[] = $category;
            $this->update(['preferred_categories' => $categories]);
        }
    }

    /**
     * Remove preferred category
     */
    public function removePreferredCategory(string $category): void
    {
        $categories = $this->preferred_categories ?? [];
        $categories = array_filter($categories, fn($cat) => $cat !== $category);
        
        $this->update(['preferred_categories' => array_values($categories)]);
    }

    /**
     * Get display name for company
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->company_name ?: $this->user->name;
    }

    /**
     * Get formatted annual budget
     */
    public function getFormattedBudgetAttribute(): string
    {
        if (!$this->annual_budget) {
            return 'Not specified';
        }
        
        return number_format($this->annual_budget, 0) . ' SAR';
    }
}

