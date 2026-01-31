#!/bin/bash

# Docker deployment functions for Reverse Tender Platform
# This file contains Docker-specific deployment logic

# Source common functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# Docker deployment main function
docker_deploy() {
    log_step "Starting Docker deployment"
    
    # Check prerequisites
    docker_check_prerequisites
    
    # Prepare Docker environment
    docker_prepare_environment
    
    # Build and deploy services
    case "$ENVIRONMENT" in
        development)
            docker_deploy_development
            ;;
        staging)
            docker_deploy_staging
            ;;
        production)
            docker_deploy_production
            ;;
        *)
            log_error "Unsupported environment for Docker deployment: $ENVIRONMENT"
            return 1
            ;;
    esac
    
    # Verify deployment
    docker_verify_deployment
    
    log_success "Docker deployment completed successfully"
}

# Check Docker prerequisites
docker_check_prerequisites() {
    log_info "Checking Docker prerequisites..."
    
    # Check required commands
    check_required_commands docker docker-compose
    
    # Check Docker daemon
    if ! docker info &> /dev/null; then
        log_error "Docker daemon is not running"
        return 1
    fi
    
    # Check Docker Compose version
    local compose_version
    compose_version=$(docker-compose --version | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
    log_info "Docker Compose version: $compose_version"
    
    # Check available disk space
    local available_space
    available_space=$(df / | awk 'NR==2 {print $4}')
    if [[ $available_space -lt 5000000 ]]; then  # 5GB in KB
        log_warning "Low disk space available: ${available_space}KB"
    fi
    
    log_success "Docker prerequisites check passed"
}

# Prepare Docker environment
docker_prepare_environment() {
    log_info "Preparing Docker environment..."
    
    # Create necessary directories
    local docker_dir="${SCRIPT_DIR}/../docker"
    create_directory "${docker_dir}/logs"
    create_directory "${docker_dir}/uploads"
    create_directory "${docker_dir}/ssl"
    create_directory "${docker_dir}/nginx"
    create_directory "${docker_dir}/database/init"
    create_directory "${docker_dir}/database/config"
    create_directory "${docker_dir}/monitoring/prometheus"
    create_directory "${docker_dir}/monitoring/grafana/dashboards"
    create_directory "${docker_dir}/monitoring/grafana/datasources"
    
    # Set proper permissions
    if [[ "$DRY_RUN" != "true" ]]; then
        chmod 755 "${docker_dir}/logs"
        chmod 755 "${docker_dir}/uploads"
    fi
    
    # Create environment file for Docker Compose
    docker_create_env_file
    
    log_success "Docker environment prepared"
}

# Create environment file for Docker Compose
docker_create_env_file() {
    local docker_dir="${SCRIPT_DIR}/../docker"
    local env_file="${docker_dir}/.env"
    
    log_info "Creating Docker Compose environment file: $env_file"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        cat > "$env_file" << EOF
# Generated Docker Compose environment file
# Environment: $ENVIRONMENT
# Generated at: $(date)

# Application Configuration
APP_ENV=$APP_ENV
APP_DEBUG=$APP_DEBUG
APP_URL=$APP_URL
APP_VERSION=$APP_VERSION

# Database Configuration
DB_HOST=$DB_HOST
DB_DATABASE=$DB_DATABASE
DB_USERNAME=$DB_USERNAME
DB_PASSWORD=$DB_PASSWORD
MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD

# Redis Configuration
REDIS_HOST=$REDIS_HOST
REDIS_PASSWORD=$REDIS_PASSWORD

# Security
JWT_SECRET=$JWT_SECRET

# Third-party Services
TWILIO_SID=$TWILIO_SID
TWILIO_TOKEN=$TWILIO_TOKEN
SENDGRID_API_KEY=$SENDGRID_API_KEY

# Cloud Storage
S3_BUCKET=$S3_BUCKET
S3_ACCESS_KEY=$S3_ACCESS_KEY
S3_SECRET_KEY=$S3_SECRET_KEY
S3_REGION=$S3_REGION

# Payment Services
ZATCA_API_URL=$ZATCA_API_URL
ZATCA_API_KEY=$ZATCA_API_KEY
PAYMENT_GATEWAY_URL=$PAYMENT_GATEWAY_URL
PAYMENT_GATEWAY_KEY=$PAYMENT_GATEWAY_KEY

# Push Notifications
FCM_SERVER_KEY=$FCM_SERVER_KEY
APNS_KEY_ID=$APNS_KEY_ID
APNS_TEAM_ID=$APNS_TEAM_ID

# Google Cloud
GOOGLE_CLOUD_VISION_KEY=$GOOGLE_CLOUD_VISION_KEY

# Monitoring
GRAFANA_PASSWORD=$GRAFANA_PASSWORD
GRAFANA_ADMIN_USER=$GRAFANA_ADMIN_USER

# Network Configuration
NETWORK_SUBNET=$NETWORK_SUBNET

# Service Ports
API_GATEWAY_PORT=$API_GATEWAY_PORT
AUTH_SERVICE_PORT=$AUTH_SERVICE_PORT
BIDDING_SERVICE_PORT=$BIDDING_SERVICE_PORT
USER_SERVICE_PORT=$USER_SERVICE_PORT
ORDER_SERVICE_PORT=$ORDER_SERVICE_PORT
NOTIFICATION_SERVICE_PORT=$NOTIFICATION_SERVICE_PORT
PAYMENT_SERVICE_PORT=$PAYMENT_SERVICE_PORT
ANALYTICS_SERVICE_PORT=$ANALYTICS_SERVICE_PORT
VIN_OCR_SERVICE_PORT=$VIN_OCR_SERVICE_PORT

# Infrastructure Ports
MYSQL_PORT=$MYSQL_PORT
REDIS_PORT=$REDIS_PORT
HTTP_PORT=$HTTP_PORT
HTTPS_PORT=$HTTPS_PORT
REVERB_PORT=$REVERB_PORT

# Laravel Reverb
REVERB_APP_ID=$REVERB_APP_ID
REVERB_APP_KEY=$REVERB_APP_KEY
REVERB_APP_SECRET=$REVERB_APP_SECRET
REVERB_SCHEME=$REVERB_SCHEME
EOF
    fi
    
    log_success "Docker Compose environment file created"
}

# Deploy development environment
docker_deploy_development() {
    log_info "Deploying development environment with Docker Compose..."
    
    local docker_dir="${SCRIPT_DIR}/../../docker"
    local compose_files=(
        "-f" "${docker_dir}/docker-compose.base.yml"
        "-f" "${docker_dir}/docker-compose.override.yml"
    )
    
    # Stop existing containers
    docker_stop_services "${compose_files[@]}"
    
    # Pull latest images
    docker_pull_images "${compose_files[@]}"
    
    # Start services
    docker_start_services "${compose_files[@]}"
    
    # Wait for services to be ready
    docker_wait_for_services_development
}

# Deploy staging environment
docker_deploy_staging() {
    log_info "Deploying staging environment with Docker Compose..."
    
    local docker_dir="${SCRIPT_DIR}/../docker"
    local compose_files=(
        "-f" "${docker_dir}/docker-compose.base.yml"
        "-f" "${docker_dir}/environments/staging.yml"
    )
    
    # Stop existing containers
    docker_stop_services "${compose_files[@]}"
    
    # Pull latest images
    docker_pull_images "${compose_files[@]}"
    
    # Start services
    docker_start_services "${compose_files[@]}"
    
    # Wait for services to be ready
    docker_wait_for_services_staging
}

# Deploy production environment
docker_deploy_production() {
    log_info "Deploying production environment with Docker Compose..."
    
    local docker_dir="${SCRIPT_DIR}/../docker"
    local compose_files=(
        "-f" "${docker_dir}/docker-compose.base.yml"
        "-f" "${docker_dir}/environments/production.yml"
    )
    
    # Create backup of current deployment
    docker_backup_current_deployment
    
    # Stop existing containers gracefully
    docker_stop_services "${compose_files[@]}"
    
    # Pull latest images
    docker_pull_images "${compose_files[@]}"
    
    # Start services
    docker_start_services "${compose_files[@]}"
    
    # Wait for services to be ready
    docker_wait_for_services_production
    
    # Run health checks
    docker_health_checks_production
}

# Stop Docker services
docker_stop_services() {
    local compose_files=("$@")
    
    log_info "Stopping Docker services..."
    
    if [[ "$DRY_RUN" != "true" ]]; then
        docker-compose "${compose_files[@]}" down --remove-orphans || {
            log_warning "Some containers may not have stopped cleanly"
        }
    fi
    
    log_success "Docker services stopped"
}

# Pull Docker images
docker_pull_images() {
    local compose_files=("$@")
    
    log_info "Pulling Docker images..."
    
    if [[ "$DRY_RUN" != "true" ]]; then
        docker-compose "${compose_files[@]}" pull || {
            log_warning "Some images may not have been pulled"
        }
    fi
    
    log_success "Docker images pulled"
}

# Start Docker services
docker_start_services() {
    local compose_files=("$@")
    
    log_info "Starting Docker services..."
    
    if [[ "$DRY_RUN" != "true" ]]; then
        docker-compose "${compose_files[@]}" up -d || {
            log_error "Failed to start Docker services"
            return 1
        }
    fi
    
    log_success "Docker services started"
}

# Wait for development services
docker_wait_for_services_development() {
    log_info "Waiting for development services to be ready..."
    
    # Wait for database
    wait_for_service "localhost" "3306" 60 5
    
    # Wait for Redis
    wait_for_service "localhost" "6379" 30 5
    
    # Wait for core services
    wait_for_service "localhost" "8000" 60 5  # API Gateway
    wait_for_service "localhost" "8001" 60 5  # Auth Service
    
    log_success "Development services are ready"
}

# Wait for staging services
docker_wait_for_services_staging() {
    log_info "Waiting for staging services to be ready..."
    
    # Wait for infrastructure
    wait_for_service "localhost" "3306" 60 5  # MySQL
    wait_for_service "localhost" "6379" 30 5  # Redis
    
    # Wait for all microservices
    local services=(8000 8001 8002 8003 8004 8005 8006 8007 8008)
    for port in "${services[@]}"; do
        wait_for_service "localhost" "$port" 60 5
    done
    
    log_success "Staging services are ready"
}

# Wait for production services
docker_wait_for_services_production() {
    log_info "Waiting for production services to be ready..."
    
    # Wait for infrastructure with longer timeouts
    wait_for_service "localhost" "3306" 120 10  # MySQL
    wait_for_service "localhost" "6379" 60 5    # Redis
    
    # Wait for all microservices
    local services=(8000 8001 8002 8003 8004 8005 8006 8007 8008)
    for port in "${services[@]}"; do
        wait_for_service "localhost" "$port" 120 10
    done
    
    # Wait for monitoring
    wait_for_service "localhost" "9090" 60 5   # Prometheus
    wait_for_service "localhost" "3000" 60 5   # Grafana
    
    log_success "Production services are ready"
}

# Production health checks
docker_health_checks_production() {
    log_info "Running production health checks..."
    
    # Check service health endpoints
    local services=(
        "http://localhost:8000/health"  # API Gateway
        "http://localhost:8001/health"  # Auth Service
        "http://localhost:8002/health"  # Bidding Service
        "http://localhost:8003/health"  # User Service
        "http://localhost:8004/health"  # Order Service
        "http://localhost:8005/health"  # Notification Service
        "http://localhost:8006/health"  # Payment Service
        "http://localhost:8007/health"  # Analytics Service
        "http://localhost:8008/health"  # VIN OCR Service
    )
    
    for url in "${services[@]}"; do
        check_url "$url" 200 30
    done
    
    log_success "Production health checks passed"
}

# Backup current deployment
docker_backup_current_deployment() {
    log_info "Creating backup of current deployment..."
    
    local backup_dir="/tmp/reverse-tender-backup-$(date +%Y%m%d_%H%M%S)"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        create_directory "$backup_dir"
        
        # Backup volumes
        docker run --rm -v mysql_primary_data:/data -v "$backup_dir":/backup alpine tar czf /backup/mysql_data.tar.gz -C /data .
        docker run --rm -v redis_primary_data:/data -v "$backup_dir":/backup alpine tar czf /backup/redis_data.tar.gz -C /data .
        
        log_info "Backup created at: $backup_dir"
    fi
    
    log_success "Deployment backup completed"
}

# Verify deployment
docker_verify_deployment() {
    log_info "Verifying Docker deployment..."
    
    # Check container status
    local failed_containers
    failed_containers=$(docker ps -a --filter "status=exited" --filter "status=dead" --format "table {{.Names}}\t{{.Status}}" | grep -v "NAMES" | wc -l)
    
    if [[ $failed_containers -gt 0 ]]; then
        log_warning "Found $failed_containers failed containers"
        docker ps -a --filter "status=exited" --filter "status=dead" --format "table {{.Names}}\t{{.Status}}"
    fi
    
    # Check resource usage
    docker_check_resource_usage
    
    # Show deployment summary
    docker_show_deployment_summary
    
    log_success "Docker deployment verification completed"
}

# Check resource usage
docker_check_resource_usage() {
    log_info "Checking Docker resource usage..."
    
    if [[ "$DRY_RUN" != "true" ]]; then
        # Check disk usage
        local disk_usage
        disk_usage=$(docker system df --format "table {{.Type}}\t{{.TotalCount}}\t{{.Size}}")
        log_info "Docker disk usage:"
        echo "$disk_usage"
        
        # Check memory usage
        local memory_usage
        memory_usage=$(docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}")
        log_info "Container resource usage:"
        echo "$memory_usage"
    fi
}

# Show deployment summary
docker_show_deployment_summary() {
    log_info "Docker Deployment Summary:"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        echo "Running containers:"
        docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
        
        echo ""
        echo "Service URLs:"
        echo "  API Gateway:      http://localhost:${API_GATEWAY_PORT:-8000}"
        echo "  Auth Service:     http://localhost:${AUTH_SERVICE_PORT:-8001}"
        echo "  Bidding Service:  http://localhost:${BIDDING_SERVICE_PORT:-8002}"
        echo "  User Service:     http://localhost:${USER_SERVICE_PORT:-8003}"
        echo "  Order Service:    http://localhost:${ORDER_SERVICE_PORT:-8004}"
        echo "  Notification:     http://localhost:${NOTIFICATION_SERVICE_PORT:-8005}"
        echo "  Payment Service:  http://localhost:${PAYMENT_SERVICE_PORT:-8006}"
        echo "  Analytics:        http://localhost:${ANALYTICS_SERVICE_PORT:-8007}"
        echo "  VIN OCR Service:  http://localhost:${VIN_OCR_SERVICE_PORT:-8008}"
        
        if [[ "$ENVIRONMENT" == "development" ]]; then
            echo ""
            echo "Development Tools:"
            echo "  phpMyAdmin:       http://localhost:8080"
            echo "  Redis Commander: http://localhost:8081"
            echo "  MailHog:         http://localhost:8025"
        fi
        
        if [[ "$ENVIRONMENT" != "development" ]]; then
            echo ""
            echo "Monitoring:"
            echo "  Prometheus:      http://localhost:9090"
            echo "  Grafana:         http://localhost:3000"
        fi
    fi
}

# Cleanup Docker resources
docker_cleanup() {
    log_info "Cleaning up Docker resources..."
    
    if [[ "$DRY_RUN" != "true" ]]; then
        # Remove unused containers
        docker container prune -f
        
        # Remove unused images
        docker image prune -f
        
        # Remove unused volumes (be careful with this)
        if [[ "$FORCE" == "true" ]]; then
            docker volume prune -f
        fi
        
        # Remove unused networks
        docker network prune -f
    fi
    
    log_success "Docker cleanup completed"
}

# Export functions
export -f docker_deploy docker_check_prerequisites docker_prepare_environment
export -f docker_deploy_development docker_deploy_staging docker_deploy_production
export -f docker_verify_deployment docker_cleanup
