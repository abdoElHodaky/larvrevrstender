# 🔐 Authentication & Authorization Guide

Complete guide for Laravel Sanctum authentication with policies and gates implementation.

## 🎯 Overview

The Reverse Tender Platform uses **Laravel Sanctum** for API authentication combined with **Laravel Policies** and **Gates** for authorization. This approach provides:

- **Stateless API authentication** with personal access tokens
- **Role-based access control** using gates
- **Resource-based authorization** using policies
- **Built-in Laravel middleware** for security
- **Scalable permission system** for microservices

## 🔑 Laravel Sanctum Authentication

### Token-Based Authentication

Laravel Sanctum provides a simple way to authenticate SPAs and mobile applications using API tokens.

#### Configuration

```env
# Laravel Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:8000
SANCTUM_TOKEN_EXPIRATION=60
SANCTUM_TOKEN_PREFIX=
```

#### User Registration & Login

```php
// Register new user
$user = User::create([
    'name' => $request->name,
    'email' => $request->email,
    'password' => Hash::make($request->password),
    'type' => $request->type,
]);

// Create token with user-specific abilities
$token = $user->createToken('auth-token', $user->getTokenAbilities());

return [
    'user' => $user,
    'token' => $token->plainTextToken,
    'token_type' => 'Bearer',
];
```

#### Token Abilities by User Type

```php
public function getTokenAbilities(): array
{
    if ($this->isAdmin()) {
        return [
            'admin:read', 'admin:write', 'admin:delete',
            'users:manage', 'system:manage'
        ];
    } elseif ($this->isMerchant()) {
        return [
            'merchant:read', 'merchant:write',
            'bids:create', 'bids:update', 'orders:view'
        ];
    } else { // Customer
        return [
            'customer:read', 'customer:write',
            'orders:create', 'orders:update', 'bids:view'
        ];
    }
}
```

## 🚪 Gates for Role-Based Access Control

Gates provide a simple way to determine if a user is authorized to perform a given action.

### Gate Definitions

```php
// Role-based gates
Gate::define('admin-access', function (User $user) {
    return $user->isAdmin();
});

Gate::define('merchant-access', function (User $user) {
    return $user->isMerchant() || $user->isAdmin();
});

Gate::define('customer-access', function (User $user) {
    return $user->isCustomer() || $user->isAdmin();
});

// Status-based gates
Gate::define('verified-user', function (User $user) {
    return $user->isVerified();
});

Gate::define('active-user', function (User $user) {
    return $user->isActive();
});

// Business logic gates
Gate::define('create-order', function (User $user) {
    return $user->isCustomer() && $user->isVerified() && $user->isActive();
});

Gate::define('place-bid', function (User $user) {
    return $user->isMerchant() && $user->isVerified() && $user->isActive();
});
```

### Using Gates in Controllers

```php
public function createOrder(Request $request)
{
    // Check gate authorization
    if (!Gate::allows('create-order')) {
        return response()->error('Unauthorized', null, 403);
    }
    
    // Create order logic...
}
```

### Using Gates in Routes

```php
// Protect routes with gates
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/admin/users', [AdminController::class, 'getUsers'])
        ->middleware('can:manage-users');
    
    Route::post('/orders', [OrderController::class, 'create'])
        ->middleware('can:create-order');
    
    Route::post('/bids', [BidController::class, 'place'])
        ->middleware('can:place-bid');
});
```

## 🛡️ Policies for Resource Authorization

Policies organize authorization logic around a particular model or resource.

### UserPolicy Implementation

```php
class UserPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->id === $model->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }

    /**
     * Determine whether the user can manage sessions.
     */
    public function manageSessions(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->isAdmin();
    }
}
```

### Using Policies in Controllers

```php
public function updateProfile(Request $request)
{
    $user = $request->user();
    
    // Check policy authorization
    $this->authorize('update', $user);
    
    // Update profile logic...
}

public function deleteUser(Request $request, $userId)
{
    $targetUser = User::findOrFail($userId);
    
    // Check policy authorization
    $this->authorize('delete', $targetUser);
    
    // Delete user logic...
}
```

## 🛣️ Route Protection with Built-in Middleware

### Authentication Middleware

```php
// Use Laravel Sanctum middleware
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
});
```

### Authorization Middleware

```php
// Combine authentication with gate authorization
Route::middleware(['auth:sanctum'])->group(function () {
    // Admin routes
    Route::prefix('admin')->middleware('can:admin-access')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/users', [AdminController::class, 'users']);
    });
    
    // Merchant routes
    Route::prefix('merchant')->middleware('can:merchant-access')->group(function () {
        Route::get('/bids', [MerchantController::class, 'bids']);
        Route::post('/bids', [MerchantController::class, 'placeBid']);
    });
});
```

### Rate Limiting

```php
// Use Laravel's built-in throttle middleware
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/orders', [OrderController::class, 'create']);
});

// Different rate limits for different user types
Route::middleware(['auth:sanctum', 'throttle:admin'])->group(function () {
    Route::get('/admin/reports', [AdminController::class, 'reports']);
});
```

## 🔄 Token Management

### Creating Tokens

```php
// Create token with specific abilities
$token = $user->createToken('mobile-app', [
    'orders:create',
    'orders:update',
    'profile:update'
]);

// Create token with all abilities
$token = $user->createToken('web-app');
```

### Revoking Tokens

```php
// Revoke current token
$request->user()->currentAccessToken()->delete();

// Revoke specific token
$user->tokens()->where('id', $tokenId)->delete();

// Revoke all tokens
$user->tokens()->delete();
```

### Token Abilities

```php
// Check token abilities
if ($request->user()->tokenCan('orders:create')) {
    // User can create orders
}

// Check multiple abilities
if ($request->user()->tokenCan(['orders:create', 'orders:update'])) {
    // User can create and update orders
}
```

## 🏗️ Service Architecture

### AuthServiceProvider Registration

```php
protected $policies = [
    User::class => UserPolicy::class,
    Order::class => OrderPolicy::class,
    Bid::class => BidPolicy::class,
];

public function boot(): void
{
    $this->registerPolicies();
    
    // Register gates
    Gate::define('admin-access', function (User $user) {
        return $user->isAdmin();
    });
    
    // More gate definitions...
}
```

### Middleware Configuration

```php
// config/app.php - No custom middleware needed
'middleware' => [
    // Laravel built-in middleware
    \Illuminate\Http\Middleware\HandleCors::class,
    \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
    \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
    \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
],

'middlewareGroups' => [
    'api' => [
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
],

'routeMiddleware' => [
    'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
    'can' => \Illuminate\Auth\Middleware\Authorize::class,
    'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
],
```

## 📱 Frontend Integration

### API Authentication

```javascript
// Login and store token
const response = await fetch('/api/v1/auth/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        email: 'user@example.com',
        password: 'password'
    })
});

const data = await response.json();
localStorage.setItem('auth_token', data.token);
```

### Making Authenticated Requests

```javascript
// Include token in requests
const response = await fetch('/api/v1/profile', {
    headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        'Content-Type': 'application/json',
    }
});
```

### Token Refresh

```javascript
// Check token expiration and refresh if needed
const refreshToken = async () => {
    const response = await fetch('/api/v1/auth/refresh', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        }
    });
    
    if (response.ok) {
        const data = await response.json();
        localStorage.setItem('auth_token', data.token);
    }
};
```

## 🧪 Testing Authorization

### Testing Gates

```php
public function test_admin_can_access_admin_dashboard()
{
    $admin = User::factory()->admin()->create();
    
    $this->assertTrue(Gate::forUser($admin)->allows('admin-access'));
    
    $response = $this->actingAs($admin, 'sanctum')
        ->get('/api/v1/admin/dashboard');
    
    $response->assertStatus(200);
}

public function test_customer_cannot_access_admin_dashboard()
{
    $customer = User::factory()->customer()->create();
    
    $this->assertFalse(Gate::forUser($customer)->allows('admin-access'));
    
    $response = $this->actingAs($customer, 'sanctum')
        ->get('/api/v1/admin/dashboard');
    
    $response->assertStatus(403);
}
```

### Testing Policies

```php
public function test_user_can_update_own_profile()
{
    $user = User::factory()->create();
    
    $this->assertTrue($user->can('update', $user));
    
    $response = $this->actingAs($user, 'sanctum')
        ->put('/api/v1/profile', ['name' => 'New Name']);
    
    $response->assertStatus(200);
}

public function test_user_cannot_delete_other_user()
{
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    
    $this->assertFalse($user->can('delete', $otherUser));
    
    $response = $this->actingAs($user, 'sanctum')
        ->delete("/api/v1/users/{$otherUser->id}");
    
    $response->assertStatus(403);
}
```

## 🔒 Security Best Practices

### Token Security

1. **Use HTTPS**: Always use HTTPS in production
2. **Token Expiration**: Set appropriate token expiration times
3. **Token Rotation**: Implement token refresh mechanisms
4. **Secure Storage**: Store tokens securely on the client side

### Authorization Security

1. **Principle of Least Privilege**: Grant minimum required permissions
2. **Regular Audits**: Review and audit permissions regularly
3. **Input Validation**: Always validate input before authorization checks
4. **Error Handling**: Don't leak sensitive information in error messages

### Rate Limiting

```php
// Different rate limits for different operations
Route::middleware(['auth:sanctum', 'throttle:5,1'])->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
});
```

## 📊 Monitoring & Logging

### Authentication Events

```php
// Log authentication events
Event::listen('Illuminate\Auth\Events\Login', function ($event) {
    Log::info('User logged in', [
        'user_id' => $event->user->id,
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);
});

Event::listen('Illuminate\Auth\Events\Logout', function ($event) {
    Log::info('User logged out', [
        'user_id' => $event->user->id,
    ]);
});
```

### Authorization Failures

```php
// Log authorization failures
Gate::after(function ($user, $ability, $result, $arguments) {
    if (!$result) {
        Log::warning('Authorization failed', [
            'user_id' => $user->id,
            'ability' => $ability,
            'arguments' => $arguments,
            'ip' => request()->ip(),
        ]);
    }
});
```

## 🚀 Performance Optimization

### Token Caching

```php
// Cache user permissions
public function getPermissions()
{
    return Cache::remember("user.{$this->id}.permissions", 3600, function () {
        return $this->calculatePermissions();
    });
}
```

### Database Optimization

```php
// Eager load relationships for authorization
$users = User::with(['roles', 'permissions'])->get();

// Use database indexes for authorization queries
Schema::table('users', function (Blueprint $table) {
    $table->index(['type', 'status']);
    $table->index('email_verified_at');
});
```

---

This authentication and authorization system provides a robust, scalable foundation for the Reverse Tender Platform using Laravel's built-in security features.

