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

// Private channel for user-specific notifications
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private channel for user-specific notification preferences
Broadcast::channel('user.{userId}.preferences', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private channel for admin notifications
Broadcast::channel('admin.notifications', function ($user) {
    return $user->hasRole('admin') || $user->hasPermission('admin.notifications');
});

// Private channel for system-wide notifications (admin only)
Broadcast::channel('system.notifications', function ($user) {
    return $user->hasRole('admin') || $user->hasRole('super-admin');
});

// Private channel for notification delivery status
Broadcast::channel('notification.{notificationId}.status', function ($user, $notificationId) {
    // Allow user to listen to their own notification status
    $notification = \App\Models\Notification::find($notificationId);
    return $notification && (int) $notification->user_id === (int) $user->id;
});

// Private channel for bulk notification operations
Broadcast::channel('bulk.notifications.{operationId}', function ($user, $operationId) {
    // Only allow admins to listen to bulk operation status
    return $user->hasRole('admin') || $user->hasPermission('notifications.bulk');
});
