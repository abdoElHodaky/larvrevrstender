#!/bin/bash

# Fix remaining URL patterns that weren't caught by the main script

set -e

echo "🔧 Fixing remaining URL patterns..."

# Function to fix URL patterns in a file
fix_url_patterns() {
    local env_file="$1"
    
    if [[ ! -f "$env_file" ]]; then
        return
    fi
    
    echo "  📝 Fixing $env_file"
    
    # Fix remaining service URL patterns
    sed -i 's/ANALYTICS_SERVICE_URL=/ANALYTICS_URL=/g' "$env_file"
    sed -i 's/AUCTION_SERVICE_URL=/AUCTIONS_URL=/g' "$env_file"
    sed -i 's/GATEWAY_SERVICE_URL=/GATEWAY_URL=/g' "$env_file"
    sed -i 's/NOTIFICATION_SERVICE_URL=/NOTIFICATIONS_URL=/g' "$env_file"
    sed -i 's/ORDER_SERVICE_URL=/ORDERS_URL=/g' "$env_file"
    sed -i 's/PAYMENT_SERVICE_URL=/PAYMENTS_URL=/g' "$env_file"
    sed -i 's/USER_SERVICE_URL=/USERS_URL=/g' "$env_file"
    sed -i 's/VIN_OCR_SERVICE_URL=/VIN_OCR_URL=/g' "$env_file"
    
    # Fix any remaining RPC URL patterns
    sed -i 's/ANALYTICS_SERVICE_RPC_URL=/ANALYTICS_URL=/g' "$env_file"
    sed -i 's/AUCTION_SERVICE_RPC_URL=/AUCTIONS_URL=/g' "$env_file"
    sed -i 's/GATEWAY_SERVICE_RPC_URL=/GATEWAY_URL=/g' "$env_file"
    sed -i 's/NOTIFICATION_SERVICE_RPC_URL=/NOTIFICATIONS_URL=/g' "$env_file"
    sed -i 's/ORDER_SERVICE_RPC_URL=/ORDERS_URL=/g' "$env_file"
    sed -i 's/PAYMENT_SERVICE_RPC_URL=/PAYMENTS_URL=/g' "$env_file"
    sed -i 's/USER_SERVICE_RPC_URL=/USERS_URL=/g' "$env_file"
    sed -i 's/VIN_OCR_SERVICE_RPC_URL=/VIN_OCR_URL=/g' "$env_file"
}

# Process all .env.example files
find services -name ".env.example" -type f | while read -r env_file; do
    fix_url_patterns "$env_file"
done

echo "✅ URL pattern fixes completed!"

