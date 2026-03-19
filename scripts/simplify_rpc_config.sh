#!/bin/bash

# RPC Configuration Simplification Script
# This script simplifies RPC configuration variable names

set -e

echo "🔧 Starting RPC Configuration Simplification..."

# Function to simplify RPC configuration in a file
simplify_rpc_config() {
    local env_file="$1"
    
    if [[ ! -f "$env_file" ]]; then
        return
    fi
    
    echo "  📝 Simplifying $env_file"
    
    # Create backup
    cp "$env_file" "${env_file}.backup"
    
    # Simplify RPC token variable names
    sed -i 's/RPC_AUTH_SERVICE_TOKEN=/AUTH_TOKEN=/g' "$env_file"
    sed -i 's/RPC_USER_SERVICE_TOKEN=/USERS_TOKEN=/g' "$env_file"
    sed -i 's/RPC_AUCTION_SERVICE_TOKEN=/AUCTIONS_TOKEN=/g' "$env_file"
    sed -i 's/RPC_BIDDING_SERVICE_TOKEN=/BIDDING_TOKEN=/g' "$env_file"
    sed -i 's/RPC_ORDER_SERVICE_TOKEN=/ORDERS_TOKEN=/g' "$env_file"
    sed -i 's/RPC_PAYMENT_SERVICE_TOKEN=/PAYMENTS_TOKEN=/g' "$env_file"
    sed -i 's/RPC_GATEWAY_SERVICE_TOKEN=/GATEWAY_TOKEN=/g' "$env_file"
    sed -i 's/RPC_NOTIFICATION_SERVICE_TOKEN=/NOTIFICATIONS_TOKEN=/g' "$env_file"
    sed -i 's/RPC_ANALYTICS_SERVICE_TOKEN=/ANALYTICS_TOKEN=/g' "$env_file"
    sed -i 's/RPC_VIN_OCR_SERVICE_TOKEN=/VIN_OCR_TOKEN=/g' "$env_file"
    
    # Simplify RPC URL variable names
    sed -i 's/AUTH_SERVICE_RPC_URL=/AUTH_URL=/g' "$env_file"
    sed -i 's/USER_SERVICE_RPC_URL=/USERS_URL=/g' "$env_file"
    sed -i 's/AUCTION_SERVICE_RPC_URL=/AUCTIONS_URL=/g' "$env_file"
    sed -i 's/BIDDING_SERVICE_RPC_URL=/BIDDING_URL=/g' "$env_file"
    sed -i 's/ORDER_SERVICE_RPC_URL=/ORDERS_URL=/g' "$env_file"
    sed -i 's/PAYMENT_SERVICE_RPC_URL=/PAYMENTS_URL=/g' "$env_file"
    sed -i 's/GATEWAY_SERVICE_RPC_URL=/GATEWAY_URL=/g' "$env_file"
    sed -i 's/NOTIFICATION_SERVICE_RPC_URL=/NOTIFICATIONS_URL=/g' "$env_file"
    sed -i 's/ANALYTICS_SERVICE_RPC_URL=/ANALYTICS_URL=/g' "$env_file"
    sed -i 's/VIN_OCR_SERVICE_RPC_URL=/VIN_OCR_URL=/g' "$env_file"
    
    # Simplify other service URL references
    sed -i 's/AUTH_SERVICE_URL=/AUTH_URL=/g' "$env_file"
    sed -i 's/USER_SERVICE_URL=/USERS_URL=/g' "$env_file"
    sed -i 's/BIDDING_SERVICE_URL=/BIDDING_URL=/g' "$env_file"
    sed -i 's/ORDER_SERVICE_URL=/ORDERS_URL=/g' "$env_file"
    sed -i 's/PAYMENT_SERVICE_URL=/PAYMENTS_URL=/g' "$env_file"
    sed -i 's/VIN_OCR_SERVICE_URL=/VIN_OCR_URL=/g' "$env_file"
    
    # Update comments to reflect simplified naming
    sed -i 's/# RPC Authentication Tokens (Generate unique tokens for each service)/# Service Authentication Tokens/g' "$env_file"
    sed -i 's/# RPC Service URLs/# Service URLs/g' "$env_file"
    sed -i 's/# RPC Configuration for Inter-Service Communication/# Inter-Service Communication Configuration/g' "$env_file"
}

# Function to update PHP configuration files
update_php_config_files() {
    echo "🔧 Updating PHP configuration files..."
    
    # Find and update config files that reference RPC variables
    find services -name "*.php" -type f | while read -r php_file; do
        if grep -q "RPC_.*_SERVICE_" "$php_file" 2>/dev/null; then
            echo "  📝 Updating $php_file"
            
            # Update environment variable references in PHP files
            sed -i "s/env('RPC_AUTH_SERVICE_TOKEN')/env('AUTH_TOKEN')/g" "$php_file"
            sed -i "s/env('RPC_USER_SERVICE_TOKEN')/env('USERS_TOKEN')/g" "$php_file"
            sed -i "s/env('RPC_AUCTION_SERVICE_TOKEN')/env('AUCTIONS_TOKEN')/g" "$php_file"
            sed -i "s/env('RPC_BIDDING_SERVICE_TOKEN')/env('BIDDING_TOKEN')/g" "$php_file"
            sed -i "s/env('RPC_ORDER_SERVICE_TOKEN')/env('ORDERS_TOKEN')/g" "$php_file"
            sed -i "s/env('RPC_PAYMENT_SERVICE_TOKEN')/env('PAYMENTS_TOKEN')/g" "$php_file"
            sed -i "s/env('RPC_GATEWAY_SERVICE_TOKEN')/env('GATEWAY_TOKEN')/g" "$php_file"
            sed -i "s/env('RPC_NOTIFICATION_SERVICE_TOKEN')/env('NOTIFICATIONS_TOKEN')/g" "$php_file"
            sed -i "s/env('RPC_ANALYTICS_SERVICE_TOKEN')/env('ANALYTICS_TOKEN')/g" "$php_file"
            sed -i "s/env('RPC_VIN_OCR_SERVICE_TOKEN')/env('VIN_OCR_TOKEN')/g" "$php_file"
            
            # Update URL references
            sed -i "s/env('AUTH_SERVICE_RPC_URL')/env('AUTH_URL')/g" "$php_file"
            sed -i "s/env('USER_SERVICE_RPC_URL')/env('USERS_URL')/g" "$php_file"
            sed -i "s/env('AUCTION_SERVICE_RPC_URL')/env('AUCTIONS_URL')/g" "$php_file"
            sed -i "s/env('BIDDING_SERVICE_RPC_URL')/env('BIDDING_URL')/g" "$php_file"
            sed -i "s/env('ORDER_SERVICE_RPC_URL')/env('ORDERS_URL')/g" "$php_file"
            sed -i "s/env('PAYMENT_SERVICE_RPC_URL')/env('PAYMENTS_URL')/g" "$php_file"
            sed -i "s/env('GATEWAY_SERVICE_RPC_URL')/env('GATEWAY_URL')/g" "$php_file"
            sed -i "s/env('NOTIFICATION_SERVICE_RPC_URL')/env('NOTIFICATIONS_URL')/g" "$php_file"
            sed -i "s/env('ANALYTICS_SERVICE_RPC_URL')/env('ANALYTICS_URL')/g" "$php_file"
            sed -i "s/env('VIN_OCR_SERVICE_RPC_URL')/env('VIN_OCR_URL')/g" "$php_file"
        fi
    done
}

# Main execution
main() {
    echo "🎯 Phase 1: Simplifying .env.example files..."
    
    # Process all .env.example files
    find services -name ".env.example" -type f | while read -r env_file; do
        simplify_rpc_config "$env_file"
    done
    
    echo "🎯 Phase 2: Updating PHP configuration references..."
    update_php_config_files
    
    echo "✅ RPC Configuration simplification completed!"
    echo ""
    echo "📋 Summary of changes:"
    echo "  • RPC_*_SERVICE_TOKEN → *_TOKEN"
    echo "  • *_SERVICE_RPC_URL → *_URL"
    echo "  • Updated PHP configuration references"
    echo "  • Created backup files (.backup extension)"
    echo ""
    echo "🔍 Next steps:"
    echo "  1. Review the changes with 'git diff'"
    echo "  2. Test service configurations"
    echo "  3. Update deployment scripts if needed"
    echo "  4. Remove backup files once validated"
}

# Run the script
main "$@"

