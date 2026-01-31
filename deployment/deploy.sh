#!/bin/bash

# Unified Deployment Script for Reverse Tender Platform
# Supports multiple environments and cloud providers
# Usage: ./deploy.sh [OPTIONS]

set -e

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/scripts/lib/common.sh"

# Default configuration
ENVIRONMENT=${ENVIRONMENT:-production}
CLOUD_PROVIDER=${CLOUD_PROVIDER:-digitalocean}
DEPLOYMENT_TYPE=${DEPLOYMENT_TYPE:-full}
DRY_RUN=${DRY_RUN:-false}
VERBOSE=${VERBOSE:-false}
SKIP_VALIDATION=${SKIP_VALIDATION:-false}
FORCE=${FORCE:-false}

# Help function
show_help() {
    cat << EOF
Unified Deployment Script for Reverse Tender Platform

USAGE:
    ./deploy.sh [OPTIONS]

OPTIONS:
    -e, --environment ENV       Environment to deploy (development, staging, production)
    -p, --provider PROVIDER     Cloud provider (digitalocean, linode)
    -t, --type TYPE            Deployment type (full, infrastructure, application, docker)
    -d, --dry-run              Show what would be deployed without executing
    -v, --verbose              Enable verbose output
    -s, --skip-validation      Skip configuration validation
    -f, --force                Force deployment even if validation fails
    -h, --help                 Show this help message

EXAMPLES:
    # Full production deployment to DigitalOcean
    ./deploy.sh -e production -p digitalocean

    # Staging deployment to Linode (dry run)
    ./deploy.sh -e staging -p linode --dry-run

    # Infrastructure only deployment
    ./deploy.sh -e production -p digitalocean -t infrastructure

    # Local Docker development
    ./deploy.sh -e development -t docker

ENVIRONMENT VARIABLES:
    DIGITALOCEAN_TOKEN         DigitalOcean API token
    LINODE_TOKEN              Linode API token
    ENVIRONMENT               Target environment
    CLOUD_PROVIDER            Target cloud provider
    DEPLOYMENT_TYPE           Type of deployment
    DRY_RUN                   Enable dry run mode
    VERBOSE                   Enable verbose output

CONFIGURATION:
    Configuration files are loaded in this order:
    1. config/base.env
    2. config/environments/\${ENVIRONMENT}.env
    3. config/providers/\${CLOUD_PROVIDER}.env
    4. Environment variables (highest priority)

EOF
}

# Parse command line arguments
parse_arguments() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -e|--environment)
                ENVIRONMENT="$2"
                shift 2
                ;;
            -p|--provider)
                CLOUD_PROVIDER="$2"
                shift 2
                ;;
            -t|--type)
                DEPLOYMENT_TYPE="$2"
                shift 2
                ;;
            -d|--dry-run)
                DRY_RUN=true
                shift
                ;;
            -v|--verbose)
                VERBOSE=true
                shift
                ;;
            -s|--skip-validation)
                SKIP_VALIDATION=true
                shift
                ;;
            -f|--force)
                FORCE=true
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
}

# Load configuration files
load_configuration() {
    log_info "Loading configuration..."
    
    # Load base configuration
    if [[ -f "${SCRIPT_DIR}/config/base.env" ]]; then
        log_debug "Loading base configuration"
        set -a
        source "${SCRIPT_DIR}/config/base.env"
        set +a
    fi
    
    # Load environment-specific configuration
    local env_config="${SCRIPT_DIR}/config/environments/${ENVIRONMENT}.env"
    if [[ -f "$env_config" ]]; then
        log_debug "Loading environment configuration: $ENVIRONMENT"
        set -a
        source "$env_config"
        set +a
    else
        log_warning "Environment configuration not found: $env_config"
    fi
    
    # Load provider-specific configuration
    local provider_config="${SCRIPT_DIR}/config/providers/${CLOUD_PROVIDER}.env"
    if [[ -f "$provider_config" ]]; then
        log_debug "Loading provider configuration: $CLOUD_PROVIDER"
        set -a
        source "$provider_config"
        set +a
    else
        log_warning "Provider configuration not found: $provider_config"
    fi
    
    # Export key variables
    export ENVIRONMENT CLOUD_PROVIDER DEPLOYMENT_TYPE DRY_RUN VERBOSE
}

# Validate configuration
validate_configuration() {
    if [[ "$SKIP_VALIDATION" == "true" ]]; then
        log_warning "Skipping configuration validation"
        return 0
    fi
    
    log_info "Validating configuration..."
    
    # Validate environment
    if [[ ! "$ENVIRONMENT" =~ ^(development|staging|production)$ ]]; then
        log_error "Invalid environment: $ENVIRONMENT"
        return 1
    fi
    
    # Validate cloud provider
    if [[ ! "$CLOUD_PROVIDER" =~ ^(digitalocean|linode)$ ]]; then
        log_error "Invalid cloud provider: $CLOUD_PROVIDER"
        return 1
    fi
    
    # Validate deployment type
    if [[ ! "$DEPLOYMENT_TYPE" =~ ^(full|infrastructure|application|docker)$ ]]; then
        log_error "Invalid deployment type: $DEPLOYMENT_TYPE"
        return 1
    fi
    
    # Validate required tokens for cloud deployments
    if [[ "$DEPLOYMENT_TYPE" != "docker" ]]; then
        case "$CLOUD_PROVIDER" in
            digitalocean)
                if [[ -z "$DIGITALOCEAN_TOKEN" && -z "$DO_TOKEN" ]]; then
                    log_error "DIGITALOCEAN_TOKEN or DO_TOKEN is required for DigitalOcean deployments"
                    return 1
                fi
                ;;
            linode)
                if [[ -z "$LINODE_TOKEN" ]]; then
                    log_error "LINODE_TOKEN is required for Linode deployments"
                    return 1
                fi
                ;;
        esac
    fi
    
    # Run validation script
    if [[ -f "${SCRIPT_DIR}/scripts/validate.sh" ]]; then
        log_debug "Running configuration validation script"
        "${SCRIPT_DIR}/scripts/validate.sh" || return 1
    fi
    
    log_success "Configuration validation passed"
}

# Show deployment summary
show_deployment_summary() {
    log_info "Deployment Summary:"
    echo "  Environment:      $ENVIRONMENT"
    echo "  Cloud Provider:   $CLOUD_PROVIDER"
    echo "  Deployment Type:  $DEPLOYMENT_TYPE"
    echo "  Dry Run:          $DRY_RUN"
    echo "  Verbose:          $VERBOSE"
    echo "  Script Directory: $SCRIPT_DIR"
    echo ""
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log_warning "DRY RUN MODE - No actual changes will be made"
        echo ""
    fi
}

# Execute deployment
execute_deployment() {
    log_info "Starting deployment..."
    
    case "$DEPLOYMENT_TYPE" in
        full)
            deploy_infrastructure
            deploy_application
            ;;
        infrastructure)
            deploy_infrastructure
            ;;
        application)
            deploy_application
            ;;
        docker)
            deploy_docker
            ;;
        *)
            log_error "Unknown deployment type: $DEPLOYMENT_TYPE"
            return 1
            ;;
    esac
    
    log_success "Deployment completed successfully!"
}

# Deploy infrastructure
deploy_infrastructure() {
    log_info "Deploying infrastructure..."
    
    if [[ -f "${SCRIPT_DIR}/scripts/lib/terraform.sh" ]]; then
        source "${SCRIPT_DIR}/scripts/lib/terraform.sh"
        terraform_deploy
    else
        log_error "Terraform deployment script not found"
        return 1
    fi
}

# Deploy application
deploy_application() {
    log_info "Deploying application..."
    
    if [[ -f "${SCRIPT_DIR}/scripts/lib/kubernetes.sh" ]]; then
        source "${SCRIPT_DIR}/scripts/lib/kubernetes.sh"
        kubernetes_deploy
    else
        log_error "Kubernetes deployment script not found"
        return 1
    fi
}

# Deploy Docker (local development)
deploy_docker() {
    log_info "Deploying Docker containers..."
    
    if [[ -f "${SCRIPT_DIR}/scripts/lib/docker.sh" ]]; then
        source "${SCRIPT_DIR}/scripts/lib/docker.sh"
        docker_deploy
    else
        log_error "Docker deployment script not found"
        return 1
    fi
}

# Cleanup function
cleanup() {
    local exit_code=$?
    if [[ $exit_code -ne 0 ]]; then
        log_error "Deployment failed with exit code: $exit_code"
    fi
    exit $exit_code
}

# Main function
main() {
    # Set up error handling
    trap cleanup EXIT
    
    # Parse arguments
    parse_arguments "$@"
    
    # Set verbose mode
    if [[ "$VERBOSE" == "true" ]]; then
        set -x
    fi
    
    # Load configuration
    load_configuration
    
    # Validate configuration
    if ! validate_configuration; then
        if [[ "$FORCE" == "true" ]]; then
            log_warning "Validation failed but continuing due to --force flag"
        else
            log_error "Configuration validation failed. Use --force to override."
            exit 1
        fi
    fi
    
    # Show deployment summary
    show_deployment_summary
    
    # Confirm deployment (unless dry run or force)
    if [[ "$DRY_RUN" != "true" && "$FORCE" != "true" ]]; then
        read -p "Continue with deployment? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log_info "Deployment cancelled by user"
            exit 0
        fi
    fi
    
    # Execute deployment
    execute_deployment
}

# Run main function
main "$@"

