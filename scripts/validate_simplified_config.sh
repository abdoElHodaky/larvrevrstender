#!/bin/bash

# Configuration Validation Script for Simplified Naming
# This script validates that all services can communicate with simplified configurations

set -e

echo "🔍 Starting Configuration Validation..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0

# Function to print status
print_status() {
    local status="$1"
    local message="$2"
    
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    
    if [[ "$status" == "PASS" ]]; then
        echo -e "${GREEN}✅ PASS${NC}: $message"
        PASSED_CHECKS=$((PASSED_CHECKS + 1))
    elif [[ "$status" == "FAIL" ]]; then
        echo -e "${RED}❌ FAIL${NC}: $message"
        FAILED_CHECKS=$((FAILED_CHECKS + 1))
    elif [[ "$status" == "WARN" ]]; then
        echo -e "${YELLOW}⚠️  WARN${NC}: $message"
    else
        echo -e "${BLUE}ℹ️  INFO${NC}: $message"
    fi
}

# Function to check if service directory exists
check_service_directory() {
    local service="$1"
    
    if [[ -d "services/$service" ]]; then
        print_status "PASS" "Service directory 'services/$service' exists"
        return 0
    else
        print_status "FAIL" "Service directory 'services/$service' not found"
        return 1
    fi
}

# Function to check .env.example file
check_env_file() {
    local service="$1"
    local env_file="services/$service/.env.example"
    
    if [[ ! -f "$env_file" ]]; then
        print_status "FAIL" "$env_file not found"
        return 1
    fi
    
    print_status "PASS" "$env_file exists"
    
    # Check for simplified naming patterns
    local has_simplified_tokens=false
    local has_simplified_urls=false
    
    # Check for simplified token naming
    if grep -q "AUTH_TOKEN=" "$env_file" && \
       grep -q "USERS_TOKEN=" "$env_file" && \
       grep -q "AUCTIONS_TOKEN=" "$env_file"; then
        has_simplified_tokens=true
        print_status "PASS" "$service: Uses simplified token naming"
    else
        print_status "FAIL" "$service: Still uses verbose token naming"
    fi
    
    # Check for simplified URL naming
    if grep -q "AUTH_URL=" "$env_file" && \
       grep -q "USERS_URL=" "$env_file" && \
       grep -q "AUCTIONS_URL=" "$env_file"; then
        has_simplified_urls=true
        print_status "PASS" "$service: Uses simplified URL naming"
    else
        print_status "FAIL" "$service: Still uses verbose URL naming"
    fi
    
    # Check APP_NAME is simplified
    local app_name=$(grep "APP_NAME=" "$env_file" | cut -d'=' -f2 | tr -d '"')
    if [[ "$app_name" == *"Service"* ]]; then
        print_status "WARN" "$service: APP_NAME still contains 'Service' suffix: $app_name"
    else
        print_status "PASS" "$service: APP_NAME is simplified: $app_name"
    fi
    
    return 0
}

# Function to check for old naming patterns
check_for_old_patterns() {
    echo -e "\n${BLUE}🔍 Checking for old naming patterns...${NC}"
    
    # Check for old service directory references
    local old_patterns_found=false
    
    # Check documentation files
    find . -name "*.md" -type f | while read -r doc_file; do
        if grep -q "analytics-service\|auction-service\|auth-service" "$doc_file" 2>/dev/null; then
            print_status "WARN" "$doc_file: Contains old service naming patterns"
            old_patterns_found=true
        fi
    done
    
    # Check for old RPC patterns in .env files
    find services -name ".env.example" -type f | while read -r env_file; do
        if grep -q "RPC_.*_SERVICE_TOKEN\|.*_SERVICE_RPC_URL" "$env_file" 2>/dev/null; then
            print_status "FAIL" "$env_file: Contains old RPC naming patterns"
            old_patterns_found=true
        fi
    done
    
    if [[ "$old_patterns_found" == false ]]; then
        print_status "PASS" "No old naming patterns found"
    fi
}

# Function to validate service connectivity (mock)
validate_service_connectivity() {
    local service="$1"
    local port="$2"
    
    echo -e "\n${BLUE}🌐 Validating $service connectivity (port $port)...${NC}"
    
    # Check if service has proper configuration structure
    local env_file="services/$service/.env.example"
    
    if [[ -f "$env_file" ]]; then
        # Check if service has all required configuration
        local required_configs=("APP_NAME" "APP_URL" "DB_CONNECTION")
        
        for config in "${required_configs[@]}"; do
            if grep -q "^$config=" "$env_file"; then
                print_status "PASS" "$service: Has $config configuration"
            else
                print_status "FAIL" "$service: Missing $config configuration"
            fi
        done
        
        # Check if service has simplified inter-service configs
        if grep -q "AUTH_TOKEN=\|USERS_TOKEN=\|AUCTIONS_TOKEN=" "$env_file"; then
            print_status "PASS" "$service: Has simplified inter-service configuration"
        else
            print_status "WARN" "$service: May be missing inter-service configuration"
        fi
    fi
}

# Function to check README files
check_readme_files() {
    echo -e "\n${BLUE}📚 Checking README files...${NC}"
    
    local services=("analytics" "auctions" "auth" "bidding" "gateway" "notifications" "orders" "payments" "users" "vin-ocr")
    
    for service in "${services[@]}"; do
        local readme_file="services/$service/README.md"
        
        if [[ -f "$readme_file" ]]; then
            # Check if title is simplified
            local title=$(head -n 1 "$readme_file")
            if [[ "$title" == *"Service"* ]]; then
                print_status "WARN" "$service: README title still contains 'Service': $title"
            else
                print_status "PASS" "$service: README title is simplified: $title"
            fi
        else
            print_status "WARN" "$service: README.md not found"
        fi
    done
}

# Function to validate Docker configurations
check_docker_configs() {
    echo -e "\n${BLUE}🐳 Checking Docker configurations...${NC}"
    
    local docker_files=("docker-compose.yml" "docker-compose.database.yml" "docker-compose.octane.yml")
    
    for docker_file in "${docker_files[@]}"; do
        if [[ -f "$docker_file" ]]; then
            print_status "PASS" "$docker_file exists"
            
            # Check if it uses simplified service names
            if grep -q "analytics-service\|auction-service\|auth-service" "$docker_file" 2>/dev/null; then
                print_status "WARN" "$docker_file: Still contains old service naming"
            else
                print_status "PASS" "$docker_file: Uses simplified service naming"
            fi
        else
            print_status "WARN" "$docker_file not found"
        fi
    done
}

# Main validation function
main() {
    echo -e "${BLUE}🚀 Configuration Validation for Simplified Naming${NC}"
    echo -e "${BLUE}=================================================${NC}\n"
    
    # Define services with their expected ports
    declare -A SERVICES=(
        ["gateway"]="8000"
        ["auth"]="8001"
        ["users"]="8002"
        ["auctions"]="8003"
        ["bidding"]="8004"
        ["analytics"]="8005"
        ["vin-ocr"]="8006"
        ["notifications"]="8007"
        ["orders"]="8008"
        ["payments"]="8009"
    )
    
    echo -e "${BLUE}📁 Checking service directories...${NC}"
    for service in "${!SERVICES[@]}"; do
        check_service_directory "$service"
    done
    
    echo -e "\n${BLUE}⚙️  Checking environment configurations...${NC}"
    for service in "${!SERVICES[@]}"; do
        check_env_file "$service"
    done
    
    echo -e "\n${BLUE}🔗 Validating service connectivity...${NC}"
    for service in "${!SERVICES[@]}"; do
        validate_service_connectivity "$service" "${SERVICES[$service]}"
    done
    
    check_for_old_patterns
    check_readme_files
    check_docker_configs
    
    # Summary
    echo -e "\n${BLUE}📊 Validation Summary${NC}"
    echo -e "${BLUE}===================${NC}"
    echo -e "Total Checks: $TOTAL_CHECKS"
    echo -e "${GREEN}Passed: $PASSED_CHECKS${NC}"
    echo -e "${RED}Failed: $FAILED_CHECKS${NC}"
    echo -e "Warnings: $((TOTAL_CHECKS - PASSED_CHECKS - FAILED_CHECKS))"
    
    if [[ $FAILED_CHECKS -eq 0 ]]; then
        echo -e "\n${GREEN}🎉 All critical validations passed!${NC}"
        echo -e "${GREEN}✅ Configuration simplification is successful${NC}"
        return 0
    else
        echo -e "\n${RED}❌ Some validations failed${NC}"
        echo -e "${YELLOW}⚠️  Please review and fix the issues above${NC}"
        return 1
    fi
}

# Run the validation
main "$@"

