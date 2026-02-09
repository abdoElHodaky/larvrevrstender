<?php

/**
 * Migration Configuration
 * 
 * Central configuration file for MySQL to PostgreSQL migration
 */

/**
 * Helper function to get environment variable
 */
if (!function_exists('env')) {
    function env($key, $default = null)
    {
        $value = getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convert string representations of boolean values
        if (in_array(strtolower($value), ['true', 'false'])) {
            return strtolower($value) === 'true';
        }
        
        // Convert numeric strings to numbers
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }
        
        return $value;
    }
}

return [
    // Database Connections
    'mysql' => [
        'host' => env('MYSQL_HOST', 'mysql'),
        'port' => env('MYSQL_PORT', 3306),
        'username' => env('MYSQL_USERNAME', 'root'),
        'password' => env('MYSQL_PASSWORD', 'root_password'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
    
    'postgresql' => [
        'host' => env('POSTGRESQL_HOST', 'postgresql'),
        'port' => env('POSTGRESQL_PORT', 5432),
        'username' => env('POSTGRESQL_USERNAME', 'postgres'),
        'password' => env('POSTGRESQL_PASSWORD', 'postgres_password'),
        'charset' => 'utf8',
        'schema' => 'public',
    ],
    
    // PgBouncer Connection (for production)
    'pgbouncer' => [
        'host' => env('PGBOUNCER_HOST', 'pgbouncer'),
        'port' => env('PGBOUNCER_PORT', 6432),
        'username' => env('POSTGRESQL_USERNAME', 'postgres'),
        'password' => env('POSTGRESQL_PASSWORD', 'postgres_password'),
    ],
    
    // Migration Settings
    'migration' => [
        'batch_size' => env('MIGRATION_BATCH_SIZE', 1000),
        'timeout' => env('MIGRATION_TIMEOUT', 300), // 5 minutes
        'max_retries' => env('MIGRATION_MAX_RETRIES', 3),
        'strict_mode' => env('MIGRATION_STRICT_MODE', false),
        'parallel_workers' => env('MIGRATION_PARALLEL_WORKERS', 1),
        'memory_limit' => env('MIGRATION_MEMORY_LIMIT', '512M'),
    ],
    
    // Validation Settings
    'validation' => [
        'sample_size' => env('VALIDATION_SAMPLE_SIZE', 100),
        'checksum_validation' => env('VALIDATION_CHECKSUM', true),
        'performance_threshold' => env('VALIDATION_PERFORMANCE_THRESHOLD', 1.5), // 50% slower is acceptable
        'row_count_tolerance' => env('VALIDATION_ROW_COUNT_TOLERANCE', 0), // No tolerance for row count differences
    ],
    
    // Backup Settings
    'backup' => [
        'enabled' => env('BACKUP_ENABLED', true),
        'directory' => env('BACKUP_DIRECTORY', 'migration/backups'),
        'retention_days' => env('BACKUP_RETENTION_DAYS', 30),
        'compression' => env('BACKUP_COMPRESSION', true),
        'verify_backup' => env('BACKUP_VERIFY', true),
    ],
    
    // Logging Settings
    'logging' => [
        'level' => env('LOG_LEVEL', 'info'),
        'directory' => env('LOG_DIRECTORY', 'migration/logs'),
        'max_file_size' => env('LOG_MAX_FILE_SIZE', '10M'),
        'max_files' => env('LOG_MAX_FILES', 10),
        'log_queries' => env('LOG_QUERIES', false),
    ],
    
    // Monitoring Settings
    'monitoring' => [
        'enabled' => env('MONITORING_ENABLED', true),
        'progress_interval' => env('MONITORING_PROGRESS_INTERVAL', 1000), // Log progress every N rows
        'health_check_interval' => env('MONITORING_HEALTH_CHECK_INTERVAL', 30), // seconds
        'alert_on_failure' => env('MONITORING_ALERT_ON_FAILURE', true),
        'webhook_url' => env('MONITORING_WEBHOOK_URL', null),
    ],
    
    // Service Configuration
    'services' => [
        'gateway-service' => [
            'mysql_database' => 'gateway_service',
            'postgres_database' => 'gateway_service',
            'postgres_user' => 'gateway_user',
            'postgres_password' => 'gateway_password',
            'priority' => 1, // High priority - migrate first
            'dependencies' => [],
            'health_endpoint' => 'http://localhost:8000/health',
            'config_files' => [
                'env' => 'services/gateway-service/.env',
                'database' => 'services/gateway-service/config/database.php',
            ],
        ],
        
        'auth-service' => [
            'mysql_database' => 'auth_service',
            'postgres_database' => 'auth_service',
            'postgres_user' => 'auth_user',
            'postgres_password' => 'auth_password',
            'priority' => 1, // High priority - migrate first
            'dependencies' => [],
            'health_endpoint' => 'http://localhost:8001/health',
            'config_files' => [
                'env' => 'services/auth-service/.env',
                'database' => 'services/auth-service/config/database.php',
            ],
        ],
        
        'user-service' => [
            'mysql_database' => 'user_service',
            'postgres_database' => 'user_service',
            'postgres_user' => 'user_user',
            'postgres_password' => 'user_password',
            'priority' => 1, // High priority - migrate first
            'dependencies' => ['auth-service'],
            'health_endpoint' => 'http://localhost:8002/health',
            'config_files' => [
                'env' => 'services/user-service/.env',
                'database' => 'services/user-service/config/database.php',
            ],
        ],
        
        'order-service' => [
            'mysql_database' => 'order_service',
            'postgres_database' => 'order_service',
            'postgres_user' => 'order_user',
            'postgres_password' => 'order_password',
            'priority' => 2, // Medium priority
            'dependencies' => ['user-service', 'auth-service'],
            'health_endpoint' => 'http://localhost:8004/health',
            'config_files' => [
                'env' => 'services/order-service/.env',
                'database' => 'services/order-service/config/database.php',
            ],
        ],
        
        'payment-service' => [
            'mysql_database' => 'payment_service',
            'postgres_database' => 'payment_service',
            'postgres_user' => 'payment_user',
            'postgres_password' => 'payment_password',
            'priority' => 2, // Medium priority
            'dependencies' => ['order-service', 'user-service', 'auth-service'],
            'health_endpoint' => 'http://localhost:8005/health',
            'config_files' => [
                'env' => 'services/payment-service/.env',
                'database' => 'services/payment-service/config/database.php',
            ],
        ],
        
        'bidding-service' => [
            'mysql_database' => 'bidding_service',
            'postgres_database' => 'bidding_service',
            'postgres_user' => 'bidding_user',
            'postgres_password' => 'bidding_password',
            'priority' => 2, // Medium priority
            'dependencies' => ['user-service', 'auth-service'],
            'health_endpoint' => 'http://localhost:8006/health',
            'config_files' => [
                'env' => 'services/bidding-service/.env',
                'database' => 'services/bidding-service/config/database.php',
            ],
        ],
        
        'auction-service' => [
            'mysql_database' => 'auction_service',
            'postgres_database' => 'auction_service',
            'postgres_user' => 'auction_user',
            'postgres_password' => 'auction_password',
            'priority' => 3, // Lower priority
            'dependencies' => ['bidding-service', 'user-service', 'auth-service'],
            'health_endpoint' => 'http://localhost:8007/health',
            'config_files' => [
                'env' => 'services/auction-service/.env',
                'database' => 'services/auction-service/config/database.php',
            ],
        ],
        
        'notification-service' => [
            'mysql_database' => 'notification_service',
            'postgres_database' => 'notification_service',
            'postgres_user' => 'notification_user',
            'postgres_password' => 'notification_password',
            'priority' => 3, // Lower priority
            'dependencies' => ['auction-service', 'user-service', 'auth-service'],
            'health_endpoint' => 'http://localhost:8008/health',
            'config_files' => [
                'env' => 'services/notification-service/.env',
                'database' => 'services/notification-service/config/database.php',
            ],
        ],
        
        'vin-ocr-service' => [
            'mysql_database' => 'vin_ocr_service',
            'postgres_database' => 'vin_ocr_service',
            'postgres_user' => 'vin_ocr_user',
            'postgres_password' => 'vin_ocr_password',
            'priority' => 3, // Lower priority
            'dependencies' => ['user-service', 'auth-service'],
            'health_endpoint' => 'http://localhost:8009/health',
            'config_files' => [
                'env' => 'services/vin-ocr-service/.env',
                'database' => 'services/vin-ocr-service/config/database.php',
            ],
        ],
        
        'analytics-service' => [
            'mysql_database' => 'analytics_service',
            'postgres_database' => 'analytics_service',
            'postgres_user' => 'analytics_user',
            'postgres_password' => 'analytics_password',
            'priority' => 4, // Lowest priority - migrate last
            'dependencies' => ['*'], // Depends on all other services
            'health_endpoint' => 'http://localhost:8003/health',
            'config_files' => [
                'env' => 'services/analytics-service/.env',
                'database' => 'services/analytics-service/config/database.php',
            ],
            'olap_enabled' => true,
            'read_replicas' => true,
        ],
    ],
    
    // Data Type Mapping
    'type_mapping' => [
        'tinyint' => 'SMALLINT',
        'smallint' => 'SMALLINT',
        'mediumint' => 'INTEGER',
        'int' => 'INTEGER',
        'bigint' => 'BIGINT',
        'decimal' => 'DECIMAL',
        'float' => 'REAL',
        'double' => 'DOUBLE PRECISION',
        'bit' => 'BIT',
        'char' => 'CHAR',
        'varchar' => 'VARCHAR',
        'binary' => 'BYTEA',
        'varbinary' => 'BYTEA',
        'tinyblob' => 'BYTEA',
        'blob' => 'BYTEA',
        'mediumblob' => 'BYTEA',
        'longblob' => 'BYTEA',
        'tinytext' => 'TEXT',
        'text' => 'TEXT',
        'mediumtext' => 'TEXT',
        'longtext' => 'TEXT',
        'enum' => 'VARCHAR(255)', // Special handling required
        'set' => 'TEXT', // Special handling required
        'date' => 'DATE',
        'time' => 'TIME',
        'datetime' => 'TIMESTAMP',
        'timestamp' => 'TIMESTAMP',
        'year' => 'SMALLINT',
        'json' => 'JSONB',
    ],
    
    // Index Type Mapping
    'index_mapping' => [
        'BTREE' => '',
        'HASH' => 'USING HASH',
        'FULLTEXT' => 'USING GIN',
        'SPATIAL' => 'USING GIST',
    ],
    
    // Performance Tuning
    'performance' => [
        'postgresql' => [
            'work_mem' => '4MB',
            'maintenance_work_mem' => '64MB',
            'shared_buffers' => '256MB',
            'effective_cache_size' => '1GB',
            'random_page_cost' => 1.1,
            'seq_page_cost' => 1.0,
            'cpu_tuple_cost' => 0.01,
            'cpu_index_tuple_cost' => 0.005,
            'cpu_operator_cost' => 0.0025,
        ],
        'mysql' => [
            'innodb_buffer_pool_size' => '256M',
            'innodb_log_file_size' => '64M',
            'innodb_flush_log_at_trx_commit' => 1,
            'query_cache_size' => '32M',
            'tmp_table_size' => '32M',
            'max_heap_table_size' => '32M',
        ],
    ],
    
    // Environment-specific overrides
    'environments' => [
        'development' => [
            'migration' => [
                'batch_size' => 100,
                'strict_mode' => true,
            ],
            'logging' => [
                'level' => 'debug',
                'log_queries' => true,
            ],
        ],
        
        'staging' => [
            'migration' => [
                'batch_size' => 500,
                'parallel_workers' => 2,
            ],
            'validation' => [
                'sample_size' => 1000,
            ],
        ],
        
        'production' => [
            'migration' => [
                'batch_size' => 2000,
                'parallel_workers' => 4,
                'strict_mode' => false,
            ],
            'postgresql' => [
                'host' => env('PGBOUNCER_HOST', 'pgbouncer'),
                'port' => env('PGBOUNCER_PORT', 6432),
            ],
            'monitoring' => [
                'progress_interval' => 5000,
                'alert_on_failure' => true,
            ],
        ],
    ],
];
