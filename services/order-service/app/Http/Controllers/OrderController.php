<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Http\Resources\OrderStateResource;
use App\Workflows\OrderSagaWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Workflow\WorkflowStub;

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
            'status' => 'sometimes|in:'.implode(',', Order::getStatuses()),
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
                'errors' => $validator->errors(),
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
                'message' => 'Orders retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders',
                'error' => $e->getMessage(),
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
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create the order
            $orderData = $request->only([
                'customer_id', 'vehicle_id', 'title', 'description',
                'part_details', 'budget_min', 'budget_max', 'delivery_location',
                'urgent', 'priority_score', 'deadline',
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
                'message' => 'Order created successfully',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
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
                'message' => 'Order retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'error' => $e->getMessage(),
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
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($id);

            // Check if order can be updated
            if (! in_array($order->status, [Order::STATUS_DRAFT, Order::STATUS_PUBLISHED])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order cannot be updated in current status',
                ], 422);
            }

            $updateData = $request->only([
                'title', 'description', 'part_details', 'budget_min',
                'budget_max', 'delivery_location', 'urgent', 'priority_score', 'deadline',
            ]);

            $order = $this->orderService->updateOrder($order, $updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $order->load(['orderImages', 'statusHistory']),
                'message' => 'Order updated successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update order',
                'error' => $e->getMessage(),
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

            if (! $order->canBePublished()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order cannot be published. Check required fields.',
                ], 422);
            }

            $order = $this->orderService->publishOrder($order);

            // Send notifications to relevant merchants
            $this->notificationService->sendOrderPublishedNotification($order);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $order,
                'message' => 'Order published successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to publish order',
                'error' => $e->getMessage(),
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
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($id);

            if ($order->status === Order::STATUS_COMPLETED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel completed order',
                ], 422);
            }

            $order = $this->orderService->cancelOrder($order, $request->reason);

            // Send cancellation notifications
            $this->notificationService->sendOrderCancelledNotification($order);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $order,
                'message' => 'Order cancelled successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage(),
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
                'errors' => $validator->errors(),
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
                'message' => 'Images uploaded successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images',
                'error' => $e->getMessage(),
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
                'message' => 'Statistics retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
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
                'errors' => $validator->errors(),
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
                'message' => 'Search completed successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get order state information
     */
    public function getState(int $id): JsonResponse
    {
        try {
            $order = Order::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new OrderStateResource($order),
                'message' => 'Order state retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order state',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Transition order to new state
     */
    public function transitionState(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'state' => 'required|string',
            'reason' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = Order::findOrFail($id);
            $newStateClass = $request->state;
            $reason = $request->get('reason');

            // Validate state class exists
            if (!class_exists($newStateClass)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid state class',
                ], 400);
            }

            // Check if transition is allowed
            if (!$order->canTransitionTo($newStateClass)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid state transition',
                    'current_state' => $order->state::class,
                    'available_transitions' => $order->getAvailableTransitions(),
                ], 400);
            }

            // Perform the transition
            $order->transitionToState($newStateClass, $reason);

            return response()->json([
                'success' => true,
                'data' => new OrderStateResource($order->fresh()),
                'message' => 'Order state transitioned successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to transition order state',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available state transitions for an order
     */
    public function getAvailableTransitions(int $id): JsonResponse
    {
        try {
            $order = Order::findOrFail($id);
            $availableTransitions = collect($order->getAvailableTransitions())->map(function ($stateClass) use ($order) {
                $state = new $stateClass($order);
                return [
                    'class' => $stateClass,
                    'name' => $state::$name,
                    'label' => $state->label(),
                    'description' => $state->description(),
                    'color' => $state->color(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'current_state' => [
                        'class' => $order->state::class,
                        'name' => $order->state::$name,
                        'label' => $order->state->label(),
                        'description' => $order->state->description(),
                        'color' => $order->state->color(),
                    ],
                    'available_transitions' => $availableTransitions,
                ],
                'message' => 'Available transitions retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available transitions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get orders by state
     */
    public function getByState(Request $request, string $state): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Validate state class exists
            if (!class_exists($state)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid state class',
                ], 400);
            }

            $orders = Order::where('state', $state)
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $orders,
                'message' => 'Orders retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders by state',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Workflow Management Methods
     */

    /**
     * Initiate order processing workflow (saga)
     */
    public function initiateWorkflow(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_data' => 'required|array',
            'payment_data.method' => 'required|string',
            'payment_data.details' => 'required|array',
            'shipping_address' => 'required|array',
            'shipping_method' => 'sometimes|string|in:standard,express,overnight',
            'priority' => 'sometimes|string|in:normal,high,urgent',
            'special_instructions' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = Order::findOrFail($id);

            // Check if order is in a state that can start workflow
            if ($order->hasWorkflow()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order already has an active workflow',
                    'workflow_id' => $order->workflow_id,
                ], 400);
            }

            // Prepare workflow data
            $workflowData = [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'amount' => $order->total_amount,
                'currency' => $order->currency ?? 'SAR',
                'payment_data' => $request->payment_data,
                'shipping_address' => $request->shipping_address,
                'shipping_method' => $request->get('shipping_method', 'standard'),
                'priority' => $request->get('priority', 'normal'),
                'special_instructions' => $request->get('special_instructions'),
                'items' => $this->prepareOrderItems($order),
            ];

            // Start the workflow
            $workflowId = 'order-saga-' . $order->id . '-' . uniqid();
            
            // Create workflow stub
            $workflow = WorkflowStub::make(OrderSagaWorkflow::class, $workflowId);
            
            // Associate order with workflow
            $order->setWorkflow($workflowId, $workflowData);

            // Start workflow execution
            $workflowResult = $workflow->start($workflowData);

            Log::info("Order workflow initiated", [
                'order_id' => $order->id,
                'workflow_id' => $workflowId,
                'customer_id' => $order->customer_id
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'workflow_id' => $workflowId,
                    'status' => 'initiated',
                    'workflow_data' => $workflowData,
                ],
                'message' => 'Order workflow initiated successfully',
            ], 201);

        } catch (\Exception $e) {
            Log::error("Failed to initiate order workflow", [
                'order_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate workflow',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get workflow status for an order
     */
    public function getWorkflowStatus(int $id): JsonResponse
    {
        try {
            $order = Order::findOrFail($id);

            if (!$order->hasWorkflow()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order does not have an associated workflow',
                ], 404);
            }

            // Get workflow status
            $workflowStatus = $order->getWorkflowStatus();
            $sagaData = $order->getSagaData();

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'workflow_id' => $order->workflow_id,
                    'workflow_status' => $workflowStatus,
                    'order_state' => $order->state::class,
                    'saga_data' => $sagaData,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ],
                'message' => 'Workflow status retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve workflow status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel an active workflow
     */
    public function cancelWorkflow(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = Order::findOrFail($id);

            if (!$order->hasWorkflow()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order does not have an active workflow',
                ], 404);
            }

            $workflowId = $order->workflow_id;
            $reason = $request->reason;

            // TODO: Cancel the workflow via Laravel Workflow
            // This would require accessing the workflow engine to cancel the running workflow
            // For now, we'll clear the workflow association and update the order state

            // Clear workflow association
            $order->clearWorkflow();

            // Update order state to cancelled
            $order->transitionToState(\App\States\Orders\Cancelled::class, "Workflow cancelled: {$reason}");

            Log::info("Order workflow cancelled", [
                'order_id' => $order->id,
                'workflow_id' => $workflowId,
                'reason' => $reason
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'workflow_id' => $workflowId,
                    'status' => 'cancelled',
                    'reason' => $reason,
                ],
                'message' => 'Workflow cancelled successfully',
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to cancel workflow", [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel workflow',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry a failed workflow
     */
    public function retryWorkflow(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_step' => 'sometimes|string|in:payment,inventory,shipping',
            'updated_data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = Order::findOrFail($id);

            // For retry, we'll create a new workflow with updated data
            $fromStep = $request->get('from_step', 'payment');
            $updatedData = $request->get('updated_data', []);

            // Get original saga data and merge with updates
            $originalData = $order->getSagaData();
            $workflowData = array_merge($originalData, $updatedData);

            // Clear old workflow association
            $order->clearWorkflow();

            // Create new workflow
            $workflowId = 'order-saga-retry-' . $order->id . '-' . uniqid();
            
            // Create workflow stub
            $workflow = WorkflowStub::make(OrderSagaWorkflow::class, $workflowId);
            
            // Associate order with new workflow
            $order->setWorkflow($workflowId, $workflowData);

            // Start workflow execution
            $workflowResult = $workflow->start($workflowData);

            Log::info("Order workflow retried", [
                'order_id' => $order->id,
                'new_workflow_id' => $workflowId,
                'from_step' => $fromStep,
                'updated_data' => $updatedData
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'workflow_id' => $workflowId,
                    'status' => 'retrying',
                    'from_step' => $fromStep,
                    'workflow_data' => $workflowData,
                ],
                'message' => 'Workflow retry initiated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to retry workflow", [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retry workflow',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get workflow execution history
     */
    public function getWorkflowHistory(int $id): JsonResponse
    {
        try {
            $order = Order::findOrFail($id);

            // Get order status history which includes workflow state changes
            $statusHistory = $order->status_history ?? [];
            
            // Filter for workflow-related entries
            $workflowHistory = collect($statusHistory)->filter(function ($entry) {
                return isset($entry['reason']) && 
                       (str_contains($entry['reason'], 'workflow') || 
                        str_contains($entry['reason'], 'saga') ||
                        str_contains($entry['reason'], 'compensation'));
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'workflow_id' => $order->workflow_id,
                    'current_state' => $order->state::class,
                    'workflow_history' => $workflowHistory->values(),
                    'full_status_history' => $statusHistory,
                ],
                'message' => 'Workflow history retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve workflow history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Prepare order items for workflow
     */
    private function prepareOrderItems(Order $order): array
    {
        // This would typically extract items from the order
        // For now, we'll create a basic structure based on order data
        return [
            [
                'id' => $order->id,
                'name' => $order->title ?? 'Order Item',
                'quantity' => 1,
                'price' => $order->total_amount,
                'description' => $order->description ?? '',
            ]
        ];
    }
}
