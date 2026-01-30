<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Order Controller
 * 
 * Handles all order-related operations including CRUD, status management,
 * and image uploads for the reverse tender platform
 */
class OrderController extends Controller
{
    protected OrderService $orderService;
    protected NotificationService $notificationService;

    public function __construct(
        OrderService $orderService,
        NotificationService $notificationService
    ) {
        $this->orderService = $orderService;
        $this->notificationService = $notificationService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of orders
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:' . implode(',', Order::getStatuses()),
            'urgent' => 'sometimes|boolean',
            'customer_id' => 'sometimes|integer',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort_by' => 'sometimes|in:created_at,updated_at,deadline,priority_score',
            'sort_direction' => 'sometimes|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = Order::with(['orderImages', 'statusHistory']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('urgent')) {
                $query->where('urgent', $request->boolean('urgent'));
            }

            if ($request->has('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginate results
            $perPage = $request->get('per_page', 15);
            $orders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $orders,
                'message' => 'Orders retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created order
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|integer',
            'vehicle_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'part_details' => 'sometimes|array',
            'budget_min' => 'sometimes|numeric|min:0',
            'budget_max' => 'required|numeric|min:0',
            'delivery_location' => 'sometimes|array',
            'urgent' => 'sometimes|boolean',
            'priority_score' => 'sometimes|integer|min:1|max:10',
            'deadline' => 'sometimes|date|after:now',
            'images' => 'sometimes|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create the order
            $orderData = $request->only([
                'customer_id', 'vehicle_id', 'title', 'description',
                'part_details', 'budget_min', 'budget_max', 'delivery_location',
                'urgent', 'priority_score', 'deadline'
            ]);

            $order = $this->orderService->createOrder($orderData);

            // Handle image uploads
            if ($request->hasFile('images')) {
                $this->orderService->uploadOrderImages($order, $request->file('images'));
            }

            // Send notification
            $this->notificationService->sendOrderCreatedNotification($order);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $order->load(['orderImages', 'statusHistory']),
                'message' => 'Order created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified order
     */
    public function show(int $id): JsonResponse
    {
        try {
            $order = Order::with(['orderImages', 'statusHistory'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $order,
                'message' => 'Order retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified order
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:5000',
            'part_details' => 'sometimes|array',
            'budget_min' => 'sometimes|numeric|min:0',
            'budget_max' => 'sometimes|numeric|min:0',
            'delivery_location' => 'sometimes|array',
            'urgent' => 'sometimes|boolean',
            'priority_score' => 'sometimes|integer|min:1|max:10',
            'deadline' => 'sometimes|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($id);

            // Check if order can be updated
            if (!in_array($order->status, [Order::STATUS_DRAFT, Order::STATUS_PUBLISHED])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order cannot be updated in current status'
                ], 422);
            }

            $updateData = $request->only([
                'title', 'description', 'part_details', 'budget_min',
                'budget_max', 'delivery_location', 'urgent', 'priority_score', 'deadline'
            ]);

            $order = $this->orderService->updateOrder($order, $updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $order->load(['orderImages', 'statusHistory']),
                'message' => 'Order updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish an order
     */
    public function publish(int $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $order = Order::findOrFail($id);

            if (!$order->canBePublished()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order cannot be published. Check required fields.'
                ], 422);
            }

            $order = $this->orderService->publishOrder($order);

            // Send notifications to relevant merchants
            $this->notificationService->sendOrderPublishedNotification($order);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $order,
                'message' => 'Order published successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel an order
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($id);

            if ($order->status === Order::STATUS_COMPLETED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel completed order'
                ], 422);
            }

            $order = $this->orderService->cancelOrder($order, $request->reason);

            // Send cancellation notifications
            $this->notificationService->sendOrderCancelledNotification($order);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $order,
                'message' => 'Order cancelled successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload images for an order
     */
    public function uploadImages(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'images' => 'required|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'image_type' => 'required|in:part_photo,damage_photo,reference,vin_photo',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $order = Order::findOrFail($id);

            $uploadedImages = $this->orderService->uploadOrderImages(
                $order,
                $request->file('images'),
                $request->image_type
            );

            return response()->json([
                'success' => true,
                'data' => $uploadedImages,
                'message' => 'Images uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->orderService->getOrderStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search orders
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:3',
            'filters' => 'sometimes|array',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $results = $this->orderService->searchOrders(
                $request->query,
                $request->get('filters', []),
                $request->get('per_page', 15)
            );

            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => 'Search completed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
