<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PreferencesController extends Controller
{
    /**
     * Get user notification preferences
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Mock preferences data - in real implementation, fetch from database
            $preferences = [
                'user_id' => $user->id,
                'email_notifications' => true,
                'sms_notifications' => false,
                'push_notifications' => true,
                'notification_types' => [
                    'order_created' => [
                        'email' => true,
                        'sms' => false,
                        'push' => true,
                        'database' => true,
                    ],
                    'bid_placed' => [
                        'email' => true,
                        'sms' => true,
                        'push' => true,
                        'database' => true,
                    ],
                    'payment_completed' => [
                        'email' => true,
                        'sms' => true,
                        'push' => false,
                        'database' => true,
                    ],
                    'order_completed' => [
                        'email' => true,
                        'sms' => false,
                        'push' => true,
                        'database' => true,
                    ],
                    'system_maintenance' => [
                        'email' => false,
                        'sms' => false,
                        'push' => true,
                        'database' => true,
                    ],
                ],
                'quiet_hours' => [
                    'enabled' => true,
                    'start_time' => '22:00',
                    'end_time' => '08:00',
                    'timezone' => 'Asia/Riyadh',
                ],
                'frequency_limits' => [
                    'max_emails_per_day' => 10,
                    'max_sms_per_day' => 5,
                    'max_push_per_hour' => 3,
                ],
                'updated_at' => now()->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'data' => $preferences
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user notification preferences
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email_notifications' => 'sometimes|boolean',
                'sms_notifications' => 'sometimes|boolean',
                'push_notifications' => 'sometimes|boolean',
                'notification_types' => 'sometimes|array',
                'notification_types.*.email' => 'boolean',
                'notification_types.*.sms' => 'boolean',
                'notification_types.*.push' => 'boolean',
                'notification_types.*.database' => 'boolean',
                'quiet_hours' => 'sometimes|array',
                'quiet_hours.enabled' => 'boolean',
                'quiet_hours.start_time' => 'string|date_format:H:i',
                'quiet_hours.end_time' => 'string|date_format:H:i',
                'quiet_hours.timezone' => 'string',
                'frequency_limits' => 'sometimes|array',
                'frequency_limits.max_emails_per_day' => 'integer|min:0|max:50',
                'frequency_limits.max_sms_per_day' => 'integer|min:0|max:20',
                'frequency_limits.max_push_per_hour' => 'integer|min:0|max:10',
            ]);

            $user = $request->user();

            // Mock updated preferences - in real implementation, save to database
            $updatedPreferences = [
                'user_id' => $user->id,
                'email_notifications' => $validated['email_notifications'] ?? true,
                'sms_notifications' => $validated['sms_notifications'] ?? false,
                'push_notifications' => $validated['push_notifications'] ?? true,
                'notification_types' => $validated['notification_types'] ?? [
                    'order_created' => [
                        'email' => true,
                        'sms' => false,
                        'push' => true,
                        'database' => true,
                    ],
                ],
                'quiet_hours' => $validated['quiet_hours'] ?? [
                    'enabled' => true,
                    'start_time' => '22:00',
                    'end_time' => '08:00',
                    'timezone' => 'Asia/Riyadh',
                ],
                'frequency_limits' => $validated['frequency_limits'] ?? [
                    'max_emails_per_day' => 10,
                    'max_sms_per_day' => 5,
                    'max_push_per_hour' => 3,
                ],
                'updated_at' => now()->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'data' => $updatedPreferences
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
