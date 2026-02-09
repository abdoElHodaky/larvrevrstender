<?php

/**
 * MySQL to PostgreSQL Schema Conversion Script
 * 
 * This script converts MySQL schemas to PostgreSQL-compatible schemas
 * for Laravel microservices migration.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

class MySQLToPostgreSQLSchemaConverter
{
    private $mysqlConnection;
    private $postgresConnection;
    private $config;
    private $logger;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->logger = new Logger();
        $this->initializeConnections();
    }
    
    private function initializeConnections()
    {
        // MySQL connection
        $this->mysqlConnection = new PDO(
            "mysql:host={$this->config['mysql']['host']};port={$this->config['mysql']['port']};charset=utf8mb4",
            $this->config['mysql']['username'],
            $this->config['mysql']['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // PostgreSQL connection
        $this->postgresConnection = new PDO(
            "pgsql:host={$this->config['postgresql']['host']};port={$this->config['postgresql']['port']}",
            $this->config['postgresql']['username'],
            $this->config['postgresql']['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    
    /**
     * Convert schema for a specific service
     */
    public function convertServiceSchema($serviceName, $mysqlDatabase, $postgresDatabase)
    {
        $this->logger->info("Starting schema conversion for service: {$serviceName}");
        
        try {
            // Get MySQL schema
            $mysqlSchema = $this->extractMySQLSchema($mysqlDatabase);
            
            // Convert to PostgreSQL schema
            $postgresSchema = $this->convertSchema($mysqlSchema);
            
            // Apply PostgreSQL schema
            $this->applyPostgreSQLSchema($postgresDatabase, $postgresSchema);
            
            // Generate migration report
            $this->generateMigrationReport($serviceName, $mysqlSchema, $postgresSchema);
            
            $this->logger->info("Schema conversion completed for service: {$serviceName}");
            
            return [
                'success' => true,
                'service' => $serviceName,
                'tables_converted' => count($postgresSchema['tables']),
                'indexes_converted' => count($postgresSchema['indexes']),
                'constraints_converted' => count($postgresSchema['constraints'])
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Schema conversion failed for {$serviceName}: " . $e->getMessage());
            return [
                'success' => false,
                'service' => $serviceName,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Extract MySQL schema information
     */
    private function extractMySQLSchema($database)
    {
        $schema = [
            'tables' => [],
            'indexes' => [],
            'constraints' => [],
            'triggers' => [],
            'procedures' => []
        ];
        
        // Get tables
        $stmt = $this->mysqlConnection->prepare("
            SELECT table_name, engine, table_collation, table_comment
            FROM information_schema.tables 
            WHERE table_schema = ? AND table_type = 'BASE TABLE'
        ");
        $stmt->execute([$database]);
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tables as $table) {
            $tableName = $table['table_name'];
            $schema['tables'][$tableName] = [
                'name' => $tableName,
                'engine' => $table['engine'],
                'collation' => $table['table_collation'],
                'comment' => $table['table_comment'],
                'columns' => $this->getTableColumns($database, $tableName),
                'indexes' => $this->getTableIndexes($database, $tableName),
                'constraints' => $this->getTableConstraints($database, $tableName)
            ];
        }
        
        return $schema;
    }
    
    /**
     * Get table columns with detailed information
     */
    private function getTableColumns($database, $tableName)
    {
        $stmt = $this->mysqlConnection->prepare("
            SELECT 
                column_name,
                data_type,
                column_type,
                is_nullable,
                column_default,
                extra,
                character_set_name,
                collation_name,
                column_comment
            FROM information_schema.columns 
            WHERE table_schema = ? AND table_name = ?
            ORDER BY ordinal_position
        ");
        $stmt->execute([$database, $tableName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get table indexes
     */
    private function getTableIndexes($database, $tableName)
    {
        $stmt = $this->mysqlConnection->prepare("
            SELECT 
                index_name,
                column_name,
                seq_in_index,
                non_unique,
                index_type,
                index_comment
            FROM information_schema.statistics 
            WHERE table_schema = ? AND table_name = ?
            ORDER BY index_name, seq_in_index
        ");
        $stmt->execute([$database, $tableName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get table constraints (foreign keys)
     */
    private function getTableConstraints($database, $tableName)
    {
        $stmt = $this->mysqlConnection->prepare("
            SELECT 
                kcu.constraint_name,
                kcu.column_name,
                kcu.referenced_table_schema,
                kcu.referenced_table_name,
                kcu.referenced_column_name,
                rc.update_rule,
                rc.delete_rule
            FROM information_schema.key_column_usage kcu
            JOIN information_schema.referential_constraints rc 
                ON kcu.constraint_name = rc.constraint_name 
                AND kcu.table_schema = rc.constraint_schema
            WHERE kcu.table_schema = ? AND kcu.table_name = ?
            AND kcu.referenced_table_name IS NOT NULL
        ");
        $stmt->execute([$database, $tableName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Convert MySQL schema to PostgreSQL schema
     */
    private function convertSchema($mysqlSchema)
    {
        $postgresSchema = [
            'tables' => [],
            'indexes' => [],
            'constraints' => [],
            'sequences' => []
        ];
        
        foreach ($mysqlSchema['tables'] as $tableName => $tableInfo) {
            $postgresSchema['tables'][$tableName] = $this->convertTable($tableInfo);
        }
        
        return $postgresSchema;
    }
    
    /**
     * Convert individual table schema
     */
    private function convertTable($tableInfo)
    {
        $convertedTable = [
            'name' => $tableInfo['name'],
            'columns' => [],
            'indexes' => [],
            'constraints' => [],
            'create_sql' => ''
        ];
        
        $createSql = "CREATE TABLE {$tableInfo['name']} (\n";
        $columnDefinitions = [];
        $sequences = [];
        
        foreach ($tableInfo['columns'] as $column) {
            $columnDef = $this->convertColumn($column);
            $columnDefinitions[] = $columnDef['definition'];
            
            if ($columnDef['sequence']) {
                $sequences[] = $columnDef['sequence'];
            }
        }
        
        $createSql .= "    " . implode(",\n    ", $columnDefinitions) . "\n";
        $createSql .= ");";
        
        // Add table comment if exists
        if (!empty($tableInfo['comment'])) {
            $createSql .= "\nCOMMENT ON TABLE {$tableInfo['name']} IS '{$tableInfo['comment']}';";
        }
        
        // Add sequences
        foreach ($sequences as $sequence) {
            $createSql = $sequence . "\n" . $createSql;
        }
        
        $convertedTable['create_sql'] = $createSql;
        $convertedTable['indexes'] = $this->convertIndexes($tableInfo['indexes'], $tableInfo['name']);
        $convertedTable['constraints'] = $this->convertConstraints($tableInfo['constraints'], $tableInfo['name']);
        
        return $convertedTable;
    }
    
    /**
     * Convert MySQL column to PostgreSQL column
     */
    private function convertColumn($column)
    {
        $name = $column['column_name'];
        $type = $this->convertDataType($column['data_type'], $column['column_type']);
        $nullable = $column['is_nullable'] === 'YES' ? '' : ' NOT NULL';
        $default = $this->convertDefault($column['column_default'], $column['extra']);
        $comment = !empty($column['column_comment']) ? " -- {$column['column_comment']}" : '';
        
        $definition = "{$name} {$type}{$nullable}{$default}{$comment}";
        
        $sequence = null;
        if (strpos($column['extra'], 'auto_increment') !== false) {
            $sequenceName = "{$name}_seq";
            $sequence = "CREATE SEQUENCE {$sequenceName};";
            $definition = str_replace($default, " DEFAULT nextval('{$sequenceName}')", $definition);
        }
        
        return [
            'definition' => $definition,
            'sequence' => $sequence
        ];
    }
    
    /**
     * Convert MySQL data types to PostgreSQL data types
     */
    private function convertDataType($dataType, $columnType)
    {
        $typeMap = [
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
            'enum' => 'VARCHAR(255)', // Will need custom handling
            'set' => 'TEXT', // Will need custom handling
            'date' => 'DATE',
            'time' => 'TIME',
            'datetime' => 'TIMESTAMP',
            'timestamp' => 'TIMESTAMP',
            'year' => 'SMALLINT',
            'json' => 'JSONB'
        ];
        
        // Extract size information for sized types
        if (preg_match('/(\w+)\(([^)]+)\)/', $columnType, $matches)) {
            $baseType = $matches[1];
            $size = $matches[2];
            
            if (isset($typeMap[$baseType])) {
                $pgType = $typeMap[$baseType];
                
                // Add size for appropriate types
                if (in_array($baseType, ['char', 'varchar', 'decimal'])) {
                    return "{$pgType}({$size})";
                }
                
                return $pgType;
            }
        }
        
        return $typeMap[$dataType] ?? 'TEXT';
    }
    
    /**
     * Convert MySQL default values to PostgreSQL
     */
    private function convertDefault($default, $extra)
    {
        if ($default === null) {
            return '';
        }
        
        // Handle special MySQL defaults
        $defaultMap = [
            'CURRENT_TIMESTAMP' => 'CURRENT_TIMESTAMP',
            '0000-00-00 00:00:00' => 'NULL',
            '0000-00-00' => 'NULL'
        ];
        
        if (isset($defaultMap[$default])) {
            return " DEFAULT {$defaultMap[$default]}";
        }
        
        // Handle auto_increment
        if (strpos($extra, 'auto_increment') !== false) {
            return ''; // Will be handled by sequence
        }
        
        // Quote string defaults
        if (is_string($default) && !is_numeric($default)) {
            return " DEFAULT '{$default}'";
        }
        
        return " DEFAULT {$default}";
    }
    
    /**
     * Convert MySQL indexes to PostgreSQL indexes
     */
    private function convertIndexes($indexes, $tableName)
    {
        $convertedIndexes = [];
        $indexGroups = [];
        
        // Group indexes by name
        foreach ($indexes as $index) {
            $indexGroups[$index['index_name']][] = $index;
        }
        
        foreach ($indexGroups as $indexName => $indexColumns) {
            if ($indexName === 'PRIMARY') {
                // Primary key
                $columns = array_column($indexColumns, 'column_name');
                $convertedIndexes[] = "ALTER TABLE {$tableName} ADD PRIMARY KEY (" . implode(', ', $columns) . ");";
            } else {
                // Regular index
                $columns = array_column($indexColumns, 'column_name');
                $unique = $indexColumns[0]['non_unique'] == 0 ? 'UNIQUE ' : '';
                $indexType = $this->convertIndexType($indexColumns[0]['index_type']);
                
                $convertedIndexes[] = "CREATE {$unique}INDEX {$indexName} ON {$tableName} {$indexType}(" . implode(', ', $columns) . ");";
            }
        }
        
        return $convertedIndexes;
    }
    
    /**
     * Convert MySQL index types to PostgreSQL
     */
    private function convertIndexType($mysqlIndexType)
    {
        $typeMap = [
            'BTREE' => '',
            'HASH' => 'USING HASH ',
            'FULLTEXT' => 'USING GIN ',
            'SPATIAL' => 'USING GIST '
        ];
        
        return $typeMap[$mysqlIndexType] ?? '';
    }
    
    /**
     * Convert MySQL constraints to PostgreSQL
     */
    private function convertConstraints($constraints, $tableName)
    {
        $convertedConstraints = [];
        
        foreach ($constraints as $constraint) {
            $constraintName = $constraint['constraint_name'];
            $column = $constraint['column_name'];
            $refTable = $constraint['referenced_table_name'];
            $refColumn = $constraint['referenced_column_name'];
            $updateRule = $constraint['update_rule'];
            $deleteRule = $constraint['delete_rule'];
            
            $sql = "ALTER TABLE {$tableName} ADD CONSTRAINT {$constraintName} ";
            $sql .= "FOREIGN KEY ({$column}) REFERENCES {$refTable}({$refColumn})";
            
            if ($updateRule !== 'RESTRICT') {
                $sql .= " ON UPDATE {$updateRule}";
            }
            
            if ($deleteRule !== 'RESTRICT') {
                $sql .= " ON DELETE {$deleteRule}";
            }
            
            $convertedConstraints[] = $sql . ";";
        }
        
        return $convertedConstraints;
    }
    
    /**
     * Apply PostgreSQL schema to database
     */
    private function applyPostgreSQLSchema($database, $schema)
    {
        // Switch to target database
        $this->postgresConnection->exec("\\c {$database}");
        
        // Create tables
        foreach ($schema['tables'] as $table) {
            $this->postgresConnection->exec($table['create_sql']);
            
            // Create indexes
            foreach ($table['indexes'] as $index) {
                $this->postgresConnection->exec($index);
            }
            
            // Create constraints
            foreach ($table['constraints'] as $constraint) {
                $this->postgresConnection->exec($constraint);
            }
        }
    }
    
    /**
     * Generate migration report
     */
    private function generateMigrationReport($serviceName, $mysqlSchema, $postgresSchema)
    {
        $report = [
            'service' => $serviceName,
            'timestamp' => date('Y-m-d H:i:s'),
            'mysql_tables' => count($mysqlSchema['tables']),
            'postgres_tables' => count($postgresSchema['tables']),
            'conversion_issues' => [],
            'recommendations' => []
        ];
        
        // Check for potential issues
        foreach ($mysqlSchema['tables'] as $tableName => $tableInfo) {
            foreach ($tableInfo['columns'] as $column) {
                if ($column['data_type'] === 'enum') {
                    $report['conversion_issues'][] = "Table {$tableName}, column {$column['column_name']}: ENUM type converted to VARCHAR(255)";
                    $report['recommendations'][] = "Consider creating CHECK constraint for {$tableName}.{$column['column_name']} ENUM values";
                }
                
                if ($column['data_type'] === 'set') {
                    $report['conversion_issues'][] = "Table {$tableName}, column {$column['column_name']}: SET type converted to TEXT";
                    $report['recommendations'][] = "Consider using array type for {$tableName}.{$column['column_name']} SET values";
                }
            }
        }
        
        file_put_contents(
            "migration/reports/schema_conversion_{$serviceName}_" . date('Y-m-d_H-i-s') . ".json",
            json_encode($report, JSON_PRETTY_PRINT)
        );
    }
}

/**
 * Simple logger class
 */
class Logger
{
    public function info($message)
    {
        echo "[INFO] " . date('Y-m-d H:i:s') . " - {$message}\n";
    }
    
    public function error($message)
    {
        echo "[ERROR] " . date('Y-m-d H:i:s') . " - {$message}\n";
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $config = require __DIR__ . '/../config/migration-config.php';
    $converter = new MySQLToPostgreSQLSchemaConverter($config);
    
    $services = [
        'gateway-service' => ['mysql' => 'gateway_service', 'postgres' => 'gateway_service'],
        'auth-service' => ['mysql' => 'auth_service', 'postgres' => 'auth_service'],
        'user-service' => ['mysql' => 'user_service', 'postgres' => 'user_service'],
        'analytics-service' => ['mysql' => 'analytics_service', 'postgres' => 'analytics_service'],
        'order-service' => ['mysql' => 'order_service', 'postgres' => 'order_service'],
        'payment-service' => ['mysql' => 'payment_service', 'postgres' => 'payment_service'],
        'bidding-service' => ['mysql' => 'bidding_service', 'postgres' => 'bidding_service'],
        'auction-service' => ['mysql' => 'auction_service', 'postgres' => 'auction_service'],
        'notification-service' => ['mysql' => 'notification_service', 'postgres' => 'notification_service'],
        'vin-ocr-service' => ['mysql' => 'vin_ocr_service', 'postgres' => 'vin_ocr_service']
    ];
    
    $serviceName = $argv[1] ?? null;
    
    if ($serviceName && isset($services[$serviceName])) {
        $result = $converter->convertServiceSchema(
            $serviceName,
            $services[$serviceName]['mysql'],
            $services[$serviceName]['postgres']
        );
        
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Usage: php mysql-to-postgresql-schema.php <service-name>\n";
        echo "Available services: " . implode(', ', array_keys($services)) . "\n";
    }
}

