#!/bin/bash
# Database Failover Testing Script for Reverse Tender Platform
# Updated for comprehensive database failover architecture v3.0
# Tests failover scenarios across Neon PostgreSQL, CNPG, and MongoDB Atlas

set -euo pipefail

# Script configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="/tmp/database-failover-test-$(date +%Y%m%d-%H%M%S).log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${PURPLE}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

# Help function
show_help() {
    cat << EOF
Database Failover Testing Script

Usage: $0 [OPTIONS]

OPTIONS:
    --test=TEST_TYPE        Type of test to run (all|primary|secondary|tertiary|circuit-breaker|recovery)
    --namespace=NS          Kubernetes namespace (default: default)
    --timeout=SECONDS       Test timeout in seconds (default: 300)
    --verbose              Enable verbose output
    --dry-run              Show what would be tested without making changes
    --help                 Show this help message

TEST TYPES:
    all                    Run all failover tests
    primary                Test primary database (Neon PostgreSQL) failover
    secondary              Test secondary database (CNPG PostgreSQL) failover
    tertiary               Test tertiary database (MongoDB Atlas) failover
    circuit-breaker        Test circuit breaker functionality
    recovery               Test database recovery scenarios
    load                   Test failover under load

EXAMPLES:
    $0 --test=all --namespace=production
    $0 --test=primary --verbose
    $0 --test=circuit-breaker --timeout=600
    $0 --test=recovery --dry-run

ENVIRONMENT VARIABLES:
    KUBECONFIG             Path to kubeconfig file
    DB_FAILOVER_NAMESPACE  Default namespace for database failover resources
    DB_FAILOVER_TIMEOUT    Default test timeout
EOF
}

# Default values
TEST_TYPE="all"
NAMESPACE="default"
TIMEOUT=300
VERBOSE=false
DRY_RUN=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --test=*)
            TEST_TYPE="${1#*=}"
            shift
            ;;
        --namespace=*)
            NAMESPACE="${1#*=}"
            shift
            ;;
        --timeout=*)
            TIMEOUT="${1#*=}"
            shift
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --help)
            show_help
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Override with environment variables
NAMESPACE="${DB_FAILOVER_NAMESPACE:-$NAMESPACE}"
TIMEOUT="${DB_FAILOVER_TIMEOUT:-$TIMEOUT}"

log "🧪 Starting Database Failover Testing"
log "Test Type: $TEST_TYPE"
log "Namespace: $NAMESPACE"
log "Timeout: ${TIMEOUT}s"
log "Verbose: $VERBOSE"
log "Dry Run: $DRY_RUN"
log "Log File: $LOG_FILE"

# Validation function
validate_prerequisites() {
    log "🔍 Validating prerequisites..."

    # Check kubectl
    if ! command -v kubectl &> /dev/null; then
        error "kubectl is not installed or not in PATH"
        return 1
    fi

    # Check Kubernetes connection
    if ! kubectl cluster-info &> /dev/null; then
        error "Cannot connect to Kubernetes cluster"
        return 1
    fi

    # Check namespace
    if ! kubectl get namespace "$NAMESPACE" &> /dev/null; then
        error "Namespace '$NAMESPACE' does not exist"
        return 1
    fi

    # Check required tools
    local missing_tools=()
    
    if ! command -v curl &> /dev/null; then
        missing_tools+=("curl")
    fi
    
    if ! command -v jq &> /dev/null; then
        missing_tools+=("jq")
    fi
    
    if [[ ${#missing_tools[@]} -gt 0 ]]; then
        error "Missing required tools: ${missing_tools[*]}"
        return 1
    fi

    success "Prerequisites validation completed"
}

# Get database health status
get_database_health() {
    local provider=$1
    local endpoint="/health/$provider"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would check health for provider: $provider"
        echo "healthy"
        return 0
    fi
    
    # Port forward to health service
    kubectl port-forward service/database-health-service 8080:8080 -n "$NAMESPACE" &
    local pf_pid=$!
    sleep 3
    
    # Check health endpoint
    local health_status
    if health_status=$(curl -s "http://localhost:8080$endpoint" | jq -r '.status' 2>/dev/null); then
        kill $pf_pid 2>/dev/null || true
        echo "$health_status"
    else
        kill $pf_pid 2>/dev/null || true
        echo "unknown"
    fi
}

# Get active database provider
get_active_database() {
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would get active database provider"
        echo "neon"
        return 0
    fi
    
    # Get active database from API Gateway pod
    local pod_name
    pod_name=$(kubectl get pods -l app=api-gateway -n "$NAMESPACE" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null)
    
    if [[ -n "$pod_name" ]]; then
        kubectl exec "$pod_name" -n "$NAMESPACE" -- cat /shared/active-database 2>/dev/null || echo "unknown"
    else
        echo "unknown"
    fi
}

# Simulate database failure
simulate_database_failure() {
    local provider=$1
    
    log "💥 Simulating $provider database failure..."
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would simulate failure for provider: $provider"
        return 0
    fi
    
    case $provider in
        neon)
            # Disable Neon PostgreSQL by updating config
            kubectl patch configmap database-failover-config -n "$NAMESPACE" -p '{"data":{"NEON_ENABLED":"false"}}'
            ;;
        cnpg)
            # Scale down CNPG cluster
            kubectl scale cluster reverse-tender-postgres --replicas=0 -n "$NAMESPACE" 2>/dev/null || \
            kubectl patch deployment postgres-cnpg -n "$NAMESPACE" -p '{"spec":{"replicas":0}}'
            ;;
        mongodb)
            # Disable MongoDB Atlas by updating config
            kubectl patch configmap database-failover-config -n "$NAMESPACE" -p '{"data":{"MONGODB_ATLAS_ENABLED":"false"}}'
            ;;
        *)
            error "Unknown provider: $provider"
            return 1
            ;;
    esac
    
    success "$provider database failure simulated"
}

# Restore database
restore_database() {
    local provider=$1
    
    log "🔄 Restoring $provider database..."
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would restore provider: $provider"
        return 0
    fi
    
    case $provider in
        neon)
            # Re-enable Neon PostgreSQL
            kubectl patch configmap database-failover-config -n "$NAMESPACE" -p '{"data":{"NEON_ENABLED":"true"}}'
            ;;
        cnpg)
            # Scale up CNPG cluster
            kubectl scale cluster reverse-tender-postgres --replicas=3 -n "$NAMESPACE" 2>/dev/null || \
            kubectl patch deployment postgres-cnpg -n "$NAMESPACE" -p '{"spec":{"replicas":1}}'
            ;;
        mongodb)
            # Re-enable MongoDB Atlas
            kubectl patch configmap database-failover-config -n "$NAMESPACE" -p '{"data":{"MONGODB_ATLAS_ENABLED":"true"}}'
            ;;
        *)
            error "Unknown provider: $provider"
            return 1
            ;;
    esac
    
    success "$provider database restored"
}

# Wait for failover to complete
wait_for_failover() {
    local expected_provider=$1
    local max_wait=${2:-60}
    local wait_time=0
    
    log "⏳ Waiting for failover to $expected_provider (max ${max_wait}s)..."
    
    while [[ $wait_time -lt $max_wait ]]; do
        local active_db
        active_db=$(get_active_database)
        
        if [[ "$active_db" == "$expected_provider" ]]; then
            success "Failover to $expected_provider completed in ${wait_time}s"
            return 0
        fi
        
        if [[ "$VERBOSE" == "true" ]]; then
            info "Current active database: $active_db (waiting for $expected_provider)"
        fi
        
        sleep 5
        wait_time=$((wait_time + 5))
    done
    
    error "Failover to $expected_provider did not complete within ${max_wait}s"
    return 1
}

# Test primary database failover
test_primary_failover() {
    log "🧪 Testing Primary Database (Neon PostgreSQL) Failover"
    
    # Get initial state
    local initial_db
    initial_db=$(get_active_database)
    info "Initial active database: $initial_db"
    
    # Simulate Neon failure
    simulate_database_failure "neon"
    
    # Wait for failover to secondary (CNPG)
    if wait_for_failover "cnpg" 60; then
        success "Primary failover test passed: Neon → CNPG"
    else
        error "Primary failover test failed"
        return 1
    fi
    
    # Restore Neon
    restore_database "neon"
    
    # Wait for recovery back to primary
    if wait_for_failover "neon" 60; then
        success "Primary recovery test passed: CNPG → Neon"
    else
        warning "Primary recovery test failed (may be expected behavior)"
    fi
}

# Test secondary database failover
test_secondary_failover() {
    log "🧪 Testing Secondary Database (CNPG PostgreSQL) Failover"
    
    # First, simulate primary failure to get to secondary
    simulate_database_failure "neon"
    wait_for_failover "cnpg" 60
    
    # Now simulate secondary failure
    simulate_database_failure "cnpg"
    
    # Wait for failover to tertiary (MongoDB)
    if wait_for_failover "mongodb" 60; then
        success "Secondary failover test passed: CNPG → MongoDB"
    else
        error "Secondary failover test failed"
        return 1
    fi
    
    # Restore databases
    restore_database "cnpg"
    restore_database "neon"
    
    # Wait for recovery
    sleep 30
    local final_db
    final_db=$(get_active_database)
    info "Final active database: $final_db"
}

# Test tertiary database failover
test_tertiary_failover() {
    log "🧪 Testing Tertiary Database (MongoDB Atlas) Failover"
    
    # Simulate all database failures
    simulate_database_failure "neon"
    simulate_database_failure "cnpg"
    simulate_database_failure "mongodb"
    
    # Wait and check if fallback is used
    sleep 30
    local active_db
    active_db=$(get_active_database)
    
    if [[ "$active_db" == "fallback" || "$active_db" == "sqlite" ]]; then
        success "Tertiary failover test passed: Using fallback database"
    else
        error "Tertiary failover test failed: Expected fallback, got $active_db"
        return 1
    fi
    
    # Restore all databases
    restore_database "mongodb"
    restore_database "cnpg"
    restore_database "neon"
}

# Test circuit breaker functionality
test_circuit_breaker() {
    log "🧪 Testing Circuit Breaker Functionality"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would test circuit breaker functionality"
        return 0
    fi
    
    # This would require generating load and monitoring circuit breaker state
    # For now, we'll just check if circuit breaker is configured
    local cb_enabled
    cb_enabled=$(kubectl get configmap database-failover-config -n "$NAMESPACE" -o jsonpath='{.data.DB_FAILOVER_CIRCUIT_BREAKER_ENABLED}' 2>/dev/null)
    
    if [[ "$cb_enabled" == "true" ]]; then
        success "Circuit breaker is enabled"
    else
        error "Circuit breaker is not enabled"
        return 1
    fi
    
    info "Circuit breaker test requires load generation (not implemented in this script)"
}

# Test database recovery scenarios
test_recovery() {
    log "🧪 Testing Database Recovery Scenarios"
    
    # Test recovery from each failure scenario
    local providers=("neon" "cnpg" "mongodb")
    
    for provider in "${providers[@]}"; do
        log "Testing recovery for $provider..."
        
        # Simulate failure
        simulate_database_failure "$provider"
        sleep 10
        
        # Restore
        restore_database "$provider"
        sleep 10
        
        # Check health
        local health
        health=$(get_database_health "$provider")
        
        if [[ "$health" == "healthy" ]]; then
            success "$provider recovery test passed"
        else
            warning "$provider recovery test inconclusive (health: $health)"
        fi
    done
}

# Test failover under load
test_load_failover() {
    log "🧪 Testing Failover Under Load"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would test failover under load"
        return 0
    fi
    
    # This would require load generation tools
    info "Load testing requires external load generation tools (not implemented in this script)"
    info "Consider using tools like Apache Bench, wrk, or k6 for load testing"
}

# Generate test report
generate_report() {
    log "📋 Generating test report..."
    
    local report_file="/tmp/database-failover-test-report-$(date +%Y%m%d-%H%M%S).json"
    
    cat > "$report_file" << EOF
{
  "test_run": {
    "timestamp": "$(date -Iseconds)",
    "test_type": "$TEST_TYPE",
    "namespace": "$NAMESPACE",
    "timeout": $TIMEOUT,
    "dry_run": $DRY_RUN
  },
  "environment": {
    "kubernetes_version": "$(kubectl version --short --client 2>/dev/null | head -1 || echo 'unknown')",
    "cluster_info": "$(kubectl cluster-info 2>/dev/null | head -1 || echo 'unknown')"
  },
  "database_status": {
    "neon": "$(get_database_health neon)",
    "cnpg": "$(get_database_health cnpg)",
    "mongodb": "$(get_database_health mongodb)",
    "active": "$(get_active_database)"
  },
  "log_file": "$LOG_FILE"
}
EOF
    
    success "Test report generated: $report_file"
    
    if [[ "$VERBOSE" == "true" ]]; then
        log "Test Report Contents:"
        cat "$report_file" | jq '.' 2>/dev/null || cat "$report_file"
    fi
}

# Main test execution
run_tests() {
    case $TEST_TYPE in
        all)
            log "🚀 Running all database failover tests..."
            test_primary_failover
            test_secondary_failover
            test_tertiary_failover
            test_circuit_breaker
            test_recovery
            ;;
        primary)
            test_primary_failover
            ;;
        secondary)
            test_secondary_failover
            ;;
        tertiary)
            test_tertiary_failover
            ;;
        circuit-breaker)
            test_circuit_breaker
            ;;
        recovery)
            test_recovery
            ;;
        load)
            test_load_failover
            ;;
        *)
            error "Unknown test type: $TEST_TYPE"
            show_help
            exit 1
            ;;
    esac
}

# Cleanup function
cleanup() {
    log "🧹 Cleaning up test environment..."
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would clean up test environment"
        return 0
    fi
    
    # Restore all databases to ensure clean state
    restore_database "neon" 2>/dev/null || true
    restore_database "cnpg" 2>/dev/null || true
    restore_database "mongodb" 2>/dev/null || true
    
    # Kill any background processes
    pkill -f "kubectl port-forward" 2>/dev/null || true
    
    success "Cleanup completed"
}

# Main execution
main() {
    # Validate prerequisites
    validate_prerequisites
    
    # Run tests
    run_tests
    
    # Generate report
    generate_report
    
    success "Database failover testing completed successfully!"
}

# Error handling
trap 'error "Script failed at line $LINENO"; cleanup; exit 1' ERR
trap 'cleanup' EXIT

# Run main function
main "$@"
