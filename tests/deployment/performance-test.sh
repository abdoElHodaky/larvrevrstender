#!/bin/bash

# Performance Testing Suite
# Measures deployment duration, resource utilization, and system behavior under load

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="/tmp/performance-test-$(date +%Y%m%d-%H%M%S).log"
TEST_NAMESPACE="performance-test"
TEST_TIMEOUT=600

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS] $1${NC}" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}" | tee -a "$LOG_FILE"
}

# Performance metrics tracking
DEPLOYMENT_TIMES=()
RESOURCE_USAGE=()
RESPONSE_TIMES=()

# Test result tracking
TESTS_PASSED=0
TESTS_FAILED=0
FAILED_TESTS=()

# Test execution wrapper
run_test() {
    local test_name="$1"
    local test_function="$2"
    
    log "Running performance test: $test_name"
    
    if $test_function; then
        success "Performance test passed: $test_name"
        ((TESTS_PASSED++))
        return 0
    else
        error "Performance test failed: $test_name"
        FAILED_TESTS+=("$test_name")
        ((TESTS_FAILED++))
        return 1
    fi
}

# Cleanup function
cleanup() {
    log "Cleaning up performance test resources..."
    
    # Delete test namespace if it exists
    if kubectl get namespace "$TEST_NAMESPACE" >/dev/null 2>&1; then
        kubectl delete namespace "$TEST_NAMESPACE" --timeout=120s || true
    fi
    
    log "Performance test cleanup completed"
}

# Set up cleanup trap
trap cleanup EXIT

# Utility function to measure time
measure_time() {
    local start_time=$(date +%s.%N)
    "$@"
    local end_time=$(date +%s.%N)
    local duration=$(echo "$end_time - $start_time" | bc -l)
    echo "$duration"
}

# Test 1: Deployment Duration Profiling
test_deployment_duration_profiling() {
    log "Testing deployment duration profiling..."
    
    # Create test namespace
    kubectl create namespace "$TEST_NAMESPACE" || return 1
    
    # Test multiple deployment scenarios
    local scenarios=("small" "medium" "large")
    local replicas=(1 3 5)
    
    for i in "${!scenarios[@]}"; do
        local scenario="${scenarios[$i]}"
        local replica_count="${replicas[$i]}"
        
        log "Testing $scenario deployment scenario ($replica_count replicas)"
        
        # Create deployment manifest
        cat > "/tmp/perf-test-$scenario.yaml" << EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  name: perf-test-$scenario
  namespace: $TEST_NAMESPACE
spec:
  replicas: $replica_count
  selector:
    matchLabels:
      app: perf-test
      size: $scenario
  template:
    metadata:
      labels:
        app: perf-test
        size: $scenario
    spec:
      containers:
      - name: app
        image: nginx:alpine
        ports:
        - containerPort: 80
        resources:
          requests:
            cpu: 50m
            memory: 64Mi
          limits:
            cpu: 100m
            memory: 128Mi
        readinessProbe:
          httpGet:
            path: /
            port: 80
          initialDelaySeconds: 2
          periodSeconds: 2
        livenessProbe:
          httpGet:
            path: /
            port: 80
          initialDelaySeconds: 5
          periodSeconds: 5
---
apiVersion: v1
kind: Service
metadata:
  name: perf-test-$scenario-service
  namespace: $TEST_NAMESPACE
spec:
  selector:
    app: perf-test
    size: $scenario
  ports:
  - port: 80
    targetPort: 80
EOF
        
        # Measure deployment time
        local deployment_start=$(date +%s.%N)
        kubectl apply -f "/tmp/perf-test-$scenario.yaml"
        
        # Wait for deployment to be ready
        if ! kubectl wait --for=condition=available --timeout=300s "deployment/perf-test-$scenario" -n "$TEST_NAMESPACE"; then
            error "$scenario deployment failed to become ready"
            return 1
        fi
        
        local deployment_end=$(date +%s.%N)
        local deployment_duration=$(echo "$deployment_end - $deployment_start" | bc -l)
        
        DEPLOYMENT_TIMES+=("$scenario:$deployment_duration")
        log "$scenario deployment completed in ${deployment_duration}s"
        
        # Measure time to first successful response
        local service_ip
        service_ip=$(kubectl get service "perf-test-$scenario-service" -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
        
        local test_pod
        test_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l size="$scenario" -o jsonpath='{.items[0].metadata.name}')
        
        local response_start=$(date +%s.%N)
        local max_attempts=30
        local attempt=0
        
        while [[ $attempt -lt $max_attempts ]]; do
            if kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s --max-time 5 "http://$service_ip/" >/dev/null 2>&1; then
                break
            fi
            ((attempt++))
            sleep 1
        done
        
        local response_end=$(date +%s.%N)
        local response_duration=$(echo "$response_end - $response_start" | bc -l)
        
        RESPONSE_TIMES+=("$scenario:$response_duration")
        log "$scenario first response in ${response_duration}s"
    done
    
    # Analyze deployment performance
    log "Deployment Performance Analysis:"
    for timing in "${DEPLOYMENT_TIMES[@]}"; do
        local scenario=$(echo "$timing" | cut -d: -f1)
        local duration=$(echo "$timing" | cut -d: -f2)
        log "  $scenario: ${duration}s"
        
        # Check against performance thresholds
        local threshold
        case "$scenario" in
            "small") threshold=30 ;;
            "medium") threshold=60 ;;
            "large") threshold=120 ;;
        esac
        
        if (( $(echo "$duration > $threshold" | bc -l) )); then
            warning "$scenario deployment exceeded threshold (${duration}s > ${threshold}s)"
        fi
    done
    
    success "Deployment duration profiling test passed"
    return 0
}

# Test 2: Resource Utilization Analysis
test_resource_utilization_analysis() {
    log "Testing resource utilization analysis..."
    
    # Check if metrics-server is available
    if ! kubectl top nodes >/dev/null 2>&1; then
        warning "Metrics server not available, skipping resource utilization analysis"
        return 0
    fi
    
    # Collect baseline resource usage
    log "Collecting baseline resource usage..."
    
    local deployments=("perf-test-small" "perf-test-medium" "perf-test-large")
    
    for deployment in "${deployments[@]}"; do
        if ! kubectl get deployment "$deployment" -n "$TEST_NAMESPACE" >/dev/null 2>&1; then
            warning "Deployment $deployment not found, skipping"
            continue
        fi
        
        log "Analyzing resource usage for $deployment"
        
        # Get pods for this deployment
        local pods
        readarray -t pods < <(kubectl get pods -n "$TEST_NAMESPACE" -l app=perf-test --no-headers | grep "$deployment" | awk '{print $1}')
        
        local total_cpu=0
        local total_memory=0
        local pod_count=0
        
        for pod in "${pods[@]}"; do
            if [[ -n "$pod" ]]; then
                local cpu_usage memory_usage
                cpu_usage=$(kubectl top pod "$pod" -n "$TEST_NAMESPACE" --no-headers | awk '{print $2}' | sed 's/m$//')
                memory_usage=$(kubectl top pod "$pod" -n "$TEST_NAMESPACE" --no-headers | awk '{print $3}' | sed 's/Mi$//')
                
                if [[ -n "$cpu_usage" && "$cpu_usage" =~ ^[0-9]+$ ]]; then
                    total_cpu=$((total_cpu + cpu_usage))
                fi
                
                if [[ -n "$memory_usage" && "$memory_usage" =~ ^[0-9]+$ ]]; then
                    total_memory=$((total_memory + memory_usage))
                fi
                
                ((pod_count++))
            fi
        done
        
        if [[ $pod_count -gt 0 ]]; then
            local avg_cpu=$((total_cpu / pod_count))
            local avg_memory=$((total_memory / pod_count))
            
            RESOURCE_USAGE+=("$deployment:cpu:$avg_cpu:memory:$avg_memory")
            log "  $deployment - Avg CPU: ${avg_cpu}m, Avg Memory: ${avg_memory}Mi (across $pod_count pods)"
            
            # Check against resource limits
            local cpu_limit=100  # 100m
            local memory_limit=128  # 128Mi
            
            if [[ $avg_cpu -gt $cpu_limit ]]; then
                warning "$deployment CPU usage ($avg_cpu m) exceeds limit ($cpu_limit m)"
            fi
            
            if [[ $avg_memory -gt $memory_limit ]]; then
                warning "$deployment memory usage ($avg_memory Mi) exceeds limit ($memory_limit Mi)"
            fi
        fi
    done
    
    success "Resource utilization analysis test passed"
    return 0
}

# Test 3: Health Check Response Times
test_health_check_response_times() {
    log "Testing health check response times..."
    
    local deployments=("perf-test-small" "perf-test-medium" "perf-test-large")
    
    for deployment in "${deployments[@]}"; do
        if ! kubectl get deployment "$deployment" -n "$TEST_NAMESPACE" >/dev/null 2>&1; then
            warning "Deployment $deployment not found, skipping"
            continue
        fi
        
        log "Testing health check response times for $deployment"
        
        # Get service IP
        local service_name="${deployment}-service"
        local service_ip
        service_ip=$(kubectl get service "$service_name" -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
        
        # Get a test pod
        local test_pod
        test_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l app=perf-test -o jsonpath='{.items[0].metadata.name}')
        
        # Measure response times
        local response_times=()
        local iterations=10
        
        for i in $(seq 1 $iterations); do
            local start_time=$(date +%s.%N)
            
            if kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s --max-time 10 "http://$service_ip/" >/dev/null; then
                local end_time=$(date +%s.%N)
                local response_time=$(echo "($end_time - $start_time) * 1000" | bc -l)
                response_times+=("$response_time")
            else
                warning "Health check failed for $deployment (iteration $i)"
            fi
            
            sleep 0.1
        done
        
        # Calculate statistics
        if [[ ${#response_times[@]} -gt 0 ]]; then
            local sum=0
            local min=${response_times[0]}
            local max=${response_times[0]}
            
            for time in "${response_times[@]}"; do
                sum=$(echo "$sum + $time" | bc -l)
                if (( $(echo "$time < $min" | bc -l) )); then
                    min=$time
                fi
                if (( $(echo "$time > $max" | bc -l) )); then
                    max=$time
                fi
            done
            
            local avg=$(echo "$sum / ${#response_times[@]}" | bc -l)
            
            log "  $deployment response times - Avg: ${avg}ms, Min: ${min}ms, Max: ${max}ms"
            
            # Check against performance thresholds
            local threshold=1000  # 1000ms
            if (( $(echo "$avg > $threshold" | bc -l) )); then
                warning "$deployment average response time (${avg}ms) exceeds threshold (${threshold}ms)"
            fi
        fi
    done
    
    success "Health check response times test passed"
    return 0
}

# Test 4: Traffic Switch Latency Measurement
test_traffic_switch_latency() {
    log "Testing traffic switch latency measurement..."
    
    # Create blue-green setup for traffic switch testing
    cat > /tmp/traffic-switch-test.yaml << EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  name: traffic-test-blue
  namespace: $TEST_NAMESPACE
spec:
  replicas: 2
  selector:
    matchLabels:
      app: traffic-test
      version: blue
  template:
    metadata:
      labels:
        app: traffic-test
        version: blue
    spec:
      containers:
      - name: app
        image: nginx:alpine
        ports:
        - containerPort: 80
        command: ["/bin/sh"]
        args:
        - -c
        - |
          echo "Blue Version" > /usr/share/nginx/html/index.html
          echo "{\"version\": \"blue\", \"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" > /usr/share/nginx/html/health
          nginx -g 'daemon off;'
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: traffic-test-green
  namespace: $TEST_NAMESPACE
spec:
  replicas: 2
  selector:
    matchLabels:
      app: traffic-test
      version: green
  template:
    metadata:
      labels:
        app: traffic-test
        version: green
    spec:
      containers:
      - name: app
        image: nginx:alpine
        ports:
        - containerPort: 80
        command: ["/bin/sh"]
        args:
        - -c
        - |
          echo "Green Version" > /usr/share/nginx/html/index.html
          echo "{\"version\": \"green\", \"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" > /usr/share/nginx/html/health
          nginx -g 'daemon off;'
---
apiVersion: v1
kind: Service
metadata:
  name: traffic-test-active
  namespace: $TEST_NAMESPACE
spec:
  selector:
    app: traffic-test
    version: blue
  ports:
  - port: 80
    targetPort: 80
EOF
    
    kubectl apply -f /tmp/traffic-switch-test.yaml
    
    # Wait for deployments
    kubectl wait --for=condition=available --timeout=180s deployment/traffic-test-blue -n "$TEST_NAMESPACE"
    kubectl wait --for=condition=available --timeout=180s deployment/traffic-test-green -n "$TEST_NAMESPACE"
    
    # Measure traffic switch time
    log "Measuring traffic switch latency..."
    
    local test_pod
    test_pod=$(kubectl get pods -n "$TEST_NAMESPACE" -l version=blue -o jsonpath='{.items[0].metadata.name}')
    
    local service_ip
    service_ip=$(kubectl get service traffic-test-active -n "$TEST_NAMESPACE" -o jsonpath='{.spec.clusterIP}')
    
    # Verify initial state (blue)
    local initial_response
    initial_response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$service_ip/health" | jq -r '.version')
    
    if [[ "$initial_response" != "blue" ]]; then
        error "Initial traffic not routing to blue. Got: $initial_response"
        return 1
    fi
    
    # Measure switch time
    local switch_start=$(date +%s.%N)
    
    # Perform traffic switch
    kubectl patch service traffic-test-active -n "$TEST_NAMESPACE" --patch '{"spec":{"selector":{"version":"green"}}}'
    
    # Wait for switch to take effect
    local max_attempts=60
    local attempt=0
    local switch_detected=false
    
    while [[ $attempt -lt $max_attempts ]]; do
        local current_response
        current_response=$(kubectl exec -n "$TEST_NAMESPACE" "$test_pod" -- curl -s "http://$service_ip/health" 2>/dev/null | jq -r '.version' 2>/dev/null)
        
        if [[ "$current_response" == "green" ]]; then
            switch_detected=true
            break
        fi
        
        ((attempt++))
        sleep 0.5
    done
    
    local switch_end=$(date +%s.%N)
    local switch_duration=$(echo "$switch_end - $switch_start" | bc -l)
    
    if [[ "$switch_detected" == true ]]; then
        log "Traffic switch completed in ${switch_duration}s"
        
        # Check against performance threshold
        local threshold=60  # 60 seconds
        if (( $(echo "$switch_duration > $threshold" | bc -l) )); then
            warning "Traffic switch duration (${switch_duration}s) exceeds threshold (${threshold}s)"
        fi
    else
        error "Traffic switch not detected within timeout"
        return 1
    fi
    
    success "Traffic switch latency measurement test passed"
    return 0
}

# Test 5: Database Migration Performance
test_database_migration_performance() {
    log "Testing database migration performance..."
    
    # Simulate database migration timing
    cat > /tmp/migration-test.yaml << EOF
apiVersion: batch/v1
kind: Job
metadata:
  name: migration-performance-test
  namespace: $TEST_NAMESPACE
spec:
  template:
    spec:
      containers:
      - name: migration
        image: alpine:latest
        command: ["/bin/sh"]
        args:
        - -c
        - |
          echo "Starting migration simulation..."
          
          # Simulate different migration scenarios
          echo "Phase 1: Schema changes (5s)"
          sleep 5
          
          echo "Phase 2: Data migration (10s)"
          sleep 10
          
          echo "Phase 3: Index creation (8s)"
          sleep 8
          
          echo "Phase 4: Validation (2s)"
          sleep 2
          
          echo "Migration completed successfully"
      restartPolicy: Never
  backoffLimit: 1
EOF
    
    kubectl apply -f /tmp/migration-test.yaml
    
    # Measure migration time
    local migration_start=$(date +%s.%N)
    
    # Wait for job completion
    if ! kubectl wait --for=condition=complete --timeout=300s job/migration-performance-test -n "$TEST_NAMESPACE"; then
        error "Migration performance test job failed"
        return 1
    fi
    
    local migration_end=$(date +%s.%N)
    local migration_duration=$(echo "$migration_end - $migration_start" | bc -l)
    
    log "Database migration simulation completed in ${migration_duration}s"
    
    # Check against performance threshold
    local threshold=60  # 60 seconds
    if (( $(echo "$migration_duration > $threshold" | bc -l) )); then
        warning "Migration duration (${migration_duration}s) exceeds threshold (${threshold}s)"
    fi
    
    success "Database migration performance test passed"
    return 0
}

# Test 6: Concurrent Deployment Performance
test_concurrent_deployment_performance() {
    log "Testing concurrent deployment performance..."
    
    # Create multiple deployments concurrently
    local concurrent_deployments=3
    local deployment_pids=()
    
    log "Starting $concurrent_deployments concurrent deployments..."
    
    local concurrent_start=$(date +%s.%N)
    
    for i in $(seq 1 $concurrent_deployments); do
        (
            cat > "/tmp/concurrent-test-$i.yaml" << EOF
apiVersion: apps/v1
kind: Deployment
metadata:
  name: concurrent-test-$i
  namespace: $TEST_NAMESPACE
spec:
  replicas: 2
  selector:
    matchLabels:
      app: concurrent-test
      instance: test-$i
  template:
    metadata:
      labels:
        app: concurrent-test
        instance: test-$i
    spec:
      containers:
      - name: app
        image: nginx:alpine
        ports:
        - containerPort: 80
        resources:
          requests:
            cpu: 50m
            memory: 64Mi
          limits:
            cpu: 100m
            memory: 128Mi
EOF
            
            kubectl apply -f "/tmp/concurrent-test-$i.yaml"
            kubectl wait --for=condition=available --timeout=300s "deployment/concurrent-test-$i" -n "$TEST_NAMESPACE"
        ) &
        
        deployment_pids+=($!)
    done
    
    # Wait for all concurrent deployments to complete
    for pid in "${deployment_pids[@]}"; do
        wait "$pid"
    done
    
    local concurrent_end=$(date +%s.%N)
    local concurrent_duration=$(echo "$concurrent_end - $concurrent_start" | bc -l)
    
    log "All $concurrent_deployments concurrent deployments completed in ${concurrent_duration}s"
    
    # Check against performance threshold
    local threshold=180  # 3 minutes for concurrent deployments
    if (( $(echo "$concurrent_duration > $threshold" | bc -l) )); then
        warning "Concurrent deployment duration (${concurrent_duration}s) exceeds threshold (${threshold}s)"
    fi
    
    # Verify all deployments are healthy
    for i in $(seq 1 $concurrent_deployments); do
        local ready_replicas
        ready_replicas=$(kubectl get deployment "concurrent-test-$i" -n "$TEST_NAMESPACE" -o jsonpath='{.status.readyReplicas}')
        
        if [[ "$ready_replicas" != "2" ]]; then
            error "Concurrent deployment $i does not have expected replicas. Expected: 2, Got: $ready_replicas"
            return 1
        fi
    done
    
    success "Concurrent deployment performance test passed"
    return 0
}

# Generate performance report
generate_performance_report() {
    log "Generating performance report..."
    
    local report_file="/tmp/performance-report-$(date +%Y%m%d-%H%M%S).json"
    
    cat > "$report_file" << EOF
{
  "test_timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "deployment_times": [
EOF
    
    # Add deployment times
    local first=true
    for timing in "${DEPLOYMENT_TIMES[@]}"; do
        if [[ "$first" == true ]]; then
            first=false
        else
            echo "," >> "$report_file"
        fi
        
        local scenario=$(echo "$timing" | cut -d: -f1)
        local duration=$(echo "$timing" | cut -d: -f2)
        echo "    {\"scenario\": \"$scenario\", \"duration_seconds\": $duration}" >> "$report_file"
    done
    
    cat >> "$report_file" << EOF
  ],
  "resource_usage": [
EOF
    
    # Add resource usage
    first=true
    for usage in "${RESOURCE_USAGE[@]}"; do
        if [[ "$first" == true ]]; then
            first=false
        else
            echo "," >> "$report_file"
        fi
        
        local deployment=$(echo "$usage" | cut -d: -f1)
        local cpu=$(echo "$usage" | cut -d: -f3)
        local memory=$(echo "$usage" | cut -d: -f5)
        echo "    {\"deployment\": \"$deployment\", \"avg_cpu_millicores\": $cpu, \"avg_memory_mb\": $memory}" >> "$report_file"
    done
    
    cat >> "$report_file" << EOF
  ],
  "response_times": [
EOF
    
    # Add response times
    first=true
    for timing in "${RESPONSE_TIMES[@]}"; do
        if [[ "$first" == true ]]; then
            first=false
        else
            echo "," >> "$report_file"
        fi
        
        local scenario=$(echo "$timing" | cut -d: -f1)
        local duration=$(echo "$timing" | cut -d: -f2)
        echo "    {\"scenario\": \"$scenario\", \"first_response_seconds\": $duration}" >> "$report_file"
    done
    
    cat >> "$report_file" << EOF
  ]
}
EOF
    
    log "Performance report generated: $report_file"
    
    # Display summary
    log "Performance Test Summary:"
    log "========================"
    
    if [[ ${#DEPLOYMENT_TIMES[@]} -gt 0 ]]; then
        log "Deployment Times:"
        for timing in "${DEPLOYMENT_TIMES[@]}"; do
            local scenario=$(echo "$timing" | cut -d: -f1)
            local duration=$(echo "$timing" | cut -d: -f2)
            log "  $scenario: ${duration}s"
        done
    fi
    
    if [[ ${#RESOURCE_USAGE[@]} -gt 0 ]]; then
        log "Resource Usage:"
        for usage in "${RESOURCE_USAGE[@]}"; do
            local deployment=$(echo "$usage" | cut -d: -f1)
            local cpu=$(echo "$usage" | cut -d: -f3)
            local memory=$(echo "$usage" | cut -d: -f5)
            log "  $deployment: CPU ${cpu}m, Memory ${memory}Mi"
        done
    fi
}

# Main test execution
main() {
    log "Starting Performance Testing Suite"
    log "Log file: $LOG_FILE"
    
    # Check if bc is available for calculations
    if ! command -v bc &> /dev/null; then
        error "bc (calculator) is not installed. Required for performance calculations."
        exit 1
    fi
    
    # Run all performance tests
    run_test "Deployment Duration Profiling" test_deployment_duration_profiling
    run_test "Resource Utilization Analysis" test_resource_utilization_analysis
    run_test "Health Check Response Times" test_health_check_response_times
    run_test "Traffic Switch Latency Measurement" test_traffic_switch_latency
    run_test "Database Migration Performance" test_database_migration_performance
    run_test "Concurrent Deployment Performance" test_concurrent_deployment_performance
    
    # Generate performance report
    generate_performance_report
    
    # Print test summary
    log "Performance Test Summary:"
    log "========================"
    success "Performance Tests Passed: $TESTS_PASSED"
    
    if [[ $TESTS_FAILED -gt 0 ]]; then
        error "Performance Tests Failed: $TESTS_FAILED"
        error "Failed Performance Tests:"
        for test in "${FAILED_TESTS[@]}"; do
            error "  - $test"
        done
        exit 1
    else
        success "All performance tests passed!"
        exit 0
    fi
}

# Run main function
main "$@"
