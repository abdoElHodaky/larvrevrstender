#!/bin/bash

# Deployment Duration Profiling Test
# Measures and analyzes deployment performance metrics
# Part of Phase 1: Performance Testing Framework

set -euo pipefail

# Configuration
BLUE_NAMESPACE="reverse-tender-blue"
GREEN_NAMESPACE="reverse-tender-green"
FLUX_NAMESPACE="flux-system"
LOG_FILE="/tmp/deployment-performance-$(date +%Y%m%d-%H%M%S).log"
METRICS_FILE="/tmp/deployment-metrics-$(date +%Y%m%d-%H%M%S).json"

# Performance targets (in seconds)
TARGET_DEPLOYMENT_DURATION=300  # 5 minutes
TARGET_SERVICE_START_TIME=60    # 1 minute
TARGET_HEALTH_CHECK_TIME=30     # 30 seconds
TARGET_ROLLBACK_TIME=120        # 2 minutes

# Services to monitor
SERVICES=(
    "gateway-service:8009"
    "auth-service:8001"
    "user-service:8002"
    "payment-service:8006"
    "notification-service:8007"
)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
PURPLE='\033[0;35m'
NC='\033[0m'

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
    echo -e "${CYAN}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

debug() {
    echo -e "${PURPLE}[DEBUG]${NC} $1" | tee -a "$LOG_FILE"
}

# Performance metrics storage
declare -A DEPLOYMENT_METRICS
declare -A SERVICE_METRICS
declare -A HEALTH_CHECK_METRICS

# Test result tracking
TESTS_PASSED=0
TESTS_FAILED=0
FAILED_TESTS=()

# Function to run a test and track results
run_test() {
    local test_name="$1"
    local test_function="$2"
    
    log "Running performance test: $test_name"
    
    if $test_function; then
        success "✅ PASSED: $test_name"
        ((TESTS_PASSED++))
    else
        error "❌ FAILED: $test_name"
        FAILED_TESTS+=("$test_name")
        ((TESTS_FAILED++))
    fi
    
    echo "" | tee -a "$LOG_FILE"
}

# Helper function to get timestamp in nanoseconds
get_timestamp_ns() {
    date +%s%N
}

# Helper function to calculate duration in seconds
calculate_duration() {
    local start_ns="$1"
    local end_ns="$2"
    echo "scale=3; ($end_ns - $start_ns) / 1000000000" | bc -l
}

# Helper function to wait for deployment and measure time
measure_deployment_time() {
    local namespace="$1"
    local service="$2"
    local timeout="$3"
    
    local start_time
    start_time=$(get_timestamp_ns)
    local attempts=0
    local max_attempts=$((timeout / 5))
    
    debug "Measuring deployment time for $service in $namespace..."
    
    while [[ $attempts -lt $max_attempts ]]; do
        local ready_replicas
        ready_replicas=$(kubectl get deployment "$service" -n "$namespace" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
        local desired_replicas
        desired_replicas=$(kubectl get deployment "$service" -n "$namespace" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "1")
        
        if [[ "$ready_replicas" == "$desired_replicas" ]] && [[ "$ready_replicas" != "0" ]]; then
            local end_time
            end_time=$(get_timestamp_ns)
            local duration
            duration=$(calculate_duration "$start_time" "$end_time")
            
            SERVICE_METRICS["${service}_deployment_time"]="$duration"
            debug "$service deployment completed in ${duration}s"
            return 0
        fi
        
        sleep 5
        ((attempts++))
    done
    
    local end_time
    end_time=$(get_timestamp_ns)
    local duration
    duration=$(calculate_duration "$start_time" "$end_time")
    
    SERVICE_METRICS["${service}_deployment_time"]="$duration"
    error "$service deployment timed out after ${duration}s"
    return 1
}

# Helper function to measure health check response time
measure_health_check_time() {
    local namespace="$1"
    local service="$2"
    local port="$3"
    local iterations="${4:-10}"
    
    local pod_name
    pod_name=$(kubectl get pods -n "$namespace" -l app="$service" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -z "$pod_name" ]]; then
        error "No pods found for $service in $namespace"
        return 1
    fi
    
    local total_time=0
    local successful_checks=0
    
    for ((i=1; i<=iterations; i++)); do
        local start_time
        start_time=$(get_timestamp_ns)
        
        local response
        response=$(kubectl exec "$pod_name" -n "$namespace" -- curl -s -o /dev/null -w "%{http_code}" "http://localhost:$port/health" 2>/dev/null || echo "000")
        
        local end_time
        end_time=$(get_timestamp_ns)
        
        if [[ "$response" == "200" ]]; then
            local duration
            duration=$(calculate_duration "$start_time" "$end_time")
            total_time=$(echo "$total_time + $duration" | bc -l)
            ((successful_checks++))
        fi
    done
    
    if [[ $successful_checks -gt 0 ]]; then
        local avg_time
        avg_time=$(echo "scale=3; $total_time / $successful_checks" | bc -l)
        HEALTH_CHECK_METRICS["${service}_avg_response_time"]="$avg_time"
        HEALTH_CHECK_METRICS["${service}_success_rate"]=$(echo "scale=2; $successful_checks * 100 / $iterations" | bc -l)
        debug "$service average health check time: ${avg_time}s (${successful_checks}/$iterations successful)"
        return 0
    else
        error "$service health checks failed completely"
        return 1
    fi
}

# Test 1: Full Deployment Duration Profiling
test_full_deployment_duration() {
    log "Testing full deployment duration profiling..."
    
    local target_namespace="$GREEN_NAMESPACE"
    local deployment_start_time
    deployment_start_time=$(get_timestamp_ns)
    
    info "Starting deployment duration measurement for $target_namespace"
    
    # Measure individual service deployment times
    local all_services_deployed=true
    
    for service_config in "${SERVICES[@]}"; do
        local service_name
        service_name=$(echo "$service_config" | cut -d':' -f1)
        
        log "Measuring deployment time for $service_name..."
        
        if measure_deployment_time "$target_namespace" "$service_name" "$TARGET_DEPLOYMENT_DURATION"; then
            success "$service_name deployment time measured"
        else
            error "$service_name deployment time measurement failed"
            all_services_deployed=false
        fi
    done
    
    local deployment_end_time
    deployment_end_time=$(get_timestamp_ns)
    local total_deployment_time
    total_deployment_time=$(calculate_duration "$deployment_start_time" "$deployment_end_time")
    
    DEPLOYMENT_METRICS["total_deployment_time"]="$total_deployment_time"
    
    info "Total deployment duration: ${total_deployment_time}s"
    
    # Analyze deployment performance
    if (( $(echo "$total_deployment_time <= $TARGET_DEPLOYMENT_DURATION" | bc -l) )); then
        success "Deployment duration within target (≤${TARGET_DEPLOYMENT_DURATION}s)"
    else
        warning "Deployment duration exceeds target: ${total_deployment_time}s > ${TARGET_DEPLOYMENT_DURATION}s"
    fi
    
    return $([[ "$all_services_deployed" == "true" ]] && echo 0 || echo 1)
}

# Test 2: Service Startup Time Analysis
test_service_startup_time() {
    log "Testing service startup time analysis..."
    
    local target_namespace="$GREEN_NAMESPACE"
    local startup_analysis_passed=true
    
    for service_config in "${SERVICES[@]}"; do
        local service_name
        service_name=$(echo "$service_config" | cut -d':' -f1)
        
        log "Analyzing startup time for $service_name..."
        
        # Get pod creation time and ready time
        local pod_name
        pod_name=$(kubectl get pods -n "$target_namespace" -l app="$service_name" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
        
        if [[ -n "$pod_name" ]]; then
            local creation_time
            creation_time=$(kubectl get pod "$pod_name" -n "$target_namespace" -o jsonpath='{.metadata.creationTimestamp}' 2>/dev/null || echo "")
            
            local ready_time=""
            local conditions
            conditions=$(kubectl get pod "$pod_name" -n "$target_namespace" -o jsonpath='{.status.conditions[?(@.type=="Ready")]}' 2>/dev/null || echo "")
            
            if [[ -n "$conditions" ]]; then
                ready_time=$(echo "$conditions" | jq -r '.lastTransitionTime' 2>/dev/null || echo "")
            fi
            
            if [[ -n "$creation_time" ]] && [[ -n "$ready_time" ]]; then
                local creation_timestamp
                creation_timestamp=$(date -d "$creation_time" +%s 2>/dev/null || echo "0")
                local ready_timestamp
                ready_timestamp=$(date -d "$ready_time" +%s 2>/dev/null || echo "0")
                
                if [[ $creation_timestamp -gt 0 ]] && [[ $ready_timestamp -gt 0 ]]; then
                    local startup_duration=$((ready_timestamp - creation_timestamp))
                    SERVICE_METRICS["${service_name}_startup_time"]="$startup_duration"
                    
                    info "$service_name startup time: ${startup_duration}s"
                    
                    if [[ $startup_duration -le $TARGET_SERVICE_START_TIME ]]; then
                        success "$service_name startup within target (≤${TARGET_SERVICE_START_TIME}s)"
                    else
                        warning "$service_name startup exceeds target: ${startup_duration}s > ${TARGET_SERVICE_START_TIME}s"
                    fi
                else
                    warning "Could not calculate startup time for $service_name (timestamp parsing failed)"
                fi
            else
                warning "Could not get timestamps for $service_name"
            fi
        else
            error "No pods found for $service_name"
            startup_analysis_passed=false
        fi
    done
    
    return $([[ "$startup_analysis_passed" == "true" ]] && echo 0 || echo 1)
}

# Test 3: Health Check Response Time Profiling
test_health_check_response_time() {
    log "Testing health check response time profiling..."
    
    local target_namespace="$GREEN_NAMESPACE"
    local health_check_passed=true
    
    for service_config in "${SERVICES[@]}"; do
        local service_name
        service_name=$(echo "$service_config" | cut -d':' -f1)
        local service_port
        service_port=$(echo "$service_config" | cut -d':' -f2)
        
        log "Profiling health check response time for $service_name..."
        
        if measure_health_check_time "$target_namespace" "$service_name" "$service_port" 20; then
            local avg_time="${HEALTH_CHECK_METRICS[${service_name}_avg_response_time]}"
            local success_rate="${HEALTH_CHECK_METRICS[${service_name}_success_rate]}"
            
            info "$service_name health check: ${avg_time}s avg, ${success_rate}% success rate"
            
            if (( $(echo "$avg_time <= 1.0" | bc -l) )); then
                success "$service_name health check response time acceptable (≤1.0s)"
            else
                warning "$service_name health check response time high: ${avg_time}s"
            fi
            
            if (( $(echo "$success_rate >= 95.0" | bc -l) )); then
                success "$service_name health check success rate acceptable (≥95%)"
            else
                error "$service_name health check success rate low: ${success_rate}%"
                health_check_passed=false
            fi
        else
            error "$service_name health check profiling failed"
            health_check_passed=false
        fi
    done
    
    return $([[ "$health_check_passed" == "true" ]] && echo 0 || echo 1)
}

# Test 4: Resource Utilization During Deployment
test_resource_utilization() {
    log "Testing resource utilization during deployment..."
    
    local target_namespace="$GREEN_NAMESPACE"
    local resource_check_passed=true
    
    # Check CPU and memory usage for each service
    for service_config in "${SERVICES[@]}"; do
        local service_name
        service_name=$(echo "$service_config" | cut -d':' -f1)
        
        log "Checking resource utilization for $service_name..."
        
        local pod_name
        pod_name=$(kubectl get pods -n "$target_namespace" -l app="$service_name" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || echo "")
        
        if [[ -n "$pod_name" ]]; then
            # Get resource usage (requires metrics-server)
            local cpu_usage
            cpu_usage=$(kubectl top pod "$pod_name" -n "$target_namespace" --no-headers 2>/dev/null | awk '{print $2}' | sed 's/m//' || echo "0")
            local memory_usage
            memory_usage=$(kubectl top pod "$pod_name" -n "$target_namespace" --no-headers 2>/dev/null | awk '{print $3}' | sed 's/Mi//' || echo "0")
            
            if [[ "$cpu_usage" != "0" ]] && [[ "$memory_usage" != "0" ]]; then
                SERVICE_METRICS["${service_name}_cpu_usage"]="$cpu_usage"
                SERVICE_METRICS["${service_name}_memory_usage"]="$memory_usage"
                
                info "$service_name resource usage: ${cpu_usage}m CPU, ${memory_usage}Mi memory"
                
                # Check if usage is within reasonable limits
                if [[ $cpu_usage -lt 1000 ]]; then  # Less than 1 CPU
                    success "$service_name CPU usage acceptable (${cpu_usage}m)"
                else
                    warning "$service_name CPU usage high: ${cpu_usage}m"
                fi
                
                if [[ $memory_usage -lt 512 ]]; then  # Less than 512Mi
                    success "$service_name memory usage acceptable (${memory_usage}Mi)"
                else
                    warning "$service_name memory usage high: ${memory_usage}Mi"
                fi
            else
                warning "Could not get resource metrics for $service_name (metrics-server may not be available)"
            fi
        else
            error "No pods found for $service_name"
            resource_check_passed=false
        fi
    done
    
    return $([[ "$resource_check_passed" == "true" ]] && echo 0 || echo 1)
}

# Test 5: Rollback Performance Measurement
test_rollback_performance() {
    log "Testing rollback performance measurement..."
    
    local current_namespace="$GREEN_NAMESPACE"
    local rollback_namespace="$BLUE_NAMESPACE"
    
    info "Simulating rollback from $current_namespace to $rollback_namespace"
    
    local rollback_start_time
    rollback_start_time=$(get_timestamp_ns)
    
    # Check rollback environment readiness
    local rollback_ready=true
    
    for service_config in "${SERVICES[@]}"; do
        local service_name
        service_name=$(echo "$service_config" | cut -d':' -f1)
        
        local ready_replicas
        ready_replicas=$(kubectl get deployment "$service_name" -n "$rollback_namespace" -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
        local desired_replicas
        desired_replicas=$(kubectl get deployment "$service_name" -n "$rollback_namespace" -o jsonpath='{.spec.replicas}' 2>/dev/null || echo "1")
        
        if [[ "$ready_replicas" != "$desired_replicas" ]]; then
            rollback_ready=false
            break
        fi
    done
    
    local rollback_end_time
    rollback_end_time=$(get_timestamp_ns)
    local rollback_duration
    rollback_duration=$(calculate_duration "$rollback_start_time" "$rollback_end_time")
    
    DEPLOYMENT_METRICS["rollback_readiness_check_time"]="$rollback_duration"
    
    if [[ "$rollback_ready" == "true" ]]; then
        success "Rollback environment ready in ${rollback_duration}s"
        
        if (( $(echo "$rollback_duration <= $TARGET_ROLLBACK_TIME" | bc -l) )); then
            success "Rollback readiness within target (≤${TARGET_ROLLBACK_TIME}s)"
        else
            warning "Rollback readiness exceeds target: ${rollback_duration}s > ${TARGET_ROLLBACK_TIME}s"
        fi
        
        return 0
    else
        error "Rollback environment not ready after ${rollback_duration}s"
        return 1
    fi
}

# Test 6: Performance Regression Detection
test_performance_regression() {
    log "Testing performance regression detection..."
    
    local regression_detected=false
    
    # Define baseline performance metrics (these would typically come from previous runs)
    declare -A BASELINE_METRICS
    BASELINE_METRICS["total_deployment_time"]="240"  # 4 minutes baseline
    BASELINE_METRICS["avg_startup_time"]="45"        # 45 seconds baseline
    BASELINE_METRICS["avg_health_check_time"]="0.5"  # 0.5 seconds baseline
    
    # Compare current metrics with baseline
    local total_deployment="${DEPLOYMENT_METRICS[total_deployment_time]:-0}"
    if (( $(echo "$total_deployment > 0" | bc -l) )); then
        local baseline_deployment="${BASELINE_METRICS[total_deployment_time]}"
        local regression_threshold
        regression_threshold=$(echo "$baseline_deployment * 1.2" | bc -l)  # 20% regression threshold
        
        if (( $(echo "$total_deployment > $regression_threshold" | bc -l) )); then
            error "Deployment time regression detected: ${total_deployment}s > ${regression_threshold}s (20% increase)"
            regression_detected=true
        else
            success "No deployment time regression detected"
        fi
    fi
    
    # Check startup time regression
    local total_startup_time=0
    local startup_count=0
    
    for service_config in "${SERVICES[@]}"; do
        local service_name
        service_name=$(echo "$service_config" | cut -d':' -f1)
        local startup_time="${SERVICE_METRICS[${service_name}_startup_time]:-0}"
        
        if [[ "$startup_time" != "0" ]]; then
            total_startup_time=$(echo "$total_startup_time + $startup_time" | bc -l)
            ((startup_count++))
        fi
    done
    
    if [[ $startup_count -gt 0 ]]; then
        local avg_startup_time
        avg_startup_time=$(echo "scale=2; $total_startup_time / $startup_count" | bc -l)
        local baseline_startup="${BASELINE_METRICS[avg_startup_time]}"
        local startup_threshold
        startup_threshold=$(echo "$baseline_startup * 1.3" | bc -l)  # 30% regression threshold
        
        if (( $(echo "$avg_startup_time > $startup_threshold" | bc -l) )); then
            error "Startup time regression detected: ${avg_startup_time}s > ${startup_threshold}s (30% increase)"
            regression_detected=true
        else
            success "No startup time regression detected"
        fi
    fi
    
    return $([[ "$regression_detected" == "false" ]] && echo 0 || echo 1)
}

# Function to generate performance report
generate_performance_report() {
    log "Generating performance report..."
    
    local report_timestamp
    report_timestamp=$(date -Iseconds)
    
    # Create JSON report
    cat > "$METRICS_FILE" << EOF
{
  "report": {
    "timestamp": "$report_timestamp",
    "test_duration": "$(date +%s)",
    "target_namespace": "$GREEN_NAMESPACE"
  },
  "deployment_metrics": {
EOF
    
    # Add deployment metrics
    local first_metric=true
    for key in "${!DEPLOYMENT_METRICS[@]}"; do
        if [[ "$first_metric" == "false" ]]; then
            echo "," >> "$METRICS_FILE"
        fi
        echo "    \"$key\": ${DEPLOYMENT_METRICS[$key]}" >> "$METRICS_FILE"
        first_metric=false
    done
    
    echo "  }," >> "$METRICS_FILE"
    echo "  \"service_metrics\": {" >> "$METRICS_FILE"
    
    # Add service metrics
    first_metric=true
    for key in "${!SERVICE_METRICS[@]}"; do
        if [[ "$first_metric" == "false" ]]; then
            echo "," >> "$METRICS_FILE"
        fi
        echo "    \"$key\": ${SERVICE_METRICS[$key]}" >> "$METRICS_FILE"
        first_metric=false
    done
    
    echo "  }," >> "$METRICS_FILE"
    echo "  \"health_check_metrics\": {" >> "$METRICS_FILE"
    
    # Add health check metrics
    first_metric=true
    for key in "${!HEALTH_CHECK_METRICS[@]}"; do
        if [[ "$first_metric" == "false" ]]; then
            echo "," >> "$METRICS_FILE"
        fi
        echo "    \"$key\": ${HEALTH_CHECK_METRICS[$key]}" >> "$METRICS_FILE"
        first_metric=false
    done
    
    echo "  }," >> "$METRICS_FILE"
    echo "  \"performance_targets\": {" >> "$METRICS_FILE"
    echo "    \"deployment_duration\": $TARGET_DEPLOYMENT_DURATION," >> "$METRICS_FILE"
    echo "    \"service_start_time\": $TARGET_SERVICE_START_TIME," >> "$METRICS_FILE"
    echo "    \"health_check_time\": $TARGET_HEALTH_CHECK_TIME," >> "$METRICS_FILE"
    echo "    \"rollback_time\": $TARGET_ROLLBACK_TIME" >> "$METRICS_FILE"
    echo "  }" >> "$METRICS_FILE"
    echo "}" >> "$METRICS_FILE"
    
    success "Performance report generated: $METRICS_FILE"
}

# Main test execution
main() {
    log "Starting Deployment Duration Profiling Test Suite"
    log "Logging to: $LOG_FILE"
    log "Metrics file: $METRICS_FILE"
    log "Target deployment duration: ${TARGET_DEPLOYMENT_DURATION}s"
    log "Target service start time: ${TARGET_SERVICE_START_TIME}s"
    echo ""
    
    # Check if kubectl is available
    if ! command -v kubectl &>/dev/null; then
        error "kubectl is not installed or not in PATH"
        exit 1
    fi
    
    # Check if bc is available for calculations
    if ! command -v bc &>/dev/null; then
        error "bc is not installed or not in PATH (required for calculations)"
        exit 1
    fi
    
    # Check if jq is available for JSON processing
    if ! command -v jq &>/dev/null; then
        warning "jq is not installed - some JSON processing may be limited"
    fi
    
    # Check if cluster is accessible
    if ! kubectl cluster-info &>/dev/null; then
        error "Cannot access Kubernetes cluster"
        exit 1
    fi
    
    # Run all performance tests
    run_test "Full Deployment Duration Profiling" test_full_deployment_duration
    run_test "Service Startup Time Analysis" test_service_startup_time
    run_test "Health Check Response Time Profiling" test_health_check_response_time
    run_test "Resource Utilization During Deployment" test_resource_utilization
    run_test "Rollback Performance Measurement" test_rollback_performance
    run_test "Performance Regression Detection" test_performance_regression
    
    # Generate performance report
    generate_performance_report
    
    # Print summary
    echo "=================================="
    log "Deployment Performance Test Summary"
    echo "=================================="
    success "Tests Passed: $TESTS_PASSED"
    
    if [[ $TESTS_FAILED -gt 0 ]]; then
        error "Tests Failed: $TESTS_FAILED"
        error "Failed tests:"
        for test in "${FAILED_TESTS[@]}"; do
            error "  - $test"
        done
        echo ""
        error "❌ Deployment performance tests FAILED"
        exit 1
    else
        echo ""
        success "✅ All deployment performance tests PASSED"
        
        # Print key metrics
        local total_deployment="${DEPLOYMENT_METRICS[total_deployment_time]:-0}"
        if (( $(echo "$total_deployment > 0" | bc -l) )); then
            info "🎯 Total deployment duration: ${total_deployment}s"
        fi
        
        success "📊 Performance report available at: $METRICS_FILE"
        exit 0
    fi
}

# Run main function
main "$@"

