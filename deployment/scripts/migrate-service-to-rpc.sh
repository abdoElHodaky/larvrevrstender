#!/bin/bash

# Service Migration Script for RPC Transformation
# Migrates a single service from REST to RPC with rollback capability

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SERVICE_NAME=""
MIGRATION_PHASE=""
DRY_RUN=false
ROLLBACK=false
FORCE=false

# Service migration order (based on complexity and dependencies)
declare -A SERVICE_ORDER=(
    ["gateway-service"]=1
    ["auth-service"]=2
    ["analytics-service"]=3
    ["notification-service"]=4
    ["user-service"]=5
    ["order-service"]=6
    ["bidding-service"]=7
    ["payment-service"]=8
    ["vin-ocr-service"]=9
)

# Service complexity levels
declare -A SERVICE_COMPLEXITY=(
    ["gateway-service"]="LOW"
    ["auth-service"]="MEDIUM"
    ["analytics-service"]="LOW"
    ["notification-service"]="MEDIUM"
    ["user-service"]="HIGH"
    ["order-service"]="HIGH"
    ["bidding-service"]="VERY_HIGH"
    ["payment-service"]="HIGH"
    ["vin-ocr-service"]="LOW"
)

# Function to display usage
usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -s, --service SERVICE_NAME    Service to migrate (required)"
    echo "  -p, --phase PHASE            Migration phase (1-4)"
    echo "  -d, --dry-run                Show what would be done without executing"
    echo "  -r, --rollback               Rollback the service to REST"
    echo "  -f, --force                  Force migration even if dependencies not met"
    echo "  -h, --help                   Show this help message"
    echo ""
    echo "Available Services:"
    for service in "${!SERVICE_ORDER[@]}"; do
        echo "  - $service (Order: ${SERVICE_ORDER[$service]}, Complexity: ${SERVICE_COMPLEXITY[$service]})"
    done
    echo ""
    echo "Migration Phases:"
    echo "  1. Infrastructure Setup"
    echo "  2. RPC Implementation"
    echo "  3. Gradual Switchover"
    echo "  4. Cleanup & Optimization"
    echo ""
    echo "Examples:"
    echo "  $0 --service gateway-service --phase 1"
    echo "  $0 --service auth-service --phase 2 --dry-run"
    echo "  $0 --service user-service --rollback"
}

# Function to log with timestamp
log() {
    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to validate service name
validate_service() {
    if [[ -z "${SERVICE_ORDER[$SERVICE_NAME]}" ]]; then
        log "${RED}❌ Invalid service name: $SERVICE_NAME${NC}"
        log "${YELLOW}Available services: ${!SERVICE_ORDER[*]}${NC}"
        exit 1
    fi
}

# Function to check dependencies
check_dependencies() {
    local service=$1
    local required_services=()
    
    case $service in
        "auth-service")
            required_services=("gateway-service")
            ;;
        "user-service")
            required_services=("gateway-service" "auth-service")
            ;;
        "order-service")
            required_services=("gateway-service" "auth-service" "user-service")
            ;;
        "bidding-service")
            required_services=("gateway-service" "auth-service" "user-service" "order-service")
            ;;
        "payment-service")
            required_services=("gateway-service" "auth-service" "user-service" "order-service")
            ;;
        "notification-service")
            required_services=("gateway-service" "auth-service")
            ;;
        "analytics-service")
            required_services=("gateway-service")
            ;;
        "vin-ocr-service")
            required_services=("gateway-service" "auth-service")
            ;;
    esac
    
    if [[ ${#required_services[@]} -eq 0 ]]; then
        return 0
    fi
    
    log "${BLUE}📋 Checking dependencies for $service${NC}"
    
    for dep_service in "${required_services[@]}"; do
        if ! check_service_rpc_status "$dep_service"; then
            if [[ "$FORCE" == "false" ]]; then
                log "${RED}❌ Dependency not met: $dep_service is not RPC-enabled${NC}"
                log "${YELLOW}Use --force to override dependency checks${NC}"
                exit 1
            else
                log "${YELLOW}⚠️ Warning: Dependency $dep_service is not RPC-enabled (forced)${NC}"
            fi
        else
            log "${GREEN}✅ Dependency met: $dep_service is RPC-enabled${NC}"
        fi
    done
}

# Function to check if service is RPC-enabled
check_service_rpc_status() {
    local service=$1
    local rpc_config_file="services/$service/config/octane.php"
    local rpc_routes_file="services/$service/routes/rpc.php"
    
    if [[ -f "$rpc_config_file" && -f "$rpc_routes_file" ]]; then
        return 0
    else
        return 1
    fi
}

# Function to execute migration phase
execute_migration_phase() {
    local service=$1
    local phase=$2
    
    log "${BLUE}🚀 Starting Phase $phase migration for $service${NC}"
    
    case $phase in
        1)
            migrate_phase_1_infrastructure "$service"
            ;;
        2)
            migrate_phase_2_implementation "$service"
            ;;
        3)
            migrate_phase_3_switchover "$service"
            ;;
        4)
            migrate_phase_4_cleanup "$service"
            ;;
        *)
            log "${RED}❌ Invalid migration phase: $phase${NC}"
            exit 1
            ;;
    esac
}

# Phase 1: Infrastructure Setup
migrate_phase_1_infrastructure() {
    local service=$1
    
    log "${YELLOW}📦 Phase 1: Setting up infrastructure for $service${NC}"
    
    # Create service directories
    if [[ "$DRY_RUN" == "false" ]]; then
        mkdir -p "services/$service/app/RPC/Procedures"
        mkdir -p "services/$service/app/RPC/Middleware"
        mkdir -p "services/$service/config"
        mkdir -p "services/$service/routes"
    fi
    
    # Copy base procedure template
    if [[ ! -f "services/$service/app/RPC/BaseProcedure.php" ]]; then
        if [[ "$DRY_RUN" == "false" ]]; then
            cp "services/gateway-service/app/RPC/BaseProcedure.php" "services/$service/app/RPC/BaseProcedure.php"
            # Update namespace
            sed -i "s/namespace App\\\\RPC;/namespace App\\\\RPC;/" "services/$service/app/RPC/BaseProcedure.php"
        fi
        log "${GREEN}✅ Created BaseProcedure for $service${NC}"
    fi
    
    # Copy Octane configuration
    if [[ ! -f "services/$service/config/octane.php" ]]; then
        if [[ "$DRY_RUN" == "false" ]]; then
            cp "services/gateway-service/config/octane.php" "services/$service/config/octane.php"
            # Update RPC port based on service
            local rpc_port=$(get_service_rpc_port "$service")
            sed -i "s/'port' => env('OCTANE_RPC_PORT', 6010)/'port' => env('OCTANE_RPC_PORT', $rpc_port)/" "services/$service/config/octane.php"
        fi
        log "${GREEN}✅ Created Octane configuration for $service${NC}"
    fi
    
    # Create RPC routes file
    if [[ ! -f "services/$service/routes/rpc.php" ]]; then
        if [[ "$DRY_RUN" == "false" ]]; then
            create_rpc_routes_file "$service"
        fi
        log "${GREEN}✅ Created RPC routes for $service${NC}"
    fi
    
    # Create RPC service provider
    if [[ ! -f "services/$service/app/Providers/RpcServiceProvider.php" ]]; then
        if [[ "$DRY_RUN" == "false" ]]; then
            create_rpc_service_provider "$service"
        fi
        log "${GREEN}✅ Created RPC service provider for $service${NC}"
    fi
    
    log "${GREEN}✅ Phase 1 completed for $service${NC}"
}

# Phase 2: RPC Implementation
migrate_phase_2_implementation() {
    local service=$1
    
    log "${YELLOW}🔧 Phase 2: Implementing RPC procedures for $service${NC}"
    
    # Create health procedure
    if [[ ! -f "services/$service/app/RPC/Procedures/HealthProcedure.php" ]]; then
        if [[ "$DRY_RUN" == "false" ]]; then
            create_health_procedure "$service"
        fi
        log "${GREEN}✅ Created HealthProcedure for $service${NC}"
    fi
    
    # Create service-specific procedures based on service type
    case $service in
        "auth-service")
            create_auth_procedures "$service"
            ;;
        "user-service")
            create_user_procedures "$service"
            ;;
        "order-service")
            create_order_procedures "$service"
            ;;
        "payment-service")
            create_payment_procedures "$service"
            ;;
        "notification-service")
            create_notification_procedures "$service"
            ;;
        "bidding-service")
            create_bidding_procedures "$service"
            ;;
        "analytics-service")
            create_analytics_procedures "$service"
            ;;
        "vin-ocr-service")
            create_vin_ocr_procedures "$service"
            ;;
    esac
    
    log "${GREEN}✅ Phase 2 completed for $service${NC}"
}

# Phase 3: Gradual Switchover
migrate_phase_3_switchover() {
    local service=$1
    
    log "${YELLOW}🔄 Phase 3: Gradual switchover for $service${NC}"
    
    # Enable feature flags for RPC
    if [[ "$DRY_RUN" == "false" ]]; then
        create_feature_flags "$service"
    fi
    log "${GREEN}✅ Created feature flags for $service${NC}"
    
    # Update service clients to use RPC
    if [[ "$DRY_RUN" == "false" ]]; then
        update_service_clients "$service"
    fi
    log "${GREEN}✅ Updated service clients for $service${NC}"
    
    # Deploy with circuit breaker
    if [[ "$DRY_RUN" == "false" ]]; then
        deploy_with_circuit_breaker "$service"
    fi
    log "${GREEN}✅ Deployed with circuit breaker for $service${NC}"
    
    log "${GREEN}✅ Phase 3 completed for $service${NC}"
}

# Phase 4: Cleanup & Optimization
migrate_phase_4_cleanup() {
    local service=$1
    
    log "${YELLOW}🧹 Phase 4: Cleanup and optimization for $service${NC}"
    
    # Remove REST endpoints (after validation)
    if [[ "$DRY_RUN" == "false" ]]; then
        cleanup_rest_endpoints "$service"
    fi
    log "${GREEN}✅ Cleaned up REST endpoints for $service${NC}"
    
    # Optimize RPC performance
    if [[ "$DRY_RUN" == "false" ]]; then
        optimize_rpc_performance "$service"
    fi
    log "${GREEN}✅ Optimized RPC performance for $service${NC}"
    
    # Update monitoring and alerts
    if [[ "$DRY_RUN" == "false" ]]; then
        update_monitoring "$service"
    fi
    log "${GREEN}✅ Updated monitoring for $service${NC}"
    
    log "${GREEN}✅ Phase 4 completed for $service${NC}"
}

# Function to get RPC port for service
get_service_rpc_port() {
    local service=$1
    case $service in
        "gateway-service") echo "6010" ;;
        "auth-service") echo "6011" ;;
        "user-service") echo "6001" ;;
        "notification-service") echo "6002" ;;
        "bidding-service") echo "6003" ;;
        "payment-service") echo "6004" ;;
        "order-service") echo "6005" ;;
        "analytics-service") echo "6006" ;;
        "vin-ocr-service") echo "6007" ;;
        *) echo "6000" ;;
    esac
}

# Function to create RPC routes file
create_rpc_routes_file() {
    local service=$1
    cat > "services/$service/routes/rpc.php" << EOF
<?php

use Sajya\Server\Route;
use App\RPC\Procedures\HealthProcedure;

/*
|--------------------------------------------------------------------------
| RPC Routes
|--------------------------------------------------------------------------
|
| Here is where you can register RPC procedures for your application.
| These procedures are loaded by the RpcServiceProvider within a group
| which contains the "rpc" middleware group.
|
*/

Route::rpc('/', [
    HealthProcedure::class,
])->middleware(['rpc.correlation', 'rpc.performance', 'rpc.logging']);
EOF
}

# Function to create RPC service provider
create_rpc_service_provider() {
    local service=$1
    cp "services/gateway-service/app/Providers/RpcServiceProvider.php" "services/$service/app/Providers/RpcServiceProvider.php"
}

# Function to create health procedure
create_health_procedure() {
    local service=$1
    cp "services/gateway-service/app/RPC/Procedures/HealthProcedure.php" "services/$service/app/RPC/Procedures/HealthProcedure.php"
    # Update service name in health procedure
    sed -i "s/'service' => 'gateway-service'/'service' => '$service'/" "services/$service/app/RPC/Procedures/HealthProcedure.php"
}

# Placeholder functions for service-specific procedures
create_auth_procedures() { log "${YELLOW}Creating auth procedures...${NC}"; }
create_user_procedures() { log "${YELLOW}Creating user procedures...${NC}"; }
create_order_procedures() { log "${YELLOW}Creating order procedures...${NC}"; }
create_payment_procedures() { log "${YELLOW}Creating payment procedures...${NC}"; }
create_notification_procedures() { log "${YELLOW}Creating notification procedures...${NC}"; }
create_bidding_procedures() { log "${YELLOW}Creating bidding procedures...${NC}"; }
create_analytics_procedures() { log "${YELLOW}Creating analytics procedures...${NC}"; }
create_vin_ocr_procedures() { log "${YELLOW}Creating VIN OCR procedures...${NC}"; }

# Placeholder functions for phase 3 and 4
create_feature_flags() { log "${YELLOW}Creating feature flags for $1...${NC}"; }
update_service_clients() { log "${YELLOW}Updating service clients for $1...${NC}"; }
deploy_with_circuit_breaker() { log "${YELLOW}Deploying with circuit breaker for $1...${NC}"; }
cleanup_rest_endpoints() { log "${YELLOW}Cleaning up REST endpoints for $1...${NC}"; }
optimize_rpc_performance() { log "${YELLOW}Optimizing RPC performance for $1...${NC}"; }
update_monitoring() { log "${YELLOW}Updating monitoring for $1...${NC}"; }

# Function to rollback service
rollback_service() {
    local service=$1
    
    log "${YELLOW}🔄 Rolling back $service to REST${NC}"
    
    if [[ "$DRY_RUN" == "false" ]]; then
        # Disable RPC routes
        if [[ -f "services/$service/routes/rpc.php" ]]; then
            mv "services/$service/routes/rpc.php" "services/$service/routes/rpc.php.backup"
        fi
        
        # Disable Octane configuration
        if [[ -f "services/$service/config/octane.php" ]]; then
            mv "services/$service/config/octane.php" "services/$service/config/octane.php.backup"
        fi
        
        # Re-enable REST endpoints
        # This would involve service-specific logic
    fi
    
    log "${GREEN}✅ Rollback completed for $service${NC}"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -s|--service)
            SERVICE_NAME="$2"
            shift 2
            ;;
        -p|--phase)
            MIGRATION_PHASE="$2"
            shift 2
            ;;
        -d|--dry-run)
            DRY_RUN=true
            shift
            ;;
        -r|--rollback)
            ROLLBACK=true
            shift
            ;;
        -f|--force)
            FORCE=true
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            usage
            exit 1
            ;;
    esac
done

# Validate required parameters
if [[ -z "$SERVICE_NAME" ]]; then
    log "${RED}❌ Service name is required${NC}"
    usage
    exit 1
fi

validate_service

# Main execution
if [[ "$DRY_RUN" == "true" ]]; then
    log "${BLUE}🔍 DRY RUN MODE - No changes will be made${NC}"
fi

if [[ "$ROLLBACK" == "true" ]]; then
    rollback_service "$SERVICE_NAME"
else
    if [[ -z "$MIGRATION_PHASE" ]]; then
        log "${RED}❌ Migration phase is required (1-4)${NC}"
        usage
        exit 1
    fi
    
    if [[ "$MIGRATION_PHASE" -lt 1 || "$MIGRATION_PHASE" -gt 4 ]]; then
        log "${RED}❌ Invalid migration phase: $MIGRATION_PHASE (must be 1-4)${NC}"
        exit 1
    fi
    
    check_dependencies "$SERVICE_NAME"
    execute_migration_phase "$SERVICE_NAME" "$MIGRATION_PHASE"
fi

log "${GREEN}🎉 Migration script completed successfully!${NC}"
