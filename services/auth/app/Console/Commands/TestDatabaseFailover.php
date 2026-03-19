<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Shared\Services\DatabaseFailoverManager;
use Shared\HealthCheck\DatabaseHealthChecker;
use Shared\Contracts\DatabaseFailoverInterface;

/**
 * Test Database Failover Command
 * 
 * Tests the database failover system functionality
 */
class TestDatabaseFailover extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:test-failover 
                            {--check-health : Only check database health status}
                            {--trigger-failover : Trigger a manual failover}
                            {--connection= : Test specific connection}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test database failover system functionality';

    private DatabaseFailoverManager $failoverManager;
    private DatabaseHealthChecker $healthChecker;

    public function __construct(
        DatabaseFailoverManager $failoverManager,
        DatabaseHealthChecker $healthChecker
    ) {
        parent::__construct();
        $this->failoverManager = $failoverManager;
        $this->healthChecker = $healthChecker;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Database Failover System Test');
        $this->info('================================');

        try {
            if ($this->option('check-health')) {
                return $this->checkHealthStatus();
            }

            if ($this->option('trigger-failover')) {
                return $this->triggerFailover();
            }

            if ($this->option('connection')) {
                return $this->testSpecificConnection($this->option('connection'));
            }

            // Run comprehensive test
            return $this->runComprehensiveTest();

        } catch (\Exception $e) {
            $this->error("❌ Test failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Check health status of all connections.
     */
    private function checkHealthStatus(): int
    {
        $this->info('🏥 Checking database health status...');
        
        try {
            $healthStatuses = $this->healthChecker->checkAllConnections();
            
            $this->table(
                ['Connection', 'Status', 'Connectable', 'Queryable', 'Duration (ms)', 'Errors', 'Warnings'],
                collect($healthStatuses)->map(function ($status) {
                    return [
                        $status->getConnectionName(),
                        $status->isHealthy() ? '✅ Healthy' : '❌ Unhealthy',
                        $status->isConnectable() ? '✅' : '❌',
                        $status->isQueryable() ? '✅' : '❌',
                        number_format($status->getCheckDuration(), 2),
                        count($status->getErrors()),
                        count($status->getWarnings()),
                    ];
                })->toArray()
            );

            // Show detailed errors and warnings
            foreach ($healthStatuses as $status) {
                if ($status->hasErrors() || $status->hasWarnings()) {
                    $this->warn("\n📋 Details for {$status->getConnectionName()}:");
                    
                    foreach ($status->getErrors() as $type => $message) {
                        $this->error("  ❌ Error ({$type}): {$message}");
                    }
                    
                    foreach ($status->getWarnings() as $type => $message) {
                        $this->warn("  ⚠️  Warning ({$type}): {$message}");
                    }
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Health check failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Trigger a manual failover.
     */
    private function triggerFailover(): int
    {
        $this->info('🔄 Triggering manual failover...');
        
        try {
            $currentConnection = $this->failoverManager->getCurrentConnection();
            $this->info("Current connection: {$currentConnection}");
            
            $newConnection = $this->failoverManager->triggerFailover();
            $this->info("✅ Failover successful! New connection: {$newConnection}");
            
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failover failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Test a specific connection.
     */
    private function testSpecificConnection(string $connectionName): int
    {
        $this->info("🔍 Testing connection: {$connectionName}");
        
        try {
            $status = $this->healthChecker->checkConnection($connectionName);
            
            $this->info("Connection Status: " . ($status->isHealthy() ? '✅ Healthy' : '❌ Unhealthy'));
            $this->info("Connectable: " . ($status->isConnectable() ? '✅ Yes' : '❌ No'));
            $this->info("Queryable: " . ($status->isQueryable() ? '✅ Yes' : '❌ No'));
            $this->info("Check Duration: " . number_format($status->getCheckDuration(), 2) . 'ms');
            
            if ($status->hasErrors()) {
                $this->warn("\n❌ Errors:");
                foreach ($status->getErrors() as $type => $message) {
                    $this->error("  - {$type}: {$message}");
                }
            }
            
            if ($status->hasWarnings()) {
                $this->warn("\n⚠️  Warnings:");
                foreach ($status->getWarnings() as $type => $message) {
                    $this->warn("  - {$type}: {$message}");
                }
            }

            // Show metrics
            $metrics = $status->getMetrics();
            if (!empty($metrics)) {
                $this->info("\n📊 Metrics:");
                foreach ($metrics as $key => $value) {
                    $this->info("  - {$key}: {$value}");
                }
            }
            
            return $status->isHealthy() ? 0 : 1;

        } catch (\Exception $e) {
            $this->error("❌ Connection test failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Run comprehensive failover system test.
     */
    private function runComprehensiveTest(): int
    {
        $this->info('🧪 Running comprehensive failover system test...');
        
        $errors = 0;

        // Test 1: Service Registration
        $this->info("\n1️⃣ Testing service registration...");
        try {
            $manager = app(DatabaseFailoverInterface::class);
            $this->info("✅ DatabaseFailoverManager is properly registered");
        } catch (\Exception $e) {
            $this->error("❌ Service registration failed: " . $e->getMessage());
            $errors++;
        }

        // Test 2: Configuration Loading
        $this->info("\n2️⃣ Testing configuration loading...");
        try {
            $config = config('database-failover');
            if (empty($config)) {
                $this->warn("⚠️  Database failover configuration is empty");
            } else {
                $this->info("✅ Configuration loaded successfully");
            }
        } catch (\Exception $e) {
            $this->error("❌ Configuration loading failed: " . $e->getMessage());
            $errors++;
        }

        // Test 3: Health Checker
        $this->info("\n3️⃣ Testing health checker...");
        try {
            $healthStatuses = $this->healthChecker->checkAllConnections();
            $healthyCount = collect($healthStatuses)->filter(fn($status) => $status->isHealthy())->count();
            $totalCount = count($healthStatuses);
            
            $this->info("✅ Health checker working. {$healthyCount}/{$totalCount} connections healthy");
            
            if ($healthyCount === 0) {
                $this->warn("⚠️  No healthy connections found - this may indicate configuration issues");
            }
        } catch (\Exception $e) {
            $this->error("❌ Health checker failed: " . $e->getMessage());
            $errors++;
        }

        // Test 4: Failover Manager
        $this->info("\n4️⃣ Testing failover manager...");
        try {
            $currentConnection = $this->failoverManager->getCurrentConnection();
            $this->info("✅ Current connection: {$currentConnection}");
            
            $healthyConnection = $this->failoverManager->getHealthyConnection();
            $this->info("✅ Healthy connection: {$healthyConnection}");
        } catch (\Exception $e) {
            $this->error("❌ Failover manager test failed: " . $e->getMessage());
            $errors++;
        }

        // Test 5: Database Connections
        $this->info("\n5️⃣ Testing database connections...");
        try {
            $connections = ['pgsql', 'pgsql_secondary', 'mongodb'];
            foreach ($connections as $connection) {
                try {
                    $config = config("database.connections.{$connection}");
                    if ($config) {
                        $this->info("✅ Connection '{$connection}' configured");
                    } else {
                        $this->warn("⚠️  Connection '{$connection}' not configured");
                    }
                } catch (\Exception $e) {
                    $this->warn("⚠️  Connection '{$connection}' configuration error: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            $this->error("❌ Database connection test failed: " . $e->getMessage());
            $errors++;
        }

        // Summary
        $this->info("\n📋 Test Summary");
        $this->info("===============");
        
        if ($errors === 0) {
            $this->info("✅ All tests passed! Database failover system is ready.");
            return 0;
        } else {
            $this->error("❌ {$errors} test(s) failed. Please check the configuration and dependencies.");
            return 1;
        }
    }
}
