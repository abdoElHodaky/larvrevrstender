#!/bin/bash
# CircleCI Service Structure Validation Script
# Validates actual service structures against CircleCI job assumptions

set -e

echo "🔍 CircleCI Service Structure Validation"
echo "========================================"
echo ""

# Define services
SERVICES=(auth user auction bidding payment gateway order analytics notification vin-ocr shared)
VALIDATION_RESULTS=""
CRITICAL_ISSUES=0
WARNINGS=0

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to log results
log_result() {
    local service=$1
    local component=$2
    local status=$3
    local message=$4
    
    if [ "$status" = "PASS" ]; then
        echo -e "${GREEN}✅${NC} $service: $component - $message"
    elif [ "$status" = "WARN" ]; then
        echo -e "${YELLOW}⚠️${NC}  $service: $component - $message"
        ((WARNINGS++))
    elif [ "$status" = "FAIL" ]; then
        echo -e "${RED}❌${NC} $service: $component - $message"
        ((CRITICAL_ISSUES++))
    fi
    
    VALIDATION_RESULTS="$VALIDATION_RESULTS\n$service,$component,$status,$message"
}

# Validate service directory structure
validate_service_structure() {
    local service=$1
    
    echo -e "${BLUE}=== Validating $service Service ===${NC}"
    
    # Check if service directory exists (try both patterns)
    if [ -d "services/$service-service" ]; then
        log_result "$service" "Directory" "PASS" "Service directory exists (services/$service-service/)"
        SERVICE_DIR="services/$service-service"
    elif [ -d "services/$service" ]; then
        log_result "$service" "Directory" "PASS" "Service directory exists (services/$service/)"
        SERVICE_DIR="services/$service"
    else
        log_result "$service" "Directory" "FAIL" "Service directory does not exist (checked services/$service/ and services/$service-service/)"
        return
    fi
        
    # Check for Laravel-specific files
    if [ -f "$SERVICE_DIR/composer.json" ]; then
        log_result "$service" "Composer" "PASS" "composer.json found"
        
        # Check composer.json content
        if grep -q "laravel/framework" "$SERVICE_DIR/composer.json" 2>/dev/null; then
            log_result "$service" "Laravel" "PASS" "Laravel framework detected"
        else
            log_result "$service" "Laravel" "WARN" "Laravel framework not detected in composer.json"
        fi
    else
        log_result "$service" "Composer" "FAIL" "composer.json missing"
    fi
    
    # Check for package.json (Node dependencies)
    if [ -f "$SERVICE_DIR/package.json" ]; then
        log_result "$service" "Node" "PASS" "package.json found"
    else
        log_result "$service" "Node" "WARN" "package.json not found (may not be needed)"
    fi
    
    # Check for Dockerfile
    if [ -f "$SERVICE_DIR/Dockerfile" ]; then
        log_result "$service" "Docker" "PASS" "Dockerfile found"
        
        # Validate Dockerfile content
        if grep -q "FROM.*php" "$SERVICE_DIR/Dockerfile" 2>/dev/null; then
            log_result "$service" "DockerPHP" "PASS" "PHP base image detected"
        else
            log_result "$service" "DockerPHP" "WARN" "PHP base image not detected"
        fi
    else
        log_result "$service" "Docker" "FAIL" "Dockerfile missing"
    fi
    
    # Check for tests directory
    if [ -d "$SERVICE_DIR/tests" ]; then
        log_result "$service" "Tests" "PASS" "Tests directory found"
        
        # Check for specific test types
        if [ -d "$SERVICE_DIR/tests/Feature" ]; then
            log_result "$service" "FeatureTests" "PASS" "Feature tests directory found"
        else
            log_result "$service" "FeatureTests" "WARN" "Feature tests directory not found"
        fi
        
        if [ -d "$SERVICE_DIR/tests/Unit" ]; then
            log_result "$service" "UnitTests" "PASS" "Unit tests directory found"
        else
            log_result "$service" "UnitTests" "WARN" "Unit tests directory not found"
        fi
    else
        log_result "$service" "Tests" "FAIL" "Tests directory missing"
    fi
    
    # Check for Laravel-specific directories
    if [ -d "$SERVICE_DIR/app" ]; then
        log_result "$service" "AppDir" "PASS" "Laravel app directory found"
    else
        log_result "$service" "AppDir" "WARN" "Laravel app directory not found"
    fi
    
    # Check for environment files
    if [ -f "$SERVICE_DIR/.env.example" ]; then
        log_result "$service" "EnvExample" "PASS" ".env.example found"
    else
        log_result "$service" "EnvExample" "WARN" ".env.example not found"
    fi
    
    if [ -f "$SERVICE_DIR/.env.testing" ]; then
        log_result "$service" "EnvTesting" "PASS" ".env.testing found"
    else
        log_result "$service" "EnvTesting" "WARN" ".env.testing not found"
    fi
    
    # Check for database migrations
    if [ -d "$SERVICE_DIR/database/migrations" ]; then
        log_result "$service" "Migrations" "PASS" "Database migrations directory found"
    else
        log_result "$service" "Migrations" "WARN" "Database migrations directory not found"
    fi
    
    # Check for PHPUnit configuration
    if [ -f "$SERVICE_DIR/phpunit.xml" ]; then
        log_result "$service" "PHPUnit" "PASS" "phpunit.xml found"
    else
        log_result "$service" "PHPUnit" "WARN" "phpunit.xml not found"
    fi
    
    echo ""
}

# Validate CircleCI configuration assumptions
validate_circleci_assumptions() {
    echo -e "${BLUE}=== Validating CircleCI Configuration Assumptions ===${NC}"
    
    # Check if CircleCI config exists
    if [ -f ".circleci/config.yml" ]; then
        log_result "CircleCI" "Config" "PASS" "CircleCI configuration found"
        
        # Validate YAML syntax
        if python3 -c "import yaml; yaml.safe_load(open('.circleci/config.yml'))" 2>/dev/null; then
            log_result "CircleCI" "YAML" "PASS" "YAML syntax is valid"
        else
            log_result "CircleCI" "YAML" "FAIL" "YAML syntax errors found"
        fi
        
        # Check for required jobs
        for service in "${SERVICES[@]}"; do
            if grep -q "test-$service-service:" ".circleci/config.yml"; then
                log_result "CircleCI" "TestJob-$service" "PASS" "Test job defined"
            else
                log_result "CircleCI" "TestJob-$service" "FAIL" "Test job missing"
            fi
            
            if grep -q "build-$service-service:" ".circleci/config.yml"; then
                log_result "CircleCI" "BuildJob-$service" "PASS" "Build job defined"
            else
                log_result "CircleCI" "BuildJob-$service" "FAIL" "Build job missing"
            fi
        done
        
    else
        log_result "CircleCI" "Config" "FAIL" "CircleCI configuration not found"
    fi
    
    echo ""
}

# Check Docker registry assumptions
validate_docker_assumptions() {
    echo -e "${BLUE}=== Validating Docker Registry Assumptions ===${NC}"
    
    # Check for existing Docker images (if registry is accessible)
    echo "Current assumptions:"
    echo "- Registry: ghcr.io"
    echo "- Image pattern: ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/\${service}"
    echo "- Authentication: GITHUB_TOKEN + GITHUB_USERNAME"
    
    log_result "Docker" "Registry" "WARN" "Registry configuration needs manual verification"
    log_result "Docker" "Authentication" "WARN" "Registry authentication needs manual verification"
    log_result "Docker" "ImageNaming" "WARN" "Image naming pattern needs manual verification"
    
    echo ""
}

# Generate summary report
generate_summary() {
    echo -e "${BLUE}=== VALIDATION SUMMARY ===${NC}"
    echo ""
    
    echo "📊 Results:"
    echo "- Critical Issues: $CRITICAL_ISSUES"
    echo "- Warnings: $WARNINGS"
    echo "- Services Validated: ${#SERVICES[@]}"
    echo ""
    
    if [ $CRITICAL_ISSUES -eq 0 ]; then
        echo -e "${GREEN}✅ No critical issues found!${NC}"
        echo "The CircleCI configuration should work with minor adjustments."
    else
        echo -e "${RED}❌ $CRITICAL_ISSUES critical issues found!${NC}"
        echo "These issues must be resolved before CircleCI deployment."
    fi
    
    if [ $WARNINGS -gt 0 ]; then
        echo -e "${YELLOW}⚠️  $WARNINGS warnings found.${NC}"
        echo "These should be reviewed and may require configuration adjustments."
    fi
    
    echo ""
    echo "📋 Detailed results saved to: validation-results.csv"
    echo -e "$VALIDATION_RESULTS" > validation-results.csv
}

# Generate recommendations
generate_recommendations() {
    echo -e "${BLUE}=== RECOMMENDATIONS ===${NC}"
    echo ""
    
    if [ $CRITICAL_ISSUES -gt 0 ]; then
        echo "🚨 CRITICAL ACTIONS REQUIRED:"
        echo "1. Create missing service directories and files"
        echo "2. Add missing Dockerfiles for services that need them"
        echo "3. Ensure all services have proper Laravel structure"
        echo "4. Add missing test directories and configurations"
        echo ""
    fi
    
    if [ $WARNINGS -gt 0 ]; then
        echo "⚠️  RECOMMENDED ACTIONS:"
        echo "1. Add .env.testing files for consistent test environments"
        echo "2. Verify Docker registry configuration and authentication"
        echo "3. Add missing package.json files if Node.js is needed"
        echo "4. Review and standardize PHPUnit configurations"
        echo ""
    fi
    
    echo "📋 NEXT STEPS:"
    echo "1. Review validation-results.csv for detailed findings"
    echo "2. Address critical issues before proceeding"
    echo "3. Test CircleCI configuration with pilot services"
    echo "4. Gradually expand to remaining services"
}

# Main execution
main() {
    echo "Starting validation of ${#SERVICES[@]} services..."
    echo ""
    
    # Validate each service
    for service in "${SERVICES[@]}"; do
        validate_service_structure "$service"
    done
    
    # Validate CircleCI configuration
    validate_circleci_assumptions
    
    # Validate Docker assumptions
    validate_docker_assumptions
    
    # Generate summary and recommendations
    generate_summary
    generate_recommendations
    
    # Exit with appropriate code
    if [ $CRITICAL_ISSUES -gt 0 ]; then
        exit 1
    else
        exit 0
    fi
}

# Execute main function
main "$@"
