<?php

/*
|--------------------------------------------------------------------------
| Database Configuration Example for Multi-Tier Failover
|--------------------------------------------------------------------------
|
| This is an example of how to configure database connections for the
| database failover system. This configuration should be merged with
| your existing config/database.php file.
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. This will be
    | managed by the DatabaseFailoverManager.
    |
    */

    'default' => env('DB_CONNECTION', 'pgsql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | The failover system requires multiple connections configured for
    | the 3-tier architecture: Primary, Secondary, and Fallback.
    |
    */

    'connections' => [
        /*
        |--------------------------------------------------------------------------
        | Primary Database Connection (Neon PostgreSQL)
        |--------------------------------------------------------------------------
        */
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('NEON_DATABASE_URL'),
            'host' => env('NEON_DB_HOST', '127.0.0.1'),
            'port' => env('NEON_DB_PORT', '5432'),
            'database' => env('NEON_DB_DATABASE', 'reverse_tender_auth'),
            'username' => env('NEON_DB_USERNAME', 'forge'),
            'password' => env('NEON_DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'options' => extension_loaded('pdo_pgsql') ? array_filter([
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]) : [],
            // Failover configuration
            'failover_priority' => 1,
            'failover_name' => 'neon_postgresql',
        ],

        /*
        |--------------------------------------------------------------------------
        | Secondary Database Connection (Cloud PostgreSQL)
        |--------------------------------------------------------------------------
        */
        'pgsql_secondary' => [
            'driver' => 'pgsql',
            'url' => env('CLOUD_DATABASE_URL'),
            'host' => env('CLOUD_DB_HOST', '127.0.0.1'),
            'port' => env('CLOUD_DB_PORT', '5432'),
            'database' => env('CLOUD_DB_DATABASE', 'reverse_tender_auth'),
            'username' => env('CLOUD_DB_USERNAME', 'forge'),
            'password' => env('CLOUD_DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'options' => extension_loaded('pdo_pgsql') ? array_filter([
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]) : [],
            // Failover configuration
            'failover_priority' => 2,
            'failover_name' => 'cloud_postgresql',
        ],

        /*
        |--------------------------------------------------------------------------
        | Fallback Database Connection (MongoDB Atlas)
        |--------------------------------------------------------------------------
        */
        'mongodb' => [
            'driver' => 'mongodb',
            'host' => env('MONGO_DB_HOST', '127.0.0.1'),
            'port' => env('MONGO_DB_PORT', 27017),
            'database' => env('MONGO_DB_DATABASE', 'reverse_tender_auth'),
            'username' => env('MONGO_DB_USERNAME'),
            'password' => env('MONGO_DB_PASSWORD'),
            'options' => [
                'database' => env('MONGO_DB_AUTHENTICATION_DATABASE', 'admin'),
                'retryWrites' => true,
                'w' => 'majority',
                'connectTimeoutMS' => 30000,
                'serverSelectionTimeoutMS' => 30000,
            ],
            // Failover configuration
            'failover_priority' => 3,
            'failover_name' => 'mongodb_atlas',
        ],

        /*
        |--------------------------------------------------------------------------
        | Additional Connection Examples
        |--------------------------------------------------------------------------
        */

        // SQLite for testing/development
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        // MySQL example (if needed)
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    */

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];

/*
|--------------------------------------------------------------------------
| Environment Variables Required
|--------------------------------------------------------------------------
|
| Add these environment variables to your .env file:
|
| # Primary Database (Neon PostgreSQL)
| NEON_DATABASE_URL=postgresql://user:password@host:5432/database
| NEON_DB_HOST=your-neon-host.neon.tech
| NEON_DB_PORT=5432
| NEON_DB_DATABASE=reverse_tender_auth
| NEON_DB_USERNAME=your-neon-username
| NEON_DB_PASSWORD=your-neon-password
|
| # Secondary Database (Cloud PostgreSQL)
| CLOUD_DATABASE_URL=postgresql://user:password@host:5432/database
| CLOUD_DB_HOST=your-cloud-host.com
| CLOUD_DB_PORT=5432
| CLOUD_DB_DATABASE=reverse_tender_auth
| CLOUD_DB_USERNAME=your-cloud-username
| CLOUD_DB_PASSWORD=your-cloud-password
|
| # Fallback Database (MongoDB Atlas)
| MONGO_DB_HOST=your-cluster.mongodb.net
| MONGO_DB_PORT=27017
| MONGO_DB_DATABASE=reverse_tender_auth
| MONGO_DB_USERNAME=your-mongo-username
| MONGO_DB_PASSWORD=your-mongo-password
| MONGO_DB_AUTHENTICATION_DATABASE=admin
|
| # Database Failover Configuration
| DATABASE_FAILOVER_ENABLED=true
| DB_PRIMARY_CONNECTION=neon_postgresql
| DB_SECONDARY_CONNECTION=cloud_postgresql
| DB_FALLBACK_CONNECTION=mongodb_atlas
| DB_HEALTH_CHECK_INTERVAL=30
| DB_AUTOMATIC_FAILOVER=true
|
*/
