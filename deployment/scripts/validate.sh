#!/bin/bash

# Configuration Validation Script for Reverse Tender Platform
# This script validates the deployment configuration before execution

set -e

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/lib/common.sh"

# Validation results
VALIDATION_ERRORS=0
VALIDATION_WARNINGS=0

# Main validation function
main() {
    log_info "Starting configuration validation..."
    
    # Basic environment validation
    validate_basic_environment
    
    # Configuration file validation
    validate_configuration_files
    
    # Environment-specific validation
    validate_environment_configuration
    
    # Provider-specific validation
    validate_provider_configuration
    
    # Docker configuration validation
    validate_docker_configuration
    
    # Terraform configuration validation
    validate_terraform_configuration
    
    # Kubernetes configuration validation
    validate_kubernetes_configuration
    
    # Security validation
    validate_security_configuration
    
    # Show validation summary
    show_validation_summary
    
    # Exit with appropriate code
    if [[ $VALIDATION_ERRORS -gt 0 ]]; then
        log_error "Validation failed with $VALIDATION_ERRORS errors"
        exit 1
    elif [[ $VALIDATION_WARNINGS -gt 0 ]]; then
        log_warning "Validation completed with $VALIDATION_WARNINGS warnings"
        exit 0
    else
        log_success "All validations passed successfully"
        exit 0
    fi
}

# Validate basic environment
validate_basic_environment() {
    log_step "Validating basic environment..."
    
    # Check required environment variables
    local required_vars=("ENVIRONMENT")
    for var in "${required_vars[@]}"; do
        if [[ -z "${!var}" ]]; then
            log_error "Required environment variable not set: $var"
            ((VALIDATION_ERRORS++))
        fi
    done
    
    # Validate environment value
    if [[ -n "$ENVIRONMENT" ]]; then
        case "$ENVIRONMENT" in
            development|staging|production)
                log_debug "Environment is valid: $ENVIRONMENT"
                ;;
            *)
                log_error "Invalid environment: $ENVIRONMENT (must be development, staging, or production)"
                ((VALIDATION_ERRORS++))
                ;;
        esac
    fi
    
    # Validate cloud provider if set
    if [[ -n "$CLOUD_PROVIDER" ]]; then
        case "$CLOUD_PROVIDER" in
            digitalocean|linode)
                log_debug "Cloud provider is valid: $CLOUD_PROVIDER"
                ;;
            *)
                log_error "Invalid cloud provider: $CLOUD_PROVIDER (must be digitalocean or linode)"
                ((VALIDATION_ERRORS++))
                ;;
        esac
    fi
    
    log_success "Basic environment validation completed"
}

# Validate configuration files
validate_configuration_files() {
    log_step "Validating configuration files..."
    
    local config_dir="${SCRIPT_DIR}/../config"
    
    # Check base configuration
    if [[ -f "$config_dir/base.env" ]]; then
        log_debug "Base configuration found"
        validate_env_file "$config_dir/base.env"
    else
        log_error "Base configuration file not found: $config_dir/base.env"
        ((VALIDATION_ERRORS++))
    fi
    
    # Check environment-specific configuration
    if [[ -n "$ENVIRONMENT" ]]; then
        local env_config="$config_dir/environments/${ENVIRONMENT}.env"
        if [[ -f "$env_config" ]]; then
            log_debug "Environment configuration found: $ENVIRONMENT"
            validate_env_file "$env_config"
        else
            log_warning "Environment configuration not found: $env_config"
            ((VALIDATION_WARNINGS++))
        fi
    fi
    
    # Check provider-specific configuration
    if [[ -n "$CLOUD_PROVIDER" ]]; then
        local provider_config="$config_dir/providers/${CLOUD_PROVIDER}.env"
        if [[ -f "$provider_config" ]]; then
            log_debug "Provider configuration found: $CLOUD_PROVIDER"
            validate_env_file "$provider_config"
        else
            log_warning "Provider configuration not found: $provider_config"
            ((VALIDATION_WARNINGS++))
        fi
    fi
    
    log_success "Configuration files validation completed"
}

# Validate environment file
validate_env_file() {
    local file="$1"
    
    # Check file is readable
    if [[ ! -r "$file" ]]; then
        log_error "Configuration file is not readable: $file"
        ((VALIDATION_ERRORS++))
        return
    fi
    
    # Check for syntax errors (basic check)
    if ! bash -n "$file" 2>/dev/null; then
        log_error "Syntax error in configuration file: $file"
        ((VALIDATION_ERRORS++))
    fi
    
    # Check for required variables in base config
    if [[ "$file" == *"base.env" ]]; then
        local required_base_vars=("APP_NAME" "APP_VERSION" "DB_CONNECTION" "REDIS_PORT")
        for var in "${required_base_vars[@]}"; do
            if ! grep -q "^${var}=" "$file"; then
                log_warning "Required variable not found in base config: $var"
                ((VALIDATION_WARNINGS++))
            fi
        done
    fi
}

# Validate environment-specific configuration
validate_environment_configuration() {
    log_step "Validating environment-specific configuration..."
    
    case "$ENVIRONMENT" in
        production)
            validate_production_config
            ;;
        staging)
            validate_staging_config
            ;;
        development)
            validate_development_config
            ;;
        *)
            log_debug "Skipping environment-specific validation for: $ENVIRONMENT"
            ;;
    esac
    
    log_success "Environment-specific validation completed"
}

# Validate production configuration
validate_production_config() {
    log_debug "Validating production configuration..."
    
    # Check required production variables
    local required_prod_vars=("JWT_SECRET" "APP_KEY" "DB_PASSWORD" "REDIS_PASSWORD")
    for var in "${required_prod_vars[@]}"; do
        if [[ -z "${!var}" ]]; then
            log_error "Required production variable not set: $var"
            ((VALIDATION_ERRORS++))
        fi
    done
    
    # Check APP_DEBUG is false
    if [[ "$APP_DEBUG" == "true" ]]; then
        log_warning "APP_DEBUG should be false in production"
        ((VALIDATION_WARNINGS++))
    fi
    
    # Check SSL is enabled
    if [[ "$SSL_ENABLED" != "true" ]]; then
        log_warning "SSL should be enabled in production"
        ((VALIDATION_WARNINGS++))
    fi
}

# Validate staging configuration
validate_staging_config() {
    log_debug "Validating staging configuration..."
    
    # Check required staging variables
    local required_staging_vars=("STAGING_JWT_SECRET" "STAGING_DB_PASSWORD")
    for var in "${required_staging_vars[@]}"; do
        if [[ -z "${!var}" ]]; then
            log_warning "Staging variable not set: $var"
            ((VALIDATION_WARNINGS++))
        fi
    done
}

# Validate development configuration
validate_development_config() {
    log_debug "Validating development configuration..."
    
    # Check development tools are enabled
    if [[ "$MAILHOG_ENABLED" != "true" ]]; then
        log_debug "MailHog not enabled in development"
    fi
    
    if [[ "$PHPMYADMIN_ENABLED" != "true" ]]; then
        log_debug "phpMyAdmin not enabled in development"
    fi
}

# Validate provider-specific configuration
validate_provider_configuration() {
    log_step "Validating provider-specific configuration..."
    
    case "$CLOUD_PROVIDER" in
        digitalocean)
            validate_digitalocean_config
            ;;
        linode)
            validate_linode_config
            ;;
        *)
            log_debug "Skipping provider-specific validation for: $CLOUD_PROVIDER"
            ;;
    esac
    
    log_success "Provider-specific validation completed"
}

# Validate DigitalOcean configuration
validate_digitalocean_config() {
    log_debug "Validating DigitalOcean configuration..."
    
    # Check required DO variables
    if [[ -z "$DIGITALOCEAN_TOKEN" && -z "$DO_TOKEN" ]]; then
        log_error "DigitalOcean token not set (DIGITALOCEAN_TOKEN or DO_TOKEN)"
        ((VALIDATION_ERRORS++))
    fi
    
    # Validate region
    if [[ -n "$DO_REGION" ]]; then
        local valid_regions=("nyc1" "nyc3" "ams3" "fra1" "lon1" "sgp1" "tor1" "blr1" "sfo3")
        if [[ ! " ${valid_regions[*]} " =~ " ${DO_REGION} " ]]; then
            log_warning "Potentially invalid DigitalOcean region: $DO_REGION"
            ((VALIDATION_WARNINGS++))
        fi
    fi
}

# Validate Linode configuration
validate_linode_config() {
    log_debug "Validating Linode configuration..."
    
    # Check required Linode variables
    if [[ -z "$LINODE_TOKEN" ]]; then
        log_error "Linode token not set (LINODE_TOKEN)"
        ((VALIDATION_ERRORS++))
    fi
    
    # Validate region
    if [[ -n "$LINODE_REGION" ]]; then
        local valid_regions=("us-east" "us-west" "eu-west" "ap-south" "eu-central")
        if [[ ! " ${valid_regions[*]} " =~ " ${LINODE_REGION} " ]]; then
            log_warning "Potentially invalid Linode region: $LINODE_REGION"
            ((VALIDATION_WARNINGS++))
        fi
    fi
}

# Validate Docker configuration
validate_docker_configuration() {
    log_step "Validating Docker configuration..."
    
    local docker_dir="${SCRIPT_DIR}/../docker"
    
    # Check base Docker Compose file
    if [[ -f "$docker_dir/docker-compose.base.yml" ]]; then
        log_debug "Base Docker Compose file found"
        validate_docker_compose_file "$docker_dir/docker-compose.base.yml"
    else
        log_error "Base Docker Compose file not found"
        ((VALIDATION_ERRORS++))
    fi
    
    # Check environment-specific overrides
    if [[ -n "$ENVIRONMENT" && "$ENVIRONMENT" != "development" ]]; then
        local env_override="$docker_dir/environments/${ENVIRONMENT}.yml"
        if [[ -f "$env_override" ]]; then
            log_debug "Environment override found: $ENVIRONMENT"
            validate_docker_compose_file "$env_override"
        else
            log_warning "Environment override not found: $env_override"
            ((VALIDATION_WARNINGS++))
        fi
    fi
    
    log_success "Docker configuration validation completed"
}

# Validate Docker Compose file
validate_docker_compose_file() {
    local file="$1"
    
    # Check if docker-compose can parse the file
    if command -v docker-compose &> /dev/null; then
        if ! docker-compose -f "$file" config &> /dev/null; then
            log_error "Invalid Docker Compose file: $file"
            ((VALIDATION_ERRORS++))
        fi
    else
        log_debug "docker-compose not available, skipping syntax validation"
    fi
    
    # Check for required services in base file
    if [[ "$file" == *"base.yml" ]]; then
        local required_services=("api-gateway" "auth-service" "mysql-primary" "redis-primary")
        for service in "${required_services[@]}"; do
            if ! grep -q "^  ${service}:" "$file"; then
                log_error "Required service not found in base Docker Compose: $service"
                ((VALIDATION_ERRORS++))
            fi
        done
    fi
}

# Validate Terraform configuration
validate_terraform_configuration() {
    log_step "Validating Terraform configuration..."
    
    local terraform_dir="${SCRIPT_DIR}/../terraform"
    
    # Check main Terraform file
    if [[ -f "$terraform_dir/main.tf" ]]; then
        log_debug "Main Terraform file found"
        validate_terraform_file "$terraform_dir/main.tf"
    else
        log_error "Main Terraform file not found"
        ((VALIDATION_ERRORS++))
    fi
    
    # Check variables file
    if [[ -f "$terraform_dir/variables.tf" ]]; then
        log_debug "Terraform variables file found"
        validate_terraform_file "$terraform_dir/variables.tf"
    else
        log_warning "Terraform variables file not found"
        ((VALIDATION_WARNINGS++))
    fi
    
    # Check provider modules
    if [[ -n "$CLOUD_PROVIDER" ]]; then
        local provider_module="$terraform_dir/modules/${CLOUD_PROVIDER}"
        if [[ -d "$provider_module" ]]; then
            log_debug "Provider module found: $CLOUD_PROVIDER"
            validate_terraform_module "$provider_module"
        else
            log_error "Provider module not found: $provider_module"
            ((VALIDATION_ERRORS++))
        fi
    fi
    
    log_success "Terraform configuration validation completed"
}

# Validate Terraform file
validate_terraform_file() {
    local file="$1"
    
    # Check if terraform can validate the file
    if command -v terraform &> /dev/null; then
        local dir=$(dirname "$file")
        if ! (cd "$dir" && terraform validate &> /dev/null); then
            log_warning "Terraform validation failed for: $file"
            ((VALIDATION_WARNINGS++))
        fi
    else
        log_debug "terraform not available, skipping syntax validation"
    fi
}

# Validate Terraform module
validate_terraform_module() {
    local module_dir="$1"
    
    # Check required module files
    local required_files=("main.tf" "variables.tf" "outputs.tf")
    for file in "${required_files[@]}"; do
        if [[ ! -f "$module_dir/$file" ]]; then
            log_warning "Required module file not found: $module_dir/$file"
            ((VALIDATION_WARNINGS++))
        fi
    done
}

# Validate Kubernetes configuration
validate_kubernetes_configuration() {
    log_step "Validating Kubernetes configuration..."
    
    local k8s_dir="${SCRIPT_DIR}/../k8s"
    
    # Check base Kubernetes configuration
    if [[ -d "$k8s_dir/base" ]]; then
        log_debug "Base Kubernetes configuration found"
        validate_kubernetes_base "$k8s_dir/base"
    else
        log_error "Base Kubernetes configuration not found"
        ((VALIDATION_ERRORS++))
    fi
    
    # Check environment overlay
    if [[ -n "$ENVIRONMENT" ]]; then
        local overlay_dir="$k8s_dir/overlays/${ENVIRONMENT}"
        if [[ -d "$overlay_dir" ]]; then
            log_debug "Kubernetes overlay found: $ENVIRONMENT"
            validate_kubernetes_overlay "$overlay_dir"
        else
            log_warning "Kubernetes overlay not found: $overlay_dir"
            ((VALIDATION_WARNINGS++))
        fi
    fi
    
    log_success "Kubernetes configuration validation completed"
}

# Validate Kubernetes base configuration
validate_kubernetes_base() {
    local base_dir="$1"
    
    # Check required base files
    local required_files=("kustomization.yaml" "namespace.yaml" "deployments.yaml" "services.yaml")
    for file in "${required_files[@]}"; do
        if [[ ! -f "$base_dir/$file" ]]; then
            log_error "Required Kubernetes base file not found: $base_dir/$file"
            ((VALIDATION_ERRORS++))
        fi
    done
    
    # Validate with kubectl if available
    if command -v kubectl &> /dev/null; then
        if ! kubectl kustomize "$base_dir" &> /dev/null; then
            log_warning "Kubernetes base configuration validation failed"
            ((VALIDATION_WARNINGS++))
        fi
    fi
}

# Validate Kubernetes overlay
validate_kubernetes_overlay() {
    local overlay_dir="$1"
    
    # Check kustomization file
    if [[ ! -f "$overlay_dir/kustomization.yaml" ]]; then
        log_error "Kustomization file not found: $overlay_dir/kustomization.yaml"
        ((VALIDATION_ERRORS++))
    fi
    
    # Validate with kubectl if available
    if command -v kubectl &> /dev/null; then
        if ! kubectl kustomize "$overlay_dir" &> /dev/null; then
            log_warning "Kubernetes overlay validation failed: $overlay_dir"
            ((VALIDATION_WARNINGS++))
        fi
    fi
}

# Validate security configuration
validate_security_configuration() {
    log_step "Validating security configuration..."
    
    # Check for weak passwords
    if [[ "$DB_PASSWORD" == "password" || "$DB_PASSWORD" == "123456" ]]; then
        log_error "Weak database password detected"
        ((VALIDATION_ERRORS++))
    fi
    
    # Check JWT secret strength
    if [[ -n "$JWT_SECRET" && ${#JWT_SECRET} -lt 32 ]]; then
        log_warning "JWT secret should be at least 32 characters long"
        ((VALIDATION_WARNINGS++))
    fi
    
    # Check SSL configuration for production
    if [[ "$ENVIRONMENT" == "production" && "$SSL_ENABLED" != "true" ]]; then
        log_error "SSL must be enabled in production"
        ((VALIDATION_ERRORS++))
    fi
    
    # Check for debug mode in production
    if [[ "$ENVIRONMENT" == "production" && "$APP_DEBUG" == "true" ]]; then
        log_error "Debug mode must be disabled in production"
        ((VALIDATION_ERRORS++))
    fi
    
    log_success "Security configuration validation completed"
}

# Show validation summary
show_validation_summary() {
    log_info "Validation Summary:"
    echo "  Errors:   $VALIDATION_ERRORS"
    echo "  Warnings: $VALIDATION_WARNINGS"
    echo ""
    
    if [[ $VALIDATION_ERRORS -eq 0 && $VALIDATION_WARNINGS -eq 0 ]]; then
        log_success "All validations passed! Configuration is ready for deployment."
    elif [[ $VALIDATION_ERRORS -eq 0 ]]; then
        log_warning "Validation completed with warnings. Review warnings before deployment."
    else
        log_error "Validation failed! Please fix errors before deployment."
    fi
}

# Run main function
main "$@"

