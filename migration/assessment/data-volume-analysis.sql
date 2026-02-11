-- Data Volume Analysis for MySQL to PostgreSQL Migration
-- This script analyzes data volumes, growth patterns, and storage characteristics

-- =============================================================================
-- DATABASE SIZE ANALYSIS
-- =============================================================================

-- Overall database sizes across all services
SELECT 
    table_schema as database_name,
    COUNT(*) as table_count,
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS total_size_mb,
    ROUND(SUM(data_length) / 1024 / 1024, 2) AS data_size_mb,
    ROUND(SUM(index_length) / 1024 / 1024, 2) AS index_size_mb,
    ROUND((SUM(index_length) / SUM(data_length)) * 100, 2) AS index_ratio_percent
FROM information_schema.tables 
WHERE table_schema IN (
    'gateway_service', 'auth_service', 'user_service', 'analytics_service',
    'order_service', 'payment_service', 'bidding_service', 'auction_service',
    'notification_service', 'vin_ocr_service'
)
GROUP BY table_schema
ORDER BY total_size_mb DESC;

-- =============================================================================
-- TABLE-LEVEL ANALYSIS
-- =============================================================================

-- Largest tables across all databases
SELECT 
    table_schema as database_name,
    table_name,
    table_rows,
    ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
    ROUND(data_length / 1024 / 1024, 2) AS data_mb,
    ROUND(index_length / 1024 / 1024, 2) AS index_mb,
    engine,
    table_collation,
    CASE 
        WHEN table_rows > 0 THEN ROUND((data_length + index_length) / table_rows, 2)
        ELSE 0 
    END AS bytes_per_row
FROM information_schema.tables 
WHERE table_schema IN (
    'gateway_service', 'auth_service', 'user_service', 'analytics_service',
    'order_service', 'payment_service', 'bidding_service', 'auction_service',
    'notification_service', 'vin_ocr_service'
)
AND table_type = 'BASE TABLE'
ORDER BY (data_length + index_length) DESC
LIMIT 50;

-- =============================================================================
-- COLUMN TYPE ANALYSIS (PostgreSQL Compatibility)
-- =============================================================================

-- Analyze column types that may need special handling in PostgreSQL
SELECT 
    table_schema as database_name,
    table_name,
    column_name,
    data_type,
    column_type,
    is_nullable,
    column_default,
    extra,
    CASE 
        WHEN data_type = 'enum' THEN 'NEEDS_ENUM_CONVERSION'
        WHEN data_type = 'set' THEN 'NEEDS_SET_CONVERSION'
        WHEN data_type = 'timestamp' AND column_default = 'CURRENT_TIMESTAMP' THEN 'NEEDS_DEFAULT_CONVERSION'
        WHEN extra = 'auto_increment' THEN 'NEEDS_SEQUENCE_CONVERSION'
        WHEN data_type IN ('tinyint', 'mediumint') THEN 'NEEDS_INT_TYPE_CONVERSION'
        WHEN data_type = 'json' THEN 'COMPATIBLE_JSON'
        WHEN data_type LIKE '%text' THEN 'COMPATIBLE_TEXT'
        WHEN data_type LIKE '%blob' THEN 'NEEDS_BYTEA_CONVERSION'
        ELSE 'COMPATIBLE'
    END as postgresql_compatibility
FROM information_schema.columns 
WHERE table_schema IN (
    'gateway_service', 'auth_service', 'user_service', 'analytics_service',
    'order_service', 'payment_service', 'bidding_service', 'auction_service',
    'notification_service', 'vin_ocr_service'
)
ORDER BY 
    CASE 
        WHEN data_type IN ('enum', 'set') THEN 1
        WHEN extra = 'auto_increment' THEN 2
        WHEN data_type IN ('tinyint', 'mediumint') THEN 3
        WHEN data_type LIKE '%blob' THEN 4
        ELSE 5
    END,
    table_schema, table_name, column_name;

-- =============================================================================
-- INDEX ANALYSIS
-- =============================================================================

-- Analyze indexes for PostgreSQL conversion
SELECT 
    table_schema as database_name,
    table_name,
    index_name,
    column_name,
    seq_in_index,
    non_unique,
    index_type,
    CASE 
        WHEN index_type = 'FULLTEXT' THEN 'NEEDS_GIN_CONVERSION'
        WHEN index_type = 'SPATIAL' THEN 'NEEDS_GIST_CONVERSION'
        WHEN index_type = 'BTREE' THEN 'COMPATIBLE_BTREE'
        WHEN index_type = 'HASH' THEN 'COMPATIBLE_HASH'
        ELSE 'REVIEW_REQUIRED'
    END as postgresql_index_type
FROM information_schema.statistics 
WHERE table_schema IN (
    'gateway_service', 'auth_service', 'user_service', 'analytics_service',
    'order_service', 'payment_service', 'bidding_service', 'auction_service',
    'notification_service', 'vin_ocr_service'
)
ORDER BY table_schema, table_name, index_name, seq_in_index;

-- =============================================================================
-- FOREIGN KEY ANALYSIS
-- =============================================================================

-- Analyze foreign key constraints
SELECT 
    kcu.table_schema as database_name,
    kcu.table_name,
    kcu.column_name,
    kcu.constraint_name,
    kcu.referenced_table_schema,
    kcu.referenced_table_name,
    kcu.referenced_column_name,
    rc.update_rule,
    rc.delete_rule,
    CASE 
        WHEN kcu.referenced_table_schema != kcu.table_schema THEN 'CROSS_DATABASE_FK'
        ELSE 'SAME_DATABASE_FK'
    END as fk_type
FROM information_schema.key_column_usage kcu
JOIN information_schema.referential_constraints rc 
    ON kcu.constraint_name = rc.constraint_name 
    AND kcu.table_schema = rc.constraint_schema
WHERE kcu.table_schema IN (
    'gateway_service', 'auth_service', 'user_service', 'analytics_service',
    'order_service', 'payment_service', 'bidding_service', 'auction_service',
    'notification_service', 'vin_ocr_service'
)
AND kcu.referenced_table_name IS NOT NULL
ORDER BY kcu.table_schema, kcu.table_name, kcu.constraint_name;

-- =============================================================================
-- GROWTH PATTERN ANALYSIS (if available)
-- =============================================================================

-- Analyze table growth patterns using auto_increment values
SELECT 
    table_schema as database_name,
    table_name,
    auto_increment as current_max_id,
    table_rows,
    CASE 
        WHEN table_rows > 0 AND auto_increment > table_rows 
        THEN ROUND(((auto_increment - table_rows) / table_rows) * 100, 2)
        ELSE 0 
    END as deletion_percentage,
    ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
FROM information_schema.tables 
WHERE table_schema IN (
    'gateway_service', 'auth_service', 'user_service', 'analytics_service',
    'order_service', 'payment_service', 'bidding_service', 'auction_service',
    'notification_service', 'vin_ocr_service'
)
AND auto_increment IS NOT NULL
ORDER BY auto_increment DESC;

-- =============================================================================
-- STORAGE ENGINE ANALYSIS
-- =============================================================================

-- Analyze storage engines in use
SELECT 
    table_schema as database_name,
    engine,
    COUNT(*) as table_count,
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS total_size_mb,
    CASE 
        WHEN engine = 'InnoDB' THEN 'COMPATIBLE'
        WHEN engine = 'MyISAM' THEN 'NEEDS_CONVERSION'
        WHEN engine = 'MEMORY' THEN 'NEEDS_REVIEW'
        ELSE 'UNKNOWN_ENGINE'
    END as postgresql_compatibility
FROM information_schema.tables 
WHERE table_schema IN (
    'gateway_service', 'auth_service', 'user_service', 'analytics_service',
    'order_service', 'payment_service', 'bidding_service', 'auction_service',
    'notification_service', 'vin_ocr_service'
)
AND table_type = 'BASE TABLE'
GROUP BY table_schema, engine
ORDER BY table_schema, total_size_mb DESC;

-- =============================================================================
-- CHARACTER SET AND COLLATION ANALYSIS
-- =============================================================================

-- Analyze character sets and collations
SELECT 
    table_schema as database_name,
    table_collation,
    COUNT(*) as table_count,
    CASE 
        WHEN table_collation LIKE 'utf8mb4%' THEN 'COMPATIBLE_UTF8'
        WHEN table_collation LIKE 'utf8%' THEN 'COMPATIBLE_UTF8'
        WHEN table_collation LIKE 'latin1%' THEN 'NEEDS_ENCODING_CONVERSION'
        ELSE 'REVIEW_REQUIRED'
    END as postgresql_compatibility
FROM information_schema.tables 
WHERE table_schema IN (
    'gateway_service', 'auth_service', 'user_service', 'analytics_service',
    'order_service', 'payment_service', 'bidding_service', 'auction_service',
    'notification_service', 'vin_ocr_service'
)
AND table_type = 'BASE TABLE'
GROUP BY table_schema, table_collation
ORDER BY table_schema, table_count DESC;

-- =============================================================================
-- MIGRATION PRIORITY ANALYSIS
-- =============================================================================

-- Calculate migration priority based on size, complexity, and dependencies
SELECT 
    table_schema as database_name,
    COUNT(*) as table_count,
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
    SUM(CASE WHEN engine != 'InnoDB' THEN 1 ELSE 0 END) as non_innodb_tables,
    (SELECT COUNT(*) FROM information_schema.key_column_usage kcu 
     WHERE kcu.table_schema = t.table_schema 
     AND kcu.referenced_table_name IS NOT NULL) as foreign_key_count,
    (SELECT COUNT(DISTINCT column_name) FROM information_schema.columns c
     WHERE c.table_schema = t.table_schema 
     AND c.data_type IN ('enum', 'set')) as special_type_columns,
    CASE 
        WHEN SUM(data_length + index_length) / 1024 / 1024 < 100 THEN 'LOW'
        WHEN SUM(data_length + index_length) / 1024 / 1024 < 1000 THEN 'MEDIUM'
        ELSE 'HIGH'
    END as size_complexity,
    CASE 
        WHEN table_schema IN ('gateway_service', 'auth_service') THEN 'HIGH_PRIORITY'
        WHEN table_schema IN ('user_service', 'order_service', 'payment_service') THEN 'MEDIUM_PRIORITY'
        ELSE 'LOW_PRIORITY'
    END as business_priority
FROM information_schema.tables t
WHERE table_schema IN (
    'gateway_service', 'auth_service', 'user_service', 'analytics_service',
    'order_service', 'payment_service', 'bidding_service', 'auction_service',
    'notification_service', 'vin_ocr_service'
)
AND table_type = 'BASE TABLE'
GROUP BY table_schema
ORDER BY 
    CASE 
        WHEN table_schema IN ('gateway_service', 'auth_service') THEN 1
        WHEN table_schema IN ('user_service', 'order_service', 'payment_service') THEN 2
        ELSE 3
    END,
    size_mb DESC;

