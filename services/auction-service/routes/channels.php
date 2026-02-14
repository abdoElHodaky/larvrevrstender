<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Public channel for all auction updates
Broadcast::channel('auctions', function () {
    return true; // Public channel - anyone can listen
});

// Public channel for specific auction updates
Broadcast::channel('auction.{auctionId}', function ($user, $auctionId) {
    return true; // Public channel - anyone can listen to specific auction
});

// Public channel for auction bid updates
Broadcast::channel('auction.{auctionId}.bids', function ($user, $auctionId) {
    return true; // Public channel - anyone can see bid updates
});

// Private channel for user-specific auction notifications
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private channel for seller-specific auction notifications
Broadcast::channel('seller.{sellerId}', function ($user, $sellerId) {
    return (int) $user->id === (int) $sellerId;
});

// Presence channel for users watching an auction (optional - for showing active viewers)
Broadcast::channel('auction.{auctionId}.presence', function ($user, $auctionId) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'watching_since' => now()->toISOString(),
    ];
});
