#!/bin/bash
# Script to fix all service paths in CircleCI configuration
# Converts services/auth/ to services/auth-service/ etc.

set -e

CONFIG_FILE=".circleci/config.yml"
BACKUP_FILE=".circleci/config.yml.backup"

echo "🔧 Fixing service paths in CircleCI configuration..."

# Create backup
cp "$CONFIG_FILE" "$BACKUP_FILE"
echo "✅ Backup created: $BACKUP_FILE"

# Define services that need -service suffix
SERVICES_WITH_SUFFIX=(auth user auction bidding payment gateway order analytics notification vin-ocr)

# Fix each service path
for service in "${SERVICES_WITH_SUFFIX[@]}"; do
    echo "🔄 Fixing paths for $service service..."
    
    # Replace all occurrences of services/SERVICE/ with services/SERVICE-service/
    sed -i "s|services/$service/|services/$service-service/|g" "$CONFIG_FILE"
    
    # Replace service parameter values (for commands)
    sed -i "s|service: services/$service\$|service: services/$service-service|g" "$CONFIG_FILE"
    
    echo "✅ Fixed $service service paths"
done

# Verify the changes
echo ""
echo "🔍 Verification - checking for remaining old paths:"
for service in "${SERVICES_WITH_SUFFIX[@]}"; do
    if grep -q "services/$service[^-]" "$CONFIG_FILE"; then
        echo "⚠️  Warning: Found remaining old path for $service"
        grep -n "services/$service[^-]" "$CONFIG_FILE" || true
    else
        echo "✅ $service: All paths updated correctly"
    fi
done

# Check YAML syntax
echo ""
echo "🔍 Validating YAML syntax..."
if python3 -c "import yaml; yaml.safe_load(open('$CONFIG_FILE'))" 2>/dev/null; then
    echo "✅ YAML syntax is valid"
else
    echo "❌ YAML syntax errors found - restoring backup"
    cp "$BACKUP_FILE" "$CONFIG_FILE"
    exit 1
fi

echo ""
echo "🎯 Service path fixes completed successfully!"
echo "📊 Summary:"
echo "- Fixed paths for ${#SERVICES_WITH_SUFFIX[@]} services"
echo "- Shared service paths remain unchanged (services/shared/)"
echo "- YAML syntax validated"
echo "- Backup available at: $BACKUP_FILE"
