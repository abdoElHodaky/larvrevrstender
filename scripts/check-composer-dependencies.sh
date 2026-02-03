#!/bin/bash

# 🔍 Composer Dependency Checker Script
# This script checks composer dependencies for all RPC services

set -e

echo "🔍 RPC Services Composer Dependency Checker"
echo "============================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Services to check
SERVICES=(
    "shared-service"
    "auth-service"
    "user-service"
    "analytics-service"
    "order-service"
    "payment-service"
    "bidding-service"
    "notification-service"
    "vin-ocr-service"
)

# Summary variables
TOTAL_SERVICES=0
SERVICES_WITH_COMPOSER=0
SERVICES_WITH_LOCK=0
SERVICES_WITH_RPC=0
SERVICES_WITH_OCTANE=0

echo "📊 Checking ${#SERVICES[@]} RPC services..."
echo ""

for service in "${SERVICES[@]}"; do
    TOTAL_SERVICES=$((TOTAL_SERVICES + 1))
    
    echo -e "${BLUE}🔍 Checking: $service${NC}"
    echo "----------------------------------------"
    
    SERVICE_PATH="services/$service"
    
    if [ ! -d "$SERVICE_PATH" ]; then
        echo -e "${RED}❌ Service directory not found: $SERVICE_PATH${NC}"
        echo ""
        continue
    fi
    
    cd "$SERVICE_PATH"
    
    # Check composer.json
    if [ -f "composer.json" ]; then
        echo -e "${GREEN}✅ composer.json found${NC}"
        SERVICES_WITH_COMPOSER=$((SERVICES_WITH_COMPOSER + 1))
        
        # Extract PHP version requirement
        PHP_REQ=$(cat composer.json | grep -o '"php"[^,]*' | head -1 || echo 'Not specified')
        echo "   📋 PHP requirement: $PHP_REQ"
        
        # Check for RPC packages
        if grep -q "sajya/server" composer.json; then
            echo -e "${GREEN}   ✅ sajya/server (RPC server) found${NC}"
            SERVICES_WITH_RPC=$((SERVICES_WITH_RPC + 1))
        else
            echo -e "${YELLOW}   ⚠️ sajya/server not found${NC}"
        fi
        
        # Check for Laravel Octane
        if grep -q "laravel/octane" composer.json; then
            echo -e "${GREEN}   ✅ laravel/octane found${NC}"
            SERVICES_WITH_OCTANE=$((SERVICES_WITH_OCTANE + 1))
        else
            echo -e "${YELLOW}   ⚠️ laravel/octane not found${NC}"
        fi
        
    else
        echo -e "${RED}❌ composer.json not found${NC}"
    fi
    
    # Check composer.lock
    if [ -f "composer.lock" ]; then
        echo -e "${GREEN}✅ composer.lock found${NC}"
        SERVICES_WITH_LOCK=$((SERVICES_WITH_LOCK + 1))
        
        # Get lock file hash
        LOCK_HASH=$(md5sum composer.lock | cut -d' ' -f1)
        echo "   🔒 Lock file hash: ${LOCK_HASH:0:8}..."
    else
        echo -e "${YELLOW}⚠️ composer.lock not found${NC}"
    fi
    
    # Check vendor directory
    if [ -d "vendor" ]; then
        echo -e "${GREEN}✅ vendor directory exists${NC}"
        
        # Check autoloader
        if [ -f "vendor/autoload.php" ]; then
            echo -e "${GREEN}   ✅ autoloader present${NC}"
        else
            echo -e "${RED}   ❌ autoloader missing${NC}"
        fi
    else
        echo -e "${YELLOW}⚠️ vendor directory not found${NC}"
    fi
    
    cd - > /dev/null
    echo ""
done

# Summary report
echo "📊 DEPENDENCY CHECK SUMMARY"
echo "============================"
echo -e "Total services checked: ${BLUE}$TOTAL_SERVICES${NC}"
echo -e "Services with composer.json: ${GREEN}$SERVICES_WITH_COMPOSER${NC}/$TOTAL_SERVICES"
echo -e "Services with composer.lock: ${GREEN}$SERVICES_WITH_LOCK${NC}/$TOTAL_SERVICES"
echo -e "Services with RPC packages: ${GREEN}$SERVICES_WITH_RPC${NC}/$TOTAL_SERVICES"
echo -e "Services with Laravel Octane: ${GREEN}$SERVICES_WITH_OCTANE${NC}/$TOTAL_SERVICES"
echo ""

# Calculate percentages
COMPOSER_PERCENT=$((SERVICES_WITH_COMPOSER * 100 / TOTAL_SERVICES))
LOCK_PERCENT=$((SERVICES_WITH_LOCK * 100 / TOTAL_SERVICES))
RPC_PERCENT=$((SERVICES_WITH_RPC * 100 / TOTAL_SERVICES))
OCTANE_PERCENT=$((SERVICES_WITH_OCTANE * 100 / TOTAL_SERVICES))

echo "📈 COMPLETION PERCENTAGES"
echo "========================="
echo -e "Composer configuration: ${GREEN}${COMPOSER_PERCENT}%${NC}"
echo -e "Dependency locking: ${GREEN}${LOCK_PERCENT}%${NC}"
echo -e "RPC integration: ${GREEN}${RPC_PERCENT}%${NC}"
echo -e "Octane integration: ${GREEN}${OCTANE_PERCENT}%${NC}"
echo ""

# Recommendations
echo "💡 RECOMMENDATIONS"
echo "=================="

if [ $SERVICES_WITH_COMPOSER -lt $TOTAL_SERVICES ]; then
    echo -e "${YELLOW}⚠️ Some services missing composer.json - add composer configuration${NC}"
fi

if [ $SERVICES_WITH_LOCK -lt $SERVICES_WITH_COMPOSER ]; then
    echo -e "${YELLOW}⚠️ Some services missing composer.lock - run 'composer install' to generate${NC}"
fi

if [ $SERVICES_WITH_RPC -lt $SERVICES_WITH_COMPOSER ]; then
    echo -e "${YELLOW}⚠️ Some services missing RPC packages - add sajya/server to composer.json${NC}"
fi

if [ $SERVICES_WITH_OCTANE -lt $SERVICES_WITH_COMPOSER ]; then
    echo -e "${YELLOW}⚠️ Some services missing Octane - add laravel/octane to composer.json${NC}"
fi

if [ $SERVICES_WITH_RPC -eq $SERVICES_WITH_COMPOSER ] && [ $SERVICES_WITH_OCTANE -eq $SERVICES_WITH_COMPOSER ]; then
    echo -e "${GREEN}🎉 All services have complete RPC-Octane integration!${NC}"
fi

echo ""
echo "✅ Dependency check completed!"
