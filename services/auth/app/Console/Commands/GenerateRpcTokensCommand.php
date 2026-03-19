<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class GenerateRpcTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'rpc:generate-tokens 
                            {--regenerate : Regenerate existing tokens}
                            {--service= : Generate tokens for specific service only}
                            {--expires-in=8760 : Token expiration in hours (default: 1 year)}';

    /**
     * The console command description.
     */
    protected $description = 'Generate Sanctum tokens for RPC inter-service authentication';

    /**
     * List of all services in the ecosystem
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
        $this->info('🔐 Starting RPC Token Generation for Microservice Authentication');
        $this->newLine();

        // Get or create RPC system user
        $rpcUser = $this->getOrCreateRpcUser();
        
        if (!$rpcUser) {
            $this->error('❌ Failed to create RPC system user');
            return Command::FAILURE;
        }

        $this->info("✅ RPC System User: {$rpcUser->email} (ID: {$rpcUser->id})");
        $this->newLine();

        // Calculate expiration
        $expiresInHours = (int) $this->option('expires-in');
        $expiresAt = Carbon::now()->addHours($expiresInHours);
        $this->info("⏰ Token Expiration: {$expiresAt->format('Y-m-d H:i:s')} ({$expiresInHours} hours)");
        $this->newLine();

        // Generate tokens for each service
        $specificService = $this->option('service');
        $servicesToProcess = $specificService ? [$specificService] : $this->services;

        $generatedTokens = [];
        $envUpdates = [];

        foreach ($servicesToProcess as $service) {
            $this->info("🔧 Processing service: {$service}");
            
            // Generate tokens for this service to call other services
            $serviceTokens = $this->generateTokensForService($service, $rpcUser, $expiresAt);
            $generatedTokens[$service] = $serviceTokens;

            // Prepare environment updates
            $envUpdates[$service] = $this->prepareEnvUpdates($service, $serviceTokens);
            
            $this->info("   ✅ Generated " . count($serviceTokens) . " tokens for {$service}");
        }

        $this->newLine();
        $this->info('📝 Token Generation Summary:');
        $this->table(
            ['Service', 'Tokens Generated', 'Status'],
            collect($generatedTokens)->map(function ($tokens, $service) {
                return [$service, count($tokens), '✅ Complete'];
            })->toArray()
        );

        // Update environment files
        $this->newLine();
        $this->info('📄 Updating environment files...');
        
        foreach ($envUpdates as $service => $updates) {
            $this->updateServiceEnvFile($service, $updates);
        }

        $this->newLine();
        $this->info('🎉 RPC Token Generation Complete!');
        $this->info('🔄 Please restart all services to load the new tokens.');
        
        // Display security recommendations
        $this->displaySecurityRecommendations();

        return Command::SUCCESS;
    }

    /**
     * Get or create the RPC system user
     */
    protected function getOrCreateRpcUser(): ?User
    {
        $email = 'rpc-system@internal.service';
        
        try {
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                $this->info('👤 Creating RPC system user...');
                
                $user = User::create([
                    'name' => 'RPC System User',
                    'email' => $email,
                    'password' => bcrypt(str()->random(32)), // Random password, won't be used
                    'type' => User::TYPE_ADMIN, // Admin type for full access
                    'status' => User::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                    'phone_verified_at' => now(),
                    'metadata' => [
                        'purpose' => 'RPC inter-service authentication',
                        'created_by' => 'rpc:generate-tokens command',
                        'created_at' => now()->toISOString(),
                    ],
                ]);
            } else {
                $this->info('👤 Using existing RPC system user...');
            }

            return $user;
        } catch (\Exception $e) {
            $this->warn('⚠️  Database not available. Generating static tokens instead.');
            $this->info('   These tokens will need to be regenerated when database is available.');
            
            // Return a mock user for token generation
            $user = new User();
            $user->id = 1;
            $user->email = $email;
            $user->name = 'RPC System User';
            
            return $user;
        }
    }

    /**
     * Generate tokens for a service to call other services
     */
    protected function generateTokensForService(string $service, User $rpcUser, Carbon $expiresAt): array
    {
        $tokens = [];
        
        // Get list of services this service needs to call (exclude self)
        $targetServices = array_filter($this->services, fn($s) => $s !== $service);
        
        foreach ($targetServices as $targetService) {
            $tokenName = "RPC-{$service}-to-{$targetService}";
            
            try {
                // Check if token already exists and should be regenerated
                if ($this->option('regenerate')) {
                    $rpcUser->tokens()->where('name', $tokenName)->delete();
                }
                
                // Create token with RPC-specific abilities
                $tokenResult = $rpcUser->createToken($tokenName, [
                    'rpc:call',
                    'rpc:validate',
                    "service:{$targetService}",
                    "caller:{$service}",
                ], $expiresAt);
                
                $tokens[$targetService] = $tokenResult->plainTextToken;
            } catch (\Exception $e) {
                // Generate a static token when database is not available
                $staticToken = $this->generateStaticToken($service, $targetService);
                $tokens[$targetService] = $staticToken;
            }
        }
        
        return $tokens;
    }

    /**
     * Generate a static token for development/testing when database is not available
     */
    protected function generateStaticToken(string $fromService, string $toService): string
    {
        // Generate a deterministic but secure token based on service names
        $appKey = config('app.key');
        if (empty($appKey)) {
            throw new \RuntimeException('Application key is required for token generation');
        }
        $seed = "rpc-{$fromService}-to-{$toService}-" . $appKey;
        $hash = hash('sha256', $seed);
        
        // Format as a Sanctum-like token (prefix|hash)
        return substr($hash, 0, 8) . '|' . $hash;
    }

    /**
     * Prepare environment variable updates for a service
     */
    protected function prepareEnvUpdates(string $service, array $tokens): array
    {
        $updates = [];
        
        foreach ($tokens as $targetService => $token) {
            $envKey = 'RPC_' . strtoupper(str_replace('-', '_', $targetService)) . '_TOKEN';
            $updates[$envKey] = $token;
        }
        
        return $updates;
    }

    /**
     * Update service environment file with new tokens
     */
    protected function updateServiceEnvFile(string $service, array $updates): void
    {
        $envPath = base_path("../{$service}/.env");
        
        if (!File::exists($envPath)) {
            $this->warn("   ⚠️  Environment file not found: {$envPath}");
            return;
        }
        
        $envContent = File::get($envPath);
        $updatedCount = 0;
        
        foreach ($updates as $key => $value) {
            $pattern = "/^{$key}=.*$/m";
            $replacement = "{$key}={$value}";
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
                $updatedCount++;
            } else {
                // Add new environment variable if it doesn't exist
                $envContent .= "\n{$replacement}";
                $updatedCount++;
            }
        }
        
        File::put($envPath, $envContent);
        $this->info("   ✅ Updated {$updatedCount} tokens in {$service}/.env");
    }

    /**
     * Display security recommendations
     */
    protected function displaySecurityRecommendations(): void
    {
        $this->newLine();
        $this->info('🔒 Security Recommendations:');
        $this->line('   • Tokens are stored in environment files - ensure proper file permissions (600)');
        $this->line('   • Consider implementing token rotation for production environments');
        $this->line('   • Monitor token usage through Laravel Sanctum logs');
        $this->line('   • Use different token expiration times for different environments');
        $this->line('   • Regularly audit RPC token usage and revoke unused tokens');
        $this->newLine();
        
        $this->info('🔧 Management Commands:');
        $this->line('   • Regenerate tokens: php artisan rpc:generate-tokens --regenerate');
        $this->line('   • Generate for specific service: php artisan rpc:generate-tokens --service=auth-service');
        $this->line('   • Set custom expiration: php artisan rpc:generate-tokens --expires-in=720 (30 days)');
        $this->line('   • Prune expired tokens: php artisan sanctum:prune-expired');
    }
}
