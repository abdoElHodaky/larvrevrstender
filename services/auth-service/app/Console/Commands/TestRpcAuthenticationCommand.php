<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestRpcAuthenticationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'rpc:test-authentication 
                            {--service= : Test specific service only}
                            {--timeout=10 : Request timeout in seconds}';

    /**
     * The console command description.
     */
    protected $description = 'Test RPC authentication between services';

    /**
     * List of services to test
     */
    protected array $services = [
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
        $this->info('🔐 Testing RPC Authentication Between Services');
        $this->newLine();

        $specificService = $this->option('service');
        $servicesToTest = $specificService ? [$specificService] : $this->services;
        $timeout = (int) $this->option('timeout');

        $results = [];
        $totalTests = 0;
        $passedTests = 0;

        foreach ($servicesToTest as $targetService) {
            $this->info("🔧 Testing authentication to: {$targetService}");
            
            $result = $this->testServiceAuthentication($targetService, $timeout);
            $results[$targetService] = $result;
            
            $totalTests++;
            if ($result['success']) {
                $passedTests++;
                $this->info("   ✅ Authentication successful");
            } else {
                $this->error("   ❌ Authentication failed: {$result['error']}");
            }
            
            if (isset($result['token_info'])) {
                $this->line("   🔑 Token: " . substr($result['token_info'], 0, 20) . "...");
            }
            
            $this->newLine();
        }

        // Display summary
        $this->info('📊 RPC Authentication Test Summary:');
        $this->table(
            ['Service', 'Status', 'Response Time', 'Details'],
            collect($results)->map(function ($result, $service) {
                return [
                    $service,
                    $result['success'] ? '✅ Pass' : '❌ Fail',
                    isset($result['response_time']) ? $result['response_time'] . 'ms' : 'N/A',
                    $result['success'] ? 'Authentication verified' : $result['error']
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info("🎯 Test Results: {$passedTests}/{$totalTests} services passed authentication");
        
        if ($passedTests === $totalTests) {
            $this->info('🎉 All RPC authentication tests passed!');
            return Command::SUCCESS;
        } else {
            $this->error('⚠️  Some RPC authentication tests failed. Check service configurations.');
            return Command::FAILURE;
        }
    }

    /**
     * Test authentication to a specific service
     */
    protected function testServiceAuthentication(string $targetService, int $timeout): array
    {
        try {
            // Get the RPC token for this service
            $tokenKey = 'RPC_' . strtoupper(str_replace('-', '_', $targetService)) . '_TOKEN';
            $token = env($tokenKey);
            
            if (empty($token)) {
                return [
                    'success' => false,
                    'error' => "No RPC token found for {$targetService} (key: {$tokenKey})",
                ];
            }

            // Get service URL
            $serviceUrl = $this->getServiceUrl($targetService);
            
            if (!$serviceUrl) {
                return [
                    'success' => false,
                    'error' => "No URL configured for {$targetService}",
                ];
            }

            // Test authentication by making a simple RPC call
            $startTime = microtime(true);
            
            $response = Http::timeout($timeout)
                ->withToken($token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Service-Name' => 'auth-service',
                    'X-Correlation-ID' => uniqid('test_', true),
                ])
                ->post($serviceUrl, [
                    'jsonrpc' => '2.0',
                    'method' => 'system.ping', // Generic ping method
                    'params' => [],
                    'id' => 1,
                ]);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'response_time' => $responseTime,
                    'token_info' => $token,
                    'status_code' => $response->status(),
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "HTTP {$response->status()}: " . $response->body(),
                    'response_time' => $responseTime,
                    'token_info' => $token,
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'token_info' => $token ?? null,
            ];
        }
    }

    /**
     * Get service URL for testing
     */
    protected function getServiceUrl(string $service): ?string
    {
        $urlKey = strtoupper(str_replace('-', '_', $service)) . '_URL';
        $baseUrl = env($urlKey, "http://{$service}:8080");
        
        return $baseUrl . '/rpc';
    }

    /**
     * Display token configuration status
     */
    protected function displayTokenStatus(): void
    {
        $this->info('🔑 RPC Token Configuration Status:');
        
        foreach ($this->services as $service) {
            $tokenKey = 'RPC_' . strtoupper(str_replace('-', '_', $service)) . '_TOKEN';
            $token = env($tokenKey);
            
            if ($token) {
                $this->line("   ✅ {$service}: " . substr($token, 0, 20) . "...");
            } else {
                $this->error("   ❌ {$service}: No token configured");
            }
        }
        
        $this->newLine();
    }
}
