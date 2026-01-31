#!/bin/bash

# Generate service structure for all microservices
# This script creates the basic application structure for each service

SERVICES=("user-service" "bidding-service" "order-service" "payment-service" "analytics-service" "vin-ocr-service")

for service in "${SERVICES[@]}"; do
    echo "Generating structure for $service..."
    
    # Create directories
    mkdir -p "services/$service/app/Providers"
    mkdir -p "services/$service/app/Http/Controllers"
    mkdir -p "services/$service/app/Http/Middleware"
    mkdir -p "services/$service/routes"
    mkdir -p "services/$service/database/migrations"
    mkdir -p "services/$service/database/seeders"
    mkdir -p "services/$service/database/factories"
    
    # Copy base files from auth-service
    cp "services/auth-service/app/Http/Controllers/Controller.php" "services/$service/app/Http/Controllers/"
    cp "services/auth-service/app/Http/Controllers/HealthController.php" "services/$service/app/Http/Controllers/"
    
    # Create service-specific AppServiceProvider
    service_name_upper=$(echo "$service" | sed 's/-/ /g' | sed 's/\b\w/\U&/g' | sed 's/ //g')
    service_name_lower=$(echo "$service" | sed 's/-/_/g')
    
    cat > "services/$service/app/Providers/AppServiceProvider.php" << EOF
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Resources\Json\JsonResource;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register service-specific bindings
        \$this->app->singleton('${service_name_lower}.service', function (\$app) {
            return new \\App\\Services\\${service_name_upper}Service();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set default string length for MySQL
        Schema::defaultStringLength(191);

        // Remove data wrapping from JSON resources
        JsonResource::withoutWrapping();

        // Register custom validation rules
        \$this->registerCustomValidationRules();

        // Register event listeners
        \$this->registerEventListeners();
    }

    /**
     * Register custom validation rules.
     */
    private function registerCustomValidationRules(): void
    {
        // Add custom validation rules here
    }

    /**
     * Register event listeners.
     */
    private function registerEventListeners(): void
    {
        // Add event listeners here
    }
}
EOF

    # Create service-specific API routes
    service_prefix=$(echo "$service" | sed 's/-service//')
    
    cat > "services/$service/routes/api.php" << EOF
<?php

use App\\Http\\Controllers\\HealthController;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check routes
Route::get('/health', [HealthController::class, 'check']);
Route::get('/up', [HealthController::class, 'up']);

// Service info route
Route::get('/info', function () {
    return response()->json([
        'service' => '$service',
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env'),
        'timestamp' => now()->toISOString(),
    ]);
});

// ${service_name_upper} routes
Route::prefix('$service_prefix')->group(function () {
    // TODO: Add $service_prefix specific routes
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request \$request) {
        return \$request->user();
    });
});
EOF

    echo "✅ Generated structure for $service"
done

echo "🎉 All service structures generated successfully!"

