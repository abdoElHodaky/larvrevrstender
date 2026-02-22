<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class GracefulShutdown extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'octane:graceful-shutdown 
                            {--timeout=30 : Maximum time to wait for requests to complete}
                            {--force : Force shutdown without waiting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gracefully shutdown Laravel Octane server with connection draining';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timeout = (int) $this->option('timeout');
        $force = $this->option('force');
        
        $this->info('🔄 Initiating graceful shutdown...');
        
        // Mark service as shutting down
        $this->markShuttingDown();
        
        if (!$force) {
            // Wait for in-flight requests to complete
            $this->waitForRequestsToComplete($timeout);
        }
        
        // Stop accepting new requests
        $this->stopAcceptingRequests();
        
        // Stop Octane server
        $this->stopOctaneServer();
        
        $this->info('✅ Graceful shutdown completed');
        
        return 0;
    }
    
    /**
     * Mark the service as shutting down
     */
    private function markShuttingDown(): void
    {
        try {
            // Set shutdown flag in cache
            Cache::put('shutdown_in_progress', true, 300); // 5 minutes TTL
            Cache::put('shutdown_started_at', now()->toISOString(), 300);
            
            // Set shutdown flag in Redis for cross-service communication
            Redis::setex('service:' . config('app.name') . ':shutdown', 300, json_encode([
                'status' => 'shutting_down',
                'started_at' => now()->toISOString(),
                'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
                'service_name' => config('app.name'),
            ]));
            
            Log::info('Service marked as shutting down', [
                'service' => config('app.name'),
                'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
                'timestamp' => now()->toISOString(),
            ]);
            
            $this->info('📢 Service marked as shutting down');
            
        } catch (\Exception $e) {
            Log::error('Failed to mark service as shutting down: ' . $e->getMessage());
            $this->error('Failed to mark service as shutting down');
        }
    }
    
    /**
     * Wait for in-flight requests to complete
     */
    private function waitForRequestsToComplete(int $timeout): void
    {
        $this->info("⏳ Waiting up to {$timeout} seconds for requests to complete...");
        
        $startTime = time();
        $progressBar = $this->output->createProgressBar($timeout);
        
        while ((time() - $startTime) < $timeout) {
            $activeRequests = $this->getActiveRequestCount();
            
            if ($activeRequests === 0) {
                $progressBar->finish();
                $this->newLine();
                $this->info('✅ All requests completed');
                return;
            }
            
            $this->line("Active requests: {$activeRequests}");
            $progressBar->advance();
            sleep(1);
        }
        
        $progressBar->finish();
        $this->newLine();
        
        $remainingRequests = $this->getActiveRequestCount();
        if ($remainingRequests > 0) {
            $this->warn("⚠️  Timeout reached with {$remainingRequests} active requests");
        }
    }
    
    /**
     * Get the count of active requests
     */
    private function getActiveRequestCount(): int
    {
        try {
            // Try to get active request count from Octane metrics
            $metrics = Cache::get('octane:metrics', []);
            return $metrics['active_requests'] ?? 0;
            
        } catch (\Exception $e) {
            Log::warning('Failed to get active request count: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Stop accepting new requests
     */
    private function stopAcceptingRequests(): void
    {
        try {
            // Update health check status
            Cache::put('health_check_status', 'shutting_down', 300);
            Cache::put('ready_check_status', 'not_ready', 300);
            
            $this->info('🚫 Stopped accepting new requests');
            
        } catch (\Exception $e) {
            Log::error('Failed to stop accepting requests: ' . $e->getMessage());
            $this->error('Failed to stop accepting requests');
        }
    }
    
    /**
     * Stop the Octane server
     */
    private function stopOctaneServer(): void
    {
        try {
            $this->info('🛑 Stopping Octane server...');
            
            // Call the octane:stop command
            $exitCode = $this->call('octane:stop');
            
            if ($exitCode === 0) {
                $this->info('✅ Octane server stopped successfully');
            } else {
                $this->error('❌ Failed to stop Octane server');
            }
            
            // Clean up shutdown flags
            $this->cleanupShutdownFlags();
            
        } catch (\Exception $e) {
            Log::error('Failed to stop Octane server: ' . $e->getMessage());
            $this->error('Failed to stop Octane server');
        }
    }
    
    /**
     * Clean up shutdown flags
     */
    private function cleanupShutdownFlags(): void
    {
        try {
            Cache::forget('shutdown_in_progress');
            Cache::forget('shutdown_started_at');
            Cache::forget('health_check_status');
            Cache::forget('ready_check_status');
            
            Redis::del('service:' . config('app.name') . ':shutdown');
            
            Log::info('Shutdown flags cleaned up');
            
        } catch (\Exception $e) {
            Log::warning('Failed to clean up shutdown flags: ' . $e->getMessage());
        }
    }
}
