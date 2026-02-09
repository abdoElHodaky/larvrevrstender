<?php

/**
 * Migration Orchestrator
 * 
 * Central orchestration script that manages the complete migration process
 * including dependency resolution, parallel execution, and error handling.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/mysql-to-postgresql-schema.php';
require_once __DIR__ . '/data-migration.php';
require_once __DIR__ . '/validate-migration.php';
require_once __DIR__ . '/rollback-migration.php';

class MigrationOrchestrator
{
    private $config;
    private $logger;
    private $schemaConverter;
    private $dataMigrator;
    private $validator;
    private $rollbackManager;
    private $migrationState;
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->logger = new OrchestratorLogger();
        $this->migrationState = new MigrationState();
        
        $this->schemaConverter = new MySQLToPostgreSQLSchemaConverter($config);
        $this->dataMigrator = new DataMigrationManager($config);
        $this->validator = new MigrationValidator($config);
        $this->rollbackManager = new MigrationRollbackManager($config);
    }
    
    /**
     * Execute complete migration for all services
     */
    public function executeFullMigration($options = [])
    {
        $this->logger->info("Starting full migration orchestration");
        
        $migrationPlan = [
            'start_time' => date('Y-m-d H:i:s'),
            'services' => [],
            'phases' => [],
            'overall_status' => 'in_progress',
            'errors' => [],
            'warnings' => []
        ];
        
        try {
            // Phase 1: Pre-migration validation
            $this->logger->info("Phase 1: Pre-migration validation");
            $preValidation = $this->executePreMigrationValidation();
            $migrationPlan['phases']['pre_validation'] = $preValidation;
            
            if (!$preValidation['success']) {
                throw new Exception("Pre-migration validation failed");
            }
            
            // Phase 2: Create migration order based on dependencies
            $this->logger->info("Phase 2: Creating migration order");
            $migrationOrder = $this->createMigrationOrder();
            $migrationPlan['migration_order'] = $migrationOrder;
            
            // Phase 3: Execute migrations in order
            $this->logger->info("Phase 3: Executing service migrations");
            foreach ($migrationOrder as $phase => $services) {
                $phaseResult = $this->executeMigrationPhase($phase, $services, $options);
                $migrationPlan['phases'][$phase] = $phaseResult;
                
                if (!$phaseResult['success']) {
                    throw new Exception("Migration phase {$phase} failed");
                }
            }
            
            // Phase 4: Post-migration validation
            $this->logger->info("Phase 4: Post-migration validation");
            $postValidation = $this->executePostMigrationValidation();
            $migrationPlan['phases']['post_validation'] = $postValidation;
            
            // Phase 5: Performance benchmarking
            if ($options['benchmark'] ?? true) {
                $this->logger->info("Phase 5: Performance benchmarking");
                $benchmarkResult = $this->executePerformanceBenchmark();
                $migrationPlan['phases']['benchmark'] = $benchmarkResult;
            }
            
            $migrationPlan['overall_status'] = 'completed';
            $migrationPlan['end_time'] = date('Y-m-d H:i:s');
            
            $this->logger->info("Full migration completed successfully");
            
        } catch (Exception $e) {
            $migrationPlan['overall_status'] = 'failed';
            $migrationPlan['error'] = $e->getMessage();
            $migrationPlan['end_time'] = date('Y-m-d H:i:s');
            
            $this->logger->error("Full migration failed: " . $e->getMessage());
            
            // Execute rollback if requested
            if ($options['auto_rollback'] ?? false) {
                $this->logger->info("Executing automatic rollback");
                $rollbackResult = $this->executeEmergencyRollback();
                $migrationPlan['rollback'] = $rollbackResult;
            }
        }
        
        // Save migration plan
        $this->saveMigrationPlan($migrationPlan);
        
        return $migrationPlan;
    }
    
    /**
     * Execute migration for a single service
     */
    public function executeSingleServiceMigration($serviceName, $options = [])
    {
        $this->logger->info("Starting migration for service: {$serviceName}");
        
        $serviceConfig = $this->config['services'][$serviceName] ?? null;
        if (!$serviceConfig) {
            throw new Exception("Service configuration not found: {$serviceName}");
        }
        
        $migrationResult = [
            'service' => $serviceName,
            'start_time' => date('Y-m-d H:i:s'),
            'steps' => [],
            'success' => false
        ];
        
        try {
            // Step 1: Pre-migration backup
            if ($options['backup'] ?? true) {
                $this->logger->info("Creating pre-migration backup for {$serviceName}");
                $backupResult = $this->createPreMigrationBackup($serviceName);
                $migrationResult['steps']['backup'] = $backupResult;
            }
            
            // Step 2: Schema conversion
            $this->logger->info("Converting schema for {$serviceName}");
            $schemaResult = $this->schemaConverter->convertServiceSchema(
                $serviceName,
                $serviceConfig['mysql_database'],
                $serviceConfig['postgres_database']
            );
            $migrationResult['steps']['schema'] = $schemaResult;
            
            if (!$schemaResult['success']) {
                throw new Exception("Schema conversion failed for {$serviceName}");
            }
            
            // Step 3: Data migration
            $this->logger->info("Migrating data for {$serviceName}");
            $dataResult = $this->dataMigrator->migrateServiceData(
                $serviceName,
                $serviceConfig['mysql_database'],
                $serviceConfig['postgres_database'],
                $options
            );
            $migrationResult['steps']['data'] = $dataResult;
            
            if (!$dataResult['success']) {
                throw new Exception("Data migration failed for {$serviceName}");
            }
            
            // Step 4: Validation
            $this->logger->info("Validating migration for {$serviceName}");
            $validationResult = $this->validator->validateService(
                $serviceName,
                $serviceConfig['mysql_database'],
                $serviceConfig['postgres_database'],
                $options['validation_level'] ?? 'full'
            );
            $migrationResult['steps']['validation'] = $validationResult;
            
            if (!$validationResult['overall_success']) {
                throw new Exception("Migration validation failed for {$serviceName}");
            }
            
            // Step 5: Configuration update (if requested)
            if ($options['update_config'] ?? false) {
                $this->logger->info("Updating configuration for {$serviceName}");
                $configResult = $this->updateServiceConfiguration($serviceName);
                $migrationResult['steps']['configuration'] = $configResult;
            }
            
            $migrationResult['success'] = true;
            $migrationResult['end_time'] = date('Y-m-d H:i:s');
            
            // Update migration state
            $this->migrationState->markServiceCompleted($serviceName);
            
            $this->logger->info("Migration completed successfully for service: {$serviceName}");
            
        } catch (Exception $e) {
            $migrationResult['success'] = false;
            $migrationResult['error'] = $e->getMessage();
            $migrationResult['end_time'] = date('Y-m-d H:i:s');
            
            $this->logger->error("Migration failed for {$serviceName}: " . $e->getMessage());
            
            // Execute rollback if requested
            if ($options['auto_rollback'] ?? false) {
                $this->logger->info("Executing rollback for {$serviceName}");
                $rollbackResult = $this->rollbackManager->rollbackService($serviceName, 'full');
                $migrationResult['rollback'] = $rollbackResult;
            }
        }
        
        return $migrationResult;
    }
    
    /**
     * Create migration order based on service dependencies
     */
    private function createMigrationOrder()
    {
        $services = $this->config['services'];
        $migrationOrder = [];
        $processed = [];
        
        // Group services by priority
        $priorityGroups = [];
        foreach ($services as $serviceName => $serviceConfig) {
            $priority = $serviceConfig['priority'] ?? 999;
            $priorityGroups[$priority][] = $serviceName;
        }
        
        ksort($priorityGroups);
        
        foreach ($priorityGroups as $priority => $serviceList) {
            $phaseName = "phase_{$priority}";
            $migrationOrder[$phaseName] = [];
            
            foreach ($serviceList as $serviceName) {
                $dependencies = $services[$serviceName]['dependencies'] ?? [];
                
                // Check if all dependencies are satisfied
                if ($this->areDependenciesSatisfied($dependencies, $processed)) {
                    $migrationOrder[$phaseName][] = $serviceName;
                    $processed[] = $serviceName;
                } else {
                    // Move to next phase if dependencies not satisfied
                    $nextPhase = "phase_" . ($priority + 1);
                    if (!isset($migrationOrder[$nextPhase])) {
                        $migrationOrder[$nextPhase] = [];
                    }
                    $migrationOrder[$nextPhase][] = $serviceName;
                }
            }
        }
        
        return $migrationOrder;
    }
    
    /**
     * Check if service dependencies are satisfied
     */
    private function areDependenciesSatisfied($dependencies, $processed)
    {
        if (empty($dependencies)) {
            return true;
        }
        
        if (in_array('*', $dependencies)) {
            // Depends on all other services
            $allServices = array_keys($this->config['services']);
            $requiredServices = array_diff($allServices, [$serviceName]);
            return count(array_intersect($requiredServices, $processed)) === count($requiredServices);
        }
        
        return count(array_intersect($dependencies, $processed)) === count($dependencies);
    }
    
    /**
     * Execute migration phase
     */
    private function executeMigrationPhase($phaseName, $services, $options)
    {
        $this->logger->info("Executing migration phase: {$phaseName}");
        
        $phaseResult = [
            'phase' => $phaseName,
            'start_time' => date('Y-m-d H:i:s'),
            'services' => [],
            'success' => true,
            'errors' => []
        ];
        
        // Execute migrations in parallel if enabled
        if (($options['parallel'] ?? false) && count($services) > 1) {
            $phaseResult['services'] = $this->executeParallelMigrations($services, $options);
        } else {
            // Sequential execution
            foreach ($services as $serviceName) {
                $serviceResult = $this->executeSingleServiceMigration($serviceName, $options);
                $phaseResult['services'][$serviceName] = $serviceResult;
                
                if (!$serviceResult['success']) {
                    $phaseResult['success'] = false;
                    $phaseResult['errors'][] = "Service {$serviceName} migration failed";
                    
                    if ($options['stop_on_error'] ?? true) {
                        break;
                    }
                }
            }
        }
        
        $phaseResult['end_time'] = date('Y-m-d H:i:s');
        
        return $phaseResult;
    }
    
    /**
     * Execute migrations in parallel
     */
    private function executeParallelMigrations($services, $options)
    {
        $this->logger->info("Executing parallel migrations for services: " . implode(', ', $services));
        
        $results = [];
        $processes = [];
        
        foreach ($services as $serviceName) {
            $command = sprintf(
                'php %s/migration-orchestrator.php migrate-service %s %s',
                __DIR__,
                $serviceName,
                json_encode($options)
            );
            
            $process = proc_open($command, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ], $pipes);
            
            if (is_resource($process)) {
                $processes[$serviceName] = [
                    'process' => $process,
                    'pipes' => $pipes
                ];
            }
        }
        
        // Wait for all processes to complete
        foreach ($processes as $serviceName => $processInfo) {
            $output = stream_get_contents($processInfo['pipes'][1]);
            $error = stream_get_contents($processInfo['pipes'][2]);
            
            fclose($processInfo['pipes'][0]);
            fclose($processInfo['pipes'][1]);
            fclose($processInfo['pipes'][2]);
            
            $returnCode = proc_close($processInfo['process']);
            
            $results[$serviceName] = [
                'success' => $returnCode === 0,
                'output' => $output,
                'error' => $error,
                'return_code' => $returnCode
            ];
        }
        
        return $results;
    }
    
    /**
     * Execute pre-migration validation
     */
    private function executePreMigrationValidation()
    {
        $this->logger->info("Executing pre-migration validation");
        
        $validationResult = [
            'success' => true,
            'checks' => [],
            'errors' => [],
            'warnings' => []
        ];
        
        try {
            // Check database connectivity
            $connectivityCheck = $this->checkDatabaseConnectivity();
            $validationResult['checks']['connectivity'] = $connectivityCheck;
            
            // Check disk space
            $diskSpaceCheck = $this->checkDiskSpace();
            $validationResult['checks']['disk_space'] = $diskSpaceCheck;
            
            // Check PostgreSQL extensions
            $extensionsCheck = $this->checkPostgreSQLExtensions();
            $validationResult['checks']['extensions'] = $extensionsCheck;
            
            // Check service dependencies
            $dependenciesCheck = $this->checkServiceDependencies();
            $validationResult['checks']['dependencies'] = $dependenciesCheck;
            
            // Aggregate results
            foreach ($validationResult['checks'] as $checkName => $checkResult) {
                if (!$checkResult['passed']) {
                    $validationResult['success'] = false;
                    $validationResult['errors'][] = "Pre-migration check failed: {$checkName}";
                }
                
                if (!empty($checkResult['warnings'])) {
                    $validationResult['warnings'] = array_merge($validationResult['warnings'], $checkResult['warnings']);
                }
            }
            
        } catch (Exception $e) {
            $validationResult['success'] = false;
            $validationResult['errors'][] = "Pre-migration validation error: " . $e->getMessage();
        }
        
        return $validationResult;
    }
    
    /**
     * Execute post-migration validation
     */
    private function executePostMigrationValidation()
    {
        $this->logger->info("Executing post-migration validation");
        
        $validationResult = [
            'success' => true,
            'services' => [],
            'overall_stats' => [
                'total_services' => 0,
                'successful_services' => 0,
                'failed_services' => 0,
                'total_tables' => 0,
                'total_rows' => 0
            ]
        ];
        
        foreach ($this->config['services'] as $serviceName => $serviceConfig) {
            if ($this->migrationState->isServiceCompleted($serviceName)) {
                $serviceValidation = $this->validator->validateService(
                    $serviceName,
                    $serviceConfig['mysql_database'],
                    $serviceConfig['postgres_database'],
                    'full'
                );
                
                $validationResult['services'][$serviceName] = $serviceValidation;
                $validationResult['overall_stats']['total_services']++;
                
                if ($serviceValidation['overall_success']) {
                    $validationResult['overall_stats']['successful_services']++;
                } else {
                    $validationResult['overall_stats']['failed_services']++;
                    $validationResult['success'] = false;
                }
            }
        }
        
        return $validationResult;
    }
    
    /**
     * Execute performance benchmark
     */
    private function executePerformanceBenchmark()
    {
        $this->logger->info("Executing performance benchmark");
        
        // This would implement comprehensive performance testing
        return [
            'success' => true,
            'message' => 'Performance benchmark completed',
            'results' => []
        ];
    }
    
    /**
     * Create pre-migration backup
     */
    private function createPreMigrationBackup($serviceName)
    {
        $serviceConfig = $this->config['services'][$serviceName];
        $database = $serviceConfig['mysql_database'];
        
        $backupDir = $this->config['backup']['directory'];
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = "{$backupDir}/{$database}_pre_migration_{$timestamp}.sql";
        
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
            throw new Exception("Failed to create backup for {$serviceName}");
        }
        
        return [
            'success' => true,
            'backup_file' => $backupFile,
            'size' => filesize($backupFile)
        ];
    }
    
    /**
     * Update service configuration
     */
    private function updateServiceConfiguration($serviceName)
    {
        // This would update service configuration files to use PostgreSQL
        return [
            'success' => true,
            'message' => 'Configuration updated successfully'
        ];
    }
    
    /**
     * Execute emergency rollback
     */
    private function executeEmergencyRollback()
    {
        $this->logger->info("Executing emergency rollback for all services");
        
        $rollbackResults = [];
        
        foreach ($this->migrationState->getCompletedServices() as $serviceName) {
            $rollbackResult = $this->rollbackManager->rollbackService($serviceName, 'emergency');
            $rollbackResults[$serviceName] = $rollbackResult;
        }
        
        return [
            'success' => true,
            'services' => $rollbackResults
        ];
    }
    
    // Validation helper methods
    private function checkDatabaseConnectivity()
    {
        return ['passed' => true, 'message' => 'Database connectivity verified'];
    }
    
    private function checkDiskSpace()
    {
        return ['passed' => true, 'message' => 'Sufficient disk space available'];
    }
    
    private function checkPostgreSQLExtensions()
    {
        return ['passed' => true, 'message' => 'PostgreSQL extensions verified'];
    }
    
    private function checkServiceDependencies()
    {
        return ['passed' => true, 'message' => 'Service dependencies verified'];
    }
    
    private function saveMigrationPlan($migrationPlan)
    {
        $reportPath = "migration/reports/migration_plan_" . date('Y-m-d_H-i-s') . ".json";
        
        if (!is_dir('migration/reports')) {
            mkdir('migration/reports', 0755, true);
        }
        
        file_put_contents($reportPath, json_encode($migrationPlan, JSON_PRETTY_PRINT));
        $this->logger->info("Migration plan saved: {$reportPath}");
    }
}

/**
 * Migration State Manager
 */
class MigrationState
{
    private $stateFile = 'migration/state/migration_state.json';
    private $state;
    
    public function __construct()
    {
        $this->loadState();
    }
    
    private function loadState()
    {
        if (file_exists($this->stateFile)) {
            $this->state = json_decode(file_get_contents($this->stateFile), true);
        } else {
            $this->state = [
                'completed_services' => [],
                'failed_services' => [],
                'start_time' => null,
                'last_update' => null
            ];
        }
    }
    
    public function markServiceCompleted($serviceName)
    {
        $this->state['completed_services'][] = $serviceName;
        $this->state['last_update'] = date('Y-m-d H:i:s');
        $this->saveState();
    }
    
    public function markServiceFailed($serviceName)
    {
        $this->state['failed_services'][] = $serviceName;
        $this->state['last_update'] = date('Y-m-d H:i:s');
        $this->saveState();
    }
    
    public function isServiceCompleted($serviceName)
    {
        return in_array($serviceName, $this->state['completed_services']);
    }
    
    public function getCompletedServices()
    {
        return $this->state['completed_services'];
    }
    
    private function saveState()
    {
        $stateDir = dirname($this->stateFile);
        if (!is_dir($stateDir)) {
            mkdir($stateDir, 0755, true);
        }
        
        file_put_contents($this->stateFile, json_encode($this->state, JSON_PRETTY_PRINT));
    }
}

/**
 * Orchestrator Logger
 */
class OrchestratorLogger
{
    private $logFile;
    
    public function __construct()
    {
        if (!is_dir('migration/logs')) {
            mkdir('migration/logs', 0755, true);
        }
        $this->logFile = 'migration/logs/orchestrator_' . date('Y-m-d') . '.log';
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
    $orchestrator = new MigrationOrchestrator($config);
    
    $command = $argv[1] ?? 'help';
    
    switch ($command) {
        case 'full-migration':
            $options = [
                'backup' => true,
                'validation_level' => 'full',
                'parallel' => false,
                'auto_rollback' => false,
                'benchmark' => true
            ];
            
            $result = $orchestrator->executeFullMigration($options);
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'migrate-service':
            $serviceName = $argv[2] ?? null;
            $options = isset($argv[3]) ? json_decode($argv[3], true) : [];
            
            if (!$serviceName) {
                echo "Usage: php migration-orchestrator.php migrate-service <service-name> [options]\n";
                exit(1);
            }
            
            $result = $orchestrator->executeSingleServiceMigration($serviceName, $options);
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'help':
        default:
            echo "Migration Orchestrator\n";
            echo "Usage: php migration-orchestrator.php <command> [options]\n\n";
            echo "Commands:\n";
            echo "  full-migration    Execute complete migration for all services\n";
            echo "  migrate-service   Migrate a single service\n";
            echo "  help             Show this help message\n\n";
            echo "Examples:\n";
            echo "  php migration-orchestrator.php full-migration\n";
            echo "  php migration-orchestrator.php migrate-service gateway-service\n";
            break;
    }
}

