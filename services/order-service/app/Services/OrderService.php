<?php

namespace App\Services;

use App\Models\Order;
use App\Events\OrderCreated;
use App\Events\OrderPublished;
use App\Events\OrderCancelled;
use App\Events\OrderStatusChanged;
use App\Services\Contracts\NotificationServiceInterface;
use App\Services\Contracts\VehicleServiceInterface;
use App\Services\Contracts\ImageProcessingServiceInterface;
use App\Services\Contracts\AnalyticsServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;

/**
 * Order Service
 * 
 * Handles all business logic related to order management
 * including creation, updates, status changes, and image processing
 */
class OrderService
{
    protected NotificationServiceInterface $notificationService;
    protected VehicleServiceInterface $vehicleService;
    protected ImageProcessingServiceInterface $imageProcessingService;
    protected AnalyticsServiceInterface $analyticsService;

    public function __construct(
        NotificationServiceInterface $notificationService,
        VehicleServiceInterface $vehicleService,
        ImageProcessingServiceInterface $imageProcessingService,
        AnalyticsServiceInterface $analyticsService
    ) {
        $this->notificationService = $notificationService;
        $this->vehicleService = $vehicleService;
        $this->imageProcessingService = $imageProcessingService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Create a new order
     */
    public function createOrder(array $data): Order
    {
        DB::beginTransaction();
        try {
            // Validate vehicle ownership
            $this->vehicleService->validateVehicleOwnership($data['vehicle_id'], $data['customer_id']);

            // Set default values
            $data['status'] = Order::STATUS_DRAFT;
            $data['priority_score'] = $data['priority_score'] ?? 5;
            
            // Calculate deadline if not provided
            if (!isset($data['deadline'])) {
                $hours = config('order.default_expiry_hours', 168); // 7 days default
                $data['deadline'] = Carbon::now()->addHours($hours);
            }

            // Create the order
            $order = Order::create($data);

            // Log status history
            $this->logStatusChange($order, null, Order::STATUS_DRAFT, 'Order created');

            // Fire event
            event(new OrderCreated($order));

            // Track analytics
            $this->analyticsService->trackOrderEvent($order, 'order_created', [
                'vehicle_id' => $data['vehicle_id'],
                'budget_max' => $data['budget_max'] ?? null,
                'urgent' => $data['urgent'] ?? false
            ]);

            DB::commit();
            return $order;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing order
     */
    public function updateOrder(Order $order, array $data): Order
    {
        DB::beginTransaction();
        try {
            // Validate order can be updated
            if (!$this->canUpdateOrder($order)) {
                throw new \Exception('Order cannot be updated in current status');
            }

            $oldData = $order->toArray();
            $order->update($data);

            // Log significant changes
            $this->logOrderChanges($order, $oldData, $data);

            DB::commit();
            return $order->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Publish an order
     */
    public function publishOrder(Order $order): Order
    {
        DB::beginTransaction();
        try {
            if (!$order->canBePublished()) {
                throw new \Exception('Order cannot be published. Missing required fields.');
            }

            $oldStatus = $order->status;
            $order->update([
                'status' => Order::STATUS_PUBLISHED,
                'published_at' => Carbon::now()
            ]);

            // Log status change
            $this->logStatusChange($order, $oldStatus, Order::STATUS_PUBLISHED, 'Order published');

            // Notify relevant merchants
            $this->notifyMerchantsOfNewOrder($order);

            // Fire event
            event(new OrderPublished($order));

            // Track analytics
            $this->analyticsService->trackOrderEvent($order, 'order_published', [
                'time_to_publish' => $order->created_at->diffInMinutes($order->published_at)
            ]);

            DB::commit();
            return $order;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(Order $order, string $reason): Order
    {
        DB::beginTransaction();
        try {
            if ($order->status === Order::STATUS_COMPLETED) {
                throw new \Exception('Cannot cancel completed order');
            }

            $oldStatus = $order->status;
            $order->update([
                'status' => Order::STATUS_CANCELLED
            ]);

            // Log status change with reason
            $this->logStatusChange($order, $oldStatus, Order::STATUS_CANCELLED, $reason);

            // Handle existing bids
            $this->handleOrderCancellation($order);

            // Fire event
            event(new OrderCancelled($order, $reason));

            DB::commit();
            return $order;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Upload images for an order
     */
    public function uploadOrderImages(Order $order, array $images, string $imageType = 'part_photo'): array
    {
        $uploadedImages = [];

        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $processedImage = $this->imageProcessingService->processOrderImage($image, $imageType);
                
                $media = $order->addMediaFromUrl($processedImage['path'])
                    ->toMediaCollection($imageType);

                $uploadedImages[] = [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                    'thumbnail_url' => $media->getUrl('thumbnail'),
                    'type' => $imageType,
                    'metadata' => $processedImage['metadata']
                ];
            }
        }

        return $uploadedImages;
    }

    /**
     * Search orders with filters
     */
    public function searchOrders(string $query, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $orderQuery = Order::query()
            ->with(['orderImages', 'statusHistory'])
            ->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%")
                  ->orWhere('order_number', 'LIKE', "%{$query}%");
            });

        // Apply filters
        if (isset($filters['status'])) {
            $orderQuery->where('status', $filters['status']);
        }

        if (isset($filters['urgent'])) {
            $orderQuery->where('urgent', $filters['urgent']);
        }

        if (isset($filters['customer_id'])) {
            $orderQuery->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['date_from'])) {
            $orderQuery->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $orderQuery->where('created_at', '<=', $filters['date_to']);
        }

        return $orderQuery->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get order statistics
     */
    public function getOrderStatistics(): array
    {
        $stats = [
            'total_orders' => Order::count(),
            'active_orders' => Order::active()->count(),
            'published_orders' => Order::published()->count(),
            'urgent_orders' => Order::urgent()->count(),
            'completed_orders' => Order::where('status', Order::STATUS_COMPLETED)->count(),
            'cancelled_orders' => Order::where('status', Order::STATUS_CANCELLED)->count(),
        ];

        // Add time-based statistics
        $stats['orders_today'] = Order::whereDate('created_at', Carbon::today())->count();
        $stats['orders_this_week'] = Order::whereBetween('created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ])->count();
        $stats['orders_this_month'] = Order::whereMonth('created_at', Carbon::now()->month)->count();

        // Add average statistics
        $stats['avg_budget'] = Order::where('budget_max', '>', 0)->avg('budget_max');
        $stats['avg_completion_time'] = $this->calculateAverageCompletionTime();

        return $stats;
    }

    /**
     * Change order status
     */
    public function changeOrderStatus(Order $order, string $newStatus, string $reason = null): Order
    {
        DB::beginTransaction();
        try {
            $oldStatus = $order->status;
            
            if (!$this->isValidStatusTransition($oldStatus, $newStatus)) {
                throw new \Exception("Invalid status transition from {$oldStatus} to {$newStatus}");
            }

            $order->update(['status' => $newStatus]);

            // Log status change
            $this->logStatusChange($order, $oldStatus, $newStatus, $reason);

            // Handle status-specific logic
            $this->handleStatusChange($order, $oldStatus, $newStatus);

            // Fire event
            event(new OrderStatusChanged($order, $oldStatus, $newStatus));

            DB::commit();
            return $order;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check if order can be updated
     */
    protected function canUpdateOrder(Order $order): bool
    {
        return in_array($order->status, [Order::STATUS_DRAFT, Order::STATUS_PUBLISHED]);
    }

    /**
     * Log status change
     */
    protected function logStatusChange(Order $order, ?string $oldStatus, string $newStatus, string $reason = null): void
    {
        $order->statusHistory()->create([
            'user_id' => auth()->id(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'metadata' => [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => Carbon::now()->toISOString()
            ]
        ]);
    }

    /**
     * Log order changes
     */
    protected function logOrderChanges(Order $order, array $oldData, array $newData): void
    {
        $changes = [];
        foreach ($newData as $key => $value) {
            if (isset($oldData[$key]) && $oldData[$key] !== $value) {
                $changes[$key] = [
                    'old' => $oldData[$key],
                    'new' => $value
                ];
            }
        }

        if (!empty($changes)) {
            // Log to audit system or create change log entry
            \Log::info('Order updated', [
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'changes' => $changes
            ]);
        }
    }

    /**
     * Notify merchants of new order
     */
    protected function notifyMerchantsOfNewOrder(Order $order): void
    {
        // Get relevant merchants based on order criteria
        $merchants = $this->findRelevantMerchants($order);
        
        foreach ($merchants as $merchant) {
            $this->notificationService->sendOrderNotification($merchant, $order, 'new_order');
        }
    }

    /**
     * Find relevant merchants for an order
     */
    protected function findRelevantMerchants(Order $order): Collection
    {
        // This would implement logic to find merchants based on:
        // - Service areas
        // - Specializations
        // - Rating thresholds
        // - Previous performance
        
        return collect(); // Placeholder
    }

    /**
     * Handle order cancellation
     */
    protected function handleOrderCancellation(Order $order): void
    {
        // Cancel active bids
        // Notify bidders
        // Handle any payments or deposits
        // Update merchant statistics
    }

    /**
     * Check if status transition is valid
     */
    protected function isValidStatusTransition(string $from, string $to): bool
    {
        $validTransitions = [
            Order::STATUS_DRAFT => [Order::STATUS_PUBLISHED, Order::STATUS_CANCELLED],
            Order::STATUS_PUBLISHED => [Order::STATUS_BIDDING, Order::STATUS_CANCELLED],
            Order::STATUS_BIDDING => [Order::STATUS_AWARDED, Order::STATUS_CANCELLED],
            Order::STATUS_AWARDED => [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED],
            Order::STATUS_COMPLETED => [], // Final state
            Order::STATUS_CANCELLED => [], // Final state
        ];

        return in_array($to, $validTransitions[$from] ?? []);
    }

    /**
     * Handle status-specific logic
     */
    protected function handleStatusChange(Order $order, string $oldStatus, string $newStatus): void
    {
        switch ($newStatus) {
            case Order::STATUS_PUBLISHED:
                $this->handleOrderPublished($order);
                break;
            case Order::STATUS_BIDDING:
                $this->handleBiddingStarted($order);
                break;
            case Order::STATUS_AWARDED:
                $this->handleOrderAwarded($order);
                break;
            case Order::STATUS_COMPLETED:
                $this->handleOrderCompleted($order);
                break;
        }
    }

    /**
     * Handle order published
     */
    protected function handleOrderPublished(Order $order): void
    {
        // Start bidding timer
        // Notify relevant merchants
        // Update search indexes
    }

    /**
     * Handle bidding started
     */
    protected function handleBiddingStarted(Order $order): void
    {
        // Enable real-time bidding
        // Start countdown timer
        // Notify customers
    }

    /**
     * Handle order awarded
     */
    protected function handleOrderAwarded(Order $order): void
    {
        // Notify winning merchant
        // Notify losing bidders
        // Create contract/agreement
        // Initialize payment process
    }

    /**
     * Handle order completed
     */
    protected function handleOrderCompleted(Order $order): void
    {
        // Process final payment
        // Update merchant ratings
        // Send completion notifications
        // Archive order data
    }

    /**
     * Calculate average completion time
     */
    protected function calculateAverageCompletionTime(): ?float
    {
        $completedOrders = Order::where('status', Order::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->whereNotNull('published_at')
            ->get();

        if ($completedOrders->isEmpty()) {
            return null;
        }

        $totalHours = $completedOrders->sum(function ($order) {
            return $order->published_at->diffInHours($order->completed_at);
        });

        return $totalHours / $completedOrders->count();
    }
}
