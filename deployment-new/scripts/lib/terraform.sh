#!/bin/bash

# Terraform deployment functions for Reverse Tender Platform
# This file contains Terraform-specific deployment logic

# Source common functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# Terraform deployment main function
terraform_deploy() {
    log_step "Starting Terraform deployment"
    
    # Check prerequisites
    terraform_check_prerequisites
    
    # Prepare Terraform environment
    terraform_prepare_environment
    
    # Initialize Terraform
    terraform_initialize
    
    # Plan deployment
    terraform_plan_deployment
    
    # Apply deployment
    terraform_apply_deployment
    
    # Verify deployment
    terraform_verify_deployment
    
    log_success "Terraform deployment completed successfully"
}

# Check Terraform prerequisites
terraform_check_prerequisites() {
    log_info "Checking Terraform prerequisites..."
    
    # Check required commands
    check_required_commands terraform
    
    # Check Terraform version
    local terraform_version
    terraform_version=$(terraform version -json | jq -r '.terraform_version' 2>/dev/null || terraform version | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
    log_info "Terraform version: $terraform_version"
    
    # Check cloud provider CLI tools
    case "$CLOUD_PROVIDER" in
        digitalocean)
            if command -v doctl &> /dev/null; then
                local doctl_version
                doctl_version=$(doctl version | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
                log_info "doctl version: $doctl_version"
            else
                log_warning "doctl not found - some features may not work"
            fi
            ;;
        linode)
            if command -v linode-cli &> /dev/null; then
                local linode_version
                linode_version=$(linode-cli --version 2>&1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
                log_info "linode-cli version: $linode_version"
            else
                log_warning "linode-cli not found - some features may not work"
            fi
            ;;
    esac
    
    # Check required environment variables
    terraform_check_required_vars
    
    log_success "Terraform prerequisites check passed"
}

# Check required Terraform variables
terraform_check_required_vars() {
    log_debug "Checking required Terraform variables..."
    
    local required_vars=()
    
    # Common required variables
    required_vars+=("ENVIRONMENT" "CLOUD_PROVIDER")
    
    # Provider-specific variables
    case "$CLOUD_PROVIDER" in
        digitalocean)
            if [[ -z "$DIGITALOCEAN_TOKEN" && -z "$DO_TOKEN" ]]; then
                log_error "DigitalOcean token required (DIGITALOCEAN_TOKEN or DO_TOKEN)"
                return 1
            fi
            ;;
        linode)
            if [[ -z "$LINODE_TOKEN" ]]; then
                log_error "Linode token required (LINODE_TOKEN)"
                return 1
            fi
            ;;
    esac
    
    # Environment-specific variables
    if [[ "$ENVIRONMENT" == "production" ]]; then
        required_vars+=("JWT_SECRET" "APP_KEY" "DB_PASSWORD")
    fi
    
    # Check all required variables
    for var in "${required_vars[@]}"; do
        if [[ -z "${!var}" ]]; then
            log_error "Required variable not set: $var"
            return 1
        fi
    done
    
    log_debug "All required Terraform variables are set"
}

# Prepare Terraform environment
terraform_prepare_environment() {
    log_info "Preparing Terraform environment..."
    
    local terraform_dir="${SCRIPT_DIR}/../../terraform"
    
    # Create terraform directory if it doesn't exist
    create_directory "$terraform_dir"
    
    # Create terraform.tfvars file
    terraform_create_tfvars
    
    # Create backend configuration if needed
    terraform_create_backend_config
    
    # Set Terraform environment variables
    terraform_set_env_vars
    
    log_success "Terraform environment prepared"
}

# Create terraform.tfvars file
terraform_create_tfvars() {
    local terraform_dir="${SCRIPT_DIR}/../../terraform"
    local tfvars_file="$terraform_dir/terraform.tfvars"
    
    log_info "Creating Terraform variables file: $tfvars_file"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        cat > "$tfvars_file" << EOF
# Generated Terraform variables file
# Environment: $ENVIRONMENT
# Cloud Provider: $CLOUD_PROVIDER
# Generated at: $(date)

# General Configuration
environment = "$ENVIRONMENT"
cloud_provider = "$CLOUD_PROVIDER"
region = "${REGION:-fra1}"

# Kubernetes Configuration
kubernetes_version = "${KUBERNETES_VERSION:-1.29}"
kubernetes_node_count = ${KUBERNETES_NODE_COUNT:-3}
kubernetes_node_type = "${KUBERNETES_NODE_TYPE:-s-4vcpu-8gb}"
kubernetes_min_nodes = ${KUBERNETES_MIN_NODES:-2}
kubernetes_max_nodes = ${KUBERNETES_MAX_NODES:-10}

# Database Configuration
database_engine = "${DATABASE_ENGINE:-mysql}"
database_version = "${DATABASE_VERSION:-8.0}"
database_instance_class = "${DATABASE_INSTANCE_CLASS:-db-s-2vcpu-4gb}"
database_storage_size = ${DATABASE_STORAGE_SIZE:-100}
database_backup_enabled = ${DATABASE_BACKUP_ENABLED:-true}

# Redis Configuration
redis_version = "${REDIS_VERSION:-7.0}"
redis_instance_class = "${REDIS_INSTANCE_CLASS:-db-s-1vcpu-1gb}"
redis_node_count = ${REDIS_NODE_COUNT:-1}

# Network Configuration
vpc_cidr = "${VPC_CIDR:-10.10.0.0/16}"
private_subnets = ["10.10.1.0/24", "10.10.2.0/24"]
public_subnets = ["10.10.101.0/24", "10.10.102.0/24"]
enable_nat_gateway = ${ENABLE_NAT_GATEWAY:-true}

# Load Balancer Configuration
load_balancer_algorithm = "${LOAD_BALANCER_ALGORITHM:-round_robin}"
health_check_path = "${HEALTH_CHECK_PATH:-/health}"
health_check_interval = ${HEALTH_CHECK_INTERVAL:-30}
health_check_timeout = ${HEALTH_CHECK_TIMEOUT:-5}
health_check_retries = ${HEALTH_CHECK_RETRIES:-3}

# Storage Configuration
storage_bucket_name = "${STORAGE_BUCKET_NAME:-}"
storage_region = "${STORAGE_REGION:-}"
storage_versioning = ${STORAGE_VERSIONING:-true}
storage_encryption = ${STORAGE_ENCRYPTION:-true}

# Security Configuration
allowed_ips = ["${ALLOWED_IPS:-0.0.0.0/0}"]
ssl_certificate = "${SSL_CERTIFICATE:-lets_encrypt}"
firewall_enabled = ${FIREWALL_ENABLED:-true}

# Application Service Replicas
api_gateway_replicas = ${API_GATEWAY_REPLICAS:-2}
auth_service_replicas = ${AUTH_SERVICE_REPLICAS:-2}
bidding_service_replicas = ${BIDDING_SERVICE_REPLICAS:-3}
user_service_replicas = ${USER_SERVICE_REPLICAS:-2}
order_service_replicas = ${ORDER_SERVICE_REPLICAS:-2}
notification_service_replicas = ${NOTIFICATION_SERVICE_REPLICAS:-2}
payment_service_replicas = ${PAYMENT_SERVICE_REPLICAS:-2}
analytics_service_replicas = ${ANALYTICS_SERVICE_REPLICAS:-1}
vin_ocr_service_replicas = ${VIN_OCR_SERVICE_REPLICAS:-2}

# Monitoring Configuration
monitoring_enabled = ${MONITORING_ENABLED:-true}
alerting_enabled = ${ALERTING_ENABLED:-true}
prometheus_retention_days = ${PROMETHEUS_RETENTION_DAYS:-30}
prometheus_storage_size = ${PROMETHEUS_STORAGE_SIZE:-50}
grafana_admin_password = "${GRAFANA_PASSWORD:-admin}"
grafana_admin_user = "${GRAFANA_ADMIN_USER:-admin}"
slack_webhook_url = "${SLACK_WEBHOOK_URL:-}"
alert_email = "${ALERT_EMAIL:-}"

# Application Secrets
jwt_secret = "${JWT_SECRET:-}"
app_key = "${APP_KEY:-}"
twilio_sid = "${TWILIO_SID:-}"
twilio_token = "${TWILIO_TOKEN:-}"
sendgrid_api_key = "${SENDGRID_API_KEY:-}"

# Cloud Provider Tokens
digitalocean_token = "${DIGITALOCEAN_TOKEN:-${DO_TOKEN:-}}"
linode_token = "${LINODE_TOKEN:-}"
EOF
    fi
    
    log_success "Terraform variables file created"
}

# Create backend configuration
terraform_create_backend_config() {
    local terraform_dir="${SCRIPT_DIR}/../../terraform"
    local backend_file="$terraform_dir/backend.tf"
    
    # Only create backend config for production
    if [[ "$ENVIRONMENT" == "production" && -n "$TERRAFORM_BACKEND_BUCKET" ]]; then
        log_info "Creating Terraform backend configuration"
        
        if [[ "$DRY_RUN" != "true" ]]; then
            cat > "$backend_file" << EOF
# Terraform Backend Configuration for Production
terraform {
  backend "s3" {
    bucket = "$TERRAFORM_BACKEND_BUCKET"
    key    = "terraform/reverse-tender-${ENVIRONMENT}.tfstate"
    region = "${TERRAFORM_BACKEND_REGION:-us-east-1}"
    
    # Enable state locking
    dynamodb_table = "${TERRAFORM_BACKEND_DYNAMODB_TABLE:-terraform-locks}"
    encrypt        = true
  }
}
EOF
        fi
        
        log_success "Terraform backend configuration created"
    else
        log_debug "Skipping backend configuration (not production or backend not configured)"
    fi
}

# Set Terraform environment variables
terraform_set_env_vars() {
    log_debug "Setting Terraform environment variables..."
    
    # Set provider-specific environment variables
    case "$CLOUD_PROVIDER" in
        digitalocean)
            export DIGITALOCEAN_TOKEN="${DIGITALOCEAN_TOKEN:-$DO_TOKEN}"
            ;;
        linode)
            export LINODE_TOKEN="$LINODE_TOKEN"
            ;;
    esac
    
    # Set Terraform-specific variables
    export TF_IN_AUTOMATION=true
    export TF_INPUT=false
    export TF_CLI_ARGS_plan="-parallelism=10"
    export TF_CLI_ARGS_apply="-parallelism=10"
    
    if [[ "$VERBOSE" == "true" ]]; then
        export TF_LOG=INFO
    fi
    
    log_debug "Terraform environment variables set"
}

# Initialize Terraform
terraform_initialize() {
    log_info "Initializing Terraform..."
    
    local terraform_dir="${SCRIPT_DIR}/../../terraform"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        (cd "$terraform_dir" && terraform init -upgrade) || {
            log_error "Terraform initialization failed"
            return 1
        }
    fi
    
    log_success "Terraform initialized successfully"
}

# Plan Terraform deployment
terraform_plan_deployment() {
    log_info "Planning Terraform deployment..."
    
    local terraform_dir="${SCRIPT_DIR}/../../terraform"
    local plan_file="$terraform_dir/terraform.tfplan"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        (cd "$terraform_dir" && terraform plan -out="$plan_file") || {
            log_error "Terraform planning failed"
            return 1
        }
        
        # Show plan summary
        log_info "Terraform plan summary:"
        (cd "$terraform_dir" && terraform show -no-color "$plan_file" | grep -E "Plan:|No changes")
    else
        log_info "DRY RUN: Would run terraform plan"
    fi
    
    log_success "Terraform planning completed"
}

# Apply Terraform deployment
terraform_apply_deployment() {
    log_info "Applying Terraform deployment..."
    
    local terraform_dir="${SCRIPT_DIR}/../../terraform"
    local plan_file="$terraform_dir/terraform.tfplan"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        if [[ -f "$plan_file" ]]; then
            (cd "$terraform_dir" && terraform apply "$plan_file") || {
                log_error "Terraform apply failed"
                return 1
            }
        else
            (cd "$terraform_dir" && terraform apply -auto-approve) || {
                log_error "Terraform apply failed"
                return 1
            }
        fi
        
        # Clean up plan file
        rm -f "$plan_file"
    else
        log_info "DRY RUN: Would run terraform apply"
    fi
    
    log_success "Terraform deployment applied successfully"
}

# Verify Terraform deployment
terraform_verify_deployment() {
    log_info "Verifying Terraform deployment..."
    
    local terraform_dir="${SCRIPT_DIR}/../../terraform"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        # Check Terraform state
        (cd "$terraform_dir" && terraform refresh) || {
            log_warning "Terraform refresh failed"
        }
        
        # Show outputs
        log_info "Terraform outputs:"
        (cd "$terraform_dir" && terraform output) || {
            log_warning "Failed to retrieve Terraform outputs"
        }
        
        # Validate infrastructure
        terraform_validate_infrastructure
    else
        log_info "DRY RUN: Would verify Terraform deployment"
    fi
    
    log_success "Terraform deployment verification completed"
}

# Validate infrastructure
terraform_validate_infrastructure() {
    log_debug "Validating deployed infrastructure..."
    
    local terraform_dir="${SCRIPT_DIR}/../../terraform"
    
    # Get important outputs
    local kubernetes_endpoint
    local load_balancer_ip
    
    kubernetes_endpoint=$(cd "$terraform_dir" && terraform output -raw kubernetes_endpoint 2>/dev/null || echo "")
    load_balancer_ip=$(cd "$terraform_dir" && terraform output -raw load_balancer_ip 2>/dev/null || echo "")
    
    # Validate Kubernetes cluster
    if [[ -n "$kubernetes_endpoint" ]]; then
        log_info "Kubernetes cluster endpoint: $kubernetes_endpoint"
        
        # Test kubectl connectivity
        if command -v kubectl &> /dev/null; then
            if kubectl cluster-info &> /dev/null; then
                log_success "Kubernetes cluster is accessible"
            else
                log_warning "Kubernetes cluster is not accessible via kubectl"
            fi
        fi
    else
        log_warning "Kubernetes endpoint not found in Terraform outputs"
    fi
    
    # Validate load balancer
    if [[ -n "$load_balancer_ip" ]]; then
        log_info "Load balancer IP: $load_balancer_ip"
        
        # Test load balancer connectivity (basic ping)
        if ping -c 1 "$load_balancer_ip" &> /dev/null; then
            log_success "Load balancer is reachable"
        else
            log_warning "Load balancer is not reachable"
        fi
    else
        log_warning "Load balancer IP not found in Terraform outputs"
    fi
}

# Destroy Terraform deployment
terraform_destroy() {
    log_warning "Destroying Terraform deployment..."
    
    local terraform_dir="${SCRIPT_DIR}/../../terraform"
    
    # Confirm destruction
    if [[ "$FORCE" != "true" ]]; then
        read -p "Are you sure you want to destroy the infrastructure? (yes/no): " -r
        if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
            log_info "Destruction cancelled"
            return 0
        fi
    fi
    
    if [[ "$DRY_RUN" != "true" ]]; then
        (cd "$terraform_dir" && terraform destroy -auto-approve) || {
            log_error "Terraform destroy failed"
            return 1
        }
    else
        log_info "DRY RUN: Would run terraform destroy"
    fi
    
    log_success "Terraform deployment destroyed"
}

# Get Terraform outputs
terraform_get_outputs() {
    local terraform_dir="${SCRIPT_DIR}/../../terraform"
    
    if [[ -f "$terraform_dir/terraform.tfstate" ]]; then
        (cd "$terraform_dir" && terraform output -json) || {
            log_error "Failed to get Terraform outputs"
            return 1
        }
    else
        log_warning "Terraform state file not found"
        echo "{}"
    fi
}

# Export functions
export -f terraform_deploy terraform_check_prerequisites terraform_prepare_environment
export -f terraform_initialize terraform_plan_deployment terraform_apply_deployment
export -f terraform_verify_deployment terraform_destroy terraform_get_outputs

