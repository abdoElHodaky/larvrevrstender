<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MENA-Compatible Notification Services Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for notification services that work well in the MENA region
    | as alternatives to Twilio and other international providers.
    |
    */

    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'infobip'),
        
        'providers' => [
            // Infobip - MENA-friendly, good coverage
            'infobip' => [
                'api_key' => env('INFOBIP_API_KEY'),
                'base_url' => env('INFOBIP_BASE_URL', 'https://api.infobip.com'),
                'from' => env('INFOBIP_WHATSAPP_FROM'),
            ],
            
            // Unifonic - Saudi-based, excellent MENA coverage
            'unifonic' => [
                'api_key' => env('UNIFONIC_API_KEY'),
                'base_url' => env('UNIFONIC_BASE_URL', 'https://api.unifonic.com'),
                'sender_id' => env('UNIFONIC_SENDER_ID'),
            ],
            
            // Msegat - UAE-based, good Gulf coverage
            'msegat' => [
                'username' => env('MSEGAT_USERNAME'),
                'api_key' => env('MSEGAT_API_KEY'),
                'base_url' => env('MSEGAT_BASE_URL', 'https://www.msegat.com'),
                'sender' => env('MSEGAT_SENDER'),
            ],
            
            // Oursms - Egypt-based, good North Africa coverage
            'oursms' => [
                'api_key' => env('OURSMS_API_KEY'),
                'base_url' => env('OURSMS_BASE_URL', 'https://oursms.net'),
                'from' => env('OURSMS_FROM'),
            ],
            
            // Meta WhatsApp Business API
            'meta' => [
                'access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),
                'base_url' => env('META_WHATSAPP_BASE_URL', 'https://graph.facebook.com/v18.0'),
                'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
            ],
        ],
        
        'templates' => [
            'welcome' => 'Welcome to our platform! Your account has been created successfully.',
            'verification' => 'Your verification code is: {code}',
            'order_confirmation' => 'Your order #{order_id} has been confirmed. Total: {amount}',
            'auction_won' => 'Congratulations! You won the auction for {item_name}. Amount: {amount}',
            'bid_outbid' => 'You have been outbid on {item_name}. Current highest bid: {amount}',
            'auction_ending' => 'Auction for {item_name} is ending soon. Current bid: {amount}',
        ],
    ],

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'unifonic'),
        
        // Unifonic - Saudi-based SMS provider
        'unifonic' => [
            'api_key' => env('UNIFONIC_SMS_API_KEY'),
            'base_url' => env('UNIFONIC_SMS_BASE_URL', 'https://api.unifonic.com'),
            'sender_id' => env('UNIFONIC_SMS_SENDER_ID'),
        ],
        
        // Msegat - UAE-based SMS provider
        'msegat' => [
            'username' => env('MSEGAT_SMS_USERNAME'),
            'api_key' => env('MSEGAT_SMS_API_KEY'),
            'base_url' => env('MSEGAT_SMS_BASE_URL', 'https://www.msegat.com'),
            'sender' => env('MSEGAT_SMS_SENDER'),
        ],
        
        // Oursms - Egypt-based SMS provider
        'oursms' => [
            'api_key' => env('OURSMS_SMS_API_KEY'),
            'base_url' => env('OURSMS_SMS_BASE_URL', 'https://oursms.net'),
            'from' => env('OURSMS_SMS_FROM'),
        ],
        
        // Infobip - International with MENA support
        'infobip' => [
            'api_key' => env('INFOBIP_SMS_API_KEY'),
            'base_url' => env('INFOBIP_SMS_BASE_URL', 'https://api.infobip.com'),
            'from' => env('INFOBIP_SMS_FROM'),
        ],
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'base_url' => env('TELEGRAM_BASE_URL', 'https://api.telegram.org/bot'),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    'signal' => [
        'method' => env('SIGNAL_METHOD', 'cli'), // cli, api_gateway, webhook
        'account' => env('SIGNAL_ACCOUNT'), // Your Signal phone number
        'cli_path' => env('SIGNAL_CLI_PATH', '/usr/local/bin/signal-cli'),
        'max_attachment_size' => env('SIGNAL_MAX_ATTACHMENT_SIZE', 104857600), // 100MB
        
        // API Gateway configuration (third-party service)
        'api_gateway' => [
            'url' => env('SIGNAL_API_GATEWAY_URL'),
            'api_key' => env('SIGNAL_API_GATEWAY_KEY'),
        ],
        
        // Webhook configuration (custom implementation)
        'webhook' => [
            'url' => env('SIGNAL_WEBHOOK_URL'),
            'secret' => env('SIGNAL_WEBHOOK_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Recommendations by Country
    |--------------------------------------------------------------------------
    |
    | Recommended providers for different MENA countries based on
    | local regulations, coverage, and reliability.
    |
    */
    'country_recommendations' => [
        'SA' => [ // Saudi Arabia
            'sms' => 'unifonic',
            'whatsapp' => 'unifonic',
            'priority' => ['whatsapp', 'sms', 'telegram', 'email']
        ],
        'AE' => [ // UAE
            'sms' => 'msegat',
            'whatsapp' => 'msegat',
            'priority' => ['whatsapp', 'sms', 'telegram', 'email']
        ],
        'EG' => [ // Egypt
            'sms' => 'oursms',
            'whatsapp' => 'oursms',
            'priority' => ['whatsapp', 'sms', 'telegram', 'email']
        ],
        'QA' => [ // Qatar
            'sms' => 'infobip',
            'whatsapp' => 'infobip',
            'priority' => ['whatsapp', 'sms', 'telegram', 'email']
        ],
        'KW' => [ // Kuwait
            'sms' => 'infobip',
            'whatsapp' => 'infobip',
            'priority' => ['whatsapp', 'sms', 'telegram', 'email']
        ],
        'BH' => [ // Bahrain
            'sms' => 'infobip',
            'whatsapp' => 'infobip',
            'priority' => ['whatsapp', 'sms', 'telegram', 'email']
        ],
        'OM' => [ // Oman
            'sms' => 'infobip',
            'whatsapp' => 'infobip',
            'priority' => ['whatsapp', 'sms', 'telegram', 'email']
        ],
        'JO' => [ // Jordan
            'sms' => 'infobip',
            'whatsapp' => 'infobip',
            'priority' => ['whatsapp', 'sms', 'telegram', 'email']
        ],
        'LB' => [ // Lebanon
            'sms' => 'infobip',
            'whatsapp' => 'infobip',
            'priority' => ['whatsapp', 'sms', 'telegram', 'email']
        ],
        'MA' => [ // Morocco
            'sms' => 'infobip',
            'whatsapp' => 'infobip',
            'priority' => ['whatsapp', 'sms', 'telegram', 'email']
        ],
        'TN' => [ // Tunisia
            'sms' => 'infobip',
            'whatsapp' => 'infobip',
            'priority' => ['whatsapp', 'sms', 'telegram', 'email']
        ],
        'DZ' => [ // Algeria
            'sms' => 'infobip',
            'whatsapp' => 'infobip',
            'priority' => ['whatsapp', 'sms', 'telegram', 'email']
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Rate limits for different providers to avoid hitting API limits
    | and ensure reliable delivery.
    |
    */
    'rate_limits' => [
        'whatsapp' => [
            'per_number_per_hour' => 10,
            'per_account_per_minute' => 100,
            'burst_limit' => 5,
        ],
        'sms' => [
            'per_number_per_hour' => 20,
            'per_account_per_minute' => 200,
            'burst_limit' => 10,
        ],
        'telegram' => [
            'per_chat_per_minute' => 30,
            'per_bot_per_second' => 30,
            'burst_limit' => 20,
        ],
        'signal' => [
            'per_number_per_hour' => 20,
            'per_account_per_minute' => 50,
            'burst_limit' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | Fallback providers and channels when primary providers fail.
    |
    */
    'fallbacks' => [
        'whatsapp' => [
            'primary' => 'unifonic',
            'secondary' => 'infobip',
            'tertiary' => 'meta',
        ],
        'sms' => [
            'primary' => 'unifonic',
            'secondary' => 'infobip',
            'tertiary' => 'msegat',
        ],
        'channels' => [
            'high_priority' => ['whatsapp', 'sms', 'telegram', 'email'],
            'medium_priority' => ['email', 'web_push', 'in_app'],
            'low_priority' => ['in_app', 'email'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization Support
    |--------------------------------------------------------------------------
    |
    | Language and localization settings for MENA region.
    |
    */
    'localization' => [
        'default_language' => 'ar',
        'supported_languages' => ['ar', 'en', 'fr'],
        'rtl_languages' => ['ar', 'he', 'fa'],
        'country_languages' => [
            'SA' => 'ar',
            'AE' => 'ar',
            'EG' => 'ar',
            'MA' => 'ar',
            'TN' => 'ar',
            'DZ' => 'ar',
            'JO' => 'ar',
            'LB' => 'ar',
            'QA' => 'ar',
            'KW' => 'ar',
            'BH' => 'ar',
            'OM' => 'ar',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Compliance and Regulations
    |--------------------------------------------------------------------------
    |
    | Settings for compliance with local regulations in MENA countries.
    |
    */
    'compliance' => [
        'data_residency' => [
            'SA' => 'local', // Data must stay in Saudi Arabia
            'AE' => 'gcc', // Data can be in GCC countries
            'EG' => 'local', // Data must stay in Egypt
        ],
        'opt_out_keywords' => ['STOP', 'توقف', 'UNSUBSCRIBE', 'إلغاء الاشتراك'],
        'sender_id_requirements' => [
            'SA' => 'alphanumeric_only',
            'AE' => 'alphanumeric_preferred',
            'EG' => 'numeric_required',
        ],
    ],
];
