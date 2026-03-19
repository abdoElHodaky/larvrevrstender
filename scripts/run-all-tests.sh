#!/bin/bash

# =============================================================================
# Unified Testing Script for Reverse Tender Microservices
# =============================================================================
# This script runs PHPUnit tests across all 11 services in the ecosystem
# Usage: ./scripts/run-all-tests.sh [options]
# Options:
#   --service <name>    Run tests for specific service only
#   --parallel          Run tests in parallel (faster but less readable output)
#   --coverage          Generate code coverage reports
#   --stop-on-failure   Stop on first test failure
#   --verbose           Show detailed test output
# =============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SERVICES_DIR="services"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PARALLEL=false
COVERAGE=false
STOP_ON_FAILURE=false
VERBOSE=false
SPECIFIC_SERVICE=""

# Service list (11 services)
SERVICES=(
    "analytics-service"
    "auction-service"
    "auth-service"
    "bidding-service"
    "gateway-service"
    "notification-service"
    "order-service"
    "payment-service"
    "shared"
    "user-service"
    "vin-ocr-service"
)

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --service)
            SPECIFIC_SERVICE="$2"
            shift 2
            ;;
        --parallel)
            PARALLEL=true
            shift
            ;;
        --coverage)
            COVERAGE=true
            shift
            ;;
        --stop-on-failure)
            STOP_ON_FAILURE=true
            shift
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        --help)
            echo "Usage: $0 [options]"
            echo "Options:"
            echo "  --service <name>    Run tests for specific service only"
            echo "  --parallel          Run tests in parallel"
            echo "  --coverage          Generate code coverage reports"
            echo "  --stop-on-failure   Stop on first test failure"
            echo "  --verbose           Show detailed test output"
            echo ""
            echo "Available services:"
            printf "  %s\n" "${SERVICES[@]}"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Function to print colored output
print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Function to run tests for a single service
run_service_tests() {
    local service=$1
    local service_path="$PROJECT_ROOT/$SERVICES_DIR/$service"
    
    if [[ ! -d "$service_path" ]]; then
        print_status $RED "❌ Service directory not found: $service_path"
        return 1
    fi
    
    if [[ ! -f "$service_path/phpunit.xml" ]]; then
        print_status $YELLOW "⚠️  No phpunit.xml found for $service, skipping..."
        return 0
    fi
    
    print_status $BLUE "🧪 Running tests for $service..."
    
    cd "$service_path"
    
    # Build PHPUnit command
    local phpunit_cmd="./vendor/bin/phpunit"
    
    if [[ $COVERAGE == true ]]; then
        phpunit_cmd="$phpunit_cmd --coverage-html coverage --coverage-text"
    fi
    
    if [[ $STOP_ON_FAILURE == true ]]; then
        phpunit_cmd="$phpunit_cmd --stop-on-failure"
    fi
    
    if [[ $VERBOSE == true ]]; then
        phpunit_cmd="$phpunit_cmd --verbose"
    fi
    
    # Run tests
    if eval $phpunit_cmd; then
        print_status $GREEN "✅ $service tests passed"
        return 0
    else
        print_status $RED "❌ $service tests failed"
        return 1
    fi
}

# Function to run tests in parallel
run_parallel_tests() {
    local pids=()
    local results=()
    
    print_status $BLUE "🚀 Running tests in parallel for all services..."
    
    for service in "${SERVICES[@]}"; do
        if [[ -n "$SPECIFIC_SERVICE" && "$service" != "$SPECIFIC_SERVICE" ]]; then
            continue
        fi
        
        (
            run_service_tests "$service"
            echo $? > "/tmp/test_result_$service"
        ) &
        pids+=($!)
    done
    
    # Wait for all tests to complete
    for pid in "${pids[@]}"; do
        wait $pid
    done
    
    # Collect results
    local failed_services=()
    for service in "${SERVICES[@]}"; do
        if [[ -n "$SPECIFIC_SERVICE" && "$service" != "$SPECIFIC_SERVICE" ]]; then
            continue
        fi
        
        if [[ -f "/tmp/test_result_$service" ]]; then
            local result=$(cat "/tmp/test_result_$service")
            if [[ $result -ne 0 ]]; then
                failed_services+=("$service")
            fi
            rm -f "/tmp/test_result_$service"
        fi
    done
    
    return ${#failed_services[@]}
}

# Function to run tests sequentially
run_sequential_tests() {
    local failed_services=()
    
    for service in "${SERVICES[@]}"; do
        if [[ -n "$SPECIFIC_SERVICE" && "$service" != "$SPECIFIC_SERVICE" ]]; then
            continue
        fi
        
        if ! run_service_tests "$service"; then
            failed_services+=("$service")
            
            if [[ $STOP_ON_FAILURE == true ]]; then
                print_status $RED "🛑 Stopping on first failure: $service"
                break
            fi
        fi
        
        echo "" # Add spacing between services
    done
    
    return ${#failed_services[@]}
}

# Main execution
main() {
    print_status $BLUE "🎯 Reverse Tender Microservices Test Runner"
    print_status $BLUE "==========================================="
    
    # Validate specific service if provided
    if [[ -n "$SPECIFIC_SERVICE" ]]; then
        if [[ ! " ${SERVICES[@]} " =~ " ${SPECIFIC_SERVICE} " ]]; then
            print_status $RED "❌ Invalid service: $SPECIFIC_SERVICE"
            print_status $YELLOW "Available services: ${SERVICES[*]}"
            exit 1
        fi
        print_status $BLUE "🎯 Running tests for specific service: $SPECIFIC_SERVICE"
    else
        print_status $BLUE "🎯 Running tests for all ${#SERVICES[@]} services"
    fi
    
    # Show configuration
    echo ""
    print_status $BLUE "Configuration:"
    print_status $BLUE "├── Parallel: $PARALLEL"
    print_status $BLUE "├── Coverage: $COVERAGE"
    print_status $BLUE "├── Stop on failure: $STOP_ON_FAILURE"
    print_status $BLUE "└── Verbose: $VERBOSE"
    echo ""
    
    # Run tests
    local start_time=$(date +%s)
    
    if [[ $PARALLEL == true ]]; then
        run_parallel_tests
        local exit_code=$?
    else
        run_sequential_tests
        local exit_code=$?
    fi
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    
    # Summary
    echo ""
    print_status $BLUE "📊 Test Summary"
    print_status $BLUE "==============="
    print_status $BLUE "Duration: ${duration}s"
    
    if [[ $exit_code -eq 0 ]]; then
        print_status $GREEN "🎉 All tests passed!"
        if [[ $COVERAGE == true ]]; then
            print_status $BLUE "📈 Coverage reports generated in each service's coverage/ directory"
        fi
    else
        print_status $RED "💥 $exit_code service(s) failed tests"
        exit 1
    fi
}

# Change to project root
cd "$PROJECT_ROOT"

# Run main function
main "$@"

