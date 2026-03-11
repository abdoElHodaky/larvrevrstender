<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

/**
 * Password Reset Token Model
 * 
 * Represents password reset tokens for user authentication.
 * Provides Eloquent interface for password_reset_tokens table.
 */
class PasswordResetToken extends Model
{
    use HasFactory;

    protected $table = 'password_reset_tokens';
    
    protected $fillable = [
        'email',
        'token',
        'created_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public $timestamps = false; // This table only has created_at

    /**
     * Scope to get expired tokens
     */
    public function scopeExpired($query, Carbon $expirationTime)
    {
        return $query->where('created_at', '<', $expirationTime);
    }

    /**
     * Scope to get tokens by email
     */
    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Check if token is expired
     */
    public function isExpired(int $hoursValid = 1): bool
    {
        return $this->created_at->addHours($hoursValid)->isPast();
    }
}
