# Escrow System Implementation Plan

## 🎯 Overview

The escrow system is **confirmed missing** and is critical for the auction platform's trustworthiness. This plan outlines the implementation of a comprehensive escrow system for the larvrevrstender platform.

## 🏗️ Architecture Design

### Core Components

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Order Service │    │ Payment Service │    │ Escrow Service  │
│                 │    │                 │    │                 │
│ • Order Created │───▶│ • Payment Hold  │───▶│ • Escrow Create │
│ • Order Status  │    │ • Fund Capture  │    │ • Fund Hold     │
│ • Completion    │◀───│ • Release Funds │◀───│ • Release Logic │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Bidding Service │    │ Notification    │    │ Dispute Service │
│                 │    │ Service         │    │                 │
│ • Bid Accepted  │    │ • Escrow Events │    │ • Dispute Cases │
│ • Auction End   │    │ • Status Updates│    │ • Resolution    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## 📋 Implementation Phases

### Phase 1: Core Escrow Service (Week 1)

#### 1.1 Database Schema

```sql
-- Escrow accounts table
CREATE TABLE escrows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    payment_id BIGINT UNSIGNED NOT NULL,
    buyer_id BIGINT UNSIGNED NOT NULL,
    seller_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status ENUM('created', 'funded', 'released', 'disputed', 'cancelled') NOT NULL DEFAULT 'created',
    hold_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    released_at TIMESTAMP NULL,
    
    INDEX idx_order_id (order_id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_buyer_id (buyer_id),
    INDEX idx_seller_id (seller_id),
    INDEX idx_status (status),
    
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    FOREIGN KEY (buyer_id) REFERENCES users(id),
    FOREIGN KEY (seller_id) REFERENCES users(id)
);

-- Escrow transactions table
CREATE TABLE escrow_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    escrow_id BIGINT UNSIGNED NOT NULL,
    type ENUM('hold', 'release', 'partial_release', 'dispute', 'cancel') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason TEXT,
    processed_by BIGINT UNSIGNED NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    external_reference VARCHAR(255) NULL,
    metadata JSON NULL,
    
    INDEX idx_escrow_id (escrow_id),
    INDEX idx_type (type),
    INDEX idx_processed_at (processed_at),
    
    FOREIGN KEY (escrow_id) REFERENCES escrows(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- Escrow release conditions table
CREATE TABLE escrow_release_conditions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    escrow_id BIGINT UNSIGNED NOT NULL,
    condition_type ENUM('delivery_confirmed', 'inspection_passed', 'time_elapsed', 'manual_approval') NOT NULL,
    condition_data JSON NULL,
    is_met BOOLEAN DEFAULT FALSE,
    met_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_escrow_id (escrow_id),
    INDEX idx_condition_type (condition_type),
    INDEX idx_is_met (is_met),
    
    FOREIGN KEY (escrow_id) REFERENCES escrows(id)
);
```

#### 1.2 Escrow Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Escrow extends Model
{
    const STATUS_CREATED = 'created';
    const STATUS_FUNDED = 'funded';
    const STATUS_RELEASED = 'released';
    const STATUS_DISPUTED = 'disputed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_id',
        'payment_id',
        'buyer_id',
        'seller_id',
        'amount',
        'currency',
        'status',
        'hold_until',
        'released_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'hold_until' => 'datetime',
        'released_at' => 'datetime'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(EscrowTransaction::class);
    }

    public function releaseConditions(): HasMany
    {
        return $this->hasMany(EscrowReleaseCondition::class);
    }

    public function canBeReleased(): bool
    {
        return $this->status === self::STATUS_FUNDED && 
               $this->releaseConditions()->where('is_met', false)->count() === 0;
    }

    public function isExpired(): bool
    {
        return $this->hold_until && $this->hold_until->isPast();
    }
}
```

#### 1.3 Escrow Service

```php
<?php

namespace App\Services;

use App\Models\Escrow;
use App\Models\EscrowTransaction;
use App\Events\EscrowCreated;
use App\Events\EscrowFunded;
use App\Events\EscrowReleased;
use Illuminate\Support\Facades\DB;
use Shared\Procedures\Micro\CircuitBreakerProcedure;

class EscrowService
{
    use CircuitBreakerProcedure;

    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * Create escrow account for order
     */
    public function createEscrow(array $data): Escrow
    {
        return DB::transaction(function () use ($data) {
            $escrow = Escrow::create([
                'order_id' => $data['order_id'],
                'payment_id' => $data['payment_id'],
                'buyer_id' => $data['buyer_id'],
                'seller_id' => $data['seller_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD',
                'status' => Escrow::STATUS_CREATED,
                'hold_until' => $data['hold_until'] ?? now()->addDays(7)
            ]);

            // Create default release conditions
            $this->createDefaultReleaseConditions($escrow);

            event(new EscrowCreated($escrow));

            return $escrow;
        });
    }

    /**
     * Fund escrow account
     */
    public function fundEscrow(int $escrowId): Escrow
    {
        return DB::transaction(function () use ($escrowId) {
            $escrow = Escrow::findOrFail($escrowId);

            if ($escrow->status !== Escrow::STATUS_CREATED) {
                throw new \Exception('Escrow cannot be funded in current status');
            }

            // Hold funds in payment gateway using existing payment service
            $holdResult = $this->executeWithCircuitBreaker(function () use ($escrow) {
                return $this->paymentService->holdFunds(
                    $escrow->payment_id,
                    $escrow->amount,
                    'Escrow hold for order #' . $escrow->order_id
                );
            });

            if (!$holdResult['success']) {
                throw new \Exception('Failed to hold funds: ' . $holdResult['error']);
            }

            // Update escrow status
            $escrow->update(['status' => Escrow::STATUS_FUNDED]);

            // Record transaction
            EscrowTransaction::create([
                'escrow_id' => $escrow->id,
                'type' => 'hold',
                'amount' => $escrow->amount,
                'reason' => 'Funds held in escrow',
                'external_reference' => $holdResult['reference']
            ]);

            event(new EscrowFunded($escrow));

            return $escrow;
        });
    }

    /**
     * Release escrow funds
     */
    public function releaseEscrow(int $escrowId, string $reason = 'Order completed'): Escrow
    {
        return DB::transaction(function () use ($escrowId, $reason) {
            $escrow = Escrow::findOrFail($escrowId);

            if (!$escrow->canBeReleased()) {
                throw new \Exception('Escrow cannot be released - conditions not met');
            }

            // Release funds using existing payment service
            $releaseResult = $this->executeWithCircuitBreaker(function () use ($escrow, $reason) {
                return $this->paymentService->releaseFunds(
                    $escrow->payment_id,
                    $escrow->amount,
                    $reason
                );
            });

            if (!$releaseResult['success']) {
                throw new \Exception('Failed to release funds: ' . $releaseResult['error']);
            }

            // Update escrow status
            $escrow->update([
                'status' => Escrow::STATUS_RELEASED,
                'released_at' => now()
            ]);

            // Record transaction
            EscrowTransaction::create([
                'escrow_id' => $escrow->id,
                'type' => 'release',
                'amount' => $escrow->amount,
                'reason' => $reason,
                'external_reference' => $releaseResult['reference']
            ]);

            event(new EscrowReleased($escrow));

            return $escrow;
        });
    }

    /**
     * Create default release conditions
     */
    private function createDefaultReleaseConditions(Escrow $escrow): void
    {
        $conditions = [
            [
                'escrow_id' => $escrow->id,
                'condition_type' => 'delivery_confirmed',
                'condition_data' => ['required' => true]
            ],
            [
                'escrow_id' => $escrow->id,
                'condition_type' => 'time_elapsed',
                'condition_data' => ['days' => 7]
            ]
        ];

        foreach ($conditions as $condition) {
            EscrowReleaseCondition::create($condition);
        }
    }
}
```

### Phase 2: RPC Integration (Week 2)

#### 2.1 Escrow RPC Procedure

```php
<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\EscrowService;
use Sajya\Server\Exceptions\RuntimeException;

class EscrowProcedure extends BaseProcedure
{
    public function __construct(
        private EscrowService $escrowService
    ) {}

    /**
     * Create escrow account
     */
    public function createEscrow(array $params): array
    {
        $this->validate($params, [
            'order_id' => 'required|integer|min:1',
            'payment_id' => 'required|integer|min:1',
            'buyer_id' => 'required|integer|min:1',
            'seller_id' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'sometimes|string|size:3',
            'hold_until' => 'sometimes|date|after:now'
        ]);

        try {
            $escrow = $this->escrowService->createEscrow($params);

            return [
                'success' => true,
                'escrow' => $escrow,
                'created_at' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            throw new RuntimeException(
                'Escrow creation failed: ' . $e->getMessage(),
                -32001,
                ['order_id' => $params['order_id']]
            );
        }
    }

    /**
     * Fund escrow account
     */
    public function fundEscrow(array $params): array
    {
        $this->validate($params, [
            'escrow_id' => 'required|integer|min:1'
        ]);

        try {
            $escrow = $this->escrowService->fundEscrow($params['escrow_id']);

            return [
                'success' => true,
                'escrow' => $escrow,
                'funded_at' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            throw new RuntimeException(
                'Escrow funding failed: ' . $e->getMessage(),
                -32002,
                ['escrow_id' => $params['escrow_id']]
            );
        }
    }

    /**
     * Release escrow funds
     */
    public function releaseEscrow(array $params): array
    {
        $this->validate($params, [
            'escrow_id' => 'required|integer|min:1',
            'reason' => 'sometimes|string|max:500'
        ]);

        try {
            $escrow = $this->escrowService->releaseEscrow(
                $params['escrow_id'],
                $params['reason'] ?? 'Order completed'
            );

            return [
                'success' => true,
                'escrow' => $escrow,
                'released_at' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            throw new RuntimeException(
                'Escrow release failed: ' . $e->getMessage(),
                -32003,
                ['escrow_id' => $params['escrow_id']]
            );
        }
    }
}
```

### Phase 3: Order Service Integration (Week 3)

#### 3.1 Order Service Enhancement

```php
<?php

namespace App\Services;

use Shared\Procedures\Micro\CircuitBreakerProcedure;

class EnhancedOrderService
{
    use CircuitBreakerProcedure;

    /**
     * Create order with escrow
     */
    public function createOrderWithEscrow(array $orderData, array $paymentData): array
    {
        return DB::transaction(function () use ($orderData, $paymentData) {
            // Create order
            $order = $this->createOrder($orderData);

            // Process payment
            $payment = $this->executeWithCircuitBreaker(function () use ($paymentData) {
                return $this->call('payment.processPayment', $paymentData);
            });

            // Create escrow
            $escrow = $this->executeWithCircuitBreaker(function () use ($order, $payment) {
                return $this->call('escrow.createEscrow', [
                    'order_id' => $order->id,
                    'payment_id' => $payment['payment']['id'],
                    'buyer_id' => $order->buyer_id,
                    'seller_id' => $order->seller_id,
                    'amount' => $order->total_amount,
                    'currency' => $order->currency
                ]);
            });

            // Fund escrow
            $fundedEscrow = $this->executeWithCircuitBreaker(function () use ($escrow) {
                return $this->call('escrow.fundEscrow', [
                    'escrow_id' => $escrow['escrow']['id']
                ]);
            });

            return [
                'order' => $order,
                'payment' => $payment,
                'escrow' => $fundedEscrow
            ];
        });
    }

    /**
     * Complete order and release escrow
     */
    public function completeOrderWithEscrowRelease(int $orderId): array
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::findOrFail($orderId);

            // Mark order as completed
            $order->update(['status' => Order::STATUS_COMPLETED]);

            // Release escrow funds
            $escrow = $this->executeWithCircuitBreaker(function () use ($order) {
                return $this->call('escrow.releaseEscrow', [
                    'escrow_id' => $order->escrow_id,
                    'reason' => 'Order completed successfully'
                ]);
            });

            return [
                'order' => $order,
                'escrow' => $escrow
            ];
        });
    }
}
```

## 🔄 Integration Points

### 1. Payment Service Integration

```php
// Add to PaymentService
public function holdFunds(int $paymentId, float $amount, string $reason): array
{
    // Implementation for holding funds in payment gateway
}

public function releaseFunds(int $paymentId, float $amount, string $reason): array
{
    // Implementation for releasing held funds
}
```

### 2. Event Listeners

```php
<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Services\EscrowService;

class ReleaseEscrowOnOrderCompletion
{
    public function __construct(private EscrowService $escrowService) {}

    public function handle(OrderCompleted $event): void
    {
        if ($event->order->escrow_id) {
            $this->escrowService->releaseEscrow(
                $event->order->escrow_id,
                'Order completed - automatic release'
            );
        }
    }
}
```

## 📊 Success Metrics

- **Trust Score**: Measure user confidence with escrow protection
- **Dispute Reduction**: Track reduction in payment disputes
- **Transaction Security**: Monitor successful escrow releases
- **User Adoption**: Track escrow usage in orders

## 🎯 Next Steps

1. **Week 1**: Implement core escrow service and database schema
2. **Week 2**: Add RPC procedures and REST endpoints
3. **Week 3**: Integrate with order and payment services
4. **Week 4**: Testing, monitoring, and documentation

This implementation leverages existing payment service infrastructure and circuit breaker patterns for reliability.

