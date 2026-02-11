<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's avatar.
     */
    public function avatar(): HasOne
    {
        return $this->hasOne(UserAvatar::class);
    }

    /**
     * Get the user's KYC documents.
     */
    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    /**
     * Get the user's approved KYC documents.
     */
    public function approvedKycDocuments(): HasMany
    {
        return $this->kycDocuments()->approved();
    }

    /**
     * Get the user's pending KYC documents.
     */
    public function pendingKycDocuments(): HasMany
    {
        return $this->kycDocuments()->pending();
    }

    /**
     * Get the user's latest version of each KYC document type.
     */
    public function latestKycDocuments(): HasMany
    {
        return $this->kycDocuments()->latestVersions();
    }

    /**
     * Check if the user has an avatar.
     */
    public function hasAvatar(): bool
    {
        return $this->avatar !== null;
    }

    /**
     * Get the user's avatar URL or default.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar?->url;
    }

    /**
     * Check if the user's KYC is approved.
     */
    public function isKycApproved(): bool
    {
        $requiredDocuments = ['identity', 'proof_of_address'];
        $approvedTypes = $this->approvedKycDocuments->pluck('document_type')->toArray();

        return count(array_intersect($requiredDocuments, $approvedTypes)) === count($requiredDocuments);
    }

    /**
     * Get the user's KYC completion percentage.
     */
    public function getKycCompletionPercentageAttribute(): int
    {
        $requiredDocuments = ['identity', 'proof_of_address'];
        $submittedTypes = $this->kycDocuments()->active()->distinct('document_type')->pluck('document_type')->toArray();

        $completed = count(array_intersect($requiredDocuments, $submittedTypes));

        return (int) (($completed / count($requiredDocuments)) * 100);
    }

    /**
     * Get the user's overall KYC status.
     */
    public function getKycStatusAttribute(): string
    {
        if ($this->isKycApproved()) {
            return 'approved';
        }

        $hasRejected = $this->kycDocuments()->rejected()->exists();
        if ($hasRejected) {
            return 'rejected';
        }

        $hasUnderReview = $this->kycDocuments()->underReview()->exists();
        if ($hasUnderReview) {
            return 'under_review';
        }

        $hasPending = $this->kycDocuments()->pending()->exists();
        if ($hasPending) {
            return 'pending';
        }

        return 'not_started';
    }
}
