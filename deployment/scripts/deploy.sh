#!/bin/bash

# 🚀 Reverse Tender Platform Deployment Script
# Multi-cloud deployment automation for DigitalOcean and Linode

set -euo pipefail

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
DEPLOYMENT_DIR="$PROJECT_ROOT/deployment"

# Default values
ENVIRONMENT="${ENVIRONMENT:-production}"
APP_VERSION="${APP_VERSION:-latest}"
PROVIDER="${PROVIDER:-both}"  # digitalocean, linode, or both
DRY_RUN="${DRY_RUN:-false}"
SKIP_TESTS="${SKIP_TESTS:-false}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Help function
show_help() {
    cat << EOF
🚀 Reverse Tender Platform Deployment Script

Usage: $0 [OPTIONS]

OPTIONS:
    -e, --environment ENV    Deployment environment (production, staging, development)
    -v, --version VERSION    Application version to deploy (default: latest)
    -p, --provider PROVIDER  Cloud provider (digitalocean, linode, both)
    -d, --dry-run           Perform a dry run without actual deployment
    -s, --skip-tests        Skip pre-deployment tests
    -h, --help              Show this help message

EXAMPLES:
    # Deploy latest version to production on both providers
    $0 --environment production --version latest

    # Deploy specific version to staging on DigitalOcean only
    $0 -e staging -v v1.2.3 -p digitalocean

    # Dry run deployment
    $0 --dry-run --environment production

ENVIRONMENT VARIABLES:
    ENVIRONMENT             Deployment environment
    APP_VERSION            Application version
    PROVIDER               Cloud provider
    DRY_RUN                Dry run mode (true/false)
    SKIP_TESTS             Skip tests (true/false)
    DO_TOKEN               DigitalOcean API token
    LINODE_TOKEN           Linode API token
    DOCKER_REGISTRY_USER   Docker registry username
    DOCKER_REGISTRY_PASS   Docker registry password

EOF
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -e|--environment)
            ENVIRONMENT="$2"
            shift 2
            ;;
        -v|--version)
            APP_VERSION="$2"
            shift 2
            ;;
        -p|--provider)
            PROVIDER="$2"
            shift 2
            ;;
        -d|--dry-run)
            DRY_RUN="true"
            shift
            ;;
        -s|--skip-tests)
            SKIP_TESTS="true"
            shift
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            log_error "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Validation
validate_environment() {
    log_info "Validating deployment environment..."
    
    # Check required environment variables
    if [[ "$PROVIDER" == "digitalocean" || "$PROVIDER" == "both" ]]; then
        if [[ -z "${DO_TOKEN:-}" ]]; then
            log_error "DO_TOKEN environment variable is required for DigitalOcean deployment"
            exit 1
        fi
    fi
    
    if [[ "$PROVIDER" == "linode" || "$PROVIDER" == "both" ]]; then
        if [[ -z "${LINODE_TOKEN:-}" ]]; then
            log_error "LINODE_TOKEN environment variable is required for Linode deployment"
            exit 1
        fi
    fi
    
    # Check required tools
    local required_tools=("docker" "docker-compose" "terraform" "ansible" "curl" "jq")
    for tool in "${required_tools[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            log_error "Required tool '$tool' is not installed"
            exit 1
        fi
    done
    
    log_success "Environment validation passed"
}

# Pre-deployment tests
run_tests() {
    if [[ "$SKIP_TESTS" == "true" ]]; then
        log_warning "Skipping pre-deployment tests"
        return 0
    fi
    
    log_info "Running pre-deployment tests..."
    
    # Build and test Docker images
    cd "$PROJECT_ROOT"
    
    # Run unit tests
    log_info "Running unit tests..."
    if ! docker-compose -f docker-compose.test.yml run --rm test-runner composer test; then
        log_error "Unit tests failed"
        exit 1
    fi
    
    # Run integration tests
    log_info "Running integration tests..."
    if ! docker-compose -f docker-compose.test.yml run --rm test-runner php artisan test --testsuite=Integration; then
        log_error "Integration tests failed"
        exit 1
    fi
    
    # Security scan
    log_info "Running security scan..."
    if ! docker run --rm -v "$PROJECT_ROOT:/app" aquasec/trivy fs /app; then
        log_warning "Security scan found issues (continuing deployment)"
    fi
    
    log_success "Pre-deployment tests passed"
}

# Build and push Docker images
build_and_push_images() {
    log_info "Building and pushing Docker images..."
    
    cd "$PROJECT_ROOT"
    
    # Login to Docker registry
    if [[ -n "${DOCKER_REGISTRY_USER:-}" && -n "${DOCKER_REGISTRY_PASS:-}" ]]; then
        echo "$DOCKER_REGISTRY_PASS" | docker login -u "$DOCKER_REGISTRY_USER" --password-stdin
    fi
    
    # Build images
    local services=("api-gateway" "auth-service" "bidding-service" "user-service" "order-service" "notification-service" "payment-service" "analytics-service" "vin-ocr-service")
    
    for service in "${services[@]}"; do
        log_info "Building $service image..."
        
        if [[ "$DRY_RUN" == "false" ]]; then
            docker build -t "reversetender/$service:$APP_VERSION" \
                -f "services/$service/Dockerfile" \
                "services/$service/"
            
            docker push "reversetender/$service:$APP_VERSION"
            
            # Tag as latest if this is a production deployment
            if [[ "$ENVIRONMENT" == "production" ]]; then
                docker tag "reversetender/$service:$APP_VERSION" "reversetender/$service:latest"
                docker push "reversetender/$service:latest"
            fi
        else
            log_info "DRY RUN: Would build and push $service:$APP_VERSION"
        fi
    done
    
    log_success "Docker images built and pushed"
}

# Deploy infrastructure with Terraform
deploy_infrastructure() {
    local provider=$1
    log_info "Deploying infrastructure to $provider..."
    
    cd "$DEPLOYMENT_DIR/terraform/$provider"
    
    # Initialize Terraform
    terraform init
    
    # Plan deployment
    terraform plan \
        -var="environment=$ENVIRONMENT" \
        -var="app_version=$APP_VERSION" \
        -out="tfplan"
    
    if [[ "$DRY_RUN" == "false" ]]; then
        # Apply infrastructure changes
        terraform apply "tfplan"
        
        # Generate Ansible inventory from Terraform output
        terraform output -json > "../../../ansible/inventory/$provider-$ENVIRONMENT.json"
        
        log_success "Infrastructure deployed to $provider"
    else
        log_info "DRY RUN: Would apply Terraform plan for $provider"
    fi
}

# Deploy application with Ansible
deploy_application() {
    local provider=$1
    log_info "Deploying application to $provider..."
    
    cd "$DEPLOYMENT_DIR/ansible"
    
    # Generate inventory from Terraform output
    python3 scripts/generate_inventory.py \
        --provider "$provider" \
        --environment "$ENVIRONMENT" \
        --terraform-output "../terraform/$provider/terraform.tfstate"
    
    if [[ "$DRY_RUN" == "false" ]]; then
        # Run Ansible playbook
        ansible-playbook \
            -i "inventory/$provider-$ENVIRONMENT" \
            playbooks/deploy.yml \
            -e "app_version=$APP_VERSION" \
            -e "environment=$ENVIRONMENT" \
            -e "provider=$provider"
        
        log_success "Application deployed to $provider"
    else
        log_info "DRY RUN: Would run Ansible playbook for $provider"
    fi
}

# Health checks
run_health_checks() {
    local provider=$1
    log_info "Running health checks for $provider..."
    
    # Get load balancer IP from Terraform output
    cd "$DEPLOYMENT_DIR/terraform/$provider"
    local lb_ip=$(terraform output -raw load_balancer_ip)
    
    # Test API Gateway health
    local health_url="https://$lb_ip/health"
    local max_attempts=30
    local attempt=1
    
    while [[ $attempt -le $max_attempts ]]; do
        if curl -f -s "$health_url" > /dev/null; then
            log_success "Health check passed for $provider (attempt $attempt)"
            return 0
        fi
        
        log_info "Health check attempt $attempt/$max_attempts failed, retrying in 10 seconds..."
        sleep 10
        ((attempt++))
    done
    
    log_error "Health checks failed for $provider after $max_attempts attempts"
    return 1
}

# Rollback function
rollback_deployment() {
    local provider=$1
    local previous_version=$2
    
    log_warning "Rolling back deployment on $provider to version $previous_version..."
    
    cd "$DEPLOYMENT_DIR/ansible"
    
    ansible-playbook \
        -i "inventory/$provider-$ENVIRONMENT" \
        playbooks/rollback.yml \
        -e "app_version=$previous_version" \
        -e "environment=$ENVIRONMENT" \
        -e "provider=$provider"
    
    log_success "Rollback completed for $provider"
}

# Main deployment function
deploy_to_provider() {
    local provider=$1
    log_info "Starting deployment to $provider..."
    
    # Deploy infrastructure
    deploy_infrastructure "$provider"
    
    # Wait for infrastructure to be ready
    sleep 60
    
    # Deploy application
    deploy_application "$provider"
    
    # Run health checks
    if ! run_health_checks "$provider"; then
        log_error "Deployment failed health checks for $provider"
        
        # Ask for rollback confirmation
        read -p "Do you want to rollback? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            # Get previous version (this would need to be implemented)
            local previous_version="previous"  # This should be retrieved from deployment history
            rollback_deployment "$provider" "$previous_version"
        fi
        
        return 1
    fi
    
    log_success "Deployment to $provider completed successfully"
}

# Main execution
main() {
    log_info "🚀 Starting Reverse Tender Platform deployment"
    log_info "Environment: $ENVIRONMENT"
    log_info "Version: $APP_VERSION"
    log_info "Provider: $PROVIDER"
    log_info "Dry Run: $DRY_RUN"
    
    # Validate environment
    validate_environment
    
    # Run tests
    run_tests
    
    # Build and push images
    build_and_push_images
    
    # Deploy to specified providers
    case "$PROVIDER" in
        "digitalocean")
            deploy_to_provider "digitalocean"
            ;;
        "linode")
            deploy_to_provider "linode"
            ;;
        "both")
            # Deploy to DigitalOcean first
            if deploy_to_provider "digitalocean"; then
                log_success "DigitalOcean deployment successful"
                
                # Deploy to Linode
                if deploy_to_provider "linode"; then
                    log_success "Linode deployment successful"
                    log_success "🎉 Multi-cloud deployment completed successfully!"
                else
                    log_error "Linode deployment failed, but DigitalOcean is running"
                    exit 1
                fi
            else
                log_error "DigitalOcean deployment failed"
                exit 1
            fi
            ;;
        *)
            log_error "Invalid provider: $PROVIDER. Must be 'digitalocean', 'linode', or 'both'"
            exit 1
            ;;
    esac
    
    log_success "🎉 Deployment completed successfully!"
    log_info "Application is available at: https://reversetender.com"
}

# Trap errors and cleanup
trap 'log_error "Deployment failed at line $LINENO"' ERR

# Run main function
main "$@"

