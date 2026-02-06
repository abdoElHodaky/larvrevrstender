<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'balance',
        'reserved_balance',
        'currency',
        'status',
        'metadata',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'reserved_balance' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const STATUS_ACTIVE = 'active';

    const STATUS_SUSPENDED = 'suspended';

    const STATUS_FROZEN = 'frozen';

    /**
     * Get the wallet transactions.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Get the wallet reservations.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(WalletReservation::class);
    }

    /**
     * Get available balance (total - reserved).
     */
    public function getAvailableBalanceAttribute(): float
    {
        return $this->balance - $this->reserved_balance;
    }

    /**
     * Check if wallet has sufficient balance.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->getAvailableBalanceAttribute() >= $amount;
    }

    /**
     * Reserve funds in the wallet.
     */
    public function reserveFunds(float $amount, string $reference): bool
    {
        if (! $this->hasSufficientBalance($amount)) {
            return false;
        }

        $this->increment('reserved_balance', $amount);

        $this->reservations()->create([
            'amount' => $amount,
            'reference' => $reference,
            'status' => 'active',
        ]);

        return true;
    }

    /**
     * Release reserved funds.
     */
    public function releaseFunds(string $reference): bool
    {
        $reservation = $this->reservations()
            ->where('reference', $reference)
            ->where('status', 'active')
            ->first();

        if (! $reservation) {
            return false;
        }

        $this->decrement('reserved_balance', $reservation->amount);
        $reservation->update(['status' => 'released']);

        return true;
    }

    /**
     * Scope for active wallets.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
