#!/bin/bash

# Script to fix database configuration in all service .env files

services=("user" "auction" "bidding" "payment" "order" "notification" "analytics" "vin-ocr" "gateway")

for service in "${services[@]}"; do
    env_file="services/${service}-service/.env"
    
    if [ -f "$env_file" ]; then
        echo "Updating $env_file..."
        
        # Update DB_CONNECTION from mysql to pgsql
        sed -i 's/DB_CONNECTION=mysql/DB_CONNECTION=pgsql/' "$env_file"
        
        # Update DB_PORT from 3306 to 5432
        sed -i 's/DB_PORT=3306/DB_PORT=5432/' "$env_file"
        
        # Update DB_USERNAME from root to postgres
        sed -i 's/DB_USERNAME=root/DB_USERNAME=postgres/' "$env_file"
        
        # Add database failover configuration if not present
        if ! grep -q "DATABASE_FAILOVER_ENABLED" "$env_file"; then
            cat >> "$env_file" << 'EOF'

# Database Failover Configuration
DATABASE_FAILOVER_ENABLED=true
DB_PRIMARY_CONNECTION=pgsql
DB_SECONDARY_CONNECTION=pgsql_secondary
DB_FALLBACK_CONNECTION=mongodb
DB_HEALTH_CHECK_INTERVAL=30
DB_AUTOMATIC_FAILOVER=true
DB_ENABLE_GRACEFUL_DEGRADATION=true

# Primary Database (Neon PostgreSQL)
NEON_DB_HOST=127.0.0.1
NEON_DB_PORT=5432
NEON_DB_DATABASE=SERVICE_DATABASE
NEON_DB_USERNAME=postgres
NEON_DB_PASSWORD=

# Secondary Database (Cloud PostgreSQL)
CLOUD_DB_HOST=127.0.0.1
CLOUD_DB_PORT=5433
CLOUD_DB_DATABASE=SERVICE_DATABASE
CLOUD_DB_USERNAME=postgres
CLOUD_DB_PASSWORD=

# Fallback Database (MongoDB)
MONGO_DB_HOST=127.0.0.1
MONGO_DB_PORT=27017
MONGO_DB_DATABASE=SERVICE_DATABASE
MONGO_DB_USERNAME=
MONGO_DB_PASSWORD=
EOF
            
            # Replace SERVICE_DATABASE with actual service database name
            case $service in
                "user") db_name="user_service" ;;
                "auction") db_name="auction_service" ;;
                "bidding") db_name="bidding_service" ;;
                "payment") db_name="payment_service" ;;
                "order") db_name="order_service" ;;
                "notification") db_name="notification_service" ;;
                "analytics") db_name="analytics_service" ;;
                "vin-ocr") db_name="vin_ocr_service" ;;
                "gateway") db_name="gateway_service" ;;
            esac
            
            sed -i "s/SERVICE_DATABASE/$db_name/g" "$env_file"
        fi
        
        echo "Updated $env_file"
    else
        echo "Warning: $env_file not found"
    fi
done

echo "All .env files updated successfully!"
