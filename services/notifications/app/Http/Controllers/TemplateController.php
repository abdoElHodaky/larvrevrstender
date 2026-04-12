<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TemplateController extends Controller
{
    /**
     * Get all notification templates
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // For now, return a basic template structure
            // In a real implementation, you'd have a Template model
            $templates = [
                [
                    'id' => 1,
                    'name' => 'order_created',
                    'title' => 'Order Created',
                    'body' => 'Your order #{order_id} has been created successfully.',
                    'channels' => ['database', 'email'],
                    'variables' => ['order_id', 'customer_name'],
                    'created_at' => now()->toISOString(),
                ],
                [
                    'id' => 2,
                    'name' => 'bid_placed',
                    'title' => 'New Bid Placed',
                    'body' => 'A new bid of {bid_amount} has been placed on your order #{order_id}.',
                    'channels' => ['database', 'push'],
                    'variables' => ['bid_amount', 'order_id', 'bidder_name'],
                    'created_at' => now()->toISOString(),
                ],
                [
                    'id' => 3,
                    'name' => 'payment_completed',
                    'title' => 'Payment Completed',
                    'body' => 'Your payment of {amount} has been processed successfully.',
                    'channels' => ['database', 'email', 'sms'],
                    'variables' => ['amount', 'transaction_id'],
                    'created_at' => now()->toISOString(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new notification template
     */
    public function createTemplate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|unique:notification_templates,name',
                'title' => 'required|string|max:255',
                'body' => 'required|string',
                'channels' => 'required|array',
                'channels.*' => 'in:database,email,sms,push',
                'variables' => 'nullable|array',
                'variables.*' => 'string',
            ]);

            // For now, just return success with the validated data
            // In a real implementation, you'd save to a Template model
            $template = [
                'id' => rand(1000, 9999),
                'name' => $validated['name'],
                'title' => $validated['title'],
                'body' => $validated['body'],
                'channels' => $validated['channels'],
                'variables' => $validated['variables'] ?? [],
                'created_at' => now()->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => $template
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
                'message' => 'Failed to create template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new template (user-facing endpoint)
     */
    public function store(Request $request): JsonResponse
    {
        return $this->createTemplate($request);
    }

    /**
     * Show a specific template
     */
    public function show(string $template): JsonResponse
    {
        try {
            // Mock template data - in real implementation, fetch from database
            $templateData = [
                'id' => $template,
                'name' => 'order_created',
                'title' => 'Order Created',
                'body' => 'Your order #{order_id} has been created successfully.',
                'channels' => ['database', 'email'],
                'variables' => ['order_id', 'customer_name'],
                'created_at' => now()->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'data' => $templateData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update a template
     */
    public function update(Request $request, string $template): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string',
                'title' => 'sometimes|string|max:255',
                'body' => 'sometimes|string',
                'channels' => 'sometimes|array',
                'channels.*' => 'in:database,email,sms,push',
                'variables' => 'sometimes|array',
                'variables.*' => 'string',
            ]);

            // Mock updated template - in real implementation, update in database
            $updatedTemplate = [
                'id' => $template,
                'name' => $validated['name'] ?? 'order_created',
                'title' => $validated['title'] ?? 'Order Created',
                'body' => $validated['body'] ?? 'Your order #{order_id} has been created successfully.',
                'channels' => $validated['channels'] ?? ['database', 'email'],
                'variables' => $validated['variables'] ?? ['order_id', 'customer_name'],
                'updated_at' => now()->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'data' => $updatedTemplate
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
                'message' => 'Failed to update template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a template
     */
    public function destroy(string $template): JsonResponse
    {
        try {
            // In real implementation, delete from database
            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
