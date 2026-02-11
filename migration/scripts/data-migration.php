<?php

/**
 * Data Migration Script - MySQL to PostgreSQL
 * 
 * This script handles the actual data transfer from MySQL to PostgreSQL
 * with validation, progress tracking, and error handling.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

class DataMigrationManager
{
    private $mysqlConnection;
    private $postgresConnection;
    private $config;
    private $logger;
    private $progressTracker;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->logger = new MigrationLogger();
        $this->progressTracker = new ProgressTracker();
        $this->initializeConnections();
    }
    
    private function initializeConnections()
    {
        // MySQL connection with optimizations for large data transfers
        $this->mysqlConnection = new PDO(
            "mysql:host={$this->config['mysql']['host']};port={$this->config['mysql']['port']};charset=utf8mb4",
            $this->config['mysql']['username'],
            $this->config['mysql']['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // For large result sets
                PDO::ATTR_TIMEOUT => 300 // 5 minute timeout
            ]
        );
        
        // PostgreSQL connection with optimizations
        $this->postgresConnection = new PDO(
            "pgsql:host={$this->config['postgresql']['host']};port={$this->config['postgresql']['port']}",
            $this->config['postgresql']['username'],
            $this->config['postgresql']['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 300
            ]
        );
        
        // Optimize PostgreSQL for bulk inserts
        $this->postgresConnection->exec("SET synchronous_commit = OFF");
        $this->postgresConnection->exec("SET wal_buffers = '16MB'");
        $this->postgresConnection->exec("SET checkpoint_segments = 32");
    }
    
    /**
     * Migrate data for a specific service
     */
    public function migrateServiceData($serviceName, $mysqlDatabase, $postgresDatabase, $options = [])
    {
        $this->logger->info("Starting data migration for service: {$serviceName}");
        
        $migrationResult = [
            'service' => $serviceName,
            'start_time' => date('Y-m-d H:i:s'),
            'tables_migrated' => 0,
            'total_rows_migrated' => 0,
            'errors' => [],
            'warnings' => [],
            'performance_stats' => []
        ];
        
        try {
            // Get list of tables to migrate
            $tables = $this->getTableList($mysqlDatabase);
            $this->logger->info("Found " . count($tables) . " tables to migrate");
            
            // Disable foreign key checks during migration
            $this->postgresConnection->exec("SET session_replication_role = replica");
            
            foreach ($tables as $table) {
                $tableResult = $this->migrateTable(
                    $mysqlDatabase, 
                    $postgresDatabase, 
                    $table['table_name'],
                    $options
                );
                
                $migrationResult['tables_migrated']++;
                $migrationResult['total_rows_migrated'] += $tableResult['rows_migrated'];
                $migrationResult['performance_stats'][$table['table_name']] = $tableResult['performance'];
                
                if (!empty($tableResult['errors'])) {
                    $migrationResult['errors'] = array_merge($migrationResult['errors'], $tableResult['errors']);
                }
                
                if (!empty($tableResult['warnings'])) {
                    $migrationResult['warnings'] = array_merge($migrationResult['warnings'], $tableResult['warnings']);
                }
            }
            
            // Re-enable foreign key checks
            $this->postgresConnection->exec("SET session_replication_role = DEFAULT");
            
            // Update sequences to correct values
            $this->updateSequences($postgresDatabase);
            
            $migrationResult['end_time'] = date('Y-m-d H:i:s');
            $migrationResult['success'] = true;
            
            $this->logger->info("Data migration completed for service: {$serviceName}");
            
        } catch (Exception $e) {
            $migrationResult['success'] = false;
            $migrationResult['error'] = $e->getMessage();
            $migrationResult['end_time'] = date('Y-m-d H:i:s');
            
            $this->logger->error("Data migration failed for {$serviceName}: " . $e->getMessage());
        }
        
        // Save migration report
        $this->saveMigrationReport($serviceName, $migrationResult);
        
        return $migrationResult;
    }
    
    /**
     * Get list of tables from MySQL database
     */
    private function getTableList($database)
    {
        $stmt = $this->mysqlConnection->prepare("
            SELECT table_name, table_rows, 
                   ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = ? AND table_type = 'BASE TABLE'
            ORDER BY table_rows DESC
        ");
        $stmt->execute([$database]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Migrate individual table data
     */
    private function migrateTable($mysqlDatabase, $postgresDatabase, $tableName, $options)
    {
        $this->logger->info("Migrating table: {$tableName}");
        
        $startTime = microtime(true);
        $batchSize = $options['batch_size'] ?? 1000;
        $offset = 0;
        $totalRows = 0;
        $errors = [];
        $warnings = [];
        
        try {
            // Get total row count for progress tracking
            $countStmt = $this->mysqlConnection->prepare("SELECT COUNT(*) FROM {$mysqlDatabase}.{$tableName}");
            $countStmt->execute();
            $totalRowCount = $countStmt->fetchColumn();
            
            $this->logger->info("Table {$tableName} has {$totalRowCount} rows");
            
            // Get column information
            $columns = $this->getTableColumns($mysqlDatabase, $tableName);
            $columnNames = array_column($columns, 'column_name');
            
            // Prepare PostgreSQL insert statement
            $placeholders = str_repeat('?,', count($columnNames) - 1) . '?';
            $insertSql = "INSERT INTO {$postgresDatabase}.{$tableName} (" . 
                        implode(',', $columnNames) . ") VALUES ({$placeholders})";
            $insertStmt = $this->postgresConnection->prepare($insertSql);
            
            // Begin transaction for batch processing
            $this->postgresConnection->beginTransaction();
            
            while ($offset < $totalRowCount) {
                // Fetch batch from MySQL
                $selectSql = "SELECT " . implode(',', $columnNames) . 
                           " FROM {$mysqlDatabase}.{$tableName} LIMIT {$batchSize} OFFSET {$offset}";
                $selectStmt = $this->mysqlConnection->prepare($selectSql);
                $selectStmt->execute();
                
                $batchData = $selectStmt->fetchAll(PDO::FETCH_NUM);
                
                if (empty($batchData)) {
                    break;
                }
                
                // Insert batch into PostgreSQL
                foreach ($batchData as $row) {
                    try {
                        // Convert data types if necessary
                        $convertedRow = $this->convertRowData($row, $columns);
                        $insertStmt->execute($convertedRow);
                        $totalRows++;
                    } catch (Exception $e) {
                        $errors[] = "Row insert error in {$tableName}: " . $e->getMessage();
                        
                        // Skip row and continue if not in strict mode
                        if (!($options['strict_mode'] ?? false)) {
                            continue;
                        } else {
                            throw $e;
                        }
                    }
                }
                
                // Commit batch
                $this->postgresConnection->commit();
                $this->postgresConnection->beginTransaction();
                
                $offset += $batchSize;
                
                // Progress reporting
                $progress = ($offset / $totalRowCount) * 100;
                $this->progressTracker->updateProgress($tableName, $progress, $totalRows);
                
                // Memory management
                if ($offset % ($batchSize * 10) === 0) {
                    gc_collect_cycles();
                }
            }
            
            $this->postgresConnection->commit();
            
        } catch (Exception $e) {
            $this->postgresConnection->rollback();
            throw $e;
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        $performance = [
            'duration_seconds' => round($duration, 2),
            'rows_per_second' => $totalRows > 0 ? round($totalRows / $duration, 2) : 0,
            'total_rows' => $totalRows
        ];
        
        $this->logger->info("Table {$tableName} migration completed: {$totalRows} rows in {$performance['duration_seconds']} seconds");
        
        return [
            'table' => $tableName,
            'rows_migrated' => $totalRows,
            'performance' => $performance,
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Get table column information
     */
    private function getTableColumns($database, $tableName)
    {
        $stmt = $this->mysqlConnection->prepare("
            SELECT column_name, data_type, column_type, is_nullable, column_default, extra
            FROM information_schema.columns 
            WHERE table_schema = ? AND table_name = ?
            ORDER BY ordinal_position
        ");
        $stmt->execute([$database, $tableName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Convert MySQL row data to PostgreSQL compatible format
     */
    private function convertRowData($row, $columns)
    {
        $convertedRow = [];
        
        foreach ($row as $index => $value) {
            $column = $columns[$index];
            $convertedValue = $this->convertValue($value, $column);
            $convertedRow[] = $convertedValue;
        }
        
        return $convertedRow;
    }
    
    /**
     * Convert individual values based on column type
     */
    private function convertValue($value, $column)
    {
        if ($value === null) {
            return null;
        }
        
        $dataType = $column['data_type'];
        
        switch ($dataType) {
            case 'tinyint':
                // Convert MySQL boolean (tinyint(1)) to PostgreSQL boolean
                if ($column['column_type'] === 'tinyint(1)') {
                    return $value ? 'true' : 'false';
                }
                return (int)$value;
                
            case 'datetime':
            case 'timestamp':
                // Handle MySQL zero dates
                if ($value === '0000-00-00 00:00:00') {
                    return null;
                }
                return $value;
                
            case 'date':
                // Handle MySQL zero dates
                if ($value === '0000-00-00') {
                    return null;
                }
                return $value;
                
            case 'enum':
                // ENUM values are already strings, just return as-is
                return $value;
                
            case 'set':
                // Convert SET to comma-separated string
                return $value;
                
            case 'json':
                // Validate JSON
                if (json_decode($value) === null && json_last_error() !== JSON_ERROR_NONE) {
                    return '{}'; // Default to empty object for invalid JSON
                }
                return $value;
                
            default:
                return $value;
        }
    }
    
    /**
     * Update PostgreSQL sequences to correct values
     */
    private function updateSequences($database)
    {
        $this->logger->info("Updating sequences for database: {$database}");
        
        // Get all sequences
        $stmt = $this->postgresConnection->prepare("
            SELECT sequence_name 
            FROM information_schema.sequences 
            WHERE sequence_schema = ?
        ");
        $stmt->execute([$database]);
        $sequences = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($sequences as $sequence) {
            // Extract table and column name from sequence name
            if (preg_match('/(.+)_(.+)_seq$/', $sequence, $matches)) {
                $tableName = $matches[1];
                $columnName = $matches[2];
                
                try {
                    // Get max value from table
                    $maxStmt = $this->postgresConnection->prepare("SELECT MAX({$columnName}) FROM {$database}.{$tableName}");
                    $maxStmt->execute();
                    $maxValue = $maxStmt->fetchColumn();
                    
                    if ($maxValue !== null) {
                        // Update sequence
                        $this->postgresConnection->exec("SELECT setval('{$sequence}', {$maxValue})");
                        $this->logger->info("Updated sequence {$sequence} to {$maxValue}");
                    }
                } catch (Exception $e) {
                    $this->logger->error("Failed to update sequence {$sequence}: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Save migration report
     */
    private function saveMigrationReport($serviceName, $migrationResult)
    {
        $reportPath = "migration/reports/data_migration_{$serviceName}_" . date('Y-m-d_H-i-s') . ".json";
        
        if (!is_dir('migration/reports')) {
            mkdir('migration/reports', 0755, true);
        }
        
        file_put_contents($reportPath, json_encode($migrationResult, JSON_PRETTY_PRINT));
        $this->logger->info("Migration report saved: {$reportPath}");
    }
    
    /**
     * Validate migrated data
     */
    public function validateMigration($serviceName, $mysqlDatabase, $postgresDatabase)
    {
        $this->logger->info("Starting data validation for service: {$serviceName}");
        
        $validationResult = [
            'service' => $serviceName,
            'timestamp' => date('Y-m-d H:i:s'),
            'tables_validated' => 0,
            'validation_errors' => [],
            'row_count_mismatches' => [],
            'checksum_mismatches' => []
        ];
        
        $tables = $this->getTableList($mysqlDatabase);
        
        foreach ($tables as $table) {
            $tableName = $table['table_name'];
            
            // Compare row counts
            $mysqlCount = $this->getRowCount($this->mysqlConnection, $mysqlDatabase, $tableName);
            $postgresCount = $this->getRowCount($this->postgresConnection, $postgresDatabase, $tableName);
            
            if ($mysqlCount !== $postgresCount) {
                $validationResult['row_count_mismatches'][] = [
                    'table' => $tableName,
                    'mysql_count' => $mysqlCount,
                    'postgres_count' => $postgresCount
                ];
            }
            
            $validationResult['tables_validated']++;
        }
        
        $validationResult['success'] = empty($validationResult['row_count_mismatches']) && 
                                     empty($validationResult['validation_errors']);
        
        // Save validation report
        $reportPath = "migration/reports/validation_{$serviceName}_" . date('Y-m-d_H-i-s') . ".json";
        file_put_contents($reportPath, json_encode($validationResult, JSON_PRETTY_PRINT));
        
        return $validationResult;
    }
    
    /**
     * Get row count for a table
     */
    private function getRowCount($connection, $database, $tableName)
    {
        $stmt = $connection->prepare("SELECT COUNT(*) FROM {$database}.{$tableName}");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}

/**
 * Migration Logger
 */
class MigrationLogger
{
    private $logFile;
    
    public function __construct()
    {
        if (!is_dir('migration/logs')) {
            mkdir('migration/logs', 0755, true);
        }
        $this->logFile = 'migration/logs/data_migration_' . date('Y-m-d') . '.log';
    }
    
    public function info($message)
    {
        $this->log('INFO', $message);
    }
    
    public function error($message)
    {
        $this->log('ERROR', $message);
    }
    
    private function log($level, $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$level}] {$timestamp} - {$message}\n";
        
        echo $logMessage;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Progress Tracker
 */
class ProgressTracker
{
    public function updateProgress($tableName, $percentage, $rowsProcessed)
    {
        $progressBar = str_repeat('=', (int)($percentage / 2)) . str_repeat('-', 50 - (int)($percentage / 2));
        echo "\r{$tableName}: [{$progressBar}] {$percentage}% ({$rowsProcessed} rows)";
        
        if ($percentage >= 100) {
            echo "\n";
        }
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $config = require __DIR__ . '/../config/migration-config.php';
    $migrator = new DataMigrationManager($config);
    
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
    $action = $argv[2] ?? 'migrate';
    
    if ($serviceName && isset($services[$serviceName])) {
        $options = [
            'batch_size' => 1000,
            'strict_mode' => false
        ];
        
        if ($action === 'migrate') {
            $result = $migrator->migrateServiceData(
                $serviceName,
                $services[$serviceName]['mysql'],
                $services[$serviceName]['postgres'],
                $options
            );
        } elseif ($action === 'validate') {
            $result = $migrator->validateMigration(
                $serviceName,
                $services[$serviceName]['mysql'],
                $services[$serviceName]['postgres']
            );
        }
        
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Usage: php data-migration.php <service-name> [migrate|validate]\n";
        echo "Available services: " . implode(', ', array_keys($services)) . "\n";
    }
}

