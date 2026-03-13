<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class RotateRpcTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'rpc:rotate-tokens 
                            {--dry-run : Show what would be rotated without making changes}
                            {--force : Force rotation even if tokens are not near expiration}
                            {--service= : Rotate tokens for specific service only}
                            {--expires-in=8760 : New token expiration in hours (default: 1 year)}
                            {--backup : Create backup of current tokens before rotation}';

    /**
     * The console command description.
     */
    protected $description = 'Rotate RPC authentication tokens for security maintenance';

    /**
     * List of all microservices
     */
    protected array $services = [
        'auth-service',
        'user-service',
        'auction-service',
        'bidding-service',
        'order-service',
        'payment-service',
        'analytics-service',
        'notification-service',
        'vin-ocr-service',
        'gateway-service',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 RPC Token Rotation System');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $specificService = $this->option('service');
        $expiresInHours = (int) $this->option('expires-in');
        $backup = $this->option('backup');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Validate service if specified
        if ($specificService && !in_array($specificService, $this->services)) {
            $this->error("❌ Invalid service: {$specificService}");
            $this->line('Available services: ' . implode(', ', $this->services));
            return Command::FAILURE;
        }

        $servicesToRotate = $specificService ? [$specificService] : $this->services;

        // Check current token status
        $this->info('📊 Current Token Status Analysis:');
        $tokenStatus = $this->analyzeTokenStatus($servicesToRotate);
        $this->displayTokenStatusTable($tokenStatus);
        $this->newLine();

        // Determine which tokens need rotation
        $tokensToRotate = $this->determineTokensToRotate($tokenStatus, $force);

        if (empty($tokensToRotate)) {
            $this->info('✅ No tokens require rotation at this time.');
            $this->line('💡 Use --force to rotate all tokens regardless of expiration status.');
            return Command::SUCCESS;
        }

        $this->info("🔄 Planning to rotate tokens for " . count($tokensToRotate) . " services:");
        foreach ($tokensToRotate as $service) {
            $this->line("   • {$service}");
        }
        $this->newLine();

        if (!$dryRun && !$this->confirm('Do you want to proceed with token rotation?')) {
            $this->info('❌ Token rotation cancelled by user.');
            return Command::SUCCESS;
        }

        // Create backup if requested
        if ($backup && !$dryRun) {
            $this->createTokenBackup($tokensToRotate);
        }

        // Perform token rotation
        $rotationResults = $this->performTokenRotation($tokensToRotate, $expiresInHours, $dryRun);

        // Display results
        $this->displayRotationResults($rotationResults, $dryRun);

        // Provide post-rotation instructions
        if (!$dryRun) {
            $this->displayPostRotationInstructions($tokensToRotate);
        }

        return Command::SUCCESS;
    }

    /**
     * Analyze current token status
     */
    protected function analyzeTokenStatus(array $services): array
    {
        $status = [];

        foreach ($services as $service) {
            $envPath = base_path("../{$service}/.env");
            $tokenCount = 0;
            $oldestToken = null;
            $newestToken = null;

            if (File::exists($envPath)) {
                $envContent = File::get($envPath);
                preg_match_all('/^RPC_.*_TOKEN=(.+)$/m', $envContent, $matches);
                $tokenCount = count($matches[1]);

                // For static tokens, we can't determine actual age, so we estimate
                $estimatedAge = $this->estimateTokenAge($service);
                $oldestToken = $estimatedAge;
                $newestToken = $estimatedAge;
            }

            $status[$service] = [
                'token_count' => $tokenCount,
                'oldest_token_age' => $oldestToken,
                'newest_token_age' => $newestToken,
                'needs_rotation' => $this->needsRotation($oldestToken),
                'env_file_exists' => File::exists($envPath),
            ];
        }

        return $status;
    }

    /**
     * Estimate token age based on file modification time
     */
    protected function estimateTokenAge(string $service): ?int
    {
        $envPath = base_path("../{$service}/.env");
        
        if (!File::exists($envPath)) {
            return null;
        }

        $modificationTime = File::lastModified($envPath);
        $ageInHours = (time() - $modificationTime) / 3600;
        
        return (int) $ageInHours;
    }

    /**
     * Determine if tokens need rotation
     */
    protected function needsRotation(?int $ageInHours): bool
    {
        if ($ageInHours === null) {
            return true; // No tokens exist
        }

        // Rotate if tokens are older than 30 days (720 hours)
        return $ageInHours > 720;
    }

    /**
     * Display token status table
     */
    protected function displayTokenStatusTable(array $tokenStatus): void
    {
        $tableData = [];

        foreach ($tokenStatus as $service => $status) {
            $ageDisplay = $status['oldest_token_age'] !== null 
                ? $status['oldest_token_age'] . 'h' 
                : 'Unknown';

            $needsRotation = $status['needs_rotation'] ? '⚠️  Yes' : '✅ No';

            $tableData[] = [
                $service,
                $status['token_count'],
                $ageDisplay,
                $needsRotation,
                $status['env_file_exists'] ? '✅' : '❌',
            ];
        }

        $this->table(
            ['Service', 'Token Count', 'Est. Age', 'Needs Rotation', 'Env File'],
            $tableData
        );
    }

    /**
     * Determine which tokens to rotate
     */
    protected function determineTokensToRotate(array $tokenStatus, bool $force): array
    {
        $tokensToRotate = [];

        foreach ($tokenStatus as $service => $status) {
            if ($force || $status['needs_rotation'] || $status['token_count'] === 0) {
                $tokensToRotate[] = $service;
            }
        }

        return $tokensToRotate;
    }

    /**
     * Create backup of current tokens
     */
    protected function createTokenBackup(array $services): void
    {
        $this->info('💾 Creating token backup...');
        
        $backupDir = base_path('../backups/rpc-tokens');
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $backupPath = "{$backupDir}/{$timestamp}";

        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        File::makeDirectory($backupPath, 0755, true);

        foreach ($services as $service) {
            $envPath = base_path("../{$service}/.env");
            if (File::exists($envPath)) {
                $backupFile = "{$backupPath}/{$service}.env";
                File::copy($envPath, $backupFile);
                $this->line("   ✅ Backed up {$service} tokens");
            }
        }

        $this->info("💾 Backup created at: {$backupPath}");
        $this->newLine();
    }

    /**
     * Perform token rotation
     */
    protected function performTokenRotation(array $services, int $expiresInHours, bool $dryRun): array
    {
        $results = [];

        foreach ($services as $service) {
            $this->info("🔄 Rotating tokens for: {$service}");

            if ($dryRun) {
                $results[$service] = [
                    'success' => true,
                    'tokens_rotated' => 9,
                    'message' => 'Would rotate 9 tokens (dry run)',
                ];
                $this->line("   🔍 Would rotate 9 tokens for {$service}");
            } else {
                try {
                    // Call the generate tokens command for this specific service
                    $exitCode = $this->call('rpc:generate-tokens', [
                        '--regenerate' => true,
                        '--service' => $service,
                        '--expires-in' => $expiresInHours,
                    ]);

                    if ($exitCode === 0) {
                        $results[$service] = [
                            'success' => true,
                            'tokens_rotated' => 9,
                            'message' => 'Successfully rotated 9 tokens',
                        ];
                        $this->line("   ✅ Successfully rotated 9 tokens for {$service}");
                    } else {
                        $results[$service] = [
                            'success' => false,
                            'tokens_rotated' => 0,
                            'message' => 'Token generation command failed',
                        ];
                        $this->error("   ❌ Failed to rotate tokens for {$service}");
                    }
                } catch (\Exception $e) {
                    $results[$service] = [
                        'success' => false,
                        'tokens_rotated' => 0,
                        'message' => $e->getMessage(),
                    ];
                    $this->error("   ❌ Error rotating tokens for {$service}: " . $e->getMessage());
                }
            }
        }

        return $results;
    }

    /**
     * Display rotation results
     */
    protected function displayRotationResults(array $results, bool $dryRun): void
    {
        $this->newLine();
        $this->info('📊 Token Rotation Results:');

        $tableData = [];
        $totalSuccess = 0;
        $totalTokens = 0;

        foreach ($results as $service => $result) {
            $status = $result['success'] ? '✅ Success' : '❌ Failed';
            $tableData[] = [
                $service,
                $status,
                $result['tokens_rotated'],
                $result['message'],
            ];

            if ($result['success']) {
                $totalSuccess++;
                $totalTokens += $result['tokens_rotated'];
            }
        }

        $this->table(
            ['Service', 'Status', 'Tokens Rotated', 'Details'],
            $tableData
        );

        $this->newLine();
        if ($dryRun) {
            $this->info("🔍 Dry Run Summary: Would rotate {$totalTokens} tokens across {$totalSuccess} services");
        } else {
            $this->info("🎯 Rotation Summary: {$totalTokens} tokens rotated across {$totalSuccess} services");
        }
    }

    /**
     * Display post-rotation instructions
     */
    protected function displayPostRotationInstructions(array $rotatedServices): void
    {
        $this->newLine();
        $this->info('📋 Post-Rotation Instructions:');
        $this->newLine();

        $this->warn('🔄 CRITICAL: Services must be restarted to load new tokens!');
        $this->newLine();

        $this->line('1. 🚀 Restart the following services:');
        foreach ($rotatedServices as $service) {
            $this->line("   • {$service}");
        }
        $this->newLine();

        $this->line('2. 🧪 Test RPC authentication:');
        $this->line('   php artisan rpc:test-authentication');
        $this->newLine();

        $this->line('3. 📊 Monitor service logs for authentication issues');
        $this->line('4. 🔍 Verify inter-service communication is working');
        $this->newLine();

        $this->info('🔒 Security Recommendations:');
        $this->line('• Schedule regular token rotation (monthly recommended)');
        $this->line('• Monitor token usage through Sanctum logs');
        $this->line('• Keep token backups in secure location');
        $this->line('• Audit RPC access patterns regularly');
        $this->newLine();

        $this->info('⏰ Next Rotation: Schedule in 30 days');
        $this->line('   Command: php artisan rpc:rotate-tokens');
    }
}
