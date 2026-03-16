<?php return array (
  'view' => 
  array (
    'paths' => 
    array (
      0 => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/resources/views',
    ),
    'compiled' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/framework/views',
  ),
  'broadcasting' => 
  array (
    'default' => 'redis',
    'connections' => 
    array (
      'reverb' => 
      array (
        'driver' => 'reverb',
        'key' => NULL,
        'secret' => NULL,
        'app_id' => NULL,
        'options' => 
        array (
          'host' => NULL,
          'port' => 443,
          'scheme' => 'https',
          'useTLS' => true,
        ),
        'client_options' => 
        array (
        ),
      ),
      'pusher' => 
      array (
        'driver' => 'pusher',
        'key' => NULL,
        'secret' => NULL,
        'app_id' => NULL,
        'options' => 
        array (
          'cluster' => NULL,
          'host' => 'api-mt1.pusher.com',
          'port' => 443,
          'scheme' => 'https',
          'encrypted' => true,
          'useTLS' => true,
        ),
        'client_options' => 
        array (
        ),
      ),
      'ably' => 
      array (
        'driver' => 'ably',
        'key' => NULL,
      ),
      'log' => 
      array (
        'driver' => 'log',
      ),
      'null' => 
      array (
        'driver' => 'null',
      ),
    ),
  ),
  'cors' => 
  array (
    'paths' => 
    array (
      0 => 'api/*',
      1 => 'sanctum/csrf-cookie',
    ),
    'allowed_methods' => 
    array (
      0 => '*',
    ),
    'allowed_origins' => 
    array (
      0 => '*',
    ),
    'allowed_origins_patterns' => 
    array (
    ),
    'allowed_headers' => 
    array (
      0 => '*',
    ),
    'exposed_headers' => 
    array (
    ),
    'max_age' => 0,
    'supports_credentials' => false,
  ),
  'hashing' => 
  array (
    'driver' => 'bcrypt',
    'bcrypt' => 
    array (
      'rounds' => '12',
      'verify' => true,
      'limit' => NULL,
    ),
    'argon' => 
    array (
      'memory' => 65536,
      'threads' => 1,
      'time' => 4,
      'verify' => true,
    ),
    'rehash_on_login' => true,
  ),
  'auth' => 
  array (
    'defaults' => 
    array (
      'guard' => 'web',
      'passwords' => 'users',
    ),
    'guards' => 
    array (
      'web' => 
      array (
        'driver' => 'session',
        'provider' => 'users',
      ),
      'sanctum' => 
      array (
        'driver' => 'sanctum',
        'provider' => NULL,
      ),
    ),
    'providers' => 
    array (
      'users' => 
      array (
        'driver' => 'eloquent',
        'model' => 'App\\Models\\User',
      ),
    ),
    'passwords' => 
    array (
      'users' => 
      array (
        'provider' => 'users',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
      ),
    ),
    'password_timeout' => 10800,
  ),
  'concurrency' => 
  array (
    'default' => 'process',
  ),
  'app' => 
  array (
    'name' => 'Auth Service',
    'env' => 'local',
    'debug' => true,
    'url' => 'http://localhost:8000',
    'frontend_url' => 'http://localhost:3000',
    'asset_url' => NULL,
    'timezone' => 'Asia/Riyadh',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'cipher' => 'AES-256-CBC',
    'key' => '',
    'previous_keys' => 
    array (
    ),
    'maintenance' => 
    array (
      'driver' => 'file',
      'store' => 'database',
    ),
    'providers' => 
    array (
      0 => 'Illuminate\\Auth\\AuthServiceProvider',
      1 => 'Illuminate\\Broadcasting\\BroadcastServiceProvider',
      2 => 'Illuminate\\Bus\\BusServiceProvider',
      3 => 'Illuminate\\Cache\\CacheServiceProvider',
      4 => 'Illuminate\\Foundation\\Providers\\ConsoleSupportServiceProvider',
      5 => 'Illuminate\\Concurrency\\ConcurrencyServiceProvider',
      6 => 'Illuminate\\Cookie\\CookieServiceProvider',
      7 => 'Illuminate\\Database\\DatabaseServiceProvider',
      8 => 'Illuminate\\Encryption\\EncryptionServiceProvider',
      9 => 'Illuminate\\Filesystem\\FilesystemServiceProvider',
      10 => 'Illuminate\\Foundation\\Providers\\FoundationServiceProvider',
      11 => 'Illuminate\\Hashing\\HashServiceProvider',
      12 => 'Illuminate\\Mail\\MailServiceProvider',
      13 => 'Illuminate\\Notifications\\NotificationServiceProvider',
      14 => 'Illuminate\\Pagination\\PaginationServiceProvider',
      15 => 'Illuminate\\Auth\\Passwords\\PasswordResetServiceProvider',
      16 => 'Illuminate\\Pipeline\\PipelineServiceProvider',
      17 => 'Illuminate\\Queue\\QueueServiceProvider',
      18 => 'Illuminate\\Redis\\RedisServiceProvider',
      19 => 'Illuminate\\Session\\SessionServiceProvider',
      20 => 'Illuminate\\Translation\\TranslationServiceProvider',
      21 => 'Illuminate\\Validation\\ValidationServiceProvider',
      22 => 'Illuminate\\View\\ViewServiceProvider',
      23 => 'Shared\\Providers\\SharedServiceProvider',
      24 => 'App\\Providers\\AppServiceProvider',
      25 => 'App\\Providers\\EventServiceProvider',
      26 => 'App\\Providers\\RpcServiceProvider',
      27 => 'Shared\\Providers\\SharedServiceProvider',
      28 => 'Tymon\\JWTAuth\\Providers\\LaravelServiceProvider',
    ),
    'aliases' => 
    array (
      'App' => 'Illuminate\\Support\\Facades\\App',
      'Arr' => 'Illuminate\\Support\\Arr',
      'Artisan' => 'Illuminate\\Support\\Facades\\Artisan',
      'Auth' => 'Illuminate\\Support\\Facades\\Auth',
      'Benchmark' => 'Illuminate\\Support\\Benchmark',
      'Blade' => 'Illuminate\\Support\\Facades\\Blade',
      'Broadcast' => 'Illuminate\\Support\\Facades\\Broadcast',
      'Bus' => 'Illuminate\\Support\\Facades\\Bus',
      'Cache' => 'Illuminate\\Support\\Facades\\Cache',
      'Concurrency' => 'Illuminate\\Support\\Facades\\Concurrency',
      'Config' => 'Illuminate\\Support\\Facades\\Config',
      'Context' => 'Illuminate\\Support\\Facades\\Context',
      'Cookie' => 'Illuminate\\Support\\Facades\\Cookie',
      'Crypt' => 'Illuminate\\Support\\Facades\\Crypt',
      'Date' => 'Illuminate\\Support\\Facades\\Date',
      'DB' => 'Illuminate\\Support\\Facades\\DB',
      'Eloquent' => 'Illuminate\\Database\\Eloquent\\Model',
      'Event' => 'Illuminate\\Support\\Facades\\Event',
      'File' => 'Illuminate\\Support\\Facades\\File',
      'Gate' => 'Illuminate\\Support\\Facades\\Gate',
      'Hash' => 'Illuminate\\Support\\Facades\\Hash',
      'Http' => 'Illuminate\\Support\\Facades\\Http',
      'Js' => 'Illuminate\\Support\\Js',
      'Lang' => 'Illuminate\\Support\\Facades\\Lang',
      'Log' => 'Illuminate\\Support\\Facades\\Log',
      'Mail' => 'Illuminate\\Support\\Facades\\Mail',
      'Notification' => 'Illuminate\\Support\\Facades\\Notification',
      'Number' => 'Illuminate\\Support\\Number',
      'Password' => 'Illuminate\\Support\\Facades\\Password',
      'Process' => 'Illuminate\\Support\\Facades\\Process',
      'Queue' => 'Illuminate\\Support\\Facades\\Queue',
      'RateLimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
      'Redirect' => 'Illuminate\\Support\\Facades\\Redirect',
      'Request' => 'Illuminate\\Support\\Facades\\Request',
      'Response' => 'Illuminate\\Support\\Facades\\Response',
      'Route' => 'Illuminate\\Support\\Facades\\Route',
      'Schedule' => 'Illuminate\\Support\\Facades\\Schedule',
      'Schema' => 'Illuminate\\Support\\Facades\\Schema',
      'Session' => 'Illuminate\\Support\\Facades\\Session',
      'Storage' => 'Illuminate\\Support\\Facades\\Storage',
      'Str' => 'Illuminate\\Support\\Str',
      'Uri' => 'Illuminate\\Support\\Uri',
      'URL' => 'Illuminate\\Support\\Facades\\URL',
      'Validator' => 'Illuminate\\Support\\Facades\\Validator',
      'View' => 'Illuminate\\Support\\Facades\\View',
      'Vite' => 'Illuminate\\Support\\Facades\\Vite',
    ),
  ),
  'cache' => 
  array (
    'default' => 'redis',
    'stores' => 
    array (
      'array' => 
      array (
        'driver' => 'array',
        'serialize' => false,
      ),
      'session' => 
      array (
        'driver' => 'session',
        'key' => '_cache',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'connection' => NULL,
        'table' => 'cache',
        'prefix' => '',
        'lock_connection' => NULL,
        'lock_table' => NULL,
      ),
      'file' => 
      array (
        'driver' => 'file',
        'path' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/framework/cache/data',
        'lock_path' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/framework/cache/data',
      ),
      'memcached' => 
      array (
        'driver' => 'memcached',
        'persistent_id' => NULL,
        'sasl' => 
        array (
          0 => NULL,
          1 => NULL,
        ),
        'options' => 
        array (
        ),
        'servers' => 
        array (
          0 => 
          array (
            'host' => '127.0.0.1',
            'port' => 11211,
            'weight' => 100,
          ),
        ),
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
      ),
      'dynamodb' => 
      array (
        'driver' => 'dynamodb',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'table' => 'cache',
        'endpoint' => NULL,
      ),
      'octane' => 
      array (
        'driver' => 'octane',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'stores' => 
        array (
          0 => 'database',
          1 => 'array',
        ),
      ),
    ),
    'prefix' => '',
  ),
  'database' => 
  array (
    'default' => 'pgsql',
    'connections' => 
    array (
      'sqlite' => 
      array (
        'driver' => 'sqlite',
        'url' => NULL,
        'database' => 'auth_service',
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => NULL,
        'journal_mode' => NULL,
        'synchronous' => NULL,
      ),
      'mysql' => 
      array (
        'driver' => 'mysql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '5432',
        'database' => 'auth_service',
        'username' => 'postgres',
        'password' => '',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'mariadb' => 
      array (
        'driver' => 'mariadb',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '5432',
        'database' => 'auth_service',
        'username' => 'postgres',
        'password' => '',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'pgsql' => 
      array (
        'driver' => 'pgsql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '5432',
        'database' => 'auth_service',
        'username' => 'postgres',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
        'options' => 
        array (
        ),
        'failover_priority' => 1,
        'failover_name' => 'neon_postgresql',
      ),
      'sqlsrv' => 
      array (
        'driver' => 'sqlsrv',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '5432',
        'database' => 'auth_service',
        'username' => 'postgres',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
      ),
      'pgsql_secondary' => 
      array (
        'driver' => 'pgsql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '5433',
        'database' => 'auth_service',
        'username' => 'postgres',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
        'options' => 
        array (
        ),
        'failover_priority' => 2,
        'failover_name' => 'cloud_postgresql',
      ),
      'mongodb' => 
      array (
        'driver' => 'mongodb',
        'host' => '127.0.0.1',
        'port' => '27017',
        'database' => 'auth_service',
        'username' => '',
        'password' => '',
        'options' => 
        array (
          'database' => 'admin',
          'retryWrites' => true,
          'w' => 'majority',
          'connectTimeoutMS' => 30000,
          'serverSelectionTimeoutMS' => 30000,
        ),
        'failover_priority' => 3,
        'failover_name' => 'mongodb_atlas',
      ),
    ),
    'migrations' => 
    array (
      'table' => 'migrations',
      'update_date_on_publish' => true,
    ),
    'redis' => 
    array (
      'client' => 'phpredis',
      'options' => 
      array (
        'cluster' => 'redis',
        'prefix' => 'auth_service_database_',
      ),
      'default' => 
      array (
        'url' => 'redis://127.0.0.1:6379/0',
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '0',
      ),
      'cache' => 
      array (
        'url' => 'redis://127.0.0.1:6379/0',
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '1',
      ),
      'horizon' => 
      array (
        'url' => 'redis://127.0.0.1:6379/0',
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '0',
        'options' => 
        array (
          'prefix' => 'auth_service_horizon:',
        ),
      ),
    ),
  ),
  'database-example' => 
  array (
    'default' => 'pgsql',
    'connections' => 
    array (
      'pgsql' => 
      array (
        'driver' => 'pgsql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '5432',
        'database' => 'auth_service',
        'username' => 'postgres',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
        'options' => 
        array (
        ),
        'failover_priority' => 1,
        'failover_name' => 'neon_postgresql',
      ),
      'pgsql_secondary' => 
      array (
        'driver' => 'pgsql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '5433',
        'database' => 'auth_service',
        'username' => 'postgres',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
        'options' => 
        array (
        ),
        'failover_priority' => 2,
        'failover_name' => 'cloud_postgresql',
      ),
      'mongodb' => 
      array (
        'driver' => 'mongodb',
        'host' => '127.0.0.1',
        'port' => '27017',
        'database' => 'auth_service',
        'username' => '',
        'password' => '',
        'options' => 
        array (
          'database' => 'admin',
          'retryWrites' => true,
          'w' => 'majority',
          'connectTimeoutMS' => 30000,
          'serverSelectionTimeoutMS' => 30000,
        ),
        'failover_priority' => 3,
        'failover_name' => 'mongodb_atlas',
      ),
      'sqlite' => 
      array (
        'driver' => 'sqlite',
        'url' => NULL,
        'database' => 'auth_service',
        'prefix' => '',
        'foreign_key_constraints' => true,
      ),
      'mysql' => 
      array (
        'driver' => 'mysql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '5432',
        'database' => 'auth_service',
        'username' => 'postgres',
        'password' => '',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
    ),
    'migrations' => 'migrations',
    'redis' => 
    array (
      'client' => 'phpredis',
      'options' => 
      array (
        'cluster' => 'redis',
        'prefix' => 'auth_service_database_',
      ),
      'default' => 
      array (
        'url' => 'redis://127.0.0.1:6379/0',
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '0',
      ),
      'cache' => 
      array (
        'url' => 'redis://127.0.0.1:6379/0',
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '1',
      ),
    ),
  ),
  'database-failover' => 
  array (
    'enabled' => true,
    'connections' => 
    array (
      'primary' => 'pgsql',
      'secondary' => 'pgsql_secondary',
      'fallback' => 'mongodb',
    ),
    'health_check' => 
    array (
      'interval' => '30',
      'timeout' => 5,
      'retry_attempts' => 3,
      'retry_delay' => 1000,
      'failure_threshold' => 3,
      'recovery_threshold' => 2,
    ),
    'failover' => 
    array (
      'automatic' => true,
      'switch_delay' => 500,
      'max_attempts' => 3,
      'circuit_breaker_timeout' => 60,
      'enable_graceful_degradation' => true,
    ),
    'services' => 
    array (
      'auth-service' => 
      array (
        'database' => 'reverse_tender_auth',
        'allow_readonly_fallback' => true,
        'critical_operations' => 
        array (
          0 => 'login',
          1 => 'register',
          2 => 'password_reset',
        ),
      ),
      'user-service' => 
      array (
        'database' => 'reverse_tender_users',
        'allow_readonly_fallback' => true,
        'critical_operations' => 
        array (
          0 => 'profile_update',
          1 => 'verification',
        ),
      ),
      'auction-service' => 
      array (
        'database' => 'reverse_tender',
        'allow_readonly_fallback' => false,
        'critical_operations' => 
        array (
          0 => 'bid_placement',
          1 => 'auction_creation',
        ),
      ),
      'bidding-service' => 
      array (
        'database' => 'reverse_tender_bidding',
        'allow_readonly_fallback' => false,
        'critical_operations' => 
        array (
          0 => 'bid_submission',
          1 => 'bid_evaluation',
        ),
      ),
      'payment-service' => 
      array (
        'database' => 'reverse_tender_payments',
        'allow_readonly_fallback' => false,
        'critical_operations' => 
        array (
          0 => 'payment_processing',
          1 => 'refund_processing',
        ),
      ),
      'order-service' => 
      array (
        'database' => 'reverse_tender_orders',
        'allow_readonly_fallback' => true,
        'critical_operations' => 
        array (
          0 => 'order_creation',
          1 => 'status_update',
        ),
      ),
      'notification-service' => 
      array (
        'database' => 'reverse_tender_notifications',
        'allow_readonly_fallback' => true,
        'critical_operations' => 
        array (
          0 => 'send_notification',
        ),
      ),
      'analytics-service' => 
      array (
        'database' => 'reverse_tender_analytics',
        'allow_readonly_fallback' => true,
        'critical_operations' => 
        array (
        ),
      ),
      'vin-ocr-service' => 
      array (
        'database' => 'reverse_tender_vehicles',
        'allow_readonly_fallback' => true,
        'critical_operations' => 
        array (
          0 => 'vin_processing',
        ),
      ),
      'gateway-service' => 
      array (
        'database' => 'reverse_tender',
        'allow_readonly_fallback' => true,
        'critical_operations' => 
        array (
          0 => 'request_routing',
        ),
      ),
    ),
    'mongodb_fallback' => 
    array (
      'enabled' => true,
      'sync_strategy' => 'async',
      'collection_mapping' => 
      array (
        'users' => 'user_profiles',
        'auctions' => 'auction_data',
        'bids' => 'bid_data',
        'orders' => 'order_data',
        'payments' => 'payment_transactions',
        'notifications' => 'notification_queue',
      ),
    ),
    'logging' => 
    array (
      'enabled' => true,
      'channel' => 'database_failover',
      'level' => 'info',
      'include_query_details' => false,
    ),
    'monitoring' => 
    array (
      'enabled' => true,
      'metrics_driver' => 'prometheus',
      'alert_webhook' => NULL,
      'dashboard_enabled' => true,
    ),
    'performance' => 
    array (
      'connection_pooling' => false,
      'pool_size' => 10,
      'connection_timeout' => 30,
      'query_timeout' => 60,
      'enable_query_cache' => true,
      'cache_ttl' => 300,
    ),
    'testing' => 
    array (
      'simulate_failures' => false,
      'failure_rate' => 0.1,
      'chaos_testing_enabled' => false,
      'mock_connections' => false,
    ),
  ),
  'filesystems' => 
  array (
    'default' => 'local',
    'disks' => 
    array (
      'local' => 
      array (
        'driver' => 'local',
        'root' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/app',
        'throw' => false,
      ),
      'public' => 
      array (
        'driver' => 'local',
        'root' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/app/public',
        'url' => 'http://localhost:8000/storage',
        'visibility' => 'public',
        'throw' => false,
      ),
      's3' => 
      array (
        'driver' => 's3',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'bucket' => '',
        'url' => NULL,
        'endpoint' => NULL,
        'use_path_style_endpoint' => false,
        'throw' => false,
        'options' => 
        array (
          'ServerSideEncryption' => 'AES256',
          'StorageClass' => 'STANDARD',
        ),
      ),
      's3-public' => 
      array (
        'driver' => 's3',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'bucket' => '',
        'url' => NULL,
        'endpoint' => NULL,
        'use_path_style_endpoint' => false,
        'visibility' => 'public',
        'throw' => false,
        'options' => 
        array (
          'CacheControl' => 'max-age=31536000',
          'StorageClass' => 'STANDARD',
        ),
      ),
      'digitalocean' => 
      array (
        'driver' => 's3',
        'key' => NULL,
        'secret' => NULL,
        'region' => 'nyc3',
        'bucket' => NULL,
        'url' => NULL,
        'endpoint' => NULL,
        'use_path_style_endpoint' => true,
        'throw' => false,
        'options' => 
        array (
          'CacheControl' => 'max-age=31536000',
          'StorageClass' => 'STANDARD',
        ),
      ),
      'linode' => 
      array (
        'driver' => 's3',
        'key' => NULL,
        'secret' => NULL,
        'region' => 'us-east-1',
        'bucket' => NULL,
        'url' => NULL,
        'endpoint' => NULL,
        'use_path_style_endpoint' => true,
        'throw' => false,
        'options' => 
        array (
          'ServerSideEncryption' => 'AES256',
          'StorageClass' => 'STANDARD',
        ),
      ),
      'backup' => 
      array (
        'driver' => 's3',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'bucket' => '-backup',
        'endpoint' => NULL,
        'use_path_style_endpoint' => false,
        'throw' => false,
        'options' => 
        array (
          'ServerSideEncryption' => 'AES256',
          'StorageClass' => 'STANDARD_IA',
        ),
      ),
    ),
    'links' => 
    array (
      '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/public/storage' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/app/public',
    ),
    'upload' => 
    array (
      'max_file_size' => 10240,
      'allowed_types' => 
      array (
        0 => 'jpg',
        1 => 'jpeg',
        2 => 'png',
        3 => 'pdf',
        4 => 'doc',
        5 => 'docx',
      ),
      'image_types' => 
      array (
        0 => 'jpg',
        1 => 'jpeg',
        2 => 'png',
        3 => 'gif',
        4 => 'webp',
      ),
      'document_types' => 
      array (
        0 => 'pdf',
        1 => 'doc',
        2 => 'docx',
        3 => 'txt',
        4 => 'csv',
        5 => 'xlsx',
      ),
      'services' => 
      array (
        'auth-service' => 
        array (
          'max_size' => 5120,
          'allowed_types' => 
          array (
            0 => 'jpg',
            1 => 'jpeg',
            2 => 'png',
          ),
          'path_prefix' => 'auth-service/',
        ),
        'user-service' => 
        array (
          'max_size' => 10240,
          'allowed_types' => 
          array (
            0 => 'jpg',
            1 => 'jpeg',
            2 => 'png',
            3 => 'pdf',
          ),
          'path_prefix' => 'user-service/',
        ),
        'bidding-service' => 
        array (
          'max_size' => 20480,
          'allowed_types' => 
          array (
            0 => 'jpg',
            1 => 'jpeg',
            2 => 'png',
            3 => 'pdf',
            4 => 'doc',
            5 => 'docx',
          ),
          'path_prefix' => 'bidding-service/',
        ),
        'order-service' => 
        array (
          'max_size' => 15360,
          'allowed_types' => 
          array (
            0 => 'jpg',
            1 => 'jpeg',
            2 => 'png',
            3 => 'pdf',
          ),
          'path_prefix' => 'order-service/',
        ),
        'payment-service' => 
        array (
          'max_size' => 5120,
          'allowed_types' => 
          array (
            0 => 'pdf',
          ),
          'path_prefix' => 'payment-service/',
        ),
        'notification-service' => 
        array (
          'max_size' => 10240,
          'allowed_types' => 
          array (
            0 => 'jpg',
            1 => 'jpeg',
            2 => 'png',
            3 => 'pdf',
          ),
          'path_prefix' => 'notification-service/',
        ),
        'analytics-service' => 
        array (
          'max_size' => 51200,
          'allowed_types' => 
          array (
            0 => 'csv',
            1 => 'xlsx',
            2 => 'pdf',
            3 => 'json',
          ),
          'path_prefix' => 'analytics-service/',
        ),
        'vin-ocr-service' => 
        array (
          'max_size' => 25600,
          'allowed_types' => 
          array (
            0 => 'jpg',
            1 => 'jpeg',
            2 => 'png',
          ),
          'path_prefix' => 'vin-ocr-service/',
        ),
      ),
    ),
    'optimization' => 
    array (
      'cdn_enabled' => false,
      'cdn_endpoint' => NULL,
      'image_optimization' => true,
      'compression_enabled' => true,
      'cache_control' => 'max-age=31536000',
    ),
  ),
  'horizon' => 
  array (
    'name' => 'Auth Service',
    'domain' => NULL,
    'path' => 'horizon',
    'use' => 'default',
    'prefix' => 'auth_service_horizon:',
    'middleware' => 
    array (
      0 => 'web',
    ),
    'waits' => 
    array (
      'redis:default' => 60,
    ),
    'trim' => 
    array (
      'recent' => 60,
      'pending' => 60,
      'completed' => 60,
      'recent_failed' => 10080,
      'failed' => 10080,
      'monitored' => 10080,
    ),
    'silenced' => 
    array (
    ),
    'silenced_tags' => 
    array (
    ),
    'metrics' => 
    array (
      'trim_snapshots' => 
      array (
        'job' => 24,
        'queue' => 24,
      ),
    ),
    'fast_termination' => false,
    'memory_limit' => 64,
    'defaults' => 
    array (
      'supervisor-1' => 
      array (
        'connection' => 'redis',
        'queue' => 
        array (
          0 => 'default',
        ),
        'balance' => 'auto',
        'autoScalingStrategy' => 'time',
        'maxProcesses' => 1,
        'maxTime' => 0,
        'maxJobs' => 0,
        'memory' => 128,
        'tries' => 1,
        'timeout' => 60,
        'nice' => 0,
      ),
    ),
    'environments' => 
    array (
      'production' => 
      array (
        'supervisor-1' => 
        array (
          'maxProcesses' => 10,
          'balanceMaxShift' => 1,
          'balanceCooldown' => 3,
        ),
      ),
      'local' => 
      array (
        'supervisor-1' => 
        array (
          'maxProcesses' => 3,
        ),
      ),
    ),
    'watch' => 
    array (
      0 => 'app',
      1 => 'bootstrap',
      2 => 'config/**/*.php',
      3 => 'database/**/*.php',
      4 => 'public/**/*.php',
      5 => 'resources/**/*.php',
      6 => 'routes',
      7 => 'composer.lock',
      8 => 'composer.json',
      9 => '.env',
    ),
  ),
  'logging' => 
  array (
    'default' => 'stack',
    'deprecations' => 
    array (
      'channel' => NULL,
      'trace' => false,
    ),
    'channels' => 
    array (
      'stack' => 
      array (
        'driver' => 'stack',
        'channels' => 
        array (
          0 => 'single',
          1 => 'database_failover',
          2 => 'shared_logger',
        ),
        'ignore_exceptions' => false,
      ),
      'single' => 
      array (
        'driver' => 'single',
        'path' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/logs/auth-service.log',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'daily' => 
      array (
        'driver' => 'daily',
        'path' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/logs/auth-service.log',
        'level' => 'debug',
        'days' => 14,
        'replace_placeholders' => true,
      ),
      'slack' => 
      array (
        'driver' => 'slack',
        'url' => NULL,
        'username' => 'Auth Service',
        'emoji' => ':shield:',
        'level' => 'critical',
        'replace_placeholders' => true,
      ),
      'papertrail' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\SyslogUdpHandler',
        'handler_with' => 
        array (
          'host' => NULL,
          'port' => NULL,
          'connectionString' => 'tls://:',
        ),
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'stderr' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\StreamHandler',
        'formatter' => NULL,
        'with' => 
        array (
          'stream' => 'php://stderr',
        ),
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
      'syslog' => 
      array (
        'driver' => 'syslog',
        'level' => 'debug',
        'facility' => 8,
        'replace_placeholders' => true,
      ),
      'errorlog' => 
      array (
        'driver' => 'errorlog',
        'level' => 'debug',
        'replace_placeholders' => true,
      ),
      'null' => 
      array (
        'driver' => 'monolog',
        'handler' => 'Monolog\\Handler\\NullHandler',
      ),
      'emergency' => 
      array (
        'path' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/logs/laravel.log',
      ),
      'database_failover' => 
      array (
        'driver' => 'daily',
        'path' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/logs/database_failover.log',
        'level' => 'info',
        'days' => 30,
        'replace_placeholders' => true,
      ),
      'shared_logger' => 
      array (
        'driver' => 'daily',
        'path' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/logs/shared.log',
        'level' => 'info',
        'days' => 14,
        'replace_placeholders' => true,
      ),
      'telescope' => 
      array (
        'driver' => 'monolog',
        'handler' => 'Monolog\\Handler\\FingersCrossedHandler',
        'handler_with' => 
        array (
          'handler' => 'Monolog\\Handler\\StreamHandler',
          'handler_with' => 
          array (
            'stream' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/logs/telescope.log',
          ),
          'activation_strategy' => 'Monolog\\Handler\\FingersCrossed\\ErrorLevelActivationStrategy',
          'activation_strategy_with' => 
          array (
            'actionLevel' => 200,
          ),
        ),
        'processors' => 
        array (
          0 => 'Monolog\\Processor\\PsrLogMessageProcessor',
        ),
      ),
    ),
  ),
  'mail' => 
  array (
    'default' => 'log',
    'mailers' => 
    array (
      'smtp' => 
      array (
        'transport' => 'smtp',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '2525',
        'encryption' => NULL,
        'username' => NULL,
        'password' => NULL,
        'timeout' => NULL,
        'local_domain' => 'localhost',
      ),
      'ses' => 
      array (
        'transport' => 'ses',
      ),
      'postmark' => 
      array (
        'transport' => 'postmark',
      ),
      'resend' => 
      array (
        'transport' => 'resend',
      ),
      'sendmail' => 
      array (
        'transport' => 'sendmail',
        'path' => '/usr/sbin/sendmail -bs -i',
      ),
      'log' => 
      array (
        'transport' => 'log',
        'channel' => NULL,
      ),
      'array' => 
      array (
        'transport' => 'array',
      ),
      'failover' => 
      array (
        'transport' => 'failover',
        'mailers' => 
        array (
          0 => 'smtp',
          1 => 'log',
        ),
      ),
      'roundrobin' => 
      array (
        'transport' => 'roundrobin',
        'mailers' => 
        array (
          0 => 'ses',
          1 => 'postmark',
        ),
      ),
    ),
    'from' => 
    array (
      'address' => 'hello@example.com',
      'name' => 'Auth Service',
    ),
    'markdown' => 
    array (
      'theme' => 'default',
      'paths' => 
      array (
        0 => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/resources/views/vendor/mail',
      ),
    ),
  ),
  'octane' => 
  array (
    'server' => 'frankenphp',
    'https' => false,
    'listeners' => 
    array (
      'Laravel\\Octane\\Events\\WorkerStarting' => 
      array (
        0 => 'Laravel\\Octane\\Listeners\\EnsureUploadedFilesAreValid',
        1 => 'Laravel\\Octane\\Listeners\\EnsureUploadedFilesCanBeMoved',
      ),
      'Laravel\\Octane\\Events\\RequestReceived' => 
      array (
      ),
      'Laravel\\Octane\\Events\\RequestHandled' => 
      array (
        0 => 'Laravel\\Octane\\Listeners\\FlushTemporaryContainerInstances',
      ),
      'Laravel\\Octane\\Events\\RequestTerminated' => 
      array (
      ),
      'Laravel\\Octane\\Events\\TaskReceived' => 
      array (
      ),
      'Laravel\\Octane\\Events\\TaskTerminated' => 
      array (
      ),
      'Laravel\\Octane\\Events\\TickReceived' => 
      array (
      ),
      'Laravel\\Octane\\Events\\TickTerminated' => 
      array (
      ),
      'Laravel\\Octane\\Events\\WorkerErrorOccurred' => 
      array (
        0 => 'Laravel\\Octane\\Listeners\\ReportException',
        1 => 'Laravel\\Octane\\Listeners\\StopWorkerIfNecessary',
      ),
      'Laravel\\Octane\\Events\\WorkerStopping' => 
      array (
      ),
    ),
    'warm' => 
    array (
      'procedures' => 
      array (
        0 => 'App\\RPC\\Procedures\\HealthProcedure',
        1 => 'App\\RPC\\Procedures\\UtilityProcedure',
      ),
    ),
    'flush' => 
    array (
    ),
    'tables' => 
    array (
      'rpc_metrics' => 
      array (
        'rows' => 1000,
        'columns' => 
        array (
          0 => 
          array (
            'name' => 'method',
            'type' => 'string',
            'size' => 100,
          ),
          1 => 
          array (
            'name' => 'response_time',
            'type' => 'float',
          ),
          2 => 
          array (
            'name' => 'memory_usage',
            'type' => 'int',
          ),
          3 => 
          array (
            'name' => 'timestamp',
            'type' => 'int',
          ),
        ),
      ),
    ),
    'cache' => 
    array (
      'rows' => 1000,
      'bytes' => 10000,
    ),
    'watch' => 
    array (
      0 => 'app',
      1 => 'bootstrap',
      2 => 'config',
      3 => 'database',
      4 => 'resources/**/*.php',
      5 => 'routes',
      6 => '.env',
    ),
    'garbage' => 50,
    'max_execution_time' => 30,
    'swoole' => 
    array (
      'options' => 
      array (
        'log_file' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/logs/swoole_http.log',
        'package_max_length' => 10485760,
      ),
    ),
    'roadrunner' => 
    array (
      'binary_path' => 'rr',
    ),
    'frankenphp' => 
    array (
      'host' => '127.0.0.1',
      'port' => 8000,
      'workers' => 2,
      'task_workers' => 4,
      'max_requests' => 500,
      'caddyfile' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/Caddyfile',
    ),
    'rpc' => 
    array (
      'host' => '127.0.0.1',
      'port' => 6010,
      'timeout' => 30,
      'batch_size' => 10,
    ),
    'performance' => 
    array (
      'memory_limit' => '256M',
      'opcache_preload' => true,
      'jit_enabled' => true,
      'gc_probability' => 0.01,
    ),
    'procedures' => 
    array (
      'cache_enabled' => true,
      'cache_ttl' => 300,
      'validation_enabled' => true,
      'logging_enabled' => true,
      'metrics_enabled' => true,
    ),
    'health' => 
    array (
      'enabled' => true,
      'endpoint' => '/health',
      'detailed_endpoint' => '/health/detailed',
      'metrics_endpoint' => '/metrics',
    ),
  ),
  'queue' => 
  array (
    'default' => 'redis',
    'connections' => 
    array (
      'sync' => 
      array (
        'driver' => 'sync',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
      ),
      'beanstalkd' => 
      array (
        'driver' => 'beanstalkd',
        'host' => 'localhost',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => 0,
        'after_commit' => false,
      ),
      'sqs' => 
      array (
        'driver' => 'sqs',
        'key' => '',
        'secret' => '',
        'prefix' => 'https://sqs.us-east-1.amazonaws.com/your-account-id',
        'queue' => 'default',
        'suffix' => NULL,
        'region' => 'us-east-1',
        'after_commit' => false,
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => NULL,
        'after_commit' => false,
      ),
      'deferred' => 
      array (
        'driver' => 'deferred',
      ),
      'failover' => 
      array (
        'driver' => 'failover',
        'connections' => 
        array (
          0 => 'database',
          1 => 'deferred',
        ),
      ),
    ),
    'batching' => 
    array (
      'database' => 'pgsql',
      'table' => 'job_batches',
    ),
    'failed' => 
    array (
      'driver' => 'database',
      'database' => 'pgsql',
      'table' => 'failed_jobs',
    ),
  ),
  'rpc' => 
  array (
    'client' => 
    array (
      'timeout' => '30',
      'retry_attempts' => '3',
      'retry_delay' => '1000',
    ),
    'services' => 
    array (
      'user' => 
      array (
        'url' => 'http://user-service:8080/rpc',
        'token' => '',
      ),
      'notification' => 
      array (
        'url' => 'http://notification-service:8080/rpc',
        'token' => '',
      ),
      'analytics' => 
      array (
        'url' => 'http://analytics-service:8080/rpc',
        'token' => '',
      ),
    ),
  ),
  'sanctum' => 
  array (
    'stateful' => 
    array (
      0 => 'localhost',
      1 => '127.0.0.1',
      2 => '127.0.0.1:8000',
      3 => '::1',
    ),
    'guard' => 
    array (
      0 => 'web',
    ),
    'expiration' => '43200',
    'token_prefix' => '',
    'middleware' => 
    array (
      'verify_csrf_token' => 'App\\Http\\Middleware\\VerifyCsrfToken',
      'encrypt_cookies' => 'App\\Http\\Middleware\\EncryptCookies',
    ),
  ),
  'services' => 
  array (
    'postmark' => 
    array (
      'token' => NULL,
    ),
    'resend' => 
    array (
      'key' => NULL,
    ),
    'ses' => 
    array (
      'key' => '',
      'secret' => '',
      'region' => 'us-east-1',
    ),
    'slack' => 
    array (
      'notifications' => 
      array (
        'bot_user_oauth_token' => NULL,
        'channel' => NULL,
      ),
    ),
    'mailgun' => 
    array (
      'domain' => NULL,
      'secret' => NULL,
      'endpoint' => 'api.mailgun.net',
      'scheme' => 'https',
    ),
    'sms' => 
    array (
      'provider' => 'twilio',
      'providers' => 
      array (
        'unifonic' => 
        array (
          'base_url' => 'https://api.unifonic.com',
          'api_key' => NULL,
          'sender_id' => 'ReverseTender',
        ),
        'msegat' => 
        array (
          'base_url' => 'https://www.msegat.com',
          'username' => NULL,
          'api_key' => NULL,
          'sender_id' => 'ReverseTender',
        ),
        'oursms' => 
        array (
          'base_url' => 'https://oursms.net',
          'api_key' => NULL,
          'sender_id' => 'ReverseTender',
        ),
        'infobip' => 
        array (
          'base_url' => 'https://api.infobip.com',
          'api_key' => NULL,
          'sender_id' => 'ReverseTender',
        ),
      ),
    ),
    'google' => 
    array (
      'client_id' => NULL,
      'client_secret' => NULL,
      'redirect' => NULL,
    ),
    'facebook' => 
    array (
      'client_id' => NULL,
      'client_secret' => NULL,
      'redirect' => NULL,
    ),
    'twitter' => 
    array (
      'client_id' => NULL,
      'client_secret' => NULL,
      'redirect' => NULL,
    ),
    'github' => 
    array (
      'client_id' => NULL,
      'client_secret' => NULL,
      'redirect' => NULL,
    ),
    'user_service' => 
    array (
      'url' => 'http://localhost:8001',
    ),
    'bidding_service' => 
    array (
      'url' => 'http://localhost:8002',
    ),
    'order_service' => 
    array (
      'url' => 'http://localhost:8003',
    ),
    'payment_service' => 
    array (
      'url' => 'http://localhost:8004',
    ),
    'analytics_service' => 
    array (
      'url' => 'http://localhost:8005',
    ),
    'vin_ocr_service' => 
    array (
      'url' => 'http://localhost:8006',
    ),
  ),
  'session' => 
  array (
    'driver' => 'database',
    'lifetime' => '120',
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/storage/framework/sessions',
    'connection' => NULL,
    'table' => 'sessions',
    'store' => NULL,
    'lottery' => 
    array (
      0 => 2,
      1 => 100,
    ),
    'cookie' => 'auth_service_session',
    'path' => '/',
    'domain' => NULL,
    'secure' => NULL,
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
  ),
  'telescope' => 
  array (
    'enabled' => true,
    'domain' => NULL,
    'path' => 'telescope',
    'driver' => 'database',
    'storage' => 
    array (
      'database' => 
      array (
        'connection' => 'pgsql',
        'chunk' => 1000,
      ),
    ),
    'queue' => 
    array (
      'connection' => NULL,
      'queue' => NULL,
      'delay' => 10,
    ),
    'middleware' => 
    array (
      0 => 'web',
      1 => 'Laravel\\Telescope\\Http\\Middleware\\Authorize',
    ),
    'only_paths' => 
    array (
    ),
    'ignore_paths' => 
    array (
      0 => 'nova-api*',
      1 => 'telescope*',
      2 => 'vendor/telescope*',
    ),
    'ignore_commands' => 
    array (
    ),
    'watchers' => 
    array (
      'Laravel\\Telescope\\Watchers\\CacheWatcher' => true,
      'Laravel\\Telescope\\Watchers\\CommandWatcher' => 
      array (
        'enabled' => true,
        'ignore' => 
        array (
        ),
      ),
      'Laravel\\Telescope\\Watchers\\DumpWatcher' => true,
      'Laravel\\Telescope\\Watchers\\EventWatcher' => 
      array (
        'enabled' => true,
        'ignore' => 
        array (
        ),
      ),
      'Laravel\\Telescope\\Watchers\\ExceptionWatcher' => true,
      'Laravel\\Telescope\\Watchers\\GateWatcher' => true,
      'Laravel\\Telescope\\Watchers\\ClientRequestWatcher' => true,
      'Laravel\\Telescope\\Watchers\\JobWatcher' => true,
      'Laravel\\Telescope\\Watchers\\LogWatcher' => true,
      'Laravel\\Telescope\\Watchers\\MailWatcher' => true,
      'Laravel\\Telescope\\Watchers\\ModelWatcher' => 
      array (
        'enabled' => true,
        'hydrations' => true,
      ),
      'Laravel\\Telescope\\Watchers\\NotificationWatcher' => true,
      'Laravel\\Telescope\\Watchers\\QueryWatcher' => 
      array (
        'enabled' => true,
        'ignore_packages' => true,
        'ignore_paths' => 
        array (
        ),
        'slow' => 100,
      ),
      'Laravel\\Telescope\\Watchers\\RedisWatcher' => true,
      'Laravel\\Telescope\\Watchers\\RequestWatcher' => 
      array (
        'enabled' => true,
        'size_limit' => 64,
      ),
      'Laravel\\Telescope\\Watchers\\ScheduleWatcher' => true,
      'Laravel\\Telescope\\Watchers\\ViewWatcher' => true,
    ),
  ),
  'sajya' => 
  array (
    'delimiter' => '@',
    'max_batch_size' => 30,
    'encode_options' => 0,
  ),
  'data' => 
  array (
    'date_format' => 'Y-m-d\\TH:i:sP',
    'date_timezone' => NULL,
    'features' => 
    array (
      'cast_and_transform_iterables' => false,
      'ignore_exception_when_trying_to_set_computed_property_value' => false,
    ),
    'transformers' => 
    array (
      'DateTimeInterface' => 'Spatie\\LaravelData\\Transformers\\DateTimeInterfaceTransformer',
      'Illuminate\\Contracts\\Support\\Arrayable' => 'Spatie\\LaravelData\\Transformers\\ArrayableTransformer',
      'BackedEnum' => 'Spatie\\LaravelData\\Transformers\\EnumTransformer',
    ),
    'casts' => 
    array (
      'DateTimeInterface' => 'Spatie\\LaravelData\\Casts\\DateTimeInterfaceCast',
      'BackedEnum' => 'Spatie\\LaravelData\\Casts\\EnumCast',
    ),
    'rule_inferrers' => 
    array (
      0 => 'Spatie\\LaravelData\\RuleInferrers\\SometimesRuleInferrer',
      1 => 'Spatie\\LaravelData\\RuleInferrers\\NullableRuleInferrer',
      2 => 'Spatie\\LaravelData\\RuleInferrers\\RequiredRuleInferrer',
      3 => 'Spatie\\LaravelData\\RuleInferrers\\BuiltInTypesRuleInferrer',
      4 => 'Spatie\\LaravelData\\RuleInferrers\\AttributesRuleInferrer',
    ),
    'normalizers' => 
    array (
      0 => 'Spatie\\LaravelData\\Normalizers\\ModelNormalizer',
      1 => 'Spatie\\LaravelData\\Normalizers\\ArrayableNormalizer',
      2 => 'Spatie\\LaravelData\\Normalizers\\ObjectNormalizer',
      3 => 'Spatie\\LaravelData\\Normalizers\\ArrayNormalizer',
      4 => 'Spatie\\LaravelData\\Normalizers\\JsonNormalizer',
    ),
    'wrap' => NULL,
    'var_dumper_caster_mode' => 'development',
    'structure_caching' => 
    array (
      'enabled' => true,
      'directories' => 
      array (
        0 => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/app/Data',
      ),
      'cache' => 
      array (
        'store' => 'redis',
        'prefix' => 'laravel-data',
        'duration' => NULL,
      ),
      'reflection_discovery' => 
      array (
        'enabled' => true,
        'base_path' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service',
        'root_namespace' => NULL,
      ),
    ),
    'validation_strategy' => 'only_requests',
    'name_mapping_strategy' => 
    array (
      'input' => NULL,
      'output' => NULL,
    ),
    'ignore_invalid_partials' => false,
    'max_transformation_depth' => NULL,
    'throw_when_max_transformation_depth_reached' => true,
    'commands' => 
    array (
      'make' => 
      array (
        'namespace' => 'Data',
        'suffix' => 'Data',
      ),
    ),
    'livewire' => 
    array (
      'enable_synths' => false,
    ),
  ),
  'event-sourcing' => 
  array (
    'auto_discover_projectors_and_reactors' => 
    array (
      0 => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/app',
    ),
    'auto_discover_base_path' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service',
    'projectors' => 
    array (
    ),
    'reactors' => 
    array (
    ),
    'queue' => NULL,
    'catch_exceptions' => false,
    'stored_event_model' => 'Spatie\\EventSourcing\\StoredEvents\\Models\\EloquentStoredEvent',
    'stored_event_repository' => 'Spatie\\EventSourcing\\StoredEvents\\Repositories\\EloquentStoredEventRepository',
    'snapshot_repository' => 'Spatie\\EventSourcing\\Snapshots\\EloquentSnapshotRepository',
    'snapshot_model' => 'Spatie\\EventSourcing\\Snapshots\\EloquentSnapshot',
    'stored_event_job' => 'Spatie\\EventSourcing\\StoredEvents\\HandleStoredEventJob',
    'enforce_event_class_map' => false,
    'event_class_map' => 
    array (
    ),
    'event_serializer' => 'Spatie\\EventSourcing\\EventSerializers\\JsonEventSerializer',
    'event_normalizers' => 
    array (
      0 => 'Spatie\\EventSourcing\\Support\\CarbonNormalizer',
      1 => 'Spatie\\EventSourcing\\Support\\ModelIdentifierNormalizer',
      2 => 'Symfony\\Component\\Serializer\\Normalizer\\DateTimeNormalizer',
      3 => 'Symfony\\Component\\Serializer\\Normalizer\\ArrayDenormalizer',
      4 => 'Spatie\\EventSourcing\\Support\\ObjectNormalizer',
    ),
    'cache_path' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/bootstrap/cache',
    'dispatch_events_from_aggregate_roots' => false,
    'aggregate_event_order_column' => 'id',
  ),
  'flare' => 
  array (
    'key' => NULL,
    'flare_middleware' => 
    array (
      0 => 'Spatie\\FlareClient\\FlareMiddleware\\RemoveRequestIp',
      1 => 'Spatie\\FlareClient\\FlareMiddleware\\AddGitInformation',
      2 => 'Spatie\\LaravelIgnition\\FlareMiddleware\\AddNotifierName',
      3 => 'Spatie\\LaravelIgnition\\FlareMiddleware\\AddEnvironmentInformation',
      4 => 'Spatie\\LaravelIgnition\\FlareMiddleware\\AddExceptionInformation',
      5 => 'Spatie\\LaravelIgnition\\FlareMiddleware\\AddDumps',
      'Spatie\\LaravelIgnition\\FlareMiddleware\\AddLogs' => 
      array (
        'maximum_number_of_collected_logs' => 200,
      ),
      'Spatie\\LaravelIgnition\\FlareMiddleware\\AddQueries' => 
      array (
        'maximum_number_of_collected_queries' => 200,
        'report_query_bindings' => true,
      ),
      'Spatie\\LaravelIgnition\\FlareMiddleware\\AddJobs' => 
      array (
        'max_chained_job_reporting_depth' => 5,
      ),
      6 => 'Spatie\\LaravelIgnition\\FlareMiddleware\\AddContext',
      7 => 'Spatie\\LaravelIgnition\\FlareMiddleware\\AddExceptionHandledStatus',
      'Spatie\\FlareClient\\FlareMiddleware\\CensorRequestBodyFields' => 
      array (
        'censor_fields' => 
        array (
          0 => 'password',
          1 => 'password_confirmation',
        ),
      ),
      'Spatie\\FlareClient\\FlareMiddleware\\CensorRequestHeaders' => 
      array (
        'headers' => 
        array (
          0 => 'API-KEY',
          1 => 'Authorization',
          2 => 'Cookie',
          3 => 'Set-Cookie',
          4 => 'X-CSRF-TOKEN',
          5 => 'X-XSRF-TOKEN',
        ),
      ),
    ),
    'send_logs_as_events' => true,
  ),
  'ignition' => 
  array (
    'editor' => 'phpstorm',
    'theme' => 'auto',
    'enable_share_button' => true,
    'register_commands' => false,
    'solution_providers' => 
    array (
      0 => 'Spatie\\Ignition\\Solutions\\SolutionProviders\\BadMethodCallSolutionProvider',
      1 => 'Spatie\\Ignition\\Solutions\\SolutionProviders\\MergeConflictSolutionProvider',
      2 => 'Spatie\\Ignition\\Solutions\\SolutionProviders\\UndefinedPropertySolutionProvider',
      3 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\IncorrectValetDbCredentialsSolutionProvider',
      4 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\MissingAppKeySolutionProvider',
      5 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\DefaultDbNameSolutionProvider',
      6 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\TableNotFoundSolutionProvider',
      7 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\MissingImportSolutionProvider',
      8 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\InvalidRouteActionSolutionProvider',
      9 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\ViewNotFoundSolutionProvider',
      10 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\RunningLaravelDuskInProductionProvider',
      11 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\MissingColumnSolutionProvider',
      12 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\UnknownValidationSolutionProvider',
      13 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\MissingMixManifestSolutionProvider',
      14 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\MissingViteManifestSolutionProvider',
      15 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\MissingLivewireComponentSolutionProvider',
      16 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\UndefinedViewVariableSolutionProvider',
      17 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\GenericLaravelExceptionSolutionProvider',
      18 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\OpenAiSolutionProvider',
      19 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\SailNetworkSolutionProvider',
      20 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\UnknownMysql8CollationSolutionProvider',
      21 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\UnknownMariadbCollationSolutionProvider',
    ),
    'ignored_solution_providers' => 
    array (
    ),
    'enable_runnable_solutions' => NULL,
    'remote_sites_path' => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service',
    'local_sites_path' => '',
    'housekeeping_endpoint_prefix' => '_ignition',
    'settings_file_path' => '',
    'recorders' => 
    array (
      0 => 'Spatie\\LaravelIgnition\\Recorders\\DumpRecorder\\DumpRecorder',
      1 => 'Spatie\\LaravelIgnition\\Recorders\\JobRecorder\\JobRecorder',
      2 => 'Spatie\\LaravelIgnition\\Recorders\\LogRecorder\\LogRecorder',
      3 => 'Spatie\\LaravelIgnition\\Recorders\\QueryRecorder\\QueryRecorder',
    ),
    'open_ai_key' => NULL,
    'with_stack_frame_arguments' => true,
    'argument_reducers' => 
    array (
      0 => 'Spatie\\Backtrace\\Arguments\\Reducers\\BaseTypeArgumentReducer',
      1 => 'Spatie\\Backtrace\\Arguments\\Reducers\\ArrayArgumentReducer',
      2 => 'Spatie\\Backtrace\\Arguments\\Reducers\\StdClassArgumentReducer',
      3 => 'Spatie\\Backtrace\\Arguments\\Reducers\\EnumArgumentReducer',
      4 => 'Spatie\\Backtrace\\Arguments\\Reducers\\ClosureArgumentReducer',
      5 => 'Spatie\\Backtrace\\Arguments\\Reducers\\DateTimeArgumentReducer',
      6 => 'Spatie\\Backtrace\\Arguments\\Reducers\\DateTimeZoneArgumentReducer',
      7 => 'Spatie\\Backtrace\\Arguments\\Reducers\\SymphonyRequestArgumentReducer',
      8 => 'Spatie\\LaravelIgnition\\ArgumentReducers\\ModelArgumentReducer',
      9 => 'Spatie\\LaravelIgnition\\ArgumentReducers\\CollectionArgumentReducer',
      10 => 'Spatie\\Backtrace\\Arguments\\Reducers\\StringableArgumentReducer',
    ),
  ),
  'query-builder' => 
  array (
    'parameters' => 
    array (
      'include' => 'include',
      'filter' => 'filter',
      'sort' => 'sort',
      'fields' => 'fields',
      'append' => 'append',
    ),
    'count_suffix' => 'Count',
    'exists_suffix' => 'Exists',
    'disable_invalid_filter_query_exception' => false,
    'disable_invalid_sort_query_exception' => false,
    'disable_invalid_includes_query_exception' => false,
    'convert_relation_names_to_snake_case_plural' => true,
    'convert_relation_table_name_strategy' => false,
    'convert_field_names_to_snake_case' => false,
  ),
  'structure-discoverer' => 
  array (
    'ignored_files' => 
    array (
    ),
    'structure_scout_directories' => 
    array (
      0 => '/tmp/abdoElHodaky/larvrevrstender/services/auth-service/app',
    ),
    'cache' => 
    array (
      'driver' => 'Spatie\\StructureDiscoverer\\Cache\\LaravelDiscoverCacheDriver',
      'store' => NULL,
    ),
  ),
  'jwt' => 
  array (
    'secret' => '',
    'keys' => 
    array (
      'public' => NULL,
      'private' => NULL,
      'passphrase' => NULL,
    ),
    'ttl' => '60',
    'refresh_ttl' => '20160',
    'algo' => 'HS256',
    'required_claims' => 
    array (
      0 => 'iss',
      1 => 'iat',
      2 => 'exp',
      3 => 'nbf',
      4 => 'sub',
      5 => 'jti',
    ),
    'persistent_claims' => 
    array (
    ),
    'lock_subject' => true,
    'leeway' => 0,
    'blacklist_enabled' => true,
    'blacklist_grace_period' => 0,
    'decrypt_cookies' => false,
    'providers' => 
    array (
      'jwt' => 'Tymon\\JWTAuth\\Providers\\JWT\\Lcobucci',
      'auth' => 'Tymon\\JWTAuth\\Providers\\Auth\\Illuminate',
      'storage' => 'Tymon\\JWTAuth\\Providers\\Storage\\Illuminate',
    ),
  ),
  'shared' => 
  array (
    'service' => 
    array (
      'name' => 'shared-service',
      'port' => 8010,
      'host' => '0.0.0.0',
      'timeout' => 30,
    ),
    'procedure_engine' => 
    array (
      'enabled' => true,
      'max_execution_time' => 300,
      'memory_limit' => '512M',
      'logging' => 
      array (
        'enabled' => true,
        'level' => 'info',
        'channel' => 'shared',
      ),
    ),
    'rest_handler' => 
    array (
      'enabled' => true,
      'cors' => 
      array (
        'enabled' => true,
        'allowed_origins' => 
        array (
          0 => '*',
        ),
        'allowed_methods' => 
        array (
          0 => 'GET',
          1 => 'POST',
          2 => 'PUT',
          3 => 'DELETE',
          4 => 'OPTIONS',
        ),
        'allowed_headers' => 
        array (
          0 => 'Content-Type',
          1 => 'Authorization',
          2 => 'X-Requested-With',
        ),
      ),
      'rate_limiting' => 
      array (
        'enabled' => true,
        'max_attempts' => 60,
        'decay_minutes' => 1,
      ),
    ),
    'rpc_handler' => 
    array (
      'enabled' => true,
      'version' => '2.0',
      'service_discovery' => 
      array (
        'enabled' => true,
        'registry_url' => 'http://gateway-service:8000/registry',
        'health_check_interval' => 30,
        'timeout' => 5,
      ),
    ),
    'caching' => 
    array (
      'enabled' => true,
      'driver' => 'redis',
      'prefix' => 'shared:',
      'ttl' => 3600,
      'compression' => 
      array (
        'enabled' => true,
        'algorithm' => 'gzip',
      ),
    ),
    'events' => 
    array (
      'enabled' => true,
      'driver' => 'redis',
      'channels' => 
      array (
        'default' => 'shared-events',
        'audit' => 'audit-events',
        'metrics' => 'metrics-events',
      ),
      'retry' => 
      array (
        'enabled' => true,
        'max_attempts' => 3,
        'delay' => 1000,
      ),
    ),
    'security' => 
    array (
      'encryption' => 
      array (
        'enabled' => true,
        'algorithm' => 'AES-256-GCM',
        'key' => NULL,
      ),
      'authentication' => 
      array (
        'enabled' => true,
        'driver' => 'jwt',
        'secret' => NULL,
      ),
      'rate_limiting' => 
      array (
        'enabled' => true,
        'max_requests' => 1000,
        'window_minutes' => 60,
      ),
    ),
    'monitoring' => 
    array (
      'metrics' => 
      array (
        'enabled' => true,
        'driver' => 'prometheus',
        'endpoint' => '/metrics',
      ),
      'tracing' => 
      array (
        'enabled' => true,
        'driver' => 'jaeger',
        'service_name' => 'shared-service',
      ),
      'health_checks' => 
      array (
        'enabled' => true,
        'endpoint' => '/health',
        'timeout' => 5,
      ),
    ),
    'circuit_breaker' => 
    array (
      'enabled' => true,
      'failure_threshold' => 5,
      'recovery_timeout' => 60,
      'expected_exception_types' => 
      array (
        0 => 'connection_timeout',
        1 => 'service_unavailable',
        2 => 'rate_limit_exceeded',
      ),
    ),
  ),
  'tinker' => 
  array (
    'commands' => 
    array (
    ),
    'alias' => 
    array (
    ),
    'dont_alias' => 
    array (
      0 => 'App\\Nova',
    ),
    'trust_project' => 'always',
  ),
);
