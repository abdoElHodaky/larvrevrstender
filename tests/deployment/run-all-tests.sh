#!/bin/bash

# Comprehensive Test Runner for Blue-Green Deployment
# Orchestrates all testing phases in the correct sequence
# Part of Phase 1: Comprehensive Testing Framework

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="/tmp/comprehensive-test-run-$(date +%Y%m%d-%H%M%S).log"
RESULTS_DIR="/tmp/test-results-$(date +%Y%m%d-%H%M%S)"

# Test categories
BASIC_TESTS=(
    "fluxcd-deployment-test.sh"
    "blue-green-validation.sh"
    "traffic-switch-test.sh"
    "e2e-deployment-test.sh"
)

CHAOS_TESTS=(
    "chaos-engineering/service-failure-simulation.sh"
)

PERFORMANCE_TESTS=(
    "performance-testing/deployment-duration-profiling.sh"
)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
PURPLE='\033[0;35m'
BOLD='\033[1m'
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

header() {
    echo -e "${BOLD}${PURPLE}$1${NC}" | tee -a "$LOG_FILE"
}

# Test execution tracking
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
SKIPPED_TESTS=0
declare -a FAILED_TEST_NAMES
declare -a TEST_RESULTS

# Function to run a single test script
run_test_script() {
    local test_script="$1"
    local test_category="$2"
    local test_path="$SCRIPT_DIR/$test_script"
    
    ((TOTAL_TESTS++))
    
    header "Running $test_category: $test_script"
    
    # Check if test script exists and is executable
    if [[ ! -f "$test_path" ]]; then
        error "Test script not found: $test_path"
        FAILED_TEST_NAMES+=("$test_script (not found)")
        ((FAILED_TESTS++))
        TEST_RESULTS+=("FAILED: $test_script - Script not found")
        return 1
    fi
    
    if [[ ! -x "$test_path" ]]; then
        log "Making test script executable: $test_script"
        chmod +x "$test_path"
    fi
    
    # Create individual test log
    local test_log="$RESULTS_DIR/${test_script%.sh}.log"
    mkdir -p "$RESULTS_DIR"
    
    # Run the test with timeout
    local test_start_time
    test_start_time=$(date +%s)
    
    if timeout 1800 "$test_path" > "$test_log" 2>&1; then
        local test_end_time
        test_end_time=$(date +%s)
        local test_duration=$((test_end_time - test_start_time))
        
        success "✅ PASSED: $test_script (${test_duration}s)"
        ((PASSED_TESTS++))
        TEST_RESULTS+=("PASSED: $test_script - ${test_duration}s")
        
        # Copy test log to main log
        echo "--- Test Output for $test_script ---" >> "$LOG_FILE"
        tail -20 "$test_log" >> "$LOG_FILE"
        echo "--- End Test Output ---" >> "$LOG_FILE"
        
        return 0
    else
        local test_end_time
        test_end_time=$(date +%s)
        local test_duration=$((test_end_time - test_start_time))
        
        error "❌ FAILED: $test_script (${test_duration}s)"
        FAILED_TEST_NAMES+=("$test_script")
        ((FAILED_TESTS++))
        TEST_RESULTS+=("FAILED: $test_script - ${test_duration}s")
        
        # Copy error output to main log
        echo "--- Error Output for $test_script ---" >> "$LOG_FILE"
        tail -50 "$test_log" >> "$LOG_FILE"
        echo "--- End Error Output ---" >> "$LOG_FILE"
        
        return 1
    fi
}

# Function to check prerequisites
check_prerequisites() {
    header "Checking Prerequisites"
    
    local prereq_failed=false
    
    # Check kubectl
    if ! command -v kubectl &>/dev/null; then
        error "kubectl is not installed or not in PATH"
        prereq_failed=true
    else
        success "kubectl is available"
    fi
    
    # Check cluster connectivity
    if ! kubectl cluster-info &>/dev/null; then
        error "Cannot connect to Kubernetes cluster"
        prereq_failed=true
    else
        success "Kubernetes cluster is accessible"
    fi
    
    # Check required namespaces
    local required_namespaces=("reverse-tender-blue" "reverse-tender-green" "flux-system")
    
    for namespace in "${required_namespaces[@]}"; do
        if kubectl get namespace "$namespace" &>/dev/null; then
            success "Namespace '$namespace' exists"
        else
            error "Required namespace '$namespace' does not exist"
            prereq_failed=true
        fi
    done
    
    # Check FluxCD controllers
    local controllers=("source-controller" "kustomize-controller" "helm-controller")
    
    for controller in "${controllers[@]}"; do
        local ready_replicas
        ready_replicas=$(kubectl get deployment "$controller" -n flux-system -o jsonpath='{.status.readyReplicas}' 2>/dev/null || echo "0")
        
        if [[ "$ready_replicas" != "0" ]]; then
            success "FluxCD $controller is running"
        else
            warning "FluxCD $controller may not be ready"
        fi
    done
    
    # Check for required tools
    local tools=("curl" "bc" "jq")
    
    for tool in "${tools[@]}"; do
        if command -v "$tool" &>/dev/null; then
            success "$tool is available"
        else
            warning "$tool is not available (some tests may be limited)"
        fi
    done
    
    if [[ "$prereq_failed" == "true" ]]; then
        error "Prerequisites check failed"
        return 1
    else
        success "All prerequisites satisfied"
        return 0
    fi
}

# Function to run basic tests
run_basic_tests() {
    header "Phase 1: Basic Deployment Tests"
    
    local basic_failed=false
    
    for test_script in "${BASIC_TESTS[@]}"; do
        if ! run_test_script "$test_script" "Basic Test"; then
            basic_failed=true
            
            # For critical tests, consider stopping
            if [[ "$test_script" == "fluxcd-deployment-test.sh" ]]; then
                error "Critical FluxCD test failed - considering stopping test suite"
                read -p "Continue with remaining tests? (y/N): " -n 1 -r
                echo
                if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                    return 1
                fi
            fi
        fi
        
        # Brief pause between tests
        sleep 5
    done
    
    if [[ "$basic_failed" == "true" ]]; then
        warning "Some basic tests failed"
        return 1
    else
        success "All basic tests passed"
        return 0
    fi
}

# Function to run chaos engineering tests
run_chaos_tests() {
    header "Phase 2: Chaos Engineering Tests"
    
    # Ask for confirmation before running destructive tests
    warning "Chaos engineering tests will intentionally cause service failures"
    read -p "Proceed with chaos tests? (y/N): " -n 1 -r
    echo
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        warning "Chaos tests skipped by user"
        SKIPPED_TESTS=$((SKIPPED_TESTS + ${#CHAOS_TESTS[@]}))
        return 0
    fi
    
    local chaos_failed=false
    
    for test_script in "${CHAOS_TESTS[@]}"; do
        if ! run_test_script "$test_script" "Chaos Test"; then
            chaos_failed=true
        fi
        
        # Longer pause between chaos tests for system recovery
        sleep 30
    done
    
    if [[ "$chaos_failed" == "true" ]]; then
        warning "Some chaos tests failed"
        return 1
    else
        success "All chaos tests passed"
        return 0
    fi
}

# Function to run performance tests
run_performance_tests() {
    header "Phase 3: Performance Tests"
    
    local performance_failed=false
    
    for test_script in "${PERFORMANCE_TESTS[@]}"; do
        if ! run_test_script "$test_script" "Performance Test"; then
            performance_failed=true
        fi
        
        # Brief pause between performance tests
        sleep 10
    done
    
    if [[ "$performance_failed" == "true" ]]; then
        warning "Some performance tests failed"
        return 1
    else
        success "All performance tests passed"
        return 0
    fi
}

# Function to generate comprehensive report
generate_comprehensive_report() {
    header "Generating Comprehensive Test Report"
    
    local report_file="$RESULTS_DIR/comprehensive-test-report.md"
    local json_report="$RESULTS_DIR/test-results.json"
    
    # Create markdown report
    cat > "$report_file" << EOF
# Comprehensive Blue-Green Deployment Test Report

**Generated**: $(date -Iseconds)
**Test Duration**: $(($(date +%s) - START_TIME)) seconds
**Results Directory**: $RESULTS_DIR

## Summary

- **Total Tests**: $TOTAL_TESTS
- **Passed**: $PASSED_TESTS
- **Failed**: $FAILED_TESTS
- **Skipped**: $SKIPPED_TESTS
- **Success Rate**: $(echo "scale=1; $PASSED_TESTS * 100 / $TOTAL_TESTS" | bc -l 2>/dev/null || echo "N/A")%

## Test Results

EOF
    
    # Add individual test results
    for result in "${TEST_RESULTS[@]}"; do
        echo "- $result" >> "$report_file"
    done
    
    if [[ $FAILED_TESTS -gt 0 ]]; then
        echo "" >> "$report_file"
        echo "## Failed Tests" >> "$report_file"
        echo "" >> "$report_file"
        
        for failed_test in "${FAILED_TEST_NAMES[@]}"; do
            echo "- $failed_test" >> "$report_file"
        done
    fi
    
    # Add recommendations
    echo "" >> "$report_file"
    echo "## Recommendations" >> "$report_file"
    echo "" >> "$report_file"
    
    if [[ $FAILED_TESTS -eq 0 ]]; then
        echo "✅ **All tests passed!** The blue-green deployment system is ready for production." >> "$report_file"
    elif [[ $FAILED_TESTS -le 2 ]]; then
        echo "⚠️ **Minor issues detected.** Review failed tests and address before production deployment." >> "$report_file"
    else
        echo "❌ **Significant issues detected.** System requires fixes before production deployment." >> "$report_file"
    fi
    
    # Create JSON report
    cat > "$json_report" << EOF
{
  "summary": {
    "timestamp": "$(date -Iseconds)",
    "total_tests": $TOTAL_TESTS,
    "passed_tests": $PASSED_TESTS,
    "failed_tests": $FAILED_TESTS,
    "skipped_tests": $SKIPPED_TESTS,
    "success_rate": $(echo "scale=2; $PASSED_TESTS * 100 / $TOTAL_TESTS" | bc -l 2>/dev/null || echo "0")
  },
  "test_results": [
EOF
    
    local first_result=true
    for result in "${TEST_RESULTS[@]}"; do
        if [[ "$first_result" == "false" ]]; then
            echo "," >> "$json_report"
        fi
        echo "    \"$result\"" >> "$json_report"
        first_result=false
    done
    
    echo "  ]" >> "$json_report"
    echo "}" >> "$json_report"
    
    success "Comprehensive report generated:"
    info "  Markdown: $report_file"
    info "  JSON: $json_report"
    info "  Logs: $RESULTS_DIR/"
}

# Function to display usage
show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --basic-only     Run only basic tests (skip chaos and performance)"
    echo "  --no-chaos       Skip chaos engineering tests"
    echo "  --no-performance Skip performance tests"
    echo "  --help           Show this help message"
    echo ""
    echo "Test Categories:"
    echo "  Basic Tests:       FluxCD, Blue-Green validation, Traffic switching, E2E"
    echo "  Chaos Tests:       Service failure simulation, resilience testing"
    echo "  Performance Tests: Duration profiling, resource utilization"
}

# Main execution
main() {
    local run_basic=true
    local run_chaos=true
    local run_performance=true
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --basic-only)
                run_chaos=false
                run_performance=false
                shift
                ;;
            --no-chaos)
                run_chaos=false
                shift
                ;;
            --no-performance)
                run_performance=false
                shift
                ;;
            --help)
                show_usage
                exit 0
                ;;
            *)
                error "Unknown option: $1"
                show_usage
                exit 1
                ;;
        esac
    done
    
    # Record start time
    START_TIME=$(date +%s)
    
    header "🚀 Starting Comprehensive Blue-Green Deployment Test Suite"
    log "Logging to: $LOG_FILE"
    log "Results directory: $RESULTS_DIR"
    echo ""
    
    # Create results directory
    mkdir -p "$RESULTS_DIR"
    
    # Check prerequisites
    if ! check_prerequisites; then
        error "Prerequisites check failed - aborting test run"
        exit 1
    fi
    
    echo ""
    
    # Run test phases
    local overall_success=true
    
    if [[ "$run_basic" == "true" ]]; then
        if ! run_basic_tests; then
            overall_success=false
        fi
        echo ""
    fi
    
    if [[ "$run_chaos" == "true" ]]; then
        if ! run_chaos_tests; then
            overall_success=false
        fi
        echo ""
    fi
    
    if [[ "$run_performance" == "true" ]]; then
        if ! run_performance_tests; then
            overall_success=false
        fi
        echo ""
    fi
    
    # Generate comprehensive report
    generate_comprehensive_report
    
    # Final summary
    local end_time
    end_time=$(date +%s)
    local total_duration=$((end_time - START_TIME))
    
    header "🏁 Test Suite Complete"
    echo "=================================="
    success "Total Tests: $TOTAL_TESTS"
    success "Passed: $PASSED_TESTS"
    
    if [[ $FAILED_TESTS -gt 0 ]]; then
        error "Failed: $FAILED_TESTS"
    fi
    
    if [[ $SKIPPED_TESTS -gt 0 ]]; then
        warning "Skipped: $SKIPPED_TESTS"
    fi
    
    info "Total Duration: ${total_duration}s"
    echo "=================================="
    
    if [[ "$overall_success" == "true" ]] && [[ $FAILED_TESTS -eq 0 ]]; then
        success "🎉 ALL TESTS PASSED! Blue-green deployment system is validated."
        exit 0
    else
        error "❌ Some tests failed. Review the report and address issues."
        exit 1
    fi
}

# Run main function with all arguments
main "$@"

