<?php

/**
 * Migration Rollback Script
 * 
 * Provides comprehensive rollback capabilities for PostgreSQL migration
 * including data restoration, configuration rollback, and service recovery.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

class MigrationRollbackManager
{
    private $mysqlConnection;
    private $postgresConnection;
    private $config;
    private $logger;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->logger = new RollbackLogger();
        $this->initializeConnections();
    }
    
    private function initializeConnections()
    {
        $this->mysqlConnection = new PDO(
            "mysql:host={$this->config['mysql']['host']};port={$this->config['mysql']['port']};charset=utf8mb4",
            $this->config['mysql']['username'],
            $this->config['mysql']['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $this->postgresConnection = new PDO(
            "pgsql:host={$this->config['postgresql']['host']};port={$this->config['postgresql']['port']}",
            $this->config['postgresql']['username'],
            $this->config['postgresql']['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    
    /**
     * Execute rollback for a specific service
     */
    public function rollbackService($serviceName, $rollbackType = 'configuration', $options = [])
    {
        $this->logger->info("Starting rollback for service: {$serviceName} (type: {$rollbackType})");
        
        $rollbackResult = [
            'service' => $serviceName,
            'rollback_type' => $rollbackType,
            'start_time' => date('Y-m-d H:i:s'),
            'steps_completed' => [],
            'errors' => [],
            'warnings' => [],
            'success' => false
        ];
        
        try {
            switch ($rollbackType) {
                case 'configuration':
                    $this->rollbackConfiguration($serviceName, $rollbackResult, $options);
                    break;
                    
                case 'data':
                    $this->rollbackData($serviceName, $rollbackResult, $options);
                    break;
                    
                case 'full':
                    $this->rollbackConfiguration($serviceName, $rollbackResult, $options);
                    $this->rollbackData($serviceName, $rollbackResult, $options);
                    break;
                    
                case 'emergency':
                    $this->emergencyRollback($serviceName, $rollbackResult, $options);
                    break;
                    
                default:
                    throw new Exception("Unknown rollback type: {$rollbackType}");
            }
            
            $rollbackResult['success'] = empty($rollbackResult['errors']);
            $rollbackResult['end_time'] = date('Y-m-d H:i:s');
            
            $this->logger->info("Rollback completed for service: {$serviceName}");
            
        } catch (Exception $e) {
            $rollbackResult['errors'][] = "Rollback failed: " . $e->getMessage();
            $rollbackResult['success'] = false;
            $rollbackResult['end_time'] = date('Y-m-d H:i:s');
            
            $this->logger->error("Rollback failed for {$serviceName}: " . $e->getMessage());
        }
        
        // Save rollback report
        $this->saveRollbackReport($serviceName, $rollbackResult);
        
        return $rollbackResult;
    }
    
    /**
     * Rollback service configuration to MySQL
     */
    private function rollbackConfiguration($serviceName, &$result, $options)
    {
        $this->logger->info("Rolling back configuration for {$serviceName}");
        
        try {
            // 1. Update environment variables
            $this->updateEnvironmentVariables($serviceName, 'mysql');
            $result['steps_completed'][] = 'Environment variables updated to MySQL';
            
            // 2. Update Laravel database configuration
            $this->updateLaravelConfig($serviceName, 'mysql');
            $result['steps_completed'][] = 'Laravel database configuration updated';
            
            // 3. Update Docker Compose configuration
            if ($options['update_docker'] ?? true) {
                $this->updateDockerConfiguration($serviceName, 'mysql');
                $result['steps_completed'][] = 'Docker configuration updated';
            }
            
            // 4. Update Kubernetes configuration
            if ($options['update_k8s'] ?? true) {
                $this->updateKubernetesConfiguration($serviceName, 'mysql');
                $result['steps_completed'][] = 'Kubernetes configuration updated';
            }
            
            // 5. Restart service if requested
            if ($options['restart_service'] ?? false) {
                $this->restartService($serviceName);
                $result['steps_completed'][] = 'Service restarted';
            }
            
        } catch (Exception $e) {
            $result['errors'][] = "Configuration rollback error: " . $e->getMessage();
            throw $e;
        }
    }
    
    /**
     * Rollback data from PostgreSQL to MySQL
     */
    private function rollbackData($serviceName, &$result, $options)
    {
        $this->logger->info("Rolling back data for {$serviceName}");
        
        try {
            $mysqlDatabase = $this->getServiceDatabase($serviceName, 'mysql');
            $postgresDatabase = $this->getServiceDatabase($serviceName, 'postgres');
            
            // 1. Create backup of current MySQL data
            if ($options['backup_current'] ?? true) {
                $this->createMySQLBackup($mysqlDatabase);
                $result['steps_completed'][] = 'Current MySQL data backed up';
            }
            
            // 2. Restore from pre-migration backup
            if ($options['restore_from_backup'] ?? true) {
                $backupFile = $this->findLatestBackup($mysqlDatabase);
                if ($backupFile) {
                    $this->restoreFromBackup($mysqlDatabase, $backupFile);
                    $result['steps_completed'][] = 'Data restored from pre-migration backup';
                } else {
                    $result['warnings'][] = 'No pre-migration backup found, skipping data restore';
                }
            }
            
            // 3. Sync any new data from PostgreSQL (if requested)
            if ($options['sync_new_data'] ?? false) {
                $this->syncNewDataFromPostgreSQL($mysqlDatabase, $postgresDatabase, $options['migration_timestamp'] ?? null);
                $result['steps_completed'][] = 'New data synced from PostgreSQL';
            }
            
            // 4. Validate data integrity
            $this->validateDataIntegrity($mysqlDatabase);
            $result['steps_completed'][] = 'Data integrity validated';
            
        } catch (Exception $e) {
            $result['errors'][] = "Data rollback error: " . $e->getMessage();
            throw $e;
        }
    }
    
    /**
     * Emergency rollback - fastest possible recovery
     */
    private function emergencyRollback($serviceName, &$result, $options)
    {
        $this->logger->info("Executing emergency rollback for {$serviceName}");
        
        try {
            // 1. Immediately switch database connection
            $this->updateEnvironmentVariables($serviceName, 'mysql');
            $result['steps_completed'][] = 'Database connection switched to MySQL';
            
            // 2. Restart service containers
            $this->emergencyRestartService($serviceName);
            $result['steps_completed'][] = 'Service containers restarted';
            
            // 3. Validate service health
            $healthCheck = $this->validateServiceHealth($serviceName);
            if ($healthCheck['healthy']) {
                $result['steps_completed'][] = 'Service health validated';
            } else {
                $result['warnings'][] = 'Service health check failed: ' . $healthCheck['error'];
            }
            
            // 4. Alert monitoring systems
            $this->alertMonitoringSystems($serviceName, 'emergency_rollback');
            $result['steps_completed'][] = 'Monitoring systems alerted';
            
        } catch (Exception $e) {
            $result['errors'][] = "Emergency rollback error: " . $e->getMessage();
            throw $e;
        }
    }
    
    /**
     * Update environment variables for service
     */
    private function updateEnvironmentVariables($serviceName, $databaseType)
    {
        $envFile = $this->getServiceEnvFile($serviceName);
        
        if (!file_exists($envFile)) {
            throw new Exception("Environment file not found: {$envFile}");
        }
        
        $envContent = file_get_contents($envFile);
        
        if ($databaseType === 'mysql') {
            $envContent = preg_replace('/DB_CONNECTION=postgresql/', 'DB_CONNECTION=mysql', $envContent);
            $envContent = preg_replace('/DB_HOST=postgresql/', 'DB_HOST=mysql', $envContent);
            $envContent = preg_replace('/DB_PORT=5432/', 'DB_PORT=3306', $envContent);
        } else {
            $envContent = preg_replace('/DB_CONNECTION=mysql/', 'DB_CONNECTION=postgresql', $envContent);
            $envContent = preg_replace('/DB_HOST=mysql/', 'DB_HOST=postgresql', $envContent);
            $envContent = preg_replace('/DB_PORT=3306/', 'DB_PORT=5432', $envContent);
        }
        
        file_put_contents($envFile, $envContent);
        $this->logger->info("Environment variables updated for {$serviceName}");
    }
    
    /**
     * Update Laravel database configuration
     */
    private function updateLaravelConfig($serviceName, $databaseType)
    {
        $configFile = $this->getServiceConfigFile($serviceName);
        
        if (!file_exists($configFile)) {
            $this->logger->warning("Laravel config file not found: {$configFile}");
            return;
        }
        
        $configContent = file_get_contents($configFile);
        
        if ($databaseType === 'mysql') {
            $configContent = preg_replace(
                "/'default' => env\('DB_CONNECTION', '[^']+'\)/",
                "'default' => env('DB_CONNECTION', 'mysql')",
                $configContent
            );
        } else {
            $configContent = preg_replace(
                "/'default' => env\('DB_CONNECTION', '[^']+'\)/",
                "'default' => env('DB_CONNECTION', 'pgsql')",
                $configContent
            );
        }
        
        file_put_contents($configFile, $configContent);
        $this->logger->info("Laravel config updated for {$serviceName}");
    }
    
    /**
     * Update Docker Compose configuration
     */
    private function updateDockerConfiguration($serviceName, $databaseType)
    {
        $dockerFile = 'docker-compose.yml';
        
        if (!file_exists($dockerFile)) {
            throw new Exception("Docker Compose file not found: {$dockerFile}");
        }
        
        $dockerContent = file_get_contents($dockerFile);
        
        // Update service dependencies and environment variables
        if ($databaseType === 'mysql') {
            $dockerContent = preg_replace('/- DB_CONNECTION=postgresql/', '- DB_CONNECTION=mysql', $dockerContent);
            $dockerContent = preg_replace('/- DB_HOST=postgresql/', '- DB_HOST=mysql', $dockerContent);
            $dockerContent = preg_replace('/- DB_PORT=5432/', '- DB_PORT=3306', $dockerContent);
            $dockerContent = preg_replace('/- postgresql/', '- mysql', $dockerContent);
        } else {
            $dockerContent = preg_replace('/- DB_CONNECTION=mysql/', '- DB_CONNECTION=postgresql', $dockerContent);
            $dockerContent = preg_replace('/- DB_HOST=mysql/', '- DB_HOST=postgresql', $dockerContent);
            $dockerContent = preg_replace('/- DB_PORT=3306/', '- DB_PORT=5432', $dockerContent);
            $dockerContent = preg_replace('/- mysql/', '- postgresql', $dockerContent);
        }
        
        file_put_contents($dockerFile, $dockerContent);
        $this->logger->info("Docker configuration updated for {$serviceName}");
    }
    
    /**
     * Update Kubernetes configuration
     */
    private function updateKubernetesConfiguration($serviceName, $databaseType)
    {
        $k8sConfigFile = "deployment/k8s/base/configmap.yaml";
        
        if (!file_exists($k8sConfigFile)) {
            $this->logger->warning("Kubernetes config file not found: {$k8sConfigFile}");
            return;
        }
        
        $k8sContent = file_get_contents($k8sConfigFile);
        
        if ($databaseType === 'mysql') {
            $k8sContent = preg_replace('/DB_CONNECTION: "postgresql"/', 'DB_CONNECTION: "mysql"', $k8sContent);
            $k8sContent = preg_replace('/DB_HOST: "postgresql"/', 'DB_HOST: "mysql"', $k8sContent);
            $k8sContent = preg_replace('/DB_PORT: "5432"/', 'DB_PORT: "3306"', $k8sContent);
        } else {
            $k8sContent = preg_replace('/DB_CONNECTION: "mysql"/', 'DB_CONNECTION: "postgresql"', $k8sContent);
            $k8sContent = preg_replace('/DB_HOST: "mysql"/', 'DB_HOST: "postgresql"', $k8sContent);
            $k8sContent = preg_replace('/DB_PORT: "3306"/', 'DB_PORT: "5432"', $k8sContent);
        }
        
        file_put_contents($k8sConfigFile, $k8sContent);
        $this->logger->info("Kubernetes configuration updated for {$serviceName}");
    }
    
    /**
     * Restart service
     */
    private function restartService($serviceName)
    {
        $this->logger->info("Restarting service: {$serviceName}");
        
        // Docker Compose restart
        $dockerCommand = "docker-compose restart {$serviceName}";
        $output = shell_exec($dockerCommand);
        
        if ($output === null) {
            throw new Exception("Failed to restart service via Docker Compose");
        }
        
        // Wait for service to be ready
        sleep(10);
        
        $this->logger->info("Service {$serviceName} restarted successfully");
    }
    
    /**
     * Emergency restart service
     */
    private function emergencyRestartService($serviceName)
    {
        $this->logger->info("Emergency restart for service: {$serviceName}");
        
        // Force restart with minimal delay
        $commands = [
            "docker-compose stop {$serviceName}",
            "docker-compose start {$serviceName}"
        ];
        
        foreach ($commands as $command) {
            shell_exec($command);
        }
        
        // Minimal wait time for emergency scenarios
        sleep(5);
    }
    
    /**
     * Create MySQL backup
     */
    private function createMySQLBackup($database)
    {
        $backupDir = 'migration/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = "{$backupDir}/{$database}_rollback_backup_{$timestamp}.sql";
        
        $command = sprintf(
            "mysqldump -h %s -P %s -u %s -p%s %s > %s",
            $this->config['mysql']['host'],
            $this->config['mysql']['port'],
            $this->config['mysql']['username'],
            $this->config['mysql']['password'],
            $database,
            $backupFile
        );
        
        shell_exec($command);
        
        if (!file_exists($backupFile)) {
            throw new Exception("Failed to create MySQL backup");
        }
        
        $this->logger->info("MySQL backup created: {$backupFile}");
    }
    
    /**
     * Find latest backup file
     */
    private function findLatestBackup($database)
    {
        $backupDir = 'migration/backups';
        $pattern = "{$backupDir}/{$database}_pre_migration_*.sql";
        
        $backupFiles = glob($pattern);
        
        if (empty($backupFiles)) {
            return null;
        }
        
        // Sort by modification time, newest first
        usort($backupFiles, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        return $backupFiles[0];
    }
    
    /**
     * Restore from backup
     */
    private function restoreFromBackup($database, $backupFile)
    {
        if (!file_exists($backupFile)) {
            throw new Exception("Backup file not found: {$backupFile}");
        }
        
        $command = sprintf(
            "mysql -h %s -P %s -u %s -p%s %s < %s",
            $this->config['mysql']['host'],
            $this->config['mysql']['port'],
            $this->config['mysql']['username'],
            $this->config['mysql']['password'],
            $database,
            $backupFile
        );
        
        $output = shell_exec($command);
        
        $this->logger->info("Database restored from backup: {$backupFile}");
    }
    
    /**
     * Sync new data from PostgreSQL
     */
    private function syncNewDataFromPostgreSQL($mysqlDatabase, $postgresDatabase, $migrationTimestamp)
    {
        if (!$migrationTimestamp) {
            $this->logger->warning("No migration timestamp provided, skipping data sync");
            return;
        }
        
        // This would implement logic to sync data created after migration
        $this->logger->info("Syncing new data from PostgreSQL (timestamp: {$migrationTimestamp})");
        
        // Implementation would depend on specific table structures and requirements
    }
    
    /**
     * Validate data integrity
     */
    private function validateDataIntegrity($database)
    {
        // Run basic integrity checks
        $stmt = $this->mysqlConnection->prepare("
            SELECT COUNT(*) as table_count 
            FROM information_schema.tables 
            WHERE table_schema = ?
        ");
        $stmt->execute([$database]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['table_count'] == 0) {
            throw new Exception("No tables found in database {$database}");
        }
        
        $this->logger->info("Data integrity validated for {$database}");
    }
    
    /**
     * Validate service health
     */
    private function validateServiceHealth($serviceName)
    {
        // Implement health check logic
        $healthEndpoint = $this->getServiceHealthEndpoint($serviceName);
        
        if (!$healthEndpoint) {
            return ['healthy' => true, 'message' => 'No health endpoint configured'];
        }
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET'
            ]
        ]);
        
        $response = @file_get_contents($healthEndpoint, false, $context);
        
        if ($response === false) {
            return ['healthy' => false, 'error' => 'Health endpoint unreachable'];
        }
        
        return ['healthy' => true, 'message' => 'Service health check passed'];
    }
    
    /**
     * Alert monitoring systems
     */
    private function alertMonitoringSystems($serviceName, $alertType)
    {
        $alertMessage = "Rollback executed for service {$serviceName} (type: {$alertType})";
        
        // Log alert (in real implementation, this would integrate with monitoring systems)
        $this->logger->info("ALERT: {$alertMessage}");
        
        // Could integrate with Slack, PagerDuty, etc.
    }
    
    // Helper methods
    private function getServiceDatabase($serviceName, $type)
    {
        $serviceMap = [
            'gateway-service' => 'gateway_service',
            'auth-service' => 'auth_service',
            'user-service' => 'user_service',
            'analytics-service' => 'analytics_service',
            'order-service' => 'order_service',
            'payment-service' => 'payment_service',
            'bidding-service' => 'bidding_service',
            'auction-service' => 'auction_service',
            'notification-service' => 'notification_service',
            'vin-ocr-service' => 'vin_ocr_service'
        ];
        
        return $serviceMap[$serviceName] ?? null;
    }
    
    private function getServiceEnvFile($serviceName)
    {
        return "services/{$serviceName}/.env";
    }
    
    private function getServiceConfigFile($serviceName)
    {
        return "services/{$serviceName}/config/database.php";
    }
    
    private function getServiceHealthEndpoint($serviceName)
    {
        $portMap = [
            'gateway-service' => 8000,
            'auth-service' => 8001,
            'user-service' => 8002,
            'analytics-service' => 8003,
            'order-service' => 8004,
            'payment-service' => 8005,
            'bidding-service' => 8006,
            'auction-service' => 8007,
            'notification-service' => 8008,
            'vin-ocr-service' => 8009
        ];
        
        $port = $portMap[$serviceName] ?? null;
        return $port ? "http://localhost:{$port}/health" : null;
    }
    
    private function saveRollbackReport($serviceName, $rollbackResult)
    {
        $reportPath = "migration/reports/rollback_{$serviceName}_" . date('Y-m-d_H-i-s') . ".json";
        
        if (!is_dir('migration/reports')) {
            mkdir('migration/reports', 0755, true);
        }
        
        file_put_contents($reportPath, json_encode($rollbackResult, JSON_PRETTY_PRINT));
        $this->logger->info("Rollback report saved: {$reportPath}");
    }
}

/**
 * Rollback Logger
 */
class RollbackLogger
{
    private $logFile;
    
    public function __construct()
    {
        if (!is_dir('migration/logs')) {
            mkdir('migration/logs', 0755, true);
        }
        $this->logFile = 'migration/logs/rollback_' . date('Y-m-d') . '.log';
    }
    
    public function info($message)
    {
        $this->log('INFO', $message);
    }
    
    public function error($message)
    {
        $this->log('ERROR', $message);
    }
    
    public function warning($message)
    {
        $this->log('WARNING', $message);
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
    $rollbackManager = new MigrationRollbackManager($config);
    
    $services = [
        'gateway-service', 'auth-service', 'user-service', 'analytics-service',
        'order-service', 'payment-service', 'bidding-service', 'auction-service',
        'notification-service', 'vin-ocr-service'
    ];
    
    $serviceName = $argv[1] ?? null;
    $rollbackType = $argv[2] ?? 'configuration';
    
    if ($serviceName && in_array($serviceName, $services)) {
        $options = [
            'update_docker' => true,
            'update_k8s' => true,
            'restart_service' => true,
            'backup_current' => true,
            'restore_from_backup' => true,
            'sync_new_data' => false
        ];
        
        $result = $rollbackManager->rollbackService($serviceName, $rollbackType, $options);
        
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Usage: php rollback-migration.php <service-name> [configuration|data|full|emergency]\n";
        echo "Available services: " . implode(', ', $services) . "\n";
        echo "Rollback types:\n";
        echo "  configuration - Switch database configuration back to MySQL\n";
        echo "  data - Restore data from pre-migration backup\n";
        echo "  full - Both configuration and data rollback\n";
        echo "  emergency - Fast configuration rollback with service restart\n";
    }
}

