<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use App\Domain\Auth\ValueObjects\UserId;
use App\Domain\Auth\ValueObjects\Email;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

final class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    protected $fillable = [
        'id',
        'name',
        'email',
        'email_verified_at',
        'password',
        'role',
        'status',
        'profile_data',
        'preferences',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'id' => 'string',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'profile_data' => 'array',
        'preferences' => 'array',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getUserId(): UserId
    {
        return UserId::fromString($this->id);
    }

    public function getEmail(): Email
    {
        return Email::fromString($this->email);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
            'status' => $this->status,
            'verified' => $this->isVerified(),
        ];
    }

    public function markAsLoggedIn(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    public function updateProfile(array $profileData): void
    {
        $this->update(['profile_data' => array_merge($this->profile_data ?? [], $profileData)]);
    }

    public function updatePreferences(array $preferences): void
    {
        $this->update(['preferences' => array_merge($this->preferences ?? [], $preferences)]);
    }
}

