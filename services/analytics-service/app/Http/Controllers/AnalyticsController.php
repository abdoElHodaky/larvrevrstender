<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Track user event
     */
    public function trackEvent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'event_type' => 'required|string|max:100',
            'event_data' => 'nullable|array',
            'session_id' => 'nullable|string|max:255',
            'ip_address' => 'nullable|ip',
            'user_agent' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $event = $this->analyticsService->trackEvent(
                $request->user_id,
                $request->event_type,
                $request->event_data ?? [],
                $request->session_id,
                $request->ip_address ?? $request->ip(),
                $request->user_agent ?? $request->userAgent()
            );

            return response()->json([
                'success' => true,
                'message' => 'Event tracked successfully',
                'data' => $event
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to track event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user analytics
     */
    public function getUserAnalytics(Request $request, int $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'event_types' => 'nullable|array',
            'event_types.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->subDays(30);
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now();

            $analytics = $this->analyticsService->getUserAnalytics(
                $userId,
                $startDate,
                $endDate,
                $request->event_types
            );

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get business metrics
     */
    public function getBusinessMetrics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'metric_type' => 'nullable|string|in:orders,bids,revenue,users,conversion',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'group_by' => 'nullable|string|in:day,week,month'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->subDays(30);
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now();

            $metrics = $this->analyticsService->getBusinessMetrics(
                $request->metric_type,
                $startDate,
                $endDate,
                $request->group_by ?? 'day'
            );

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get business metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard overview
     */
    public function getDashboardOverview(Request $request): JsonResponse
    {
        try {
            $overview = $this->analyticsService->getDashboardOverview();

            return response()->json([
                'success' => true,
                'data' => $overview
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get dashboard overview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get conversion funnel
     */
    public function getConversionFunnel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_type' => 'nullable|string|in:customer,merchant'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->subDays(30);
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now();

            $funnel = $this->analyticsService->getConversionFunnel(
                $startDate,
                $endDate,
                $request->user_type
            );

            return response()->json([
                'success' => true,
                'data' => $funnel
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get conversion funnel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get real-time metrics
     */
    public function getRealTimeMetrics(): JsonResponse
    {
        try {
            $metrics = $this->analyticsService->getRealTimeMetrics();

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get real-time metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate custom report
     */
    public function generateReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|string|in:user_behavior,business_performance,revenue_analysis,conversion_report',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'filters' => 'nullable|array',
            'format' => 'nullable|string|in:json,csv,pdf'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $report = $this->analyticsService->generateReport(
                $request->report_type,
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date),
                $request->filters ?? [],
                $request->format ?? 'json'
            );

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

