<?php

namespace App\Http\Controllers;

use App\Services\TransactionService;
use App\Services\PaymentService;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AnalyticsController extends Controller
{
    public function __construct(
        private TransactionService $transactionService,
        private PaymentService $paymentService
    ) {}

    /**
     * Get payment analytics dashboard data.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'customer_id' => 'sometimes|integer|min:1',
            'merchant_id' => 'sometimes|integer|min:1',
            'provider' => 'sometimes|string|in:stripe,paypal,mada,stc_pay',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = new \DateTime($request->start_date);
            $endDate = new \DateTime($request->end_date);
            $filters = $request->only(['customer_id', 'merchant_id', 'provider']);

            // Cache key for dashboard data
            $cacheKey = 'analytics_dashboard_' . md5(serialize([
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'filters' => $filters,
            ]));

            $dashboardData = Cache::remember($cacheKey, 300, function () use ($startDate, $endDate, $filters) {
                return [
                    'overview' => $this->getOverviewMetrics($startDate, $endDate, $filters),
                    'transactions' => $this->transactionService->getTransactionAnalytics($startDate, $endDate, $filters),
                    'payments' => $this->getPaymentAnalytics($startDate, $endDate, $filters),
                    'gateways' => $this->getGatewayAnalytics($startDate, $endDate, $filters),
                    'trends' => $this->getTrendAnalytics($startDate, $endDate, $filters),
                    'top_customers' => $this->getTopCustomers($startDate, $endDate, $filters),
                    'recent_activity' => $this->getRecentActivity($filters),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $dashboardData,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate analytics dashboard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get transaction trends.
     */
    public function transactionTrends(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'interval' => 'sometimes|string|in:hour,day,week,month',
            'provider' => 'sometimes|string|in:stripe,paypal,mada,stc_pay',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = new \DateTime($request->start_date);
            $endDate = new \DateTime($request->end_date);
            $interval = $request->get('interval', 'day');

            $trends = $this->transactionService->getTransactionTrends($startDate, $endDate, $interval);

            return response()->json([
                'success' => true,
                'data' => $trends,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get transaction trends',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer analytics.
     */
    public function customerAnalytics(Request $request, int $customerId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = $request->start_date ? new \DateTime($request->start_date) : null;
            $endDate = $request->end_date ? new \DateTime($request->end_date) : null;

            $analytics = [
                'customer_id' => $customerId,
                'summary' => $this->transactionService->getCustomerTransactionSummary($customerId, $startDate, $endDate),
                'payment_methods' => $this->getCustomerPaymentMethodAnalytics($customerId),
                'spending_patterns' => $this->getCustomerSpendingPatterns($customerId, $startDate, $endDate),
                'recent_transactions' => $this->getCustomerRecentTransactions($customerId, 20),
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get customer analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get merchant analytics.
     */
    public function merchantAnalytics(Request $request, int $merchantId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = $request->start_date ? new \DateTime($request->start_date) : null;
            $endDate = $request->end_date ? new \DateTime($request->end_date) : null;

            $analytics = [
                'merchant_id' => $merchantId,
                'summary' => $this->transactionService->getMerchantTransactionSummary($merchantId, $startDate, $endDate),
                'revenue_trends' => $this->getMerchantRevenueTrends($merchantId, $startDate, $endDate),
                'top_customers' => $this->getMerchantTopCustomers($merchantId, $startDate, $endDate),
                'payment_method_breakdown' => $this->getMerchantPaymentMethodBreakdown($merchantId, $startDate, $endDate),
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get merchant analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get gateway performance analytics.
     */
    public function gatewayPerformance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = new \DateTime($request->start_date);
            $endDate = new \DateTime($request->end_date);

            $performance = $this->getGatewayPerformanceMetrics($startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $performance,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get gateway performance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get webhook analytics.
     */
    public function webhookAnalytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'provider' => 'sometimes|string|in:stripe,paypal,razorpay,square',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = new \DateTime($request->start_date);
            $endDate = new \DateTime($request->end_date);
            $provider = $request->get('provider');

            $analytics = $this->getWebhookAnalytics($startDate, $endDate, $provider);

            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get webhook analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get overview metrics.
     */
    private function getOverviewMetrics(\DateTime $startDate, \DateTime $endDate, array $filters): array
    {
        $query = Transaction::byDateRange($startDate, $endDate);

        if (isset($filters['customer_id'])) {
            $query->byCustomer($filters['customer_id']);
        }

        if (isset($filters['merchant_id'])) {
            $query->byMerchant($filters['merchant_id']);
        }

        if (isset($filters['provider'])) {
            $query->byProvider($filters['provider']);
        }

        $totalTransactions = $query->count();
        $totalRevenue = (clone $query)->revenue()->completed()->sum('amount');
        $totalCustomers = (clone $query)->distinct('customer_id')->count('customer_id');
        $avgTransactionValue = $totalTransactions > 0 ? (clone $query)->completed()->avg('amount') : 0;

        return [
            'total_transactions' => $totalTransactions,
            'total_revenue' => round($totalRevenue, 2),
            'total_customers' => $totalCustomers,
            'avg_transaction_value' => round($avgTransactionValue, 2),
        ];
    }

    /**
     * Get payment analytics.
     */
    private function getPaymentAnalytics(\DateTime $startDate, \DateTime $endDate, array $filters): array
    {
        $query = Payment::whereBetween('created_at', [$startDate, $endDate]);

        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['provider'])) {
            $query->where('payment_provider', $filters['provider']);
        }

        $totalPayments = $query->count();
        $successfulPayments = (clone $query)->where('status', 'completed')->count();
        $failedPayments = (clone $query)->where('status', 'failed')->count();
        $successRate = $totalPayments > 0 ? ($successfulPayments / $totalPayments) * 100 : 0;

        return [
            'total_payments' => $totalPayments,
            'successful_payments' => $successfulPayments,
            'failed_payments' => $failedPayments,
            'success_rate' => round($successRate, 2),
        ];
    }

    /**
     * Get gateway analytics.
     */
    private function getGatewayAnalytics(\DateTime $startDate, \DateTime $endDate, array $filters): array
    {
        $query = Transaction::byDateRange($startDate, $endDate)->completed();

        if (isset($filters['customer_id'])) {
            $query->byCustomer($filters['customer_id']);
        }

        if (isset($filters['merchant_id'])) {
            $query->byMerchant($filters['merchant_id']);
        }

        $gatewayStats = $query->selectRaw('payment_provider, COUNT(*) as transaction_count, SUM(amount) as total_amount, AVG(amount) as avg_amount')
                             ->groupBy('payment_provider')
                             ->get()
                             ->keyBy('payment_provider');

        return $gatewayStats->toArray();
    }

    /**
     * Get trend analytics.
     */
    private function getTrendAnalytics(\DateTime $startDate, \DateTime $endDate, array $filters): array
    {
        return $this->transactionService->getTransactionTrends($startDate, $endDate, 'day');
    }

    /**
     * Get top customers.
     */
    private function getTopCustomers(\DateTime $startDate, \DateTime $endDate, array $filters): array
    {
        $query = Transaction::byDateRange($startDate, $endDate)->completed()->revenue();

        if (isset($filters['merchant_id'])) {
            $query->byMerchant($filters['merchant_id']);
        }

        if (isset($filters['provider'])) {
            $query->byProvider($filters['provider']);
        }

        return $query->selectRaw('customer_id, COUNT(*) as transaction_count, SUM(amount) as total_spent')
                    ->groupBy('customer_id')
                    ->orderBy('total_spent', 'desc')
                    ->limit(10)
                    ->get()
                    ->toArray();
    }

    /**
     * Get recent activity.
     */
    private function getRecentActivity(array $filters): array
    {
        $query = Transaction::query();

        if (isset($filters['customer_id'])) {
            $query->byCustomer($filters['customer_id']);
        }

        if (isset($filters['merchant_id'])) {
            $query->byMerchant($filters['merchant_id']);
        }

        if (isset($filters['provider'])) {
            $query->byProvider($filters['provider']);
        }

        return $query->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get()
                    ->toArray();
    }

    /**
     * Get customer payment method analytics.
     */
    private function getCustomerPaymentMethodAnalytics(int $customerId): array
    {
        $paymentMethods = PaymentMethod::byCustomer($customerId)->get();

        $analytics = [
            'total_methods' => $paymentMethods->count(),
            'active_methods' => $paymentMethods->where('status', 'active')->count(),
            'verified_methods' => $paymentMethods->where('is_verified', true)->count(),
            'by_type' => $paymentMethods->groupBy('type')->map->count(),
            'by_provider' => $paymentMethods->groupBy('provider')->map->count(),
        ];

        return $analytics;
    }

    /**
     * Get customer spending patterns.
     */
    private function getCustomerSpendingPatterns(int $customerId, ?\DateTime $startDate, ?\DateTime $endDate): array
    {
        $query = Transaction::byCustomer($customerId)->completed()->revenue();

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        $monthlySpending = $query->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(amount) as total_spent')
                                ->groupBy('year', 'month')
                                ->orderBy('year', 'desc')
                                ->orderBy('month', 'desc')
                                ->limit(12)
                                ->get();

        return $monthlySpending->toArray();
    }

    /**
     * Get customer recent transactions.
     */
    private function getCustomerRecentTransactions(int $customerId, int $limit = 10): array
    {
        return Transaction::byCustomer($customerId)
                         ->orderBy('created_at', 'desc')
                         ->limit($limit)
                         ->get()
                         ->toArray();
    }

    /**
     * Get merchant revenue trends.
     */
    private function getMerchantRevenueTrends(int $merchantId, ?\DateTime $startDate, ?\DateTime $endDate): array
    {
        $query = Transaction::byMerchant($merchantId)->completed()->revenue();

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        return $query->selectRaw('DATE(created_at) as date, SUM(amount) as revenue')
                    ->groupBy('date')
                    ->orderBy('date', 'desc')
                    ->limit(30)
                    ->get()
                    ->toArray();
    }

    /**
     * Get merchant top customers.
     */
    private function getMerchantTopCustomers(int $merchantId, ?\DateTime $startDate, ?\DateTime $endDate): array
    {
        $query = Transaction::byMerchant($merchantId)->completed()->revenue();

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        return $query->selectRaw('customer_id, COUNT(*) as transaction_count, SUM(amount) as total_spent')
                    ->groupBy('customer_id')
                    ->orderBy('total_spent', 'desc')
                    ->limit(10)
                    ->get()
                    ->toArray();
    }

    /**
     * Get merchant payment method breakdown.
     */
    private function getMerchantPaymentMethodBreakdown(int $merchantId, ?\DateTime $startDate, ?\DateTime $endDate): array
    {
        $query = Payment::where('merchant_id', $merchantId)->where('status', 'completed');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        return $query->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total_amount')
                    ->groupBy('payment_method')
                    ->get()
                    ->toArray();
    }

    /**
     * Get gateway performance metrics.
     */
    private function getGatewayPerformanceMetrics(\DateTime $startDate, \DateTime $endDate): array
    {
        $payments = Payment::whereBetween('created_at', [$startDate, $endDate])->get();

        $performance = [];
        foreach ($payments->groupBy('payment_provider') as $provider => $providerPayments) {
            $total = $providerPayments->count();
            $successful = $providerPayments->where('status', 'completed')->count();
            $failed = $providerPayments->where('status', 'failed')->count();
            $avgProcessingTime = $providerPayments->where('processed_at', '!=', null)->avg(function ($payment) {
                return $payment->processed_at ? $payment->processed_at->diffInSeconds($payment->created_at) : 0;
            });

            $performance[$provider] = [
                'total_payments' => $total,
                'successful_payments' => $successful,
                'failed_payments' => $failed,
                'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
                'avg_processing_time_seconds' => round($avgProcessingTime, 2),
            ];
        }

        return $performance;
    }

    /**
     * Get webhook analytics.
     */
    private function getWebhookAnalytics(\DateTime $startDate, \DateTime $endDate, ?string $provider): array
    {
        $query = PaymentWebhook::whereBetween('received_at', [$startDate, $endDate]);

        if ($provider) {
            $query->byProvider($provider);
        }

        $totalWebhooks = $query->count();
        $processedWebhooks = (clone $query)->processed()->count();
        $failedWebhooks = (clone $query)->failed()->count();
        $avgProcessingTime = (clone $query)->whereNotNull('processing_time_ms')->avg('processing_time_ms');

        $webhooksByProvider = (clone $query)->selectRaw('provider, COUNT(*) as count, AVG(processing_time_ms) as avg_processing_time')
                                           ->groupBy('provider')
                                           ->get()
                                           ->keyBy('provider');

        return [
            'total_webhooks' => $totalWebhooks,
            'processed_webhooks' => $processedWebhooks,
            'failed_webhooks' => $failedWebhooks,
            'success_rate' => $totalWebhooks > 0 ? round(($processedWebhooks / $totalWebhooks) * 100, 2) : 0,
            'avg_processing_time_ms' => round($avgProcessingTime, 2),
            'by_provider' => $webhooksByProvider->toArray(),
        ];
    }
}
