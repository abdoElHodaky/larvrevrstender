<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GatewayController extends Controller
{
    /**
     * Get available payment gateways for inter-service communication.
     */
    public function getAvailableGateways(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'payment_method',
                'currency',
                'country',
                'is_enabled',
                'status',
                'supports_refunds',
                'supports_3ds',
            ]);

            // Start with base query
            $query = PaymentGateway::query()
                ->where('is_enabled', true)
                ->where('status', 'active')
                ->orderBy('priority', 'asc');

            // Apply filters
            if (isset($filters['payment_method'])) {
                $query->whereJsonContains('supported_payment_methods', $filters['payment_method']);
            }

            if (isset($filters['currency'])) {
                $query->whereJsonContains('supported_currencies', $filters['currency']);
            }

            if (isset($filters['country'])) {
                $query->where(function ($q) use ($filters) {
                    $q->whereNull('supported_countries')
                      ->orWhereJsonContains('supported_countries', $filters['country']);
                });
            }

            if (isset($filters['supports_refunds'])) {
                $query->where('supports_refunds', $filters['supports_refunds']);
            }

            if (isset($filters['supports_3ds'])) {
                $query->where('supports_3ds', $filters['supports_3ds']);
            }

            $gateways = $query->get()->map(function ($gateway) {
                return [
                    'id' => $gateway->id,
                    'name' => $gateway->name,
                    'display_name' => $gateway->display_name,
                    'description' => $gateway->description,
                    'status' => $gateway->status,
                    'health_status' => $gateway->health_status,
                    'priority' => $gateway->priority,
                    'supported_payment_methods' => $gateway->supported_payment_methods,
                    'supported_currencies' => $gateway->supported_currencies,
                    'supported_countries' => $gateway->supported_countries,
                    'supports_refunds' => $gateway->supports_refunds,
                    'supports_partial_refunds' => $gateway->supports_partial_refunds,
                    'supports_3ds' => $gateway->supports_3ds,
                    'supports_webhooks' => $gateway->supports_webhooks,
                    'supports_recurring' => $gateway->supports_recurring,
                    'fee_percentage' => $gateway->fee_percentage,
                    'fee_fixed' => $gateway->fee_fixed,
                    'minimum_amount' => $gateway->minimum_amount,
                    'maximum_amount' => $gateway->maximum_amount,
                    'success_rate' => $gateway->success_rate,
                    'avg_response_time_ms' => $gateway->avg_response_time_ms,
                    'last_health_check' => $gateway->last_health_check,
                    'logo_url' => $gateway->logo_url,
                    'is_test_mode' => $gateway->is_test_mode,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'gateways' => $gateways,
                    'total_count' => $gateways->count(),
                    'filters_applied' => $filters,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve available gateways', [
                'filters' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'gateway_retrieval_failed',
                'message' => 'Failed to retrieve available payment gateways.',
            ], 500);
        }
    }

    /**
     * Get specific gateway status for inter-service communication.
     */
    public function getGatewayStatus(Request $request, string $gateway): JsonResponse
    {
        try {
            $paymentGateway = PaymentGateway::where('name', $gateway)->first();

            if (!$paymentGateway) {
                return response()->json([
                    'success' => false,
                    'error' => 'gateway_not_found',
                    'message' => "Payment gateway '{$gateway}' not found.",
                ], 404);
            }

            // Check if we should perform a health check
            $shouldCheckHealth = $this->shouldPerformHealthCheck($paymentGateway);
            
            if ($shouldCheckHealth) {
                $this->performHealthCheck($paymentGateway);
                $paymentGateway->refresh();
            }

            $statusData = [
                'gateway_name' => $paymentGateway->name,
                'display_name' => $paymentGateway->display_name,
                'status' => $paymentGateway->status,
                'health_status' => $paymentGateway->health_status,
                'is_enabled' => $paymentGateway->is_enabled,
                'is_available' => $this->isGatewayAvailable($paymentGateway),
                'last_health_check' => $paymentGateway->last_health_check,
                'success_rate' => $paymentGateway->success_rate,
                'avg_response_time_ms' => $paymentGateway->avg_response_time_ms,
                'health_metrics' => $paymentGateway->health_metrics,
                'maintenance_info' => null,
                'rate_limit_info' => [
                    'current_usage' => $paymentGateway->current_usage_count,
                    'limits' => [
                        'per_minute' => $paymentGateway->rate_limit_per_minute,
                        'per_hour' => $paymentGateway->rate_limit_per_hour,
                        'per_day' => $paymentGateway->rate_limit_per_day,
                    ],
                    'reset_at' => $paymentGateway->rate_limit_reset_at,
                ],
            ];

            // Add maintenance info if in maintenance
            if ($paymentGateway->status === 'maintenance') {
                $statusData['maintenance_info'] = [
                    'start' => $paymentGateway->maintenance_start,
                    'end' => $paymentGateway->maintenance_end,
                    'reason' => $paymentGateway->maintenance_reason,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $statusData,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve gateway status', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'gateway_status_failed',
                'message' => 'Failed to retrieve gateway status.',
            ], 500);
        }
    }

    /**
     * Get gateway recommendations based on criteria.
     */
    public function getGatewayRecommendations(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'required|string|size:3',
                'payment_method' => 'required|string',
                'country' => 'nullable|string|size:2',
                'customer_id' => 'nullable|integer',
                'priority_factors' => 'nullable|array', // ['cost', 'speed', 'reliability']
            ]);

            $recommendations = $this->calculateGatewayRecommendations($validated);

            return response()->json([
                'success' => true,
                'data' => [
                    'recommendations' => $recommendations,
                    'criteria' => $validated,
                    'recommendation_timestamp' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate gateway recommendations', [
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'recommendation_failed',
                'message' => 'Failed to generate gateway recommendations.',
            ], 500);
        }
    }

    /**
     * Update gateway health status (internal use).
     */
    public function updateGatewayHealth(Request $request, string $gateway): JsonResponse
    {
        try {
            $validated = $request->validate([
                'health_status' => 'required|string|in:healthy,degraded,unhealthy,unknown',
                'success_rate' => 'nullable|numeric|min:0|max:100',
                'avg_response_time_ms' => 'nullable|integer|min:0',
                'health_metrics' => 'nullable|array',
                'error_details' => 'nullable|string',
            ]);

            $paymentGateway = PaymentGateway::where('name', $gateway)->first();

            if (!$paymentGateway) {
                return response()->json([
                    'success' => false,
                    'error' => 'gateway_not_found',
                    'message' => "Payment gateway '{$gateway}' not found.",
                ], 404);
            }

            $updateData = [
                'health_status' => $validated['health_status'],
                'last_health_check' => now(),
            ];

            if (isset($validated['success_rate'])) {
                $updateData['success_rate'] = $validated['success_rate'];
            }

            if (isset($validated['avg_response_time_ms'])) {
                $updateData['avg_response_time_ms'] = $validated['avg_response_time_ms'];
            }

            if (isset($validated['health_metrics'])) {
                $updateData['health_metrics'] = $validated['health_metrics'];
            }

            $paymentGateway->update($updateData);

            // Clear cache for this gateway
            Cache::forget("gateway_status_{$gateway}");

            return response()->json([
                'success' => true,
                'data' => [
                    'gateway_name' => $paymentGateway->name,
                    'health_status' => $paymentGateway->health_status,
                    'updated_at' => $paymentGateway->updated_at,
                ],
                'message' => 'Gateway health status updated successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update gateway health', [
                'gateway' => $gateway,
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'health_update_failed',
                'message' => 'Failed to update gateway health status.',
            ], 500);
        }
    }

    /**
     * Check if gateway is available for processing.
     */
    private function isGatewayAvailable(PaymentGateway $gateway): bool
    {
        // Check basic status
        if (!$gateway->is_enabled || $gateway->status !== 'active') {
            return false;
        }

        // Check health status
        if (in_array($gateway->health_status, ['unhealthy'])) {
            return false;
        }

        // Check maintenance window
        if ($gateway->status === 'maintenance') {
            $now = now();
            if ($gateway->maintenance_start && $gateway->maintenance_end) {
                return !($now->between($gateway->maintenance_start, $gateway->maintenance_end));
            }
            return false;
        }

        // Check rate limits
        if ($this->isRateLimited($gateway)) {
            return false;
        }

        return true;
    }

    /**
     * Check if gateway is rate limited.
     */
    private function isRateLimited(PaymentGateway $gateway): bool
    {
        $now = now();

        // Check if rate limit reset time has passed
        if ($gateway->rate_limit_reset_at && $now->gt($gateway->rate_limit_reset_at)) {
            // Reset usage count
            $gateway->update([
                'current_usage_count' => 0,
                'rate_limit_reset_at' => $now->addHour(),
            ]);
            return false;
        }

        // Check daily limit
        if ($gateway->rate_limit_per_day && $gateway->current_usage_count >= $gateway->rate_limit_per_day) {
            return true;
        }

        // Additional rate limit checks can be added here for per-minute and per-hour limits

        return false;
    }

    /**
     * Determine if health check should be performed.
     */
    private function shouldPerformHealthCheck(PaymentGateway $gateway): bool
    {
        if (!$gateway->last_health_check) {
            return true;
        }

        $timeSinceLastCheck = now()->diffInMinutes($gateway->last_health_check);
        
        // Perform health check every 5 minutes for unhealthy gateways
        if ($gateway->health_status === 'unhealthy' && $timeSinceLastCheck >= 5) {
            return true;
        }

        // Perform health check every 15 minutes for degraded gateways
        if ($gateway->health_status === 'degraded' && $timeSinceLastCheck >= 15) {
            return true;
        }

        // Perform health check every 30 minutes for healthy gateways
        if ($gateway->health_status === 'healthy' && $timeSinceLastCheck >= 30) {
            return true;
        }

        return false;
    }

    /**
     * Perform health check on gateway.
     */
    private function performHealthCheck(PaymentGateway $gateway): void
    {
        try {
            $startTime = microtime(true);
            $healthStatus = 'healthy';
            $healthMetrics = [];

            // Perform basic connectivity check based on gateway type
            switch ($gateway->name) {
                case 'stripe':
                    $healthStatus = $this->checkStripeHealth();
                    break;
                case 'paypal':
                    $healthStatus = $this->checkPayPalHealth();
                    break;
                case 'mada':
                    $healthStatus = $this->checkMadaHealth();
                    break;
                case 'stc_pay':
                    $healthStatus = $this->checkStcPayHealth();
                    break;
                default:
                    $healthStatus = 'unknown';
            }

            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            $healthMetrics = [
                'response_time_ms' => round($responseTime, 2),
                'check_timestamp' => now()->toISOString(),
                'check_type' => 'connectivity',
            ];

            $gateway->update([
                'health_status' => $healthStatus,
                'last_health_check' => now(),
                'avg_response_time_ms' => round($responseTime),
                'health_metrics' => $healthMetrics,
            ]);

        } catch (\Exception $e) {
            Log::error('Health check failed for gateway', [
                'gateway' => $gateway->name,
                'error' => $e->getMessage(),
            ]);

            $gateway->update([
                'health_status' => 'unhealthy',
                'last_health_check' => now(),
                'health_metrics' => [
                    'error' => $e->getMessage(),
                    'check_timestamp' => now()->toISOString(),
                ],
            ]);
        }
    }

    /**
     * Check Stripe health.
     */
    private function checkStripeHealth(): string
    {
        // Implement Stripe health check
        // This would typically involve making a simple API call to Stripe
        return 'healthy'; // Simplified for now
    }

    /**
     * Check PayPal health.
     */
    private function checkPayPalHealth(): string
    {
        // Implement PayPal health check
        return 'healthy'; // Simplified for now
    }

    /**
     * Check Mada health.
     */
    private function checkMadaHealth(): string
    {
        // Implement Mada health check
        return 'healthy'; // Simplified for now
    }

    /**
     * Check STC Pay health.
     */
    private function checkStcPayHealth(): string
    {
        // Implement STC Pay health check
        return 'healthy'; // Simplified for now
    }

    /**
     * Calculate gateway recommendations based on criteria.
     */
    private function calculateGatewayRecommendations(array $criteria): array
    {
        $gateways = PaymentGateway::where('is_enabled', true)
            ->where('status', 'active')
            ->whereJsonContains('supported_payment_methods', $criteria['payment_method'])
            ->whereJsonContains('supported_currencies', $criteria['currency'])
            ->get();

        $recommendations = [];

        foreach ($gateways as $gateway) {
            if (!$this->isGatewayAvailable($gateway)) {
                continue;
            }

            // Check amount limits
            if ($criteria['amount'] < $gateway->minimum_amount) {
                continue;
            }

            if ($gateway->maximum_amount && $criteria['amount'] > $gateway->maximum_amount) {
                continue;
            }

            // Calculate total cost
            $totalCost = ($criteria['amount'] * $gateway->fee_percentage) + $gateway->fee_fixed;

            // Calculate recommendation score
            $score = $this->calculateRecommendationScore($gateway, $criteria, $totalCost);

            $recommendations[] = [
                'gateway_name' => $gateway->name,
                'display_name' => $gateway->display_name,
                'recommendation_score' => $score,
                'total_cost' => $totalCost,
                'fee_percentage' => $gateway->fee_percentage,
                'fee_fixed' => $gateway->fee_fixed,
                'success_rate' => $gateway->success_rate,
                'avg_response_time_ms' => $gateway->avg_response_time_ms,
                'health_status' => $gateway->health_status,
                'supports_3ds' => $gateway->supports_3ds,
                'supports_refunds' => $gateway->supports_refunds,
                'priority' => $gateway->priority,
            ];
        }

        // Sort by recommendation score (highest first)
        usort($recommendations, function ($a, $b) {
            return $b['recommendation_score'] <=> $a['recommendation_score'];
        });

        return array_slice($recommendations, 0, 5); // Return top 5 recommendations
    }

    /**
     * Calculate recommendation score for a gateway.
     */
    private function calculateRecommendationScore(PaymentGateway $gateway, array $criteria, float $totalCost): float
    {
        $score = 100; // Start with perfect score

        // Factor in success rate (higher is better)
        if ($gateway->success_rate) {
            $score *= ($gateway->success_rate / 100);
        }

        // Factor in response time (lower is better)
        if ($gateway->avg_response_time_ms) {
            $responseTimeFactor = max(0.5, 1 - ($gateway->avg_response_time_ms / 5000)); // Penalize if > 5 seconds
            $score *= $responseTimeFactor;
        }

        // Factor in cost (lower is better)
        $costFactor = max(0.5, 1 - ($totalCost / $criteria['amount'])); // Penalize high fees
        $score *= $costFactor;

        // Factor in health status
        $healthMultiplier = match ($gateway->health_status) {
            'healthy' => 1.0,
            'degraded' => 0.8,
            'unhealthy' => 0.3,
            default => 0.5,
        };
        $score *= $healthMultiplier;

        // Factor in priority (lower number = higher priority)
        $priorityFactor = max(0.5, 1 - ($gateway->priority / 1000));
        $score *= $priorityFactor;

        return round($score, 2);
    }
}
