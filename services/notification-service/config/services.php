<?php

return [
    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Notification Services
    'pusher' => [
        'app_id' => env('PUSHER_APP_ID'),
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
    ],

    'firebase' => [
        'server_key' => env('FIREBASE_SERVER_KEY'),
        'sender_id' => env('FIREBASE_SENDER_ID'),
    ],

    // Microservice URLs for inter-service communication
    'auth_service' => [
        'url' => env('AUTH_SERVICE_URL', 'http://auth-service:8000'),
    ],

    'user_service' => [
        'url' => env('USER_SERVICE_URL', 'http://user-service:8000'),
    ],

    'order_service' => [
        'url' => env('ORDER_SERVICE_URL', 'http://order-service:8000'),
    ],

    'payment_service' => [
        'url' => env('PAYMENT_SERVICE_URL', 'http://payment-service:8000'),
    ],

    'bidding_service' => [
        'url' => env('BIDDING_SERVICE_URL', 'http://bidding-service:8000'),
    ],

    'analytics_service' => [
        'url' => env('ANALYTICS_SERVICE_URL', 'http://analytics-service:8000'),
    ],

    'vin_ocr_service' => [
        'url' => env('VIN_OCR_SERVICE_URL', 'http://vin-ocr-service:8000'),
    ],
];

