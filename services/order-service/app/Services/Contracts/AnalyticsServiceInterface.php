<?php

namespace App\Services\Contracts;

use App\Models\Order;
use App\Models\User;

/**
 * Analytics Service Interface
 * 
 * Defines the contract for analytics service implementations
 * Handles event tracking and metrics collection
 */
interface AnalyticsServiceInterface
{
    /**
     * Track order event
     */
    public function trackOrderEvent(Order $order, string $event, array $data = []): void;

    /**
     * Track user event
     */
    public function trackUserEvent(User $user, string $event, array $data = []): void;

    /**
     * Record business metric
     */
    public function recordMetric(string $metric, float $value, array $tags = []): void;

    /**
     * Get order analytics
     */
    public function getOrderAnalytics(array $filters = []): array;

    /**
     * Get user behavior analytics
     */
    public function getUserBehaviorAnalytics(int $userId, array $dateRange = []): array;

    /**
     * Get business metrics
     */
    public function getBusinessMetrics(array $metrics, array $dateRange = []): array;

    /**
     * Generate analytics report
     */
    public function generateReport(string $reportType, array $parameters = []): array;
}
