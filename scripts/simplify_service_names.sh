#!/bin/bash

# Service Name Simplification Script
# This script renames services and updates all references

set -e

echo "🚀 Starting Service Name Simplification..."

# Define service mappings (old_name:new_name)
declare -A SERVICE_MAPPINGS=(
    ["analytics-service"]="analytics"
    ["auction-service"]="auctions"
    ["auth-service"]="auth"
    ["bidding-service"]="bidding"
    ["gateway-service"]="gateway"
    ["notification-service"]="notifications"
    ["order-service"]="orders"
    ["payment-service"]="payments"
    ["user-service"]="users"
    ["vin-ocr-service"]="vin-ocr"
)

# Function to update file contents
update_file_references() {
    local file="$1"
    local old_name="$2"
    local new_name="$3"
    
    if [[ -f "$file" ]]; then
        # Update service references in various formats
        sed -i "s/${old_name}/${new_name}/g" "$file"
        sed -i "s/${old_name^^}/${new_name^^}/g" "$file"  # Uppercase
        sed -i "s/${old_name^}/${new_name^}/g" "$file"    # Capitalize first letter
    fi
}

# Function to rename service directory
rename_service_directory() {
    local old_name="$1"
    local new_name="$2"
    
    if [[ -d "services/$old_name" ]]; then
        echo "📁 Renaming services/$old_name → services/$new_name"
        mv "services/$old_name" "services/$new_name"
        
        # Update APP_NAME in .env.example
        if [[ -f "services/$new_name/.env.example" ]]; then
            sed -i "s/APP_NAME=\".*\"/APP_NAME=\"${new_name^}\"/" "services/$new_name/.env.example"
        fi
        
        # Update composer.json name if it exists
        if [[ -f "services/$new_name/composer.json" ]]; then
            sed -i "s/\"name\": \".*\"/\"name\": \"reverse-tender\/${new_name}\"/" "services/$new_name/composer.json"
        fi
        
        # Update README.md title
        if [[ -f "services/$new_name/README.md" ]]; then
            sed -i "1s/.*/# ${new_name^}/" "services/$new_name/README.md"
        fi
    fi
}

# Function to update inter-service references
update_inter_service_references() {
    echo "🔗 Updating inter-service references..."
    
    # Update all .env.example files
    find services -name ".env.example" -type f | while read -r env_file; do
        echo "  📝 Updating $env_file"
        
        # Update service URL references
        for old_name in "${!SERVICE_MAPPINGS[@]}"; do
            new_name="${SERVICE_MAPPINGS[$old_name]}"
            
            # Update URL variable names
            sed -i "s/${old_name^^}_SERVICE_URL/${new_name^^}_URL/g" "$env_file"
            sed -i "s/${old_name^^}_URL/${new_name^^}_URL/g" "$env_file"
        done
    done
    
    # Update Docker Compose files
    find . -name "docker-compose*.yml" -type f | while read -r compose_file; do
        echo "  🐳 Updating $compose_file"
        
        for old_name in "${!SERVICE_MAPPINGS[@]}"; do
            new_name="${SERVICE_MAPPINGS[$old_name]}"
            update_file_references "$compose_file" "$old_name" "$new_name"
        done
    done
    
    # Update documentation files
    find . -name "*.md" -type f | while read -r doc_file; do
        echo "  📚 Updating $doc_file"
        
        for old_name in "${!SERVICE_MAPPINGS[@]}"; do
            new_name="${SERVICE_MAPPINGS[$old_name]}"
            update_file_references "$doc_file" "$old_name" "$new_name"
        done
    done
}

# Main execution
main() {
    echo "🎯 Phase 1: Renaming service directories..."
    
    # Rename service directories
    for old_name in "${!SERVICE_MAPPINGS[@]}"; do
        new_name="${SERVICE_MAPPINGS[$old_name]}"
        rename_service_directory "$old_name" "$new_name"
    done
    
    echo "🎯 Phase 2: Updating inter-service references..."
    update_inter_service_references
    
    echo "✅ Service name simplification completed!"
    echo ""
    echo "📋 Summary of changes:"
    for old_name in "${!SERVICE_MAPPINGS[@]}"; do
        new_name="${SERVICE_MAPPINGS[$old_name]}"
        echo "  • $old_name → $new_name"
    done
    echo ""
    echo "🔍 Next steps:"
    echo "  1. Review the changes with 'git status'"
    echo "  2. Test service connectivity"
    echo "  3. Update any remaining hardcoded references"
    echo "  4. Update CI/CD pipeline configurations"
}

# Run the script
main "$@"

