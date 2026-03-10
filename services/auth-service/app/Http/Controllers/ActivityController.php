<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    /**
     * Display a listing of activities.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Activity::query();

        // Apply filters
        if ($request->has('log_name')) {
            $query->where('log_name', $request->get('log_name'));
        }

        if ($request->has('description')) {
            $query->where('description', 'like', '%' . $request->get('description') . '%');
        }

        if ($request->has('subject_type')) {
            $query->where('subject_type', $request->get('subject_type'));
        }

        if ($request->has('subject_id')) {
            $query->where('subject_id', $request->get('subject_id'));
        }

        if ($request->has('causer_id')) {
            $query->where('causer_id', $request->get('causer_id'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        // Order by latest first
        $query->latest();

        // Include relationships
        $query->with(['subject', 'causer']);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $activities = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $activities,
            'message' => 'Activities retrieved successfully'
        ]);
    }

    /**
     * Display the specified activity.
     */
    public function show(Activity $activity): JsonResponse
    {
        $activity->load(['subject', 'causer']);

        return response()->json([
            'success' => true,
            'data' => $activity,
            'message' => 'Activity retrieved successfully'
        ]);
    }

    /**
     * Get activities for a specific user.
     */
    public function getUserActivities(Request $request, int $userId): JsonResponse
    {
        $query = Activity::query();

        // Filter by user as causer (activities performed by the user)
        if ($request->get('type') === 'performed') {
            $query->where('causer_id', $userId);
        }
        // Filter by user as subject (activities performed on the user)
        elseif ($request->get('type') === 'received') {
            $query->where('subject_type', 'App\\Models\\User')
                  ->where('subject_id', $userId);
        }
        // Default: both performed and received
        else {
            $query->where(function ($q) use ($userId) {
                $q->where('causer_id', $userId)
                  ->orWhere(function ($subQ) use ($userId) {
                      $subQ->where('subject_type', 'App\\Models\\User')
                           ->where('subject_id', $userId);
                  });
            });
        }

        // Apply additional filters
        if ($request->has('log_name')) {
            $query->where('log_name', $request->get('log_name'));
        }

        if ($request->has('description')) {
            $query->where('description', 'like', '%' . $request->get('description') . '%');
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        // Order by latest first
        $query->latest();

        // Include relationships
        $query->with(['subject', 'causer']);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $activities = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $activities,
            'message' => 'User activities retrieved successfully'
        ]);
    }

    /**
     * Get activity statistics.
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        $query = Activity::whereBetween('created_at', [$dateFrom, $dateTo]);

        $statistics = [
            'total_activities' => $query->count(),
            'activities_by_log_name' => $query->groupBy('log_name')
                                            ->selectRaw('log_name, count(*) as count')
                                            ->pluck('count', 'log_name'),
            'activities_by_day' => $query->selectRaw('DATE(created_at) as date, count(*) as count')
                                        ->groupBy('date')
                                        ->orderBy('date')
                                        ->pluck('count', 'date'),
            'top_causers' => $query->whereNotNull('causer_id')
                                  ->with('causer:id,name,email')
                                  ->get()
                                  ->groupBy('causer_id')
                                  ->map(function ($activities) {
                                      return [
                                          'user' => $activities->first()->causer,
                                          'count' => $activities->count()
                                      ];
                                  })
                                  ->sortByDesc('count')
                                  ->take(10)
                                  ->values(),
            'recent_activities' => $query->with(['subject', 'causer'])
                                        ->latest()
                                        ->limit(10)
                                        ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $statistics,
            'message' => 'Activity statistics retrieved successfully'
        ]);
    }
}
