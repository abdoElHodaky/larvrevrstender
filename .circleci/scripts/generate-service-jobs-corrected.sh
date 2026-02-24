#!/bin/bash
# Generate dynamic service jobs for CircleCI with CORRECTED service paths
# This script generates test and build jobs for all microservices

set -e

OUTPUT_FILE="/tmp/dynamic-jobs-corrected.yml"

# Service definitions with dependencies
declare -A SERVICE_DEPENDENCIES
SERVICE_DEPENDENCIES[shared]=""
SERVICE_DEPENDENCIES[auth]="shared"
SERVICE_DEPENDENCIES[user]="shared auth"
SERVICE_DEPENDENCIES[auction]="shared auth user"
SERVICE_DEPENDENCIES[bidding]="shared auth auction"
SERVICE_DEPENDENCIES[payment]="shared auth user"
SERVICE_DEPENDENCIES[gateway]="shared auth"
SERVICE_DEPENDENCIES[order]="shared auth user auction bidding payment"
SERVICE_DEPENDENCIES[analytics]="shared auth user auction bidding"
SERVICE_DEPENDENCIES[notification]="shared auth user"
SERVICE_DEPENDENCIES[vin-ocr]="shared"

# Function to get correct service directory path
get_service_dir() {
    local service=$1
    if [ "$service" = "shared" ]; then
        echo "services/shared"
    else
        echo "services/$service-service"
    fi
}

# Generate test job for a service
generate_test_job() {
    local service=$1
    local dependencies="$2"
    local service_dir=$(get_service_dir "$service")
    
    cat << EOF
  # Test job for $service service
  test-$service-service:
    executor: medium-executor
    steps:
      - checkout
      - attach_workspace:
          at: /tmp/workspace
      - setup-php
      - install-composer-deps:
          service: $service_dir
      - run:
          name: Check for Node dependencies
          command: |
            if [ -f $service_dir/package.json ]; then
              echo "Installing Node dependencies for $service"
            fi
      - install-node-deps:
          service: $service_dir
      - run:
          name: Setup $service test environment
          command: |
            cd $service_dir
            if [ -f .env.testing ]; then
              cp .env.testing .env
            elif [ -f .env.example ]; then
              cp .env.example .env
            fi
            
            # Generate application key
            php artisan key:generate --force
            
            # Cache configuration
            php artisan config:cache
            
            # Clear any existing cache
            php artisan cache:clear || true
            php artisan view:clear || true

      - run:
          name: Run $service database migrations
          command: |
            cd $service_dir
            php artisan migrate --force --seed

      - run:
          name: Run $service PHPUnit tests
          command: |
            cd $service_dir
            vendor/bin/phpunit \\
              --coverage-clover=coverage.xml \\
              --coverage-html=coverage-html \\
              --log-junit=test-results.xml \\
              --testdox-html=testdox.html

      - run:
          name: Run $service Feature tests
          command: |
            cd $service_dir
            if [ -d tests/Feature ]; then
              vendor/bin/phpunit tests/Feature \\
                --log-junit=feature-test-results.xml
            fi

      - store_test_results:
          path: $service_dir/test-results.xml
      - store_test_results:
          path: $service_dir/feature-test-results.xml
      - store_artifacts:
          path: $service_dir/coverage-html
          destination: coverage-$service
      - store_artifacts:
          path: $service_dir/testdox.html
          destination: testdox-$service.html

      - codecov/upload:
          file: $service_dir/coverage.xml
          flags: $service

      - notify-slack:
          status: "passed"
          job_name: "Test $service Service"

EOF
}

# Generate build job for a service
generate_build_job() {
    local service=$1
    local dependencies="$2"
    local service_dir=$(get_service_dir "$service")
    
    cat << EOF
  # Build job for $service service
  build-$service-service:
    executor: large-executor
    steps:
      - checkout
      - attach_workspace:
          at: /tmp/workspace
      - setup_remote_docker:
          docker_layer_caching: true

      - run:
          name: Check if $service should be built
          command: |
            AFFECTED_SERVICES=\$(cat /tmp/workspace/affected_services)
            if echo "\$AFFECTED_SERVICES" | grep -q "$service"; then
              echo "Building $service service..."
              echo "true" > /tmp/should_build_$service
            else
              echo "Skipping $service service build (not affected)"
              echo "false" > /tmp/should_build_$service
            fi

      - run:
          name: Build $service Docker image
          command: |
            if [ "\$(cat /tmp/should_build_$service)" = "true" ]; then
              cd $service_dir
              
              # Pull cache images
              docker pull ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:cache || true
              docker pull ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:latest || true
              
              # Build multi-stage image with caching
              docker build \\
                --cache-from=ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:cache \\
                --cache-from=ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:latest \\
                --target=production \\
                --tag=ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:\${CIRCLE_SHA1} \\
                --tag=ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:latest \\
                --tag=ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:cache \\
                --build-arg BUILDKIT_INLINE_CACHE=1 \\
                .
              
              # Analyze image size
              echo "=== $service Image Analysis ==="
              docker images ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service --format "table {{.Repository}}\\t{{.Tag}}\\t{{.Size}}"
              
              # Security scan with Trivy
              if command -v trivy >/dev/null 2>&1; then
                trivy image --exit-code 0 --severity HIGH,CRITICAL ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:\${CIRCLE_SHA1}
              fi
            fi

      - run:
          name: Push $service Docker image
          command: |
            if [ "\$(cat /tmp/should_build_$service)" = "true" ]; then
              # Login to GitHub Container Registry
              echo \$GITHUB_TOKEN | docker login ghcr.io -u \$GITHUB_USERNAME --password-stdin
              
              # Push all tags
              docker push ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:\${CIRCLE_SHA1}
              docker push ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:latest
              docker push ghcr.io/\${CIRCLE_PROJECT_USERNAME}/larvrevrstender/$service:cache
              
              echo "Successfully pushed $service images"
            fi

      - notify-slack:
          status: "passed"
          job_name: "Build $service Service"

EOF
}

# Main generation function
main() {
    echo "Generating dynamic service jobs for CircleCI with CORRECTED paths..."
    echo ""
    
    # Initialize output file
    cat > "$OUTPUT_FILE" << EOF
# ============================================================================
# DYNAMIC SERVICE TESTING JOBS - Phase 2 (CORRECTED PATHS)
# ============================================================================

EOF
    
    # Generate test jobs for all services
    for service in shared auth user auction bidding payment gateway order analytics notification vin-ocr; do
        dependencies="${SERVICE_DEPENDENCIES[$service]}"
        generate_test_job "$service" "$dependencies" >> "$OUTPUT_FILE"
    done
    
    # Add separator
    cat >> "$OUTPUT_FILE" << EOF

# ============================================================================
# DYNAMIC SERVICE BUILD JOBS - Phase 3 (CORRECTED PATHS)
# ============================================================================

EOF
    
    # Generate build jobs for all services
    for service in shared auth user auction bidding payment gateway order analytics notification vin-ocr; do
        dependencies="${SERVICE_DEPENDENCIES[$service]}"
        generate_build_job "$service" "$dependencies" >> "$OUTPUT_FILE"
    done
    
    echo "Dynamic jobs generated successfully!"
    echo "Output saved to $OUTPUT_FILE"
    echo ""
    echo "=== GENERATION SUMMARY ==="
    echo "Services processed: 11"
    echo "Test jobs generated: 11"
    echo "Build jobs generated: 11"
    echo "Total jobs: 22"
    echo ""
    echo "=== SERVICE DEPENDENCIES ==="
    for service in shared auth user auction bidding payment gateway order analytics notification vin-ocr; do
        deps="${SERVICE_DEPENDENCIES[$service]}"
        if [ -n "$deps" ]; then
            echo "$service depends on: $deps"
        else
            echo "$service: no dependencies"
        fi
    done
    echo ""
    echo "=== PATH CORRECTIONS APPLIED ==="
    echo "✅ All services use correct directory paths:"
    for service in auth user auction bidding payment gateway order analytics notification vin-ocr; do
        echo "  - $service: services/$service-service/"
    done
    echo "  - shared: services/shared/"
}

# Execute main function
main "$@"
