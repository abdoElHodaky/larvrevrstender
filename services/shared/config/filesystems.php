<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // S3-Compatible Storage (DigitalOcean Spaces / Linode Object Storage)
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => false,
            'options' => [
                'ServerSideEncryption' => env('STORAGE_ENCRYPTION_ENABLED', true) ? 'AES256' : null,
                'StorageClass' => 'STANDARD',
            ],
        ],

        // Public S3 disk for publicly accessible files
        's3-public' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('CDN_ENABLED', false) ? env('CDN_ENDPOINT') : env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'visibility' => 'public',
            'throw' => false,
            'options' => [
                'CacheControl' => 'max-age=31536000',
                'StorageClass' => 'STANDARD',
            ],
        ],

        // DigitalOcean Spaces specific configuration
        'digitalocean' => [
            'driver' => 's3',
            'key' => env('DO_SPACES_ACCESS_KEY'),
            'secret' => env('DO_SPACES_SECRET_KEY'),
            'region' => env('DO_SPACES_REGION', 'nyc3'),
            'bucket' => env('DO_SPACES_BUCKET'),
            'url' => env('DO_SPACES_URL'),
            'endpoint' => env('DO_SPACES_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => false,
            'options' => [
                'CacheControl' => 'max-age=31536000',
                'StorageClass' => 'STANDARD',
            ],
        ],

        // Linode Object Storage specific configuration
        'linode' => [
            'driver' => 's3',
            'key' => env('LINODE_ACCESS_KEY'),
            'secret' => env('LINODE_SECRET_KEY'),
            'region' => env('LINODE_REGION', 'us-east-1'),
            'bucket' => env('LINODE_BUCKET'),
            'url' => env('LINODE_URL'),
            'endpoint' => env('LINODE_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => false,
            'options' => [
                'ServerSideEncryption' => 'AES256',
                'StorageClass' => 'STANDARD',
            ],
        ],

        // Backup storage (separate bucket)
        'backup' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('AWS_BUCKET') . '-backup',
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => false,
            'options' => [
                'ServerSideEncryption' => 'AES256',
                'StorageClass' => 'STANDARD_IA',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for file uploads including size limits and allowed types
    |
    */

    'upload' => [
        'max_file_size' => env('MAX_FILE_SIZE', 10240), // KB
        'allowed_types' => explode(',', env('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,pdf,doc,docx')),
        'image_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'document_types' => ['pdf', 'doc', 'docx', 'txt', 'csv', 'xlsx'],
        
        // Service-specific configurations
        'services' => [
            'auth-service' => [
                'max_size' => 5120, // 5MB
                'allowed_types' => ['jpg', 'jpeg', 'png'],
                'path_prefix' => 'auth-service/',
            ],
            'user-service' => [
                'max_size' => 10240, // 10MB
                'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf'],
                'path_prefix' => 'user-service/',
            ],
            'bidding-service' => [
                'max_size' => 20480, // 20MB
                'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
                'path_prefix' => 'bidding-service/',
            ],
            'order-service' => [
                'max_size' => 15360, // 15MB
                'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf'],
                'path_prefix' => 'order-service/',
            ],
            'payment-service' => [
                'max_size' => 5120, // 5MB
                'allowed_types' => ['pdf'],
                'path_prefix' => 'payment-service/',
            ],
            'notification-service' => [
                'max_size' => 10240, // 10MB
                'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf'],
                'path_prefix' => 'notification-service/',
            ],
            'analytics-service' => [
                'max_size' => 51200, // 50MB
                'allowed_types' => ['csv', 'xlsx', 'pdf', 'json'],
                'path_prefix' => 'analytics-service/',
            ],
            'vin-ocr-service' => [
                'max_size' => 25600, // 25MB
                'allowed_types' => ['jpg', 'jpeg', 'png'],
                'path_prefix' => 'vin-ocr-service/',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Optimization
    |--------------------------------------------------------------------------
    |
    | Configuration for storage optimization including CDN and caching
    |
    */

    'optimization' => [
        'cdn_enabled' => env('CDN_ENABLED', false),
        'cdn_endpoint' => env('CDN_ENDPOINT'),
        'image_optimization' => env('IMAGE_OPTIMIZATION_ENABLED', true),
        'compression_enabled' => env('COMPRESSION_ENABLED', true),
        'cache_control' => env('CACHE_CONTROL', 'max-age=31536000'),
    ],

];
