<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;

/**
 * Activity Log Controller
 * 
 * Handles activity log viewing and management
 * for the authentication service.
 */
class ActivityController extends Controller
{
    /**
     * Display a listing of activities
     */
    public function index(Request $request): JsonResponse
    {
        $query = Activity::query();

        // Apply filters
        if ($request->has('log_name')) {
            $query->where('log_name', $request->get('log_name'));
        }

        if ($request->has('subject_type')) {
            $query->where('subject_type', $request->get('subject_type'));
        }

        if ($request->has('causer_id')) {
            $query->where('causer_id', $request->get('causer_id'));
        }

        if ($request->has('description')) {
            $query->where('description', 'like', '%' . $request->get('description') . '%');
        }

        // Date range filter
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->get('from_date'));
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->get('to_date'));
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $activities = $query->with(['subject', 'causer'])
                           ->latest()
                           ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $activities,
        ]);
    }

    /**
     * Display the specified activity
     */
    public function show(Activity $activity): JsonResponse
    {
        $activity->load(['subject', 'causer']);

        return response()->json([
            'status' => 'success',
            'data' => $activity,
        ]);
    }

    /**
     * Get activities for a specific user
     */
    public function getUserActivities(Request $request, int $userId): JsonResponse
    {
        $query = Activity::where('causer_id', $userId);

        // Apply additional filters
        if ($request->has('log_name')) {
            $query->where('log_name', $request->get('log_name'));
        }

        if ($request->has('description')) {
            $query->where('description', 'like', '%' . $request->get('description') . '%');
        }

        // Date range filter
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->get('from_date'));
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->get('to_date'));
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $activities = $query->with(['subject', 'causer'])
                           ->latest()
                           ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $activities,
        ]);
    }
}
