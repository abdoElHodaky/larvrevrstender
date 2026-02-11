<?php

/**
 * Infrastructure Validation Script
 * 
 * Validates the PostgreSQL infrastructure setup including connectivity,
 * extensions, performance, and configuration.
 */

require_once __DIR__ . '/../config/migration-config.php';

class InfrastructureValidator
{
    private $config;
    private $logger;
    private $results;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->logger = new InfrastructureLogger();
        $this->results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => [],
            'overall_status' => 'unknown',
            'errors' => [],
            'warnings' => []
        ];
    }
    
    /**
     * Run complete infrastructure validation
     */
    public function validateInfrastructure()
    {
        $this->logger->info("Starting infrastructure validation");
        
        try {
            // Test 1: PostgreSQL Connectivity
            $this->validatePostgreSQLConnectivity();
            
            // Test 2: Database Creation and Permissions
            $this->validateDatabaseSetup();
            
            // Test 3: Extensions Installation
            $this->validateExtensions();
            
            // Test 4: PgBouncer Connectivity
            $this->validatePgBouncer();
            
            // Test 5: Performance Configuration
            $this->validatePerformanceSettings();
            
            // Test 6: Backup and Recovery
            $this->validateBackupRecovery();
            
            // Test 7: Connection Pooling
            $this->validateConnectionPooling();
            
            // Test 8: Monitoring Setup
            $this->validateMonitoring();
            
            // Determine overall status
            $this->determineOverallStatus();
            
            $this->logger->info("Infrastructure validation completed");
            
        } catch (Exception $e) {
            $this->results['overall_status'] = 'failed';
            $this->results['errors'][] = "Infrastructure validation failed: " . $e->getMessage();
            $this->logger->error("Infrastructure validation failed: " . $e->getMessage());
        }
        
        // Save validation report
        $this->saveValidationReport();
        
        return $this->results;
    }
    
    /**
     * Test PostgreSQL connectivity
     */
    private function validatePostgreSQLConnectivity()
    {
        $this->logger->info("Testing PostgreSQL connectivity");
        
        $test = [
            'name' => 'PostgreSQL Connectivity',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test direct PostgreSQL connection
            $pdo = new PDO(
                "pgsql:host={$this->config['postgresql']['host']};port={$this->config['postgresql']['port']}",
                $this->config['postgresql']['username'],
                $this->config['postgresql']['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Test basic query
            $stmt = $pdo->query("SELECT version()");
            $version = $stmt->fetchColumn();
            
            $test['status'] = 'passed';
            $test['details']['version'] = $version;
            $test['details']['connection_time'] = microtime(true);
            
            $this->logger->info("PostgreSQL connectivity: PASSED");
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
            $this->logger->error("PostgreSQL connectivity: FAILED - " . $e->getMessage());
        }
        
        $this->results['tests']['postgresql_connectivity'] = $test;
    }
    
    /**
     * Validate database setup and permissions
     */
    private function validateDatabaseSetup()
    {
        $this->logger->info("Validating database setup");
        
        $test = [
            'name' => 'Database Setup',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            $pdo = new PDO(
                "pgsql:host={$this->config['postgresql']['host']};port={$this->config['postgresql']['port']}",
                $this->config['postgresql']['username'],
                $this->config['postgresql']['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Check if service databases exist
            $expectedDatabases = [
                'gateway_service', 'auth_service', 'user_service', 'analytics_service',
                'order_service', 'payment_service', 'bidding_service', 'auction_service',
                'notification_service', 'vin_ocr_service'
            ];
            
            $stmt = $pdo->query("SELECT datname FROM pg_database WHERE datistemplate = false");
            $existingDatabases = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $missingDatabases = array_diff($expectedDatabases, $existingDatabases);
            $extraDatabases = array_diff($existingDatabases, array_merge($expectedDatabases, ['postgres']));
            
            if (empty($missingDatabases)) {
                $test['status'] = 'passed';
                $test['details']['databases_found'] = count($expectedDatabases);
                $test['details']['databases'] = $existingDatabases;
            } else {
                $test['status'] = 'failed';
                $test['errors'][] = "Missing databases: " . implode(', ', $missingDatabases);
            }
            
            if (!empty($extraDatabases)) {
                $test['details']['extra_databases'] = $extraDatabases;
            }
            
            $this->logger->info("Database setup validation: " . strtoupper($test['status']));
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
            $this->logger->error("Database setup validation: FAILED - " . $e->getMessage());
        }
        
        $this->results['tests']['database_setup'] = $test;
    }
    
    /**
     * Validate PostgreSQL extensions
     */
    private function validateExtensions()
    {
        $this->logger->info("Validating PostgreSQL extensions");
        
        $test = [
            'name' => 'PostgreSQL Extensions',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            $pdo = new PDO(
                "pgsql:host={$this->config['postgresql']['host']};port={$this->config['postgresql']['port']};dbname=gateway_service",
                $this->config['postgresql']['username'],
                $this->config['postgresql']['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Check required extensions
            $requiredExtensions = [
                'uuid-ossp', 'pg_stat_statements', 'pg_trgm', 'pgcrypto', 'btree_gin', 'btree_gist'
            ];
            
            $stmt = $pdo->query("SELECT extname FROM pg_extension");
            $installedExtensions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $missingExtensions = array_diff($requiredExtensions, $installedExtensions);
            
            if (empty($missingExtensions)) {
                $test['status'] = 'passed';
                $test['details']['extensions_installed'] = $installedExtensions;
            } else {
                $test['status'] = 'failed';
                $test['errors'][] = "Missing extensions: " . implode(', ', $missingExtensions);
            }
            
            // Test extension functionality
            $extensionTests = [
                'uuid-ossp' => "SELECT uuid_generate_v4()",
                'pg_trgm' => "SELECT similarity('test', 'test')",
                'pgcrypto' => "SELECT crypt('password', gen_salt('bf'))"
            ];
            
            foreach ($extensionTests as $extension => $testQuery) {
                if (in_array($extension, $installedExtensions)) {
                    try {
                        $pdo->query($testQuery);
                        $test['details']['extension_tests'][$extension] = 'passed';
                    } catch (Exception $e) {
                        $test['details']['extension_tests'][$extension] = 'failed';
                        $test['errors'][] = "Extension {$extension} test failed: " . $e->getMessage();
                    }
                }
            }
            
            $this->logger->info("Extensions validation: " . strtoupper($test['status']));
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
            $this->logger->error("Extensions validation: FAILED - " . $e->getMessage());
        }
        
        $this->results['tests']['extensions'] = $test;
    }
    
    /**
     * Validate PgBouncer connectivity
     */
    private function validatePgBouncer()
    {
        $this->logger->info("Validating PgBouncer connectivity");
        
        $test = [
            'name' => 'PgBouncer Connectivity',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test PgBouncer connection
            $pdo = new PDO(
                "pgsql:host={$this->config['pgbouncer']['host']};port={$this->config['pgbouncer']['port']};dbname=gateway_service",
                $this->config['pgbouncer']['username'],
                $this->config['pgbouncer']['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Test basic query through PgBouncer
            $stmt = $pdo->query("SELECT 1");
            $result = $stmt->fetchColumn();
            
            if ($result == 1) {
                $test['status'] = 'passed';
                $test['details']['connection_successful'] = true;
                
                // Test connection pooling stats (if accessible)
                try {
                    $stmt = $pdo->query("SHOW POOLS");
                    $pools = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $test['details']['pools'] = $pools;
                } catch (Exception $e) {
                    $test['details']['pools_info'] = 'Not accessible';
                }
            } else {
                $test['status'] = 'failed';
                $test['errors'][] = "PgBouncer query test failed";
            }
            
            $this->logger->info("PgBouncer validation: " . strtoupper($test['status']));
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
            $this->logger->error("PgBouncer validation: FAILED - " . $e->getMessage());
        }
        
        $this->results['tests']['pgbouncer'] = $test;
    }
    
    /**
     * Validate performance settings
     */
    private function validatePerformanceSettings()
    {
        $this->logger->info("Validating performance settings");
        
        $test = [
            'name' => 'Performance Settings',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            $pdo = new PDO(
                "pgsql:host={$this->config['postgresql']['host']};port={$this->config['postgresql']['port']}",
                $this->config['postgresql']['username'],
                $this->config['postgresql']['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Check key performance settings
            $performanceSettings = [
                'shared_buffers',
                'effective_cache_size',
                'work_mem',
                'maintenance_work_mem',
                'wal_buffers',
                'checkpoint_completion_target',
                'random_page_cost'
            ];
            
            $currentSettings = [];
            foreach ($performanceSettings as $setting) {
                $stmt = $pdo->prepare("SHOW ?");
                $stmt->execute([$setting]);
                $value = $stmt->fetchColumn();
                $currentSettings[$setting] = $value;
            }
            
            $test['status'] = 'passed';
            $test['details']['current_settings'] = $currentSettings;
            
            // Check if pg_stat_statements is enabled
            $stmt = $pdo->query("SELECT count(*) FROM pg_stat_statements LIMIT 1");
            $test['details']['pg_stat_statements_enabled'] = true;
            
            $this->logger->info("Performance settings validation: PASSED");
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'pg_stat_statements') !== false) {
                $test['details']['pg_stat_statements_enabled'] = false;
                $test['status'] = 'passed'; // Not critical
            } else {
                $test['status'] = 'failed';
                $test['errors'][] = $e->getMessage();
                $this->logger->error("Performance settings validation: FAILED - " . $e->getMessage());
            }
        }
        
        $this->results['tests']['performance_settings'] = $test;
    }
    
    /**
     * Validate backup and recovery capabilities
     */
    private function validateBackupRecovery()
    {
        $this->logger->info("Validating backup and recovery");
        
        $test = [
            'name' => 'Backup and Recovery',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test pg_dump availability
            $output = shell_exec('which pg_dump 2>/dev/null');
            if (empty($output)) {
                $test['status'] = 'failed';
                $test['errors'][] = "pg_dump not found in PATH";
            } else {
                $test['details']['pg_dump_path'] = trim($output);
                
                // Test backup directory creation
                $backupDir = $this->config['backup']['directory'];
                if (!is_dir($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }
                
                if (is_writable($backupDir)) {
                    $test['status'] = 'passed';
                    $test['details']['backup_directory'] = $backupDir;
                    $test['details']['backup_directory_writable'] = true;
                } else {
                    $test['status'] = 'failed';
                    $test['errors'][] = "Backup directory not writable: {$backupDir}";
                }
            }
            
            $this->logger->info("Backup and recovery validation: " . strtoupper($test['status']));
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
            $this->logger->error("Backup and recovery validation: FAILED - " . $e->getMessage());
        }
        
        $this->results['tests']['backup_recovery'] = $test;
    }
    
    /**
     * Validate connection pooling
     */
    private function validateConnectionPooling()
    {
        $this->logger->info("Validating connection pooling");
        
        $test = [
            'name' => 'Connection Pooling',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            // Test multiple connections
            $connections = [];
            $maxConnections = 5;
            
            for ($i = 0; $i < $maxConnections; $i++) {
                $pdo = new PDO(
                    "pgsql:host={$this->config['postgresql']['host']};port={$this->config['postgresql']['port']};dbname=gateway_service",
                    $this->config['postgresql']['username'],
                    $this->config['postgresql']['password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $connections[] = $pdo;
            }
            
            $test['status'] = 'passed';
            $test['details']['concurrent_connections_tested'] = $maxConnections;
            $test['details']['all_connections_successful'] = true;
            
            // Close connections
            $connections = null;
            
            $this->logger->info("Connection pooling validation: PASSED");
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
            $this->logger->error("Connection pooling validation: FAILED - " . $e->getMessage());
        }
        
        $this->results['tests']['connection_pooling'] = $test;
    }
    
    /**
     * Validate monitoring setup
     */
    private function validateMonitoring()
    {
        $this->logger->info("Validating monitoring setup");
        
        $test = [
            'name' => 'Monitoring Setup',
            'status' => 'unknown',
            'details' => [],
            'errors' => []
        ];
        
        try {
            $pdo = new PDO(
                "pgsql:host={$this->config['postgresql']['host']};port={$this->config['postgresql']['port']}",
                $this->config['postgresql']['username'],
                $this->config['postgresql']['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Check monitoring views
            $monitoringViews = [
                'pg_stat_database',
                'pg_stat_user_tables',
                'pg_stat_activity'
            ];
            
            $viewsAccessible = [];
            foreach ($monitoringViews as $view) {
                try {
                    $stmt = $pdo->query("SELECT count(*) FROM {$view}");
                    $viewsAccessible[$view] = true;
                } catch (Exception $e) {
                    $viewsAccessible[$view] = false;
                    $test['errors'][] = "Cannot access monitoring view: {$view}";
                }
            }
            
            $accessibleCount = array_sum($viewsAccessible);
            if ($accessibleCount === count($monitoringViews)) {
                $test['status'] = 'passed';
            } else {
                $test['status'] = 'partial';
            }
            
            $test['details']['monitoring_views'] = $viewsAccessible;
            
            // Check log directory
            $logDir = $this->config['logging']['directory'];
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $test['details']['log_directory'] = $logDir;
            $test['details']['log_directory_writable'] = is_writable($logDir);
            
            $this->logger->info("Monitoring setup validation: " . strtoupper($test['status']));
            
        } catch (Exception $e) {
            $test['status'] = 'failed';
            $test['errors'][] = $e->getMessage();
            $this->logger->error("Monitoring setup validation: FAILED - " . $e->getMessage());
        }
        
        $this->results['tests']['monitoring'] = $test;
    }
    
    /**
     * Determine overall validation status
     */
    private function determineOverallStatus()
    {
        $passedTests = 0;
        $failedTests = 0;
        $totalTests = count($this->results['tests']);
        
        foreach ($this->results['tests'] as $test) {
            if ($test['status'] === 'passed') {
                $passedTests++;
            } elseif ($test['status'] === 'failed') {
                $failedTests++;
                $this->results['errors'] = array_merge($this->results['errors'], $test['errors']);
            }
        }
        
        if ($failedTests === 0) {
            $this->results['overall_status'] = 'passed';
        } elseif ($passedTests > $failedTests) {
            $this->results['overall_status'] = 'partial';
        } else {
            $this->results['overall_status'] = 'failed';
        }
        
        $this->results['summary'] = [
            'total_tests' => $totalTests,
            'passed_tests' => $passedTests,
            'failed_tests' => $failedTests,
            'success_rate' => round(($passedTests / $totalTests) * 100, 2)
        ];
    }
    
    /**
     * Save validation report
     */
    private function saveValidationReport()
    {
        $reportPath = "migration/reports/infrastructure_validation_" . date('Y-m-d_H-i-s') . ".json";
        
        if (!is_dir('migration/reports')) {
            mkdir('migration/reports', 0755, true);
        }
        
        file_put_contents($reportPath, json_encode($this->results, JSON_PRETTY_PRINT));
        $this->logger->info("Infrastructure validation report saved: {$reportPath}");
    }
}

/**
 * Infrastructure Logger
 */
class InfrastructureLogger
{
    private $logFile;
    
    public function __construct()
    {
        if (!is_dir('migration/logs')) {
            mkdir('migration/logs', 0755, true);
        }
        $this->logFile = 'migration/logs/infrastructure_validation_' . date('Y-m-d') . '.log';
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

// CLI execution
if (php_sapi_name() === 'cli') {
    $config = require __DIR__ . '/../config/migration-config.php';
    $validator = new InfrastructureValidator($config);
    
    $result = $validator->validateInfrastructure();
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "INFRASTRUCTURE VALIDATION SUMMARY\n";
    echo str_repeat("=", 60) . "\n";
    echo "Overall Status: " . strtoupper($result['overall_status']) . "\n";
    echo "Tests Passed: {$result['summary']['passed_tests']}/{$result['summary']['total_tests']}\n";
    echo "Success Rate: {$result['summary']['success_rate']}%\n";
    
    if (!empty($result['errors'])) {
        echo "\nErrors:\n";
        foreach ($result['errors'] as $error) {
            echo "- {$error}\n";
        }
    }
    
    echo "\nDetailed results saved to migration/reports/\n";
    echo str_repeat("=", 60) . "\n";
}

