#!/bin/bash

# Services to fix (excluding auth-service, user-service, auction-service which are already done)
SERVICES=(
    "analytics-service"
    "bidding-service" 
    "gateway-service"
    "notification-service"
    "order-service"
    "payment-service"
    "vin-ocr-service"
)

echo "🔧 Fixing RPC authentication middleware registration..."

for service in "${SERVICES[@]}"; do
    echo "Fixing $service..."
    
    # Fix RpcServiceProvider.php
    provider_file="services/$service/app/Providers/RpcServiceProvider.php"
    if [ -f "$provider_file" ]; then
        # Add rpc.auth middleware registration
        sed -i "/aliasMiddleware('rpc\.logging'/a\\        \$router->aliasMiddleware('rpc.auth', \\\\Shared\\\\RPC\\\\Middleware\\\\RpcAuthMiddleware::class);" "$provider_file"
        echo "  ✅ Updated $provider_file"
    else
        echo "  ❌ $provider_file not found"
    fi
    
    # Fix routes/rpc.php
    routes_file="services/$service/routes/rpc.php"
    if [ -f "$routes_file" ]; then
        # Add rpc.auth to middleware array
        sed -i "s/'rpc\.logging'\]/'rpc.logging', 'rpc.auth']/g" "$routes_file"
        echo "  ✅ Updated $routes_file"
    else
        echo "  ❌ $routes_file not found"
    fi
done

echo "🎯 RPC authentication middleware fix completed!"
