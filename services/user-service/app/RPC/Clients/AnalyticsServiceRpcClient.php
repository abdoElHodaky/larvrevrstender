<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * Analytics Service RPC Client for User Service
 *
 * Handles RPC communication with the Analytics Service for user-related
 * event tracking, metrics collection, behavior analysis, and
 * business intelligence operations.
 *
 * This client provides comprehensive analytics operations needed for
 * user management workflows including activity tracking, metrics collection,
 * and user behavior analysis.
 */
class AnalyticsServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('analytics-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }

    /**
     * Track user registration event
     *
     * @param int $userId User ID
     * @param array $registrationData Registration details
     * @return array Event tracking result
     */
    public function trackUserRegistration(int $userId, array $registrationData): array
    {
        return $this->call('analytics.track_user_registration', [
            'user_id' => $userId,
            'registration_data' => $registrationData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track user login event
     *
     * @param int $userId User ID
     * @param array $loginData Login details
     * @return array Event tracking result
     */
    public function trackUserLogin(int $userId, array $loginData): array
    {
        return $this->call('analytics.track_user_login', [
            'user_id' => $userId,
            'login_data' => $loginData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track user profile update event
     *
     * @param int $userId User ID
     * @param array $updateData Profile update details
     * @return array Event tracking result
     */
    public function trackUserProfileUpdate(int $userId, array $updateData): array
    {
        return $this->call('analytics.track_user_profile_update', [
            'user_id' => $userId,
            'update_data' => $updateData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track user activity event
     *
     * @param int $userId User ID
     * @param string $activity Activity type
     * @param array $activityData Activity details
     * @return array Event tracking result
     */
    public function trackUserActivity(int $userId, string $activity, array $activityData = []): array
    {
        return $this->call('analytics.track_user_activity', [
            'user_id' => $userId,
            'activity' => $activity,
            'activity_data' => $activityData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track KYC verification event
     *
     * @param int $userId User ID
     * @param string $kycStatus KYC status
     * @param array $kycData KYC details
     * @return array Event tracking result
     */
    public function trackKycVerification(int $userId, string $kycStatus, array $kycData): array
    {
        return $this->call('analytics.track_kyc_verification', [
            'user_id' => $userId,
            'kyc_status' => $kycStatus,
            'kyc_data' => $kycData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Track vehicle addition event
     *
     * @param int $userId User ID
     * @param int $vehicleId Vehicle ID
     * @param array $vehicleData Vehicle details
     * @return array Event tracking result
     */
    public function trackVehicleAddition(int $userId, int $vehicleId, array $vehicleData): array
    {
        return $this->call('analytics.track_vehicle_addition', [
            'user_id' => $userId,
            'vehicle_id' => $vehicleId,
            'vehicle_data' => $vehicleData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get user analytics data
     *
     * @param int $userId User ID
     * @param array $filters Analytics filters
     * @return array User analytics data
     */
    public function getUserAnalytics(int $userId, array $filters = []): array
    {
        return $this->call('analytics.get_user_analytics', [
            'user_id' => $userId,
            'filters' => $filters,
        ]);
    }

    /**
     * Get user behavior analytics
     *
     * @param int $userId User ID
     * @param array $dateRange Date range filter
     * @return array User behavior analytics
     */
    public function getUserBehaviorAnalytics(int $userId, array $dateRange = []): array
    {
        return $this->call('analytics.get_user_behavior_analytics', [
            'user_id' => $userId,
            'date_range' => $dateRange,
        ]);
    }

    /**
     * Get user engagement metrics
     *
     * @param array $userIds Array of user IDs
     * @param array $dateRange Date range filter
     * @return array User engagement metrics
     */
    public function getUserEngagementMetrics(array $userIds = [], array $dateRange = []): array
    {
        return $this->call('analytics.get_user_engagement_metrics', [
            'user_ids' => $userIds,
            'date_range' => $dateRange,
        ]);
    }

    /**
     * Track user session event
     *
     * @param int $userId User ID
     * @param string $sessionId Session ID
     * @param array $sessionData Session details
     * @return array Event tracking result
     */
    public function trackUserSession(int $userId, string $sessionId, array $sessionData): array
    {
        return $this->call('analytics.track_user_session', [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'session_data' => $sessionData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get user retention analytics
     *
     * @param array $cohortFilters Cohort analysis filters
     * @return array User retention analytics
     */
    public function getUserRetentionAnalytics(array $cohortFilters = []): array
    {
        return $this->call('analytics.get_user_retention_analytics', [
            'cohort_filters' => $cohortFilters,
        ]);
    }

    /**
     * Track user conversion event
     *
     * @param int $userId User ID
     * @param string $conversionType Conversion type
     * @param array $conversionData Conversion details
     * @return array Event tracking result
     */
    public function trackUserConversion(int $userId, string $conversionType, array $conversionData): array
    {
        return $this->call('analytics.track_user_conversion', [
            'user_id' => $userId,
            'conversion_type' => $conversionType,
            'conversion_data' => $conversionData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get user conversion funnel analytics
     *
     * @param string $funnelType Funnel type
     * @param array $filters Funnel filters
     * @return array Conversion funnel analytics
     */
    public function getUserConversionFunnelAnalytics(string $funnelType, array $filters = []): array
    {
        return $this->call('analytics.get_user_conversion_funnel_analytics', [
            'funnel_type' => $funnelType,
            'filters' => $filters,
        ]);
    }

    /**
     * Track user error event
     *
     * @param int $userId User ID
     * @param string $errorType Error type
     * @param array $errorData Error details
     * @return array Event tracking result
     */
    public function trackUserError(int $userId, string $errorType, array $errorData): array
    {
        return $this->call('analytics.track_user_error', [
            'user_id' => $userId,
            'error_type' => $errorType,
            'error_data' => $errorData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get user segmentation analytics
     *
     * @param array $segmentationCriteria Segmentation criteria
     * @return array User segmentation analytics
     */
    public function getUserSegmentationAnalytics(array $segmentationCriteria): array
    {
        return $this->call('analytics.get_user_segmentation_analytics', [
            'segmentation_criteria' => $segmentationCriteria,
        ]);
    }

    /**
     * Track user feature usage
     *
     * @param int $userId User ID
     * @param string $feature Feature name
     * @param array $usageData Usage details
     * @return array Event tracking result
     */
    public function trackUserFeatureUsage(int $userId, string $feature, array $usageData): array
    {
        return $this->call('analytics.track_user_feature_usage', [
            'user_id' => $userId,
            'feature' => $feature,
            'usage_data' => $usageData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get feature adoption analytics
     *
     * @param array $features Array of features to analyze
     * @param array $dateRange Date range filter
     * @return array Feature adoption analytics
     */
    public function getFeatureAdoptionAnalytics(array $features, array $dateRange = []): array
    {
        return $this->call('analytics.get_feature_adoption_analytics', [
            'features' => $features,
            'date_range' => $dateRange,
        ]);
    }

    /**
     * Generate user analytics report
     *
     * @param string $reportType Report type
     * @param array $parameters Report parameters
     * @return array Report generation result
     */
    public function generateUserAnalyticsReport(string $reportType, array $parameters = []): array
    {
        return $this->call('analytics.generate_user_analytics_report', [
            'report_type' => $reportType,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Track user satisfaction survey
     *
     * @param int $userId User ID
     * @param array $surveyData Survey responses
     * @return array Event tracking result
     */
    public function trackUserSatisfactionSurvey(int $userId, array $surveyData): array
    {
        return $this->call('analytics.track_user_satisfaction_survey', [
            'user_id' => $userId,
            'survey_data' => $surveyData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get user lifetime value analytics
     *
     * @param array $userIds Array of user IDs
     * @return array User lifetime value analytics
     */
    public function getUserLifetimeValueAnalytics(array $userIds = []): array
    {
        return $this->call('analytics.get_user_lifetime_value_analytics', [
            'user_ids' => $userIds,
        ]);
    }

    /**
     * Track batch user events
     *
     * @param array $userEvents Array of user events
     * @return array Batch event tracking results
     */
    public function trackBatchUserEvents(array $userEvents): array
    {
        $calls = [];
        foreach ($userEvents as $index => $event) {
            $calls[] = [
                'method' => 'analytics.track_user_activity',
                'params' => $event,
                'id' => "track_user_event_{$index}",
            ];
        }

        return $this->batchCall($calls);
    }

    /**
     * Get user analytics dashboard data
     *
     * @param array $dateRange Date range filter
     * @param array $widgets Dashboard widgets to include
     * @return array Dashboard data
     */
    public function getUserAnalyticsDashboard(array $dateRange = [], array $widgets = []): array
    {
        return $this->call('analytics.get_user_analytics_dashboard', [
            'date_range' => $dateRange,
            'widgets' => $widgets,
        ]);
    }
}
