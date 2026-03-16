#!/bin/bash

echo "🔧 Optimizing environment configuration for develop branch..."
echo ""

# Generate a secure app key for Laravel
generate_app_key() {
    echo "base64:$(openssl rand -base64 32)"
}

# Generate a secure JWT secret
generate_jwt_secret() {
    openssl rand -base64 64
}

# Generate RPC tokens for service-to-service communication
generate_rpc_token() {
    echo "rpc_$(openssl rand -hex 32)"
}

# Service port mapping
declare -A SERVICE_PORTS=(
    ["auth-service"]=8001
    ["user-service"]=8002
    ["auction-service"]=8003
    ["bidding-service"]=8004
    ["order-service"]=8005
    ["payment-service"]=8006
    ["analytics-service"]=8007
    ["notification-service"]=8008
    ["gateway-service"]=8009
    ["vin-ocr-service"]=8010
)

# Database configuration for each service
declare -A SERVICE_DATABASES=(
    ["auth-service"]="auth_service_db"
    ["user-service"]="user_service_db"
    ["auction-service"]="auction_service_db"
    ["bidding-service"]="bidding_service_db"
    ["order-service"]="order_service_db"
    ["payment-service"]="payment_service_db"
    ["analytics-service"]="analytics_service_db"
    ["notification-service"]="notification_service_db"
    ["gateway-service"]="gateway_service_db"
    ["vin-ocr-service"]="vin_ocr_service_db"
)

echo "🔑 Generating secure keys and tokens..."

# Generate master keys
APP_KEY=$(generate_app_key)
JWT_SECRET=$(generate_jwt_secret)
REDIS_PASSWORD=$(openssl rand -hex 16)

echo "✅ Generated master keys"

# Generate RPC tokens for each service
declare -A RPC_TOKENS
for service in "${!SERVICE_PORTS[@]}"; do
    RPC_TOKENS[$service]=$(generate_rpc_token)
done

echo "✅ Generated RPC tokens for all services"

# Update each service's .env file
for service in "${!SERVICE_PORTS[@]}"; do
    env_file="services/$service/.env"
    
    if [ -f "$env_file" ]; then
        echo "🔧 Optimizing $service configuration..."
        
        # Update basic Laravel configuration
        sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" "$env_file"
        sed -i "s|^APP_ENV=.*|APP_ENV=develop|" "$env_file"
        sed -i "s|^APP_DEBUG=.*|APP_DEBUG=true|" "$env_file"
        sed -i "s|^APP_URL=.*|APP_URL=http://localhost:${SERVICE_PORTS[$service]}|" "$env_file"
        
        # Update database configuration
        sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" "$env_file"
        sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" "$env_file"
        sed -i "s|^DB_PORT=.*|DB_PORT=3306|" "$env_file"
        sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${SERVICE_DATABASES[$service]}|" "$env_file"
        sed -i "s|^DB_USERNAME=.*|DB_USERNAME=root|" "$env_file"
        sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=password|" "$env_file"
        
        # Update Redis configuration
        sed -i "s|^REDIS_HOST=.*|REDIS_HOST=127.0.0.1|" "$env_file"
        sed -i "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=$REDIS_PASSWORD|" "$env_file"
        sed -i "s|^REDIS_PORT=.*|REDIS_PORT=6379|" "$env_file"
        
        # Update JWT configuration (if exists)
        if grep -q "^JWT_SECRET=" "$env_file"; then
            sed -i "s|^JWT_SECRET=.*|JWT_SECRET=$JWT_SECRET|" "$env_file"
        fi
        
        # Update RPC token for this service
        if grep -q "RPC_.*_TOKEN=" "$env_file"; then
            # Update all RPC token lines with the service's token
            sed -i "s|^RPC_.*_TOKEN=.*|RPC_SERVICE_TOKEN=${RPC_TOKENS[$service]}|" "$env_file"
        fi
        
        # Add RPC service token if not exists
        if ! grep -q "RPC_SERVICE_TOKEN=" "$env_file"; then
            echo "" >> "$env_file"
            echo "# RPC Authentication Token" >> "$env_file"
            echo "RPC_SERVICE_TOKEN=${RPC_TOKENS[$service]}" >> "$env_file"
        fi
        
        # Update service URLs for inter-service communication
        for target_service in "${!SERVICE_PORTS[@]}"; do
            if [ "$service" != "$target_service" ]; then
                service_url_var=$(echo "${target_service^^}" | tr '-' '_')_SERVICE_URL
                service_url="http://localhost:${SERVICE_PORTS[$target_service]}"
                
                if grep -q "^$service_url_var=" "$env_file"; then
                    sed -i "s|^$service_url_var=.*|$service_url_var=$service_url|" "$env_file"
                fi
                
                # Update RPC service URLs
                rpc_url_var="RPC_$(echo "${target_service^^}" | tr '-' '_')_SERVICE_URL"
                rpc_url="http://localhost:${SERVICE_PORTS[$target_service]}/rpc"
                
                if grep -q "^$rpc_url_var=" "$env_file"; then
                    sed -i "s|^$rpc_url_var=.*|$rpc_url_var=$rpc_url|" "$env_file"
                fi
                
                # Update RPC service tokens
                rpc_token_var="RPC_$(echo "${target_service^^}" | tr '-' '_')_SERVICE_TOKEN"
                
                if grep -q "^$rpc_token_var=" "$env_file"; then
                    sed -i "s|^$rpc_token_var=.*|$rpc_token_var=${RPC_TOKENS[$target_service]}|" "$env_file"
                fi
            fi
        done
        
        echo "  ✅ Updated $service/.env"
    else
        echo "  ⚠️  $service/.env not found"
    fi
done

echo ""
echo "🎯 Environment optimization completed!"
echo ""
echo "📋 Configuration Summary:"
echo "  🔑 APP_KEY: Generated secure key for all services"
echo "  🔐 JWT_SECRET: Generated secure JWT secret"
echo "  🔒 REDIS_PASSWORD: Generated secure Redis password"
echo "  🌐 Service URLs: Configured for localhost development"
echo "  🗄️  Database: Configured separate databases for each service"
echo "  🔗 RPC Tokens: Generated unique tokens for each service"
echo ""
echo "⚠️  IMPORTANT NEXT STEPS:"
echo "  1. Create the databases listed above in MySQL"
echo "  2. Start Redis server with the configured password"
echo "  3. Run migrations for each service"
echo "  4. Test inter-service RPC communication"
echo ""
echo "🚀 The develop branch is now optimized for development!"
