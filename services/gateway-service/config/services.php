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

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'unifonic'),
        'providers' => [
            'unifonic' => [
                'base_url' => 'https://api.unifonic.com',
                'api_key' => env('UNIFONIC_API_KEY'),
                'sender_id' => env('UNIFONIC_SENDER_ID', 'ReverseTender'),
            ],
            'msegat' => [
                'base_url' => 'https://www.msegat.com',
                'username' => env('MSEGAT_USERNAME'),
                'api_key' => env('MSEGAT_API_KEY'),
                'sender_id' => env('MSEGAT_SENDER_ID', 'ReverseTender'),
            ],
            'oursms' => [
                'base_url' => 'https://oursms.net',
                'api_key' => env('OURSMS_API_KEY'),
                'sender_id' => env('OURSMS_SENDER_ID', 'ReverseTender'),
            ],
            'infobip' => [
                'base_url' => 'https://api.infobip.com',
                'api_key' => env('INFOBIP_API_KEY'),
                'sender_id' => env('INFOBIP_SENDER_ID', 'ReverseTender'),
            ],
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'twitter' => [
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_REDIRECT_URI'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    // Microservice URLs for inter-service communication
    'user_service' => [
        'url' => env('USER_SERVICE_URL', 'http://user-service:8000'),
    ],

    'bidding_service' => [
        'url' => env('BIDDING_SERVICE_URL', 'http://bidding-service:8000'),
    ],

    'order_service' => [
        'url' => env('ORDER_SERVICE_URL', 'http://order-service:8000'),
    ],

    'payment_service' => [
        'url' => env('PAYMENT_SERVICE_URL', 'http://payment-service:8000'),
    ],

    'analytics_service' => [
        'url' => env('ANALYTICS_SERVICE_URL', 'http://analytics-service:8000'),
    ],

    'vin_ocr_service' => [
        'url' => env('VIN_OCR_SERVICE_URL', 'http://vin-ocr-service:8000'),
    ],

];
