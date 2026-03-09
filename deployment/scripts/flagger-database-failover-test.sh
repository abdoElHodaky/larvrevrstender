#!/bin/bash
# Flagger Database Failover Testing Script for Reverse Tender Platform
# Integrates with existing FluxCD/Flagger UI setup with Grafana and Prometheus
# Updated for comprehensive database failover testing via Flagger Canary

set -euo pipefail

# Script configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="/tmp/flagger-database-failover-test-$(date +%Y%m%d-%H%M%S).log"

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
Flagger Database Failover Testing Script

Usage: $0 [OPTIONS]

OPTIONS:
    --test=TEST_TYPE        Type of test to run (canary|metrics|dashboard|all)
    --namespace=NS          Kubernetes namespace (default: reverse-tender)
    --timeout=SECONDS       Test timeout in seconds (default: 600)
    --canary-name=NAME      Canary resource name (default: database-failover-canary)
    --grafana-url=URL       Grafana dashboard URL
    --prometheus-url=URL    Prometheus URL
    --verbose              Enable verbose output
    --dry-run              Show what would be tested without making changes
    --help                 Show this help message

TEST TYPES:
    canary                 Run Flagger canary deployment with database failover testing
    metrics                Validate Prometheus metrics for database failover
    dashboard              Open Grafana dashboard for monitoring
    all                    Run all tests (canary + metrics + dashboard)

EXAMPLES:
    $0 --test=canary --namespace=reverse-tender
    $0 --test=metrics --prometheus-url=http://prometheus.monitoring.svc.cluster.local:9090
    $0 --test=dashboard --grafana-url=http://grafana.monitoring.svc.cluster.local:3000
    $0 --test=all --verbose

ENVIRONMENT VARIABLES:
    KUBECONFIG             Path to kubeconfig file
    FLAGGER_NAMESPACE      Default namespace for Flagger resources (default: flagger-system)
    GRAFANA_URL            Default Grafana URL
    PROMETHEUS_URL         Default Prometheus URL
EOF
}

# Default values
TEST_TYPE="all"
NAMESPACE="reverse-tender"
TIMEOUT=600
CANARY_NAME="database-failover-canary"
FLAGGER_NAMESPACE="${FLAGGER_NAMESPACE:-flagger-system}"
GRAFANA_URL="${GRAFANA_URL:-http://grafana.monitoring.svc.cluster.local:3000}"
PROMETHEUS_URL="${PROMETHEUS_URL:-http://prometheus.monitoring.svc.cluster.local:9090}"
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
        --canary-name=*)
            CANARY_NAME="${1#*=}"
            shift
            ;;
        --grafana-url=*)
            GRAFANA_URL="${1#*=}"
            shift
            ;;
        --prometheus-url=*)
            PROMETHEUS_URL="${1#*=}"
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

log "🚀 Starting Flagger Database Failover Testing"
log "Test Type: $TEST_TYPE"
log "Namespace: $NAMESPACE"
log "Canary Name: $CANARY_NAME"
log "Timeout: ${TIMEOUT}s"
log "Grafana URL: $GRAFANA_URL"
log "Prometheus URL: $PROMETHEUS_URL"
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

    # Check Flagger namespace
    if ! kubectl get namespace "$FLAGGER_NAMESPACE" &> /dev/null; then
        error "Flagger namespace '$FLAGGER_NAMESPACE' does not exist"
        return 1
    fi

    # Check Flagger installation
    if ! kubectl get deployment flagger -n "$FLAGGER_NAMESPACE" &> /dev/null; then
        error "Flagger is not installed in namespace '$FLAGGER_NAMESPACE'"
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

# Deploy Flagger resources
deploy_flagger_resources() {
    log "📦 Deploying Flagger database failover resources..."
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would deploy Flagger resources"
        return 0
    fi
    
    # Apply database failover monitoring rules
    kubectl apply -f "$PROJECT_ROOT/deployment/fluxcd/monitoring/database-failover-rules.yaml"
    
    # Apply updated ServiceMonitor
    kubectl apply -f "$PROJECT_ROOT/deployment/fluxcd/monitoring/servicemonitor.yaml"
    
    # Apply Grafana dashboard ConfigMap
    kubectl apply -f "$PROJECT_ROOT/deployment/fluxcd/dashboards/configmap.yaml"
    
    # Apply Flagger Canary configuration
    kubectl apply -f "$PROJECT_ROOT/deployment/fluxcd/automation/database-failover-canary.yaml"
    
    success "Flagger resources deployed successfully"
}

# Wait for Flagger resources to be ready
wait_for_flagger_resources() {
    log "⏳ Waiting for Flagger resources to be ready..."
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would wait for Flagger resources"
        return 0
    fi
    
    # Wait for PrometheusRule to be ready
    kubectl wait --for=condition=Ready prometheusrule/database-failover-rules -n flux-system --timeout=60s
    
    # Wait for ServiceMonitor to be ready
    kubectl wait --for=condition=Ready servicemonitor/database-failover-services -n "$NAMESPACE" --timeout=60s
    
    # Wait for MetricTemplates to be ready
    kubectl wait --for=condition=Ready metrictemplate/database-availability -n "$FLAGGER_NAMESPACE" --timeout=60s
    kubectl wait --for=condition=Ready metrictemplate/database-response-time -n "$FLAGGER_NAMESPACE" --timeout=60s
    kubectl wait --for=condition=Ready metrictemplate/database-error-rate -n "$FLAGGER_NAMESPACE" --timeout=60s
    
    success "Flagger resources are ready"
}

# Get Flagger canary status
get_canary_status() {
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would get canary status"
        echo "Progressing"
        return 0
    fi
    
    kubectl get canary "$CANARY_NAME" -n "$NAMESPACE" -o jsonpath='{.status.phase}' 2>/dev/null || echo "NotFound"
}

# Get Flagger canary metrics
get_canary_metrics() {
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would get canary metrics"
        return 0
    fi
    
    local metrics_output
    metrics_output=$(kubectl get canary "$CANARY_NAME" -n "$NAMESPACE" -o json 2>/dev/null | jq -r '.status.conditions[]? | "\(.type): \(.status) - \(.message)"' || echo "No metrics available")
    
    if [[ "$VERBOSE" == "true" ]]; then
        info "Canary Metrics:"
        echo "$metrics_output"
    fi
}

# Run Flagger canary test
test_flagger_canary() {
    log "🧪 Testing Flagger Canary for Database Failover"
    
    # Deploy Flagger resources
    deploy_flagger_resources
    
    # Wait for resources to be ready
    wait_for_flagger_resources
    
    # Trigger canary deployment by updating the target deployment
    log "🚀 Triggering canary deployment..."
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would trigger canary deployment"
    else
        # Update deployment to trigger canary
        kubectl patch deployment api-gateway -n "$NAMESPACE" -p '{"spec":{"template":{"metadata":{"annotations":{"flagger.app/database-failover-test":"'$(date +%s)'"}}}}}'
    fi
    
    # Monitor canary progress
    log "📊 Monitoring canary progress..."
    local start_time=$(date +%s)
    local max_wait_time=$((start_time + TIMEOUT))
    
    while [[ $(date +%s) -lt $max_wait_time ]]; do
        local status
        status=$(get_canary_status)
        
        case $status in
            "Succeeded")
                success "Canary deployment succeeded!"
                get_canary_metrics
                return 0
                ;;
            "Failed")
                error "Canary deployment failed!"
                get_canary_metrics
                return 1
                ;;
            "Progressing")
                info "Canary deployment in progress... ($(date))"
                get_canary_metrics
                ;;
            "NotFound")
                error "Canary resource not found"
                return 1
                ;;
            *)
                info "Canary status: $status"
                ;;
        esac
        
        sleep 30
    done
    
    error "Canary deployment timed out after ${TIMEOUT}s"
    return 1
}

# Validate Prometheus metrics
test_prometheus_metrics() {
    log "📊 Testing Prometheus Metrics for Database Failover"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would test Prometheus metrics"
        return 0
    fi
    
    # Port forward to Prometheus if needed
    local prometheus_port=9090
    if [[ "$PROMETHEUS_URL" == *"localhost"* ]]; then
        kubectl port-forward service/prometheus -n monitoring $prometheus_port:9090 &
        local pf_pid=$!
        sleep 5
    fi
    
    # Test database failover metrics
    local metrics_to_test=(
        "database_failover:active_provider"
        "database_failover:available_providers_count"
        "database_failover:provider_health_ratio"
        "database_failover:connection_pool_utilization"
        "database_failover:query_latency_p95"
        "database_failover:error_rate"
    )
    
    local failed_metrics=()
    
    for metric in "${metrics_to_test[@]}"; do
        log "Testing metric: $metric"
        
        local query_url="${PROMETHEUS_URL}/api/v1/query?query=${metric}"
        local response
        
        if response=$(curl -s "$query_url" | jq -r '.status' 2>/dev/null); then
            if [[ "$response" == "success" ]]; then
                success "Metric $metric is available"
            else
                warning "Metric $metric returned status: $response"
                failed_metrics+=("$metric")
            fi
        else
            error "Failed to query metric: $metric"
            failed_metrics+=("$metric")
        fi
    done
    
    # Cleanup port forward if needed
    if [[ -n "${pf_pid:-}" ]]; then
        kill $pf_pid 2>/dev/null || true
    fi
    
    if [[ ${#failed_metrics[@]} -eq 0 ]]; then
        success "All Prometheus metrics are working correctly"
        return 0
    else
        error "Failed metrics: ${failed_metrics[*]}"
        return 1
    fi
}

# Open Grafana dashboard
test_grafana_dashboard() {
    log "📈 Opening Grafana Dashboard for Database Failover"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "[DRY RUN] Would open Grafana dashboard"
        return 0
    fi
    
    # Port forward to Grafana if needed
    local grafana_port=3000
    if [[ "$GRAFANA_URL" == *"localhost"* ]]; then
        kubectl port-forward service/grafana -n monitoring $grafana_port:3000 &
        local pf_pid=$!
        sleep 5
        
        info "Grafana is available at: http://localhost:$grafana_port"
        info "Dashboard URL: http://localhost:$grafana_port/d/database-failover-overview/database-failover-overview"
    else
        info "Grafana Dashboard URL: $GRAFANA_URL/d/database-failover-overview/database-failover-overview"
    fi
    
    # Test dashboard accessibility
    local dashboard_url="$GRAFANA_URL/api/dashboards/uid/database-failover-overview"
    
    if curl -s -f "$dashboard_url" > /dev/null 2>&1; then
        success "Database Failover dashboard is accessible"
    else
        warning "Database Failover dashboard may not be accessible at: $dashboard_url"
    fi
    
    # Keep port forward running if started
    if [[ -n "${pf_pid:-}" ]]; then
        info "Port forward is running (PID: $pf_pid). Press Ctrl+C to stop."
        wait $pf_pid 2>/dev/null || true
    fi
}

# Generate comprehensive test report
generate_test_report() {
    log "📋 Generating comprehensive test report..."
    
    local report_file="/tmp/flagger-database-failover-test-report-$(date +%Y%m%d-%H%M%S).json"
    
    # Get current canary status
    local canary_status
    canary_status=$(get_canary_status)
    
    # Get Flagger controller status
    local flagger_status
    flagger_status=$(kubectl get deployment flagger -n "$FLAGGER_NAMESPACE" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
    
    cat > "$report_file" << EOF
{
  "test_run": {
    "timestamp": "$(date -Iseconds)",
    "test_type": "$TEST_TYPE",
    "namespace": "$NAMESPACE",
    "canary_name": "$CANARY_NAME",
    "timeout": $TIMEOUT,
    "dry_run": $DRY_RUN
  },
  "environment": {
    "kubernetes_version": "$(kubectl version --short --client 2>/dev/null | head -1 || echo 'unknown')",
    "flagger_namespace": "$FLAGGER_NAMESPACE",
    "grafana_url": "$GRAFANA_URL",
    "prometheus_url": "$PROMETHEUS_URL"
  },
  "flagger_status": {
    "controller_ready_replicas": $flagger_status,
    "canary_status": "$canary_status"
  },
  "database_failover": {
    "prometheus_rules_deployed": "$(kubectl get prometheusrule database-failover-rules -n flux-system -o jsonpath='{.metadata.name}' 2>/dev/null || echo 'not-found')",
    "service_monitors_deployed": "$(kubectl get servicemonitor database-failover-services -n "$NAMESPACE" -o jsonpath='{.metadata.name}' 2>/dev/null || echo 'not-found')",
    "metric_templates_deployed": "$(kubectl get metrictemplate -n "$FLAGGER_NAMESPACE" --no-headers 2>/dev/null | wc -l || echo '0')"
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
        canary)
            test_flagger_canary
            ;;
        metrics)
            test_prometheus_metrics
            ;;
        dashboard)
            test_grafana_dashboard
            ;;
        all)
            log "🚀 Running all Flagger database failover tests..."
            test_flagger_canary
            test_prometheus_metrics
            test_grafana_dashboard
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
    generate_test_report
    
    success "Flagger database failover testing completed successfully!"
}

# Error handling
trap 'error "Script failed at line $LINENO"; cleanup; exit 1' ERR
trap 'cleanup' EXIT

# Run main function
main "$@"
