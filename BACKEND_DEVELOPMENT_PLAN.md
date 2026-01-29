# 🔧 Backend Development Plan | خطة تطوير الخلفية

## 📋 Overview

This comprehensive backend development plan is based on the detailed diagrams from the **Reverse Tender Platform Implementation Plan**. It provides a structured approach to implementing all microservices with proper architecture, database design, and API development.

## 🏗️ Architecture Overview

Based on the **Microservices Architecture Diagram**, our backend consists of:

### **Core Services**
1. **🚪 API Gateway** - Central entry point with rate limiting and authentication
2. **🔐 Auth Service** - JWT + OAuth + OTP verification system
3. **👥 User Service** - Customer and merchant profile management
4. **📋 Order Service** - Part request management and processing
5. **🎯 Bidding Service** - Real-time auction system with WebSocket
6. **📢 Notification Service** - Multi-channel notification system
7. **💳 Payment Service** - Future payment gateway integration
8. **📊 Analytics Service** - Business intelligence and reporting

### **Data Layer**
- **🗃️ MySQL 8.0** - Primary database with service-specific schemas
- **⚡ Redis 7.0** - Caching, sessions, and message queuing
- **📁 MinIO S3** - File storage for images and documents

---

## 📊 Database Schema Implementation

Based on the **Enhanced Database Schema Diagram**:

### **Core Tables Structure**

```sql
-- Users table (Auth Service)
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    type ENUM('customer', 'merchant', 'admin') NOT NULL,
    verified BOOLEAN DEFAULT FALSE,
    email_verified_at TIMESTAMP NULL,
    phone_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_email (email),
    INDEX idx_type (type)
);

-- Customer profiles (User Service)
CREATE TABLE customer_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    national_address TEXT,
    default_location JSON,
    preferences JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Merchant profiles (User Service)
CREATE TABLE merchant_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    business_license VARCHAR(100),
    specializations JSON,
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT DEFAULT 0,
    verified BOOLEAN DEFAULT FALSE,
    verification_documents JSON,
    business_hours JSON,
    service_areas JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_verified (verified),
    INDEX idx_rating (rating)
);
```

---

## 🔐 Phase 1: Authentication Service Implementation

Based on the **Authentication Flow Diagram**:

### **1.1 JWT Authentication System**

```php
<?php
// app/Services/AuthService.php

namespace App\Services;

use App\Models\User;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function register(array $data): array
    {
        // 1. Validate phone number
        $this->validatePhoneNumber($data['phone']);
        
        // 2. Create user
        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'type' => $data['type'],
            'verified' => false
        ]);
        
        // 3. Generate and send OTP
        $otpCode = $this->generateOTP($user);
        $this->sendOTP($user->phone, $otpCode);
        
        return [
            'user_id' => $user->id,
            'message' => 'OTP sent successfully',
            'expires_in' => 300 // 5 minutes
        ];
    }
    
    public function verifyOTP(int $userId, string $otpCode): array
    {
        $user = User::findOrFail($userId);
        
        // Verify OTP
        if (!$this->validateOTP($user, $otpCode)) {
            throw new \Exception('Invalid or expired OTP code');
        }
        
        // Mark user as verified
        $user->update([
            'verified' => true,
            'phone_verified_at' => now()
        ]);
        
        // Generate JWT tokens
        $accessToken = JWTAuth::fromUser($user);
        $refreshToken = $this->generateRefreshToken($user);
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => $user->load('profile')
        ];
    }
    
    private function generateOTP(User $user): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        OtpCode::create([
            'user_id' => $user->id,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(5)
        ]);
        
        return $code;
    }
    
    private function sendOTP(string $phone, string $code): void
    {
        // Integration with SMS provider (Twilio/Unifonic)
        // Implementation depends on chosen provider
    }
}
```

### **1.2 OTP Management System**

```php
<?php
// app/Models/OtpCode.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'expires_at',
        'used_at'
    ];
    
    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
    
    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }
}
```

### **1.3 Authentication API Endpoints**

```php
<?php
// routes/api.php (Auth Service)

use App\Http\Controllers\AuthController;

Route::prefix('v1/auth')->group(function () {
    // Registration flow
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOTP']);
    Route::post('/resend-otp', [AuthController::class, 'resendOTP']);
    
    // Login flow
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
    
    // Password management
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    
    // Profile management
    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});
```

---

## 👥 Phase 2: User Service Implementation

### **2.1 Customer Profile Management**

```php
<?php
// app/Models/CustomerProfile.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'national_address',
        'default_location',
        'preferences'
    ];
    
    protected $casts = [
        'default_location' => 'array',
        'preferences' => 'array'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'customer_id');
    }
    
    public function partRequests()
    {
        return $this->hasMany(PartRequest::class, 'customer_id');
    }
}
```

### **2.2 Vehicle Management System**

```php
<?php
// app/Models/Vehicle.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'customer_id',
        'brand_id',
        'model_id',
        'trim_id',
        'year',
        'vin',
        'is_primary',
        'custom_name',
        'mileage',
        'engine_type',
        'transmission_type'
    ];
    
    protected $casts = [
        'is_primary' => 'boolean',
        'year' => 'integer',
        'mileage' => 'integer'
    ];
    
    public function customer()
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_id');
    }
    
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
    
    public function model()
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }
    
    public function trim()
    {
        return $this->belongsTo(Trim::class);
    }
    
    public function getFullNameAttribute(): string
    {
        return "{$this->year} {$this->brand->name} {$this->model->name} {$this->trim->name}";
    }
}
```

---

## 📋 Phase 3: Order Service Implementation

Based on the **Bidding System Flow Diagram**:

### **3.1 Part Request Management**

```php
<?php
// app/Models/PartRequest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartRequest extends Model
{
    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_BIDDING_OPEN = 'bidding_open';
    const STATUS_BIDDING_CLOSED = 'bidding_closed';
    const STATUS_REVIEWING = 'reviewing';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    
    protected $fillable = [
        'customer_id',
        'vehicle_id',
        'title',
        'description',
        'status',
        'total_parts',
        'preferred_pickup',
        'bidding_duration',
        'expires_at',
        'budget_min',
        'budget_max',
        'urgency_level',
        'images'
    ];
    
    protected $casts = [
        'expires_at' => 'datetime',
        'images' => 'array',
        'total_parts' => 'integer',
        'bidding_duration' => 'integer',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2'
    ];
    
    public function customer()
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_id');
    }
    
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
    
    public function items()
    {
        return $this->hasMany(PartRequestItem::class, 'request_id');
    }
    
    public function bids()
    {
        return $this->hasMany(Bid::class, 'request_id');
    }
    
    public function winningBid()
    {
        return $this->hasOne(Bid::class, 'request_id')->where('status', 'accepted');
    }
    
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
    
    public function canReceiveBids(): bool
    {
        return in_array($this->status, [self::STATUS_BIDDING_OPEN]) && !$this->isExpired();
    }
}
```

### **3.2 Part Request Items**

```php
<?php
// app/Models/PartRequestItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartRequestItem extends Model
{
    protected $fillable = [
        'request_id',
        'part_name',
        'part_number',
        'quantity',
        'condition',
        'description',
        'images',
        'estimated_price'
    ];
    
    protected $casts = [
        'images' => 'array',
        'quantity' => 'integer',
        'estimated_price' => 'decimal:2'
    ];
    
    public function request()
    {
        return $this->belongsTo(PartRequest::class, 'request_id');
    }
    
    public function bidItems()
    {
        return $this->hasMany(BidItem::class, 'request_item_id');
    }
}
```

---

## 🎯 Phase 4: Bidding Service Implementation

Based on the **Real-time Bidding Architecture**:

### **4.1 Bid Management System**

```php
<?php
// app/Models/Bid.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';
    
    protected $fillable = [
        'request_id',
        'merchant_id',
        'total_amount',
        'status',
        'notes',
        'delivery_time',
        'warranty_period',
        'payment_terms',
        'expires_at'
    ];
    
    protected $casts = [
        'total_amount' => 'decimal:2',
        'delivery_time' => 'integer',
        'warranty_period' => 'integer',
        'expires_at' => 'datetime'
    ];
    
    public function request()
    {
        return $this->belongsTo(PartRequest::class, 'request_id');
    }
    
    public function merchant()
    {
        return $this->belongsTo(MerchantProfile::class, 'merchant_id');
    }
    
    public function items()
    {
        return $this->hasMany(BidItem::class, 'bid_id');
    }
    
    public function order()
    {
        return $this->hasOne(Order::class, 'bid_id');
    }
    
    public function calculateRank(): float
    {
        // Ranking algorithm based on price, merchant rating, delivery time
        $priceScore = $this->calculatePriceScore();
        $merchantScore = $this->merchant->rating * 20;
        $deliveryScore = $this->calculateDeliveryScore();
        
        return ($priceScore * 0.5) + ($merchantScore * 0.3) + ($deliveryScore * 0.2);
    }
}
```

### **4.2 Real-time Bidding Service**

```php
<?php
// app/Services/BiddingService.php

namespace App\Services;

use App\Models\Bid;
use App\Models\PartRequest;
use App\Events\NewBidReceived;
use App\Events\BiddingClosed;
use Illuminate\Support\Facades\DB;

class BiddingService
{
    public function submitBid(array $data): Bid
    {
        return DB::transaction(function () use ($data) {
            $request = PartRequest::findOrFail($data['request_id']);
            
            // Validate bidding is still open
            if (!$request->canReceiveBids()) {
                throw new \Exception('Bidding is closed for this request');
            }
            
            // Create bid
            $bid = Bid::create($data);
            
            // Create bid items
            foreach ($data['items'] as $itemData) {
                $bid->items()->create($itemData);
            }
            
            // Calculate total amount
            $bid->update([
                'total_amount' => $bid->items()->sum('total_price')
            ]);
            
            // Broadcast new bid event
            broadcast(new NewBidReceived($bid))->toOthers();
            
            // Update request bid count
            $request->increment('total_bids');
            
            return $bid->load('items', 'merchant');
        });
    }
    
    public function getRankedBids(int $requestId, int $limit = 5): Collection
    {
        $bids = Bid::where('request_id', $requestId)
            ->where('status', 'submitted')
            ->with(['merchant', 'items'])
            ->get();
            
        // Calculate ranks and sort
        return $bids->map(function ($bid) {
            $bid->rank_score = $bid->calculateRank();
            return $bid;
        })->sortByDesc('rank_score')->take($limit);
    }
    
    public function closeBidding(int $requestId): void
    {
        $request = PartRequest::findOrFail($requestId);
        
        $request->update([
            'status' => PartRequest::STATUS_BIDDING_CLOSED
        ]);
        
        // Broadcast bidding closed event
        broadcast(new BiddingClosed($request))->toOthers();
        
        // Schedule automatic expiry if no selection
        $this->scheduleRequestExpiry($request);
    }
}
```

---

## 📢 Phase 5: Notification Service Implementation

Based on the **Real-time Notification Architecture Diagram**:

### **5.1 Multi-channel Notification System**

```php
<?php
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use App\Jobs\SendPushNotification;
use App\Jobs\SendSMSNotification;
use App\Jobs\SendEmailNotification;

class NotificationService
{
    public function sendNotification(User $user, array $data): void
    {
        // Store in database
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'data' => $data['data'] ?? [],
            'channels' => $data['channels'] ?? ['database']
        ]);
        
        // Send via requested channels
        foreach ($data['channels'] as $channel) {
            $this->sendViaChannel($user, $notification, $channel);
        }
    }
    
    private function sendViaChannel(User $user, Notification $notification, string $channel): void
    {
        switch ($channel) {
            case 'push':
                SendPushNotification::dispatch($user, $notification);
                break;
            case 'sms':
                SendSMSNotification::dispatch($user, $notification);
                break;
            case 'email':
                SendEmailNotification::dispatch($user, $notification);
                break;
        }
    }
    
    // Event-specific notification methods
    public function notifyNewRequest(PartRequest $request): void
    {
        // Notify eligible merchants
        $merchants = $this->getEligibleMerchants($request);
        
        foreach ($merchants as $merchant) {
            $this->sendNotification($merchant->user, [
                'type' => 'new_request',
                'title' => 'طلب جديد متاح',
                'message' => "طلب جديد لقطع غيار {$request->vehicle->full_name}",
                'data' => ['request_id' => $request->id],
                'channels' => ['push', 'database']
            ]);
        }
    }
    
    public function notifyNewBid(Bid $bid): void
    {
        $customer = $bid->request->customer;
        
        $this->sendNotification($customer->user, [
            'type' => 'new_bid',
            'title' => 'عرض سعر جديد',
            'message' => "تم استلام عرض سعر جديد بقيمة {$bid->total_amount} ريال",
            'data' => ['bid_id' => $bid->id, 'request_id' => $bid->request_id],
            'channels' => ['push', 'database']
        ]);
    }
}
```

---

## 🚪 Phase 6: API Gateway Implementation

### **6.1 Request Routing and Rate Limiting**

```php
<?php
// app/Http/Middleware/ServiceRouter.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ServiceRouter
{
    private array $serviceMap = [
        'auth' => 'http://auth-service:80',
        'users' => 'http://user-service:80',
        'orders' => 'http://order-service:80',
        'bidding' => 'http://bidding-service:80',
        'notifications' => 'http://notification-service:80',
        'analytics' => 'http://analytics-service:80'
    ];
    
    public function handle(Request $request, Closure $next)
    {
        $service = $this->extractServiceFromPath($request->path());
        
        if (!isset($this->serviceMap[$service])) {
            return response()->json(['error' => 'Service not found'], 404);
        }
        
        // Forward request to appropriate service
        $response = Http::withHeaders($request->headers->all())
            ->timeout(30)
            ->send(
                $request->method(),
                $this->serviceMap[$service] . '/' . $request->path(),
                $request->all()
            );
            
        return response($response->body(), $response->status())
            ->withHeaders($response->headers());
    }
    
    private function extractServiceFromPath(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        return $segments[2] ?? 'unknown'; // /api/v1/{service}/...
    }
}
```

---

## 📊 Phase 7: Analytics Service Implementation

### **7.1 Business Intelligence System**

```php
<?php
// app/Services/AnalyticsService.php

namespace App\Services;

use App\Models\PartRequest;
use App\Models\Bid;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function getDashboardMetrics(): array
    {
        return [
            'total_requests' => PartRequest::count(),
            'active_requests' => PartRequest::where('status', 'bidding_open')->count(),
            'total_bids' => Bid::count(),
            'completed_orders' => Order::where('status', 'completed')->count(),
            'total_revenue' => $this->calculateTotalRevenue(),
            'average_bid_value' => Bid::avg('total_amount'),
            'merchant_satisfaction' => $this->calculateMerchantSatisfaction(),
            'customer_satisfaction' => $this->calculateCustomerSatisfaction()
        ];
    }
    
    public function getRequestAnalytics(array $filters = []): array
    {
        $query = PartRequest::query();
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        return [
            'requests_by_status' => $query->groupBy('status')->selectRaw('status, count(*) as count')->get(),
            'requests_by_day' => $query->selectRaw('DATE(created_at) as date, count(*) as count')
                ->groupBy('date')->orderBy('date')->get(),
            'popular_parts' => $this->getPopularParts($filters),
            'average_response_time' => $this->calculateAverageResponseTime($filters)
        ];
    }
}
```

---

## 🔄 Implementation Timeline

### **Phase 1: Foundation (Weeks 1-2)**
- ✅ Docker infrastructure setup
- ✅ Database schema creation
- ✅ Basic service structure

### **Phase 2: Core Services (Weeks 3-6)**
- 🔄 Auth Service implementation
- 🔄 User Service development
- 🔄 Order Service foundation
- 🔄 API Gateway setup

### **Phase 3: Advanced Features (Weeks 7-10)**
- ⏳ Bidding Service with real-time features
- ⏳ Notification Service implementation
- ⏳ WebSocket server integration

### **Phase 4: Integration & Testing (Weeks 11-12)**
- ⏳ Service integration testing
- ⏳ API documentation
- ⏳ Performance optimization

---

## 🧪 Testing Strategy

### **Unit Testing (60% Coverage)**
```php
<?php
// tests/Unit/Services/AuthServiceTest.php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AuthService;
use App\Models\User;

class AuthServiceTest extends TestCase
{
    public function test_user_registration_creates_user_and_sends_otp()
    {
        $authService = new AuthService();
        
        $result = $authService->register([
            'name' => 'Test User',
            'phone' => '+966501234567',
            'password' => 'password123',
            'type' => 'customer'
        ]);
        
        $this->assertArrayHasKey('user_id', $result);
        $this->assertDatabaseHas('users', [
            'phone' => '+966501234567',
            'verified' => false
        ]);
    }
}
```

### **Integration Testing (30% Coverage)**
```php
<?php
// tests/Feature/Api/AuthApiTest.php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;

class AuthApiTest extends TestCase
{
    public function test_registration_endpoint_returns_success()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone' => '+966501234567',
            'password' => 'password123',
            'type' => 'customer'
        ]);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'user_id',
                'message',
                'expires_in'
            ]);
    }
}
```

---

## 🚀 Deployment Configuration

### **Service-specific Environment Variables**
```bash
# Auth Service
JWT_SECRET=your-jwt-secret
JWT_TTL=1440
SMS_PROVIDER=unifonic
SMS_API_KEY=your-sms-api-key

# Bidding Service
WEBSOCKET_HOST=websocket-server
WEBSOCKET_PORT=6001
REDIS_HOST=redis
REDIS_PORT=6379

# Notification Service
PUSH_NOTIFICATION_KEY=your-push-key
EMAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
```

---

## 📋 Next Steps

1. **✅ Merge Foundation PR** - Establish the infrastructure
2. **🔄 Begin Auth Service** - Implement JWT + OTP system
3. **📋 Create User Service** - Customer and merchant profiles
4. **🎯 Develop Order Service** - Part request management
5. **⚡ Build Bidding Service** - Real-time auction system

---

**🚀 Ready for Backend Development!** This comprehensive plan provides the complete roadmap for implementing all backend services based on the detailed diagrams from the implementation plan.
