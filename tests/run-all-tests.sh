#!/bin/bash

# Test Orchestration Framework
# Runs all test suites in sequence or parallel and generates comprehensive reports

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
LOG_FILE="/tmp/test-orchestration-$(date +%Y%m%d-%H%M%S).log"
REPORT_DIR="/tmp/test-reports-$(date +%Y%m%d-%H%M%S)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
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

info() {
    echo -e "${CYAN}[INFO] $1${NC}" | tee -a "$LOG_FILE"
}

header() {
    echo -e "${BOLD}${CYAN}$1${NC}" | tee -a "$LOG_FILE"
}

# Test suite definitions
declare -A TEST_SUITES=(
    ["fluxcd"]="FluxCD Deployment Tests"
    ["blue-green"]="Blue-Green Validation Tests"
    ["traffic-switch"]="Traffic Switch Tests"
    ["e2e"]="End-to-End Deployment Tests"
    ["chaos"]="Chaos Engineering Tests"
    ["performance"]="Performance Tests"
)

declare -A TEST_SCRIPTS=(
    ["fluxcd"]="deployment/fluxcd-deployment-test.sh"
    ["blue-green"]="deployment/blue-green-validation.sh"
    ["traffic-switch"]="deployment/traffic-switch-test.sh"
    ["e2e"]="deployment/e2e-deployment-test.sh"
    ["chaos"]="deployment/chaos-engineering/service-failure-test.sh"
    ["performance"]="deployment/performance-test.sh"
)

# Test execution tracking
declare -A TEST_RESULTS=()
declare -A TEST_DURATIONS=()
declare -A TEST_LOGS=()
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
SKIPPED_TESTS=0

# Configuration options
PARALLEL_EXECUTION=false
SELECTED_SUITES=()
CONTINUE_ON_FAILURE=true
GENERATE_REPORT=true
CLEANUP_AFTER_TESTS=true

# Usage information
usage() {
    cat << EOF
Usage: $0 [OPTIONS] [TEST_SUITES...]

Test Orchestration Framework for Blue-Green Deployment System

OPTIONS:
    -p, --parallel          Run test suites in parallel (default: sequential)
    -s, --stop-on-failure   Stop execution on first test failure
    -n, --no-report         Skip generating test report
    -k, --keep-resources    Keep test resources after completion
    -h, --help              Show this help message

TEST_SUITES:
    fluxcd                  FluxCD deployment and reconciliation tests
    blue-green              Blue-green environment validation tests
    traffic-switch          Traffic routing and switching tests
    e2e                     End-to-end deployment cycle tests
    chaos                   Chaos engineering and resilience tests
    performance             Performance and resource utilization tests
    all                     Run all test suites (default)

EXAMPLES:
    $0                      # Run all tests sequentially
    $0 --parallel all       # Run all tests in parallel
    $0 fluxcd blue-green    # Run only FluxCD and blue-green tests
    $0 -p -s performance    # Run performance tests in parallel, stop on failure

EOF
}

# Parse command line arguments
parse_arguments() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -p|--parallel)
                PARALLEL_EXECUTION=true
                shift
                ;;
            -s|--stop-on-failure)
                CONTINUE_ON_FAILURE=false
                shift
                ;;
            -n|--no-report)
                GENERATE_REPORT=false
                shift
                ;;
            -k|--keep-resources)
                CLEANUP_AFTER_TESTS=false
                shift
                ;;
            -h|--help)
                usage
                exit 0
                ;;
            all)
                SELECTED_SUITES=(${!TEST_SUITES[@]})
                shift
                ;;
            *)
                if [[ -n "${TEST_SUITES[$1]:-}" ]]; then
                    SELECTED_SUITES+=("$1")
                else
                    error "Unknown test suite: $1"
                    usage
                    exit 1
                fi
                shift
                ;;
        esac
    done
    
    # Default to all suites if none specified
    if [[ ${#SELECTED_SUITES[@]} -eq 0 ]]; then
        SELECTED_SUITES=(${!TEST_SUITES[@]})
    fi
}

# Setup test environment
setup_test_environment() {
    header "Setting up test environment..."
    
    # Create report directory
    mkdir -p "$REPORT_DIR"
    
    # Check prerequisites
    local required_tools=("kubectl" "jq" "bc")
    for tool in "${required_tools[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            error "Required tool not found: $tool"
            return 1
        fi
    done
    
    # Check Kubernetes connectivity
    if ! kubectl cluster-info >/dev/null 2>&1; then
        error "Cannot connect to Kubernetes cluster"
        return 1
    fi
    
    # Get cluster information
    local cluster_info
    cluster_info=$(kubectl cluster-info 2>/dev/null | head -1)
    info "Connected to: $cluster_info"
    
    # Check available resources
    local node_count
    node_count=$(kubectl get nodes --no-headers | wc -l)
    info "Cluster nodes: $node_count"
    
    success "Test environment setup completed"
    return 0
}

# Execute a single test suite
execute_test_suite() {
    local suite_name="$1"
    local suite_description="${TEST_SUITES[$suite_name]}"
    local script_path="${TEST_SCRIPTS[$suite_name]}"
    local full_script_path="$SCRIPT_DIR/$script_path"
    
    header "Executing: $suite_description"
    
    # Check if test script exists
    if [[ ! -f "$full_script_path" ]]; then
        error "Test script not found: $full_script_path"
        TEST_RESULTS["$suite_name"]="MISSING"
        return 1
    fi
    
    # Check if script is executable
    if [[ ! -x "$full_script_path" ]]; then
        warning "Making test script executable: $full_script_path"
        chmod +x "$full_script_path"
    fi
    
    # Create suite-specific log file
    local suite_log="$REPORT_DIR/${suite_name}-test.log"
    TEST_LOGS["$suite_name"]="$suite_log"
    
    # Execute test suite
    local start_time=$(date +%s)
    local exit_code=0
    
    log "Starting $suite_description..."
    
    if "$full_script_path" > "$suite_log" 2>&1; then
        TEST_RESULTS["$suite_name"]="PASSED"
        success "$suite_description completed successfully"
    else
        exit_code=$?
        TEST_RESULTS["$suite_name"]="FAILED"
        error "$suite_description failed with exit code $exit_code"
    fi
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    TEST_DURATIONS["$suite_name"]="$duration"
    
    log "$suite_description completed in ${duration}s"
    
    return $exit_code
}

# Execute test suites sequentially
execute_sequential() {
    header "Executing test suites sequentially..."
    
    for suite in "${SELECTED_SUITES[@]}"; do
        ((TOTAL_TESTS++))
        
        if execute_test_suite "$suite"; then
            ((PASSED_TESTS++))
        else
            ((FAILED_TESTS++))
            
            if [[ "$CONTINUE_ON_FAILURE" == false ]]; then
                error "Stopping execution due to test failure"
                break
            fi
        fi
    done
}

# Execute test suites in parallel
execute_parallel() {
    header "Executing test suites in parallel..."
    
    local pids=()
    
    # Start all test suites in background
    for suite in "${SELECTED_SUITES[@]}"; do
        ((TOTAL_TESTS++))
        execute_test_suite "$suite" &
        pids+=($!)
    done
    
    # Wait for all test suites to complete
    for i in "${!pids[@]}"; do
        local pid=${pids[$i]}
        local suite=${SELECTED_SUITES[$i]}
        
        if wait "$pid"; then
            ((PASSED_TESTS++))
        else
            ((FAILED_TESTS++))
        fi
    done
}

# Generate comprehensive test report
generate_test_report() {
    if [[ "$GENERATE_REPORT" == false ]]; then
        return 0
    fi
    
    header "Generating comprehensive test report..."
    
    local report_file="$REPORT_DIR/test-report.html"
    local json_report="$REPORT_DIR/test-report.json"
    
    # Generate JSON report
    cat > "$json_report" << EOF
{
  "test_execution": {
    "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "execution_mode": "$([ "$PARALLEL_EXECUTION" == true ] && echo "parallel" || echo "sequential")",
    "continue_on_failure": $CONTINUE_ON_FAILURE,
    "total_suites": $TOTAL_TESTS,
    "passed_suites": $PASSED_TESTS,
    "failed_suites": $FAILED_TESTS,
    "skipped_suites": $SKIPPED_TESTS
  },
  "test_results": [
EOF
    
    local first=true
    for suite in "${!TEST_RESULTS[@]}"; do
        if [[ "$first" == true ]]; then
            first=false
        else
            echo "," >> "$json_report"
        fi
        
        local result="${TEST_RESULTS[$suite]}"
        local duration="${TEST_DURATIONS[$suite]:-0}"
        local description="${TEST_SUITES[$suite]}"
        local log_file="${TEST_LOGS[$suite]:-}"
        
        cat >> "$json_report" << EOF
    {
      "suite": "$suite",
      "description": "$description",
      "result": "$result",
      "duration_seconds": $duration,
      "log_file": "$log_file"
    }
EOF
    done
    
    cat >> "$json_report" << EOF
  ]
}
EOF
    
    # Generate HTML report
    cat > "$report_file" << 'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blue-Green Deployment Test Report</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .summary-card.passed {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        }
        .summary-card.failed {
            background: linear-gradient(135deg, #f44336 0%, #da190b 100%);
        }
        .summary-card h3 {
            margin: 0 0 10px 0;
            font-size: 2em;
        }
        .summary-card p {
            margin: 0;
            opacity: 0.9;
        }
        .test-results {
            margin-top: 30px;
        }
        .test-suite {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .test-suite-header {
            padding: 15px 20px;
            background-color: #e9ecef;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .test-suite-header h3 {
            margin: 0;
            color: #495057;
        }
        .status {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.8em;
        }
        .status.passed {
            background-color: #d4edda;
            color: #155724;
        }
        .status.failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        .status.missing {
            background-color: #fff3cd;
            color: #856404;
        }
        .test-suite-body {
            padding: 20px;
        }
        .duration {
            color: #6c757d;
            font-size: 0.9em;
        }
        .log-link {
            color: #007bff;
            text-decoration: none;
            font-size: 0.9em;
        }
        .log-link:hover {
            text-decoration: underline;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 Blue-Green Deployment Test Report</h1>
            <p>Generated on <span id="timestamp"></span></p>
        </div>
        
        <div class="summary">
            <div class="summary-card">
                <h3 id="total-tests">0</h3>
                <p>Total Test Suites</p>
            </div>
            <div class="summary-card passed">
                <h3 id="passed-tests">0</h3>
                <p>Passed</p>
            </div>
            <div class="summary-card failed">
                <h3 id="failed-tests">0</h3>
                <p>Failed</p>
            </div>
        </div>
        
        <div class="test-results">
            <h2>Test Suite Results</h2>
            <div id="test-suites"></div>
        </div>
        
        <div class="footer">
            <p>Blue-Green Deployment Testing Framework</p>
        </div>
    </div>
    
    <script>
        // Load test results from JSON
        fetch('./test-report.json')
            .then(response => response.json())
            .then(data => {
                // Update summary
                document.getElementById('timestamp').textContent = new Date(data.test_execution.timestamp).toLocaleString();
                document.getElementById('total-tests').textContent = data.test_execution.total_suites;
                document.getElementById('passed-tests').textContent = data.test_execution.passed_suites;
                document.getElementById('failed-tests').textContent = data.test_execution.failed_suites;
                
                // Generate test suite results
                const testSuitesContainer = document.getElementById('test-suites');
                data.test_results.forEach(suite => {
                    const suiteDiv = document.createElement('div');
                    suiteDiv.className = 'test-suite';
                    
                    const duration = suite.duration_seconds > 0 ? `${suite.duration_seconds}s` : 'N/A';
                    const logLink = suite.log_file ? `<a href="${suite.log_file}" class="log-link">View Log</a>` : '';
                    
                    suiteDiv.innerHTML = `
                        <div class="test-suite-header">
                            <h3>${suite.description}</h3>
                            <span class="status ${suite.result.toLowerCase()}">${suite.result}</span>
                        </div>
                        <div class="test-suite-body">
                            <p><strong>Suite:</strong> ${suite.suite}</p>
                            <p class="duration"><strong>Duration:</strong> ${duration}</p>
                            ${logLink}
                        </div>
                    `;
                    
                    testSuitesContainer.appendChild(suiteDiv);
                });
            })
            .catch(error => {
                console.error('Error loading test results:', error);
            });
    </script>
</body>
</html>
EOF
    
    success "Test report generated:"
    info "  HTML Report: $report_file"
    info "  JSON Report: $json_report"
    info "  Log Directory: $REPORT_DIR"
}

# Display test summary
display_summary() {
    header "Test Execution Summary"
    
    echo -e "${BOLD}Total Test Suites:${NC} $TOTAL_TESTS"
    echo -e "${GREEN}Passed:${NC} $PASSED_TESTS"
    echo -e "${RED}Failed:${NC} $FAILED_TESTS"
    echo -e "${YELLOW}Skipped:${NC} $SKIPPED_TESTS"
    
    if [[ $TOTAL_TESTS -gt 0 ]]; then
        local success_rate=$((PASSED_TESTS * 100 / TOTAL_TESTS))
        echo -e "${BOLD}Success Rate:${NC} ${success_rate}%"
    fi
    
    echo ""
    echo -e "${BOLD}Individual Results:${NC}"
    for suite in "${!TEST_RESULTS[@]}"; do
        local result="${TEST_RESULTS[$suite]}"
        local duration="${TEST_DURATIONS[$suite]:-0}"
        local description="${TEST_SUITES[$suite]}"
        
        case "$result" in
            "PASSED")
                echo -e "  ${GREEN}✓${NC} $description (${duration}s)"
                ;;
            "FAILED")
                echo -e "  ${RED}✗${NC} $description (${duration}s)"
                ;;
            "MISSING")
                echo -e "  ${YELLOW}?${NC} $description (script not found)"
                ;;
        esac
    done
}

# Cleanup test resources
cleanup_test_resources() {
    if [[ "$CLEANUP_AFTER_TESTS" == false ]]; then
        info "Skipping cleanup (--keep-resources specified)"
        return 0
    fi
    
    header "Cleaning up test resources..."
    
    # List of test namespaces that might have been created
    local test_namespaces=(
        "flux-system-test"
        "reverse-tender-test"
        "reverse-tender-e2e"
        "chaos-test-service-failure"
        "performance-test"
    )
    
    for namespace in "${test_namespaces[@]}"; do
        if kubectl get namespace "$namespace" >/dev/null 2>&1; then
            log "Cleaning up namespace: $namespace"
            kubectl delete namespace "$namespace" --timeout=60s || warning "Failed to delete namespace: $namespace"
        fi
    done
    
    success "Cleanup completed"
}

# Main execution function
main() {
    header "🚀 Blue-Green Deployment Test Orchestration Framework"
    log "Starting test orchestration at $(date)"
    log "Log file: $LOG_FILE"
    
    # Parse command line arguments
    parse_arguments "$@"
    
    # Display configuration
    info "Configuration:"
    info "  Execution Mode: $([ "$PARALLEL_EXECUTION" == true ] && echo "Parallel" || echo "Sequential")"
    info "  Continue on Failure: $CONTINUE_ON_FAILURE"
    info "  Generate Report: $GENERATE_REPORT"
    info "  Cleanup Resources: $CLEANUP_AFTER_TESTS"
    info "  Selected Suites: ${SELECTED_SUITES[*]}"
    
    # Setup test environment
    if ! setup_test_environment; then
        error "Test environment setup failed"
        exit 1
    fi
    
    # Execute test suites
    local start_time=$(date +%s)
    
    if [[ "$PARALLEL_EXECUTION" == true ]]; then
        execute_parallel
    else
        execute_sequential
    fi
    
    local end_time=$(date +%s)
    local total_duration=$((end_time - start_time))
    
    # Generate test report
    generate_test_report
    
    # Display summary
    display_summary
    
    # Cleanup resources
    cleanup_test_resources
    
    # Final status
    header "Test Orchestration Completed"
    info "Total execution time: ${total_duration}s"
    
    if [[ $FAILED_TESTS -gt 0 ]]; then
        error "Some test suites failed. Check the reports for details."
        exit 1
    else
        success "All test suites passed successfully!"
        exit 0
    fi
}

# Run main function with all arguments
main "$@"
