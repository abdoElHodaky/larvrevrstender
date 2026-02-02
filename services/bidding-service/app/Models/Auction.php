<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'description',
        'vehicle_id',
        'starting_price',
        'reserve_price',
        'current_highest_bid',
        'status',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'starting_price' => 'decimal:2',
        'reserve_price' => 'decimal:2',
        'current_highest_bid' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Get the bids for the auction.
     */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    /**
     * Get the highest bid for the auction.
     */
    public function highestBid()
    {
        return $this->bids()->orderBy('amount', 'desc')->first();
    }

    /**
     * Check if the auction is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               now()->between($this->starts_at, $this->ends_at);
    }
}
