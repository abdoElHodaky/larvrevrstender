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

// Public channel for auction bid updates
Broadcast::channel('auction.{auctionId}.bids', function ($user, $auctionId) {
    return true; // Public channel - anyone can see bid updates for an auction
});

// Private channel for specific bid updates
Broadcast::channel('bid.{bidId}', function ($user, $bidId) {
    // Check if user owns this bid
    $bid = \App\Models\Bid::find($bidId);
    if (!$bid) {
        return false;
    }
    
    return (int) $user->id === (int) $bid->bidder_id;
});

// Private channel for user's bids
Broadcast::channel('user.{userId}.bids', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private channel for bidder-specific notifications
Broadcast::channel('bidder.{bidderId}', function ($user, $bidderId) {
    return (int) $user->id === (int) $bidderId;
});

// Public channel for bid statistics (anonymized)
Broadcast::channel('auction.{auctionId}.stats', function ($user, $auctionId) {
    return true; // Public channel - anyone can see bid statistics
});

// Private channel for auction owner to see all bids on their auctions
Broadcast::channel('seller.{sellerId}.auction-bids', function ($user, $sellerId) {
    return (int) $user->id === (int) $sellerId;
});

// Private channel for bid status updates (accepted/rejected)
Broadcast::channel('bid.{bidId}.status', function ($user, $bidId) {
    // Check if user owns this bid or is the auction owner
    $bid = \App\Models\Bid::find($bidId);
    if (!$bid) {
        return false;
    }
    
    // Allow bidder and auction owner to listen
    return (int) $user->id === (int) $bid->bidder_id || 
           (int) $user->id === (int) $bid->auction_owner_id;
});

// Presence channel for active bidders on an auction
Broadcast::channel('auction.{auctionId}.bidders', function ($user, $auctionId) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'joined_at' => now()->toISOString(),
        'bid_count' => \App\Models\Bid::where('auction_id', $auctionId)
                                     ->where('bidder_id', $user->id)
                                     ->count(),
    ];
});

// Admin channel for bid monitoring
Broadcast::channel('admin.bids', function ($user) {
    return $user->role === 'admin' || $user->role === 'moderator';
});
