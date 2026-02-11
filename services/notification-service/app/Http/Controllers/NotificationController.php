<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Send a single notification
     */
    public function sendNotification(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer',
                'type' => 'required|string',
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'data' => 'nullable|array',
                'channels' => 'nullable|array',
                'priority' => 'nullable|in:low,normal,high,urgent',
                'scheduled_at' => 'nullable|date|after:now',
            ]);

            $notification = $this->notificationService->sendNotification(
                $validated['user_id'],
                $validated['type'],
                $validated['title'],
                $validated['message'],
                $validated['data'] ?? [],
                $validated['channels'] ?? ['database'],
                $validated['priority'] ?? 'normal',
                $validated['scheduled_at'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully',
                'data' => [
                    'notification_id' => $notification->id,
                    'status' => $notification->status,
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send bulk notifications
     */
    public function sendBulkNotification(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_ids' => 'required|array',
                'user_ids.*' => 'integer',
                'type' => 'required|string',
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'data' => 'nullable|array',
                'channels' => 'nullable|array',
                'priority' => 'nullable|in:low,normal,high,urgent',
                'scheduled_at' => 'nullable|date|after:now',
            ]);

            $results = $this->notificationService->sendBulkNotification(
                $validated['user_ids'],
                $validated['type'],
                $validated['title'],
                $validated['message'],
                $validated['data'] ?? [],
                $validated['channels'] ?? ['database'],
                $validated['priority'] ?? 'normal',
                $validated['scheduled_at'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk notifications processed',
                'data' => [
                    'total_sent' => count($results['successful']),
                    'total_failed' => count($results['failed']),
                    'successful' => $results['successful'],
                    'failed' => $results['failed'],
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification status
     */
    public function getNotificationStatus(string $notificationId): JsonResponse
    {
        try {
            $notification = Notification::findOrFail($notificationId);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $notification->id,
                    'status' => $notification->status,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'sent_at' => $notification->sent_at,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get user notifications (paginated)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = $request->get('per_page', 15);
            $type = $request->get('type');
            $status = $request->get('status');

            $query = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            if ($type) {
                $query->where('type', $type);
            }

            if ($status) {
                if ($status === 'read') {
                    $query->whereNotNull('read_at');
                } elseif ($status === 'unread') {
                    $query->whereNull('read_at');
                }
            }

            $notifications = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show specific notification
     */
    public function show(Request $request, Notification $notification): JsonResponse
    {
        try {
            // Ensure user can only see their own notifications
            if ($notification->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $notification
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        try {
            // Ensure user can only mark their own notifications as read
            if ($notification->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => $notification
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete notification
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        try {
            // Ensure user can only delete their own notifications
            if ($notification->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for the authenticated user
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $updated = Notification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'data' => [
                    'updated_count' => $updated
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
