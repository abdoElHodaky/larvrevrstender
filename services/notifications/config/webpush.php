<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Push Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Laravel Web Push Notifications
    |
    */

    /*
     * These are the keys for authentication (VAPID).
     * These keys must be safely stored and should not change.
     */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@reversetender.com'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    /*
     * This is the global Time To Live for push notifications.
     * This can be overridden for each notification.
     * Default is 4 weeks.
     */
    'ttl' => env('WEBPUSH_TTL', 2419200),

    /*
     * This is the global urgency for push notifications.
     * This can be overridden for each notification.
     * Options: very-low, low, normal, high
     */
    'urgency' => env('WEBPUSH_URGENCY', 'normal'),

    /*
     * This is the global topic for push notifications.
     * This can be overridden for each notification.
     */
    'topic' => env('WEBPUSH_TOPIC', 'reversetender'),

    /*
     * GCM and FCM configuration
     */
    'gcm' => [
        'key' => env('GCM_KEY'),
        'sender_id' => env('GCM_SENDER_ID'),
    ],

    'fcm' => [
        'key' => env('FCM_KEY'),
        'sender_id' => env('FCM_SENDER_ID'),
    ],

    /*
     * Default notification options
     */
    'notification_defaults' => [
        'badge' => env('WEBPUSH_DEFAULT_BADGE', '/images/notification-badge.png'),
        'icon' => env('WEBPUSH_DEFAULT_ICON', '/images/notification-icon.png'),
        'image' => env('WEBPUSH_DEFAULT_IMAGE'),
        'dir' => env('WEBPUSH_DEFAULT_DIR', 'auto'),
        'lang' => env('WEBPUSH_DEFAULT_LANG', 'en'),
        'renotify' => env('WEBPUSH_DEFAULT_RENOTIFY', false),
        'requireInteraction' => env('WEBPUSH_DEFAULT_REQUIRE_INTERACTION', false),
        'silent' => env('WEBPUSH_DEFAULT_SILENT', false),
        'tag' => env('WEBPUSH_DEFAULT_TAG'),
        'timestamp' => null, // Will be set to current timestamp if null
        'vibrate' => env('WEBPUSH_DEFAULT_VIBRATE') ? explode(',', env('WEBPUSH_DEFAULT_VIBRATE')) : [200, 100, 200],
    ],

    /*
     * Action buttons configuration
     */
    'actions' => [
        'view' => [
            'action' => 'view',
            'title' => 'View',
            'icon' => '/images/actions/view.png',
        ],
        'dismiss' => [
            'action' => 'dismiss',
            'title' => 'Dismiss',
            'icon' => '/images/actions/dismiss.png',
        ],
        'reply' => [
            'action' => 'reply',
            'title' => 'Reply',
            'icon' => '/images/actions/reply.png',
        ],
    ],

    /*
     * Notification categories with specific configurations
     */
    'categories' => [
        'order' => [
            'icon' => '/images/categories/order.png',
            'badge' => '/images/categories/order-badge.png',
            'vibrate' => [300, 100, 300],
            'requireInteraction' => true,
            'actions' => ['view', 'dismiss'],
        ],
        'bid' => [
            'icon' => '/images/categories/bid.png',
            'badge' => '/images/categories/bid-badge.png',
            'vibrate' => [200, 100, 200, 100, 200],
            'requireInteraction' => true,
            'actions' => ['view', 'dismiss'],
        ],
        'payment' => [
            'icon' => '/images/categories/payment.png',
            'badge' => '/images/categories/payment-badge.png',
            'vibrate' => [400, 200, 400],
            'requireInteraction' => true,
            'actions' => ['view', 'dismiss'],
        ],
        'auction' => [
            'icon' => '/images/categories/auction.png',
            'badge' => '/images/categories/auction-badge.png',
            'vibrate' => [250, 150, 250],
            'requireInteraction' => false,
            'actions' => ['view', 'dismiss'],
        ],
        'system' => [
            'icon' => '/images/categories/system.png',
            'badge' => '/images/categories/system-badge.png',
            'vibrate' => [100, 50, 100],
            'requireInteraction' => false,
            'actions' => ['dismiss'],
        ],
    ],

    /*
     * Retry configuration for failed push notifications
     */
    'retry' => [
        'enabled' => env('WEBPUSH_RETRY_ENABLED', true),
        'max_attempts' => env('WEBPUSH_RETRY_MAX_ATTEMPTS', 3),
        'delay' => env('WEBPUSH_RETRY_DELAY', 60), // seconds
        'backoff_multiplier' => env('WEBPUSH_RETRY_BACKOFF_MULTIPLIER', 2),
    ],

    /*
     * Analytics and tracking
     */
    'analytics' => [
        'enabled' => env('WEBPUSH_ANALYTICS_ENABLED', true),
        'track_delivery' => env('WEBPUSH_TRACK_DELIVERY', true),
        'track_clicks' => env('WEBPUSH_TRACK_CLICKS', true),
        'track_dismissals' => env('WEBPUSH_TRACK_DISMISSALS', true),
    ],

    /*
     * Rate limiting for push notifications
     */
    'rate_limiting' => [
        'enabled' => env('WEBPUSH_RATE_LIMITING_ENABLED', true),
        'max_per_user_per_hour' => env('WEBPUSH_MAX_PER_USER_PER_HOUR', 10),
        'max_per_user_per_day' => env('WEBPUSH_MAX_PER_USER_PER_DAY', 50),
        'cooldown_period' => env('WEBPUSH_COOLDOWN_PERIOD', 300), // seconds
    ],

];
