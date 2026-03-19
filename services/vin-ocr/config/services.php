<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as OCR providers, cloud storage, and microservice communication.
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

    // OCR Service Providers
    'aws_textract' => [
        'key' => env('AWS_TEXTRACT_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
        'secret' => env('AWS_TEXTRACT_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
        'region' => env('AWS_TEXTRACT_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        'version' => env('AWS_TEXTRACT_VERSION', 'latest'),
        'endpoint' => env('AWS_TEXTRACT_ENDPOINT'),
    ],

    'google_vision' => [
        'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        'key_file' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'location' => env('GOOGLE_VISION_LOCATION', 'us-central1'),
    ],

    'azure_computer_vision' => [
        'endpoint' => env('AZURE_COMPUTER_VISION_ENDPOINT'),
        'subscription_key' => env('AZURE_COMPUTER_VISION_KEY'),
        'api_version' => env('AZURE_COMPUTER_VISION_API_VERSION', '2023-02-01-preview'),
    ],

    'tesseract' => [
        'binary_path' => env('TESSERACT_BINARY_PATH', '/usr/bin/tesseract'),
        'tessdata_path' => env('TESSERACT_TESSDATA_PATH', '/usr/share/tesseract-ocr/4.00/tessdata'),
        'languages' => env('TESSERACT_LANGUAGES', 'eng+ara'),
        'config_options' => [
            'psm' => env('TESSERACT_PSM', '6'), // Page segmentation mode
            'oem' => env('TESSERACT_OEM', '3'), // OCR Engine mode
        ],
    ],

    // Cloud Storage for VIN Images
    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'secure' => env('CLOUDINARY_SECURE', true),
        'folder' => env('CLOUDINARY_VIN_FOLDER', 'vin-images'),
    ],

    's3_vin_storage' => [
        'key' => env('AWS_VIN_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
        'secret' => env('AWS_VIN_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
        'region' => env('AWS_VIN_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        'bucket' => env('AWS_VIN_BUCKET', 'reversetender-vin-images'),
        'url' => env('AWS_VIN_URL'),
        'endpoint' => env('AWS_VIN_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_VIN_USE_PATH_STYLE_ENDPOINT', false),
    ],

    // VIN Validation Services
    'vin_decoder_api' => [
        'base_url' => env('VIN_DECODER_API_URL', 'https://vpic.nhtsa.dot.gov/api'),
        'timeout' => env('VIN_DECODER_TIMEOUT', 30),
        'rate_limit' => env('VIN_DECODER_RATE_LIMIT', 100), // requests per minute
    ],

    'carmd_api' => [
        'base_url' => env('CARMD_API_URL', 'https://api.carmd.com/v3.0'),
        'authorization' => env('CARMD_API_KEY'),
        'partner_token' => env('CARMD_PARTNER_TOKEN'),
        'timeout' => env('CARMD_TIMEOUT', 30),
    ],

    'edmunds_api' => [
        'base_url' => env('EDMUNDS_API_URL', 'https://api.edmunds.com/api'),
        'api_key' => env('EDMUNDS_API_KEY'),
        'timeout' => env('EDMUNDS_TIMEOUT', 30),
    ],

    // Microservice URLs for inter-service communication
    'auth_service' => [
        'url' => env('AUTH_SERVICE_URL', 'http://auth-service:8000'),
    ],

    'user_service' => [
        'url' => env('USER_SERVICE_URL', 'http://user-service:8000'),
    ],

    'analytics_service' => [
        'url' => env('ANALYTICS_SERVICE_URL', 'http://analytics-service:8000'),
    ],

    'notification_service' => [
        'url' => env('NOTIFICATION_SERVICE_URL', 'http://notification-service:8000'),
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

    'auction_service' => [
        'url' => env('AUCTION_SERVICE_URL', 'http://auction-service:8000'),
    ],

    'gateway_service' => [
        'url' => env('GATEWAY_SERVICE_URL', 'http://gateway-service:8000'),
    ],

];
