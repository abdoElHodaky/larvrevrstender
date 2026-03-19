<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

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

    // Payment Gateway Services
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox or live
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

    'analytics_service' => [
        'url' => env('ANALYTICS_SERVICE_URL', 'http://analytics-service:8000'),
    ],

    'bidding_service' => [
        'url' => env('BIDDING_SERVICE_URL', 'http://bidding-service:8000'),
    ],

    'vin_ocr_service' => [
        'url' => env('VIN_OCR_SERVICE_URL', 'http://vin-ocr-service:8000'),
    ],

    'notification_service' => [
        'url' => env('NOTIFICATION_SERVICE_URL', 'http://notification-service:8000'),
    ],

];
