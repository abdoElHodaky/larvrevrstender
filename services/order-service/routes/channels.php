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

// Private channel for specific order updates
Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    // Check if user is the customer or merchant for this order
    $order = \App\Models\Order::find($orderId);
    if (!$order) {
        return false;
    }
    
    return (int) $user->id === (int) $order->customer_id || 
           (int) $user->id === (int) $order->merchant_id;
});

// Private channel for user's orders
Broadcast::channel('user.{userId}.orders', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private channel for merchant's orders
Broadcast::channel('merchant.{merchantId}.orders', function ($user, $merchantId) {
    return (int) $user->id === (int) $merchantId;
});

// Private channel for customer's orders
Broadcast::channel('customer.{customerId}.orders', function ($user, $customerId) {
    return (int) $user->id === (int) $customerId;
});

// Private channel for workflow notifications
Broadcast::channel('workflow.{workflowId}', function ($user, $workflowId) {
    // Check if user has access to this workflow
    // This would need to be implemented based on your workflow access logic
    return true; // For now, allow all authenticated users
});

// Private channel for order status updates by status
Broadcast::channel('orders.status.{status}', function ($user, $status) {
    // Allow merchants to listen to orders by status
    return $user->role === 'merchant' || $user->role === 'admin';
});

// Presence channel for order tracking (optional - for customer service)
Broadcast::channel('order.{orderId}.tracking', function ($user, $orderId) {
    $order = \App\Models\Order::find($orderId);
    if (!$order) {
        return false;
    }
    
    // Allow customer, merchant, and support staff
    $hasAccess = (int) $user->id === (int) $order->customer_id || 
                 (int) $user->id === (int) $order->merchant_id ||
                 $user->role === 'support' || 
                 $user->role === 'admin';
    
    if ($hasAccess) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'joined_at' => now()->toISOString(),
        ];
    }
    
    return false;
});
