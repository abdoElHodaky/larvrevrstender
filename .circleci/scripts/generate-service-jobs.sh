#!/bin/bash
# Dynamic Service Job Generation Script for CircleCI
# Generates test and build jobs for all 11 microservices

set -e

# Define all microservices
SERVICES=(
    "auth"
    "user" 
    "auction"
    "bidding"
    "payment"
    "gateway"
    "order"
    "analytics"
    "notification"
    "vin-ocr"
    "shared"
)

# Service dependencies mapping for proper orchestration
declare -A SERVICE_DEPS
SERVICE_DEPS["auth"]="shared"
SERVICE_DEPS["user"]="shared auth"
SERVICE_DEPS["auction"]="shared auth user"
SERVICE_DEPS["bidding"]="shared auth auction"
SERVICE_DEPS["payment"]="shared auth user"
SERVICE_DEPS["gateway"]="shared auth"
SERVICE_DEPS["order"]="shared auth user auction bidding payment"
SERVICE_DEPS["analytics"]="shared auth user auction bidding"
SERVICE_DEPS["notification"]="shared auth user"
SERVICE_DEPS["vin-ocr"]="shared"
SERVICE_DEPS["shared"]=""

# Generate service testing jobs
generate_test_jobs() {
    echo "# ============================================================================"
    echo "# DYNAMIC SERVICE TESTING JOBS - Phase 2"
    echo "# ============================================================================"
    echo ""
    
    for service in "${SERVICES[@]}"; do
        echo "  # Test job for $service service"
        echo "  test-$service-service:"
        echo "    executor: medium-executor"
        echo "    steps:"
        echo "      - checkout"
        echo "      - attach_workspace:"
        echo "          at: /tmp/workspace"
        echo "      - setup-php"
        echo "      - install-composer-deps:"
        echo "          service: services/$service"
        
        # Add Node dependencies if package.json exists
        echo "      - run:"
        echo "          name: Check for Node dependencies"
        echo "          command: |"
        echo "            if [ -f services/$service/package.json ]; then"
        echo "              echo \"Installing Node dependencies for $service\""
        echo "            fi"
        echo "      - install-node-deps:"
        echo "          service: services/$service"
        
        echo "      - run:"
        echo "          name: Setup $service test environment"
        echo "          command: |"
        echo "            cd services/$service"
        echo "            if [ -f .env.testing ]; then"
        echo "              cp .env.testing .env"
        echo "            elif [ -f .env.example ]; then"
        echo "              cp .env.example .env"
        echo "            fi"
        echo "            "
        echo "            # Generate application key"
        echo "            php artisan key:generate --force"
        echo "            "
        echo "            # Cache configuration"
        echo "            php artisan config:cache"
        echo "            "
        echo "            # Clear any existing cache"
        echo "            php artisan cache:clear || true"
        echo "            php artisan view:clear || true"
        echo ""
        
        echo "      - run:"
        echo "          name: Run $service database migrations"
        echo "          command: |"
        echo "            cd services/$service"
        echo "            php artisan migrate --force --seed"
        echo ""
        
        echo "      - run:"
        echo "          name: Run $service PHPUnit tests"
        echo "          command: |"
        echo "            cd services/$service"
        echo "            vendor/bin/phpunit \\"
        echo "              --coverage-clover=coverage.xml \\"
        echo "              --coverage-html=coverage-html \\"
        echo "              --log-junit=test-results.xml \\"
        echo "              --testdox-html=testdox.html"
        echo ""
        
        echo "      - run:"
        echo "          name: Run $service Feature tests"
        echo "          command: |"
        echo "            cd services/$service"
        echo "            if [ -d tests/Feature ]; then"
        echo "              vendor/bin/phpunit tests/Feature \\"
        echo "                --log-junit=feature-test-results.xml"
        echo "            fi"
        echo ""
        
        echo "      - store_test_results:"
        echo "          path: services/$service/test-results.xml"
        echo "      - store_test_results:"
        echo "          path: services/$service/feature-test-results.xml"
        echo "      - store_artifacts:"
        echo "          path: services/$service/coverage-html"
        echo "          destination: coverage-$service"
        echo "      - store_artifacts:"
        echo "          path: services/$service/testdox.html"
        echo "          destination: testdox-$service.html"
        echo ""
        
        echo "      - codecov/upload:"
        echo "          file: services/$service/coverage.xml"
        echo "          flags: $service"
        echo ""
        
        echo "      - notify-slack:"
        echo "          status: \"passed\""
        echo "          job_name: \"Test $service Service\""
        echo ""
    done
}

# Generate service build jobs
generate_build_jobs() {
    echo "# ============================================================================"
    echo "# DYNAMIC SERVICE BUILD JOBS - Phase 3"
    echo "# ============================================================================"
    echo ""
    
    for service in "${SERVICES[@]}"; do
        echo "  # Build job for $service service"
        echo "  build-$service-service:"
        echo "    executor: large-executor"
        echo "    steps:"
        echo "      - checkout"
        echo "      - attach_workspace:"
        echo "          at: /tmp/workspace"
        echo "      - setup_remote_docker:"
        echo "          docker_layer_caching: true"
        echo ""
        
        echo "      - run:"
        echo "          name: Check if $service should be built"
        echo "          command: |"
        echo "            AFFECTED_SERVICES=\$(cat /tmp/workspace/affected_services)"
        echo "            if echo \"\$AFFECTED_SERVICES\" | grep -q \"$service\"; then"
        echo "              echo \"Building $service service...\""
        echo "              echo \"true\" > /tmp/should_build_$service"
        echo "            else"
        echo "              echo \"Skipping $service service build (not affected)\""
        echo "              echo \"false\" > /tmp/should_build_$service"
        echo "            fi"
        echo ""
        
        echo "      - run:"
        echo "          name: Build $service Docker image"
        echo "          command: |"
        echo "            if [ \"\$(cat /tmp/should_build_$service)\" = \"true\" ]; then"
        echo "              cd services/$service"
        echo "              "
        echo "              # Pull cache images"
        echo "              docker pull ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:cache || true"
        echo "              docker pull ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:latest || true"
        echo "              "
        echo "              # Build multi-stage image with caching"
        echo "              docker build \\"
        echo "                --cache-from=ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:cache \\"
        echo "                --cache-from=ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:latest \\"
        echo "                --target=production \\"
        echo "                --tag=ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:\${CIRCLE_SHA1} \\"
        echo "                --tag=ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:latest \\"
        echo "                --tag=ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:cache \\"
        echo "                --build-arg BUILDKIT_INLINE_CACHE=1 \\"
        echo "                ."
        echo "              "
        echo "              # Analyze image size"
        echo "              echo \"=== $service Image Analysis ===\""
        echo "              docker images ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service --format \"table {{.Repository}}\\t{{.Tag}}\\t{{.Size}}\""
        echo "              "
        echo "              # Security scan with Trivy"
        echo "              if command -v trivy >/dev/null 2>&1; then"
        echo "                trivy image --exit-code 0 --severity HIGH,CRITICAL ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:\${CIRCLE_SHA1}"
        echo "              fi"
        echo "            fi"
        echo ""
        
        echo "      - run:"
        echo "          name: Push $service Docker image"
        echo "          command: |"
        echo "            if [ \"\$(cat /tmp/should_build_$service)\" = \"true\" ]; then"
        echo "              # Login to GitHub Container Registry"
        echo "              echo \$GITHUB_TOKEN | docker login ghcr.io -u \$GITHUB_USERNAME --password-stdin"
        echo "              "
        echo "              # Push all tags"
        echo "              docker push ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:\${CIRCLE_SHA1}"
        echo "              docker push ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:latest"
        echo "              docker push ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:cache"
        echo "              "
        echo "              echo \"Successfully pushed $service images\""
        echo "            fi"
        echo ""
        
        echo "      - notify-slack:"
        echo "          status: \"passed\""
        echo "          job_name: \"Build $service Service\""
        echo ""
    done
}

# Generate workflow dependencies
generate_workflow_dependencies() {
    echo "# ============================================================================"
    echo "# DYNAMIC WORKFLOW DEPENDENCIES"
    echo "# ============================================================================"
    echo ""
    echo "      # Phase 2: Service Testing (11 parallel services)"
    
    for service in "${SERVICES[@]}"; do
        echo "      - test-$service-service:"
        echo "          requires:"
        echo "            - security-scanning"
        echo "            - code-quality-php82"
        echo "            - code-quality-php83"
        echo "          filters:"
        echo "            branches:"
        echo "              only: /.*/"
    done
    
    echo ""
    echo "      # Phase 3: Service Builds (11 parallel services)"
    
    for service in "${SERVICES[@]}"; do
        echo "      - build-$service-service:"
        echo "          requires:"
        echo "            - test-$service-service"
        
        # Add dependencies based on service relationships
        if [ -n "${SERVICE_DEPS[$service]}" ]; then
            for dep in ${SERVICE_DEPS[$service]}; do
                echo "            - test-$dep-service"
            done
        fi
        
        echo "          filters:"
        echo "            branches:"
        echo "              only: /.*/"
    done
    
    echo ""
    echo "      # Phase 4: Docker Optimization (requires all builds)"
    echo "      - docker-optimization:"
    echo "          requires:"
    for service in "${SERVICES[@]}"; do
        echo "            - build-$service-service"
    done
    echo "          filters:"
    echo "            branches:"
    echo "              only: [main, v2, staging, develop]"
}

# Main execution
main() {
    echo "Generating dynamic service jobs for CircleCI..."
    echo ""
    
    # Generate all job definitions
    {
        generate_test_jobs
        echo ""
        generate_build_jobs
        echo ""
        generate_workflow_dependencies
    } > /tmp/dynamic-jobs.yml
    
    echo "Dynamic jobs generated successfully!"
    echo "Output saved to /tmp/dynamic-jobs.yml"
    
    # Show summary
    echo ""
    echo "=== GENERATION SUMMARY ==="
    echo "Services processed: ${#SERVICES[@]}"
    echo "Test jobs generated: ${#SERVICES[@]}"
    echo "Build jobs generated: ${#SERVICES[@]}"
    echo "Total jobs: $((${#SERVICES[@]} * 2))"
    echo ""
    
    # Show service dependencies
    echo "=== SERVICE DEPENDENCIES ==="
    for service in "${SERVICES[@]}"; do
        if [ -n "${SERVICE_DEPS[$service]}" ]; then
            echo "$service depends on: ${SERVICE_DEPS[$service]}"
        else
            echo "$service: no dependencies"
        fi
    done
}

# Execute if run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
