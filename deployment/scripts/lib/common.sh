#!/bin/bash

# Common functions for Reverse Tender Platform deployment scripts
# This file contains shared utilities used across all deployment scripts

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}ℹ️  INFO:${NC} $1"
}

log_success() {
    echo -e "${GREEN}✅ SUCCESS:${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}⚠️  WARNING:${NC} $1"
}

log_error() {
    echo -e "${RED}❌ ERROR:${NC} $1" >&2
}

log_debug() {
    if [[ "$VERBOSE" == "true" ]]; then
        echo -e "${PURPLE}🔍 DEBUG:${NC} $1"
    fi
}

log_step() {
    echo -e "${CYAN}🚀 STEP:${NC} $1"
}

# Utility functions
check_command() {
    local cmd="$1"
    if ! command -v "$cmd" &> /dev/null; then
        log_error "Required command not found: $cmd"
        return 1
    fi
    log_debug "Command found: $cmd"
}

check_required_commands() {
    local commands=("$@")
    local missing_commands=()
    
    for cmd in "${commands[@]}"; do
        if ! command -v "$cmd" &> /dev/null; then
            missing_commands+=("$cmd")
        fi
    done
    
    if [[ ${#missing_commands[@]} -gt 0 ]]; then
        log_error "Missing required commands: ${missing_commands[*]}"
        log_info "Please install the missing commands and try again"
        return 1
    fi
    
    log_success "All required commands are available"
}

check_file_exists() {
    local file="$1"
    if [[ ! -f "$file" ]]; then
        log_error "Required file not found: $file"
        return 1
    fi
    log_debug "File found: $file"
}

check_directory_exists() {
    local dir="$1"
    if [[ ! -d "$dir" ]]; then
        log_error "Required directory not found: $dir"
        return 1
    fi
    log_debug "Directory found: $dir"
}

create_directory() {
    local dir="$1"
    if [[ ! -d "$dir" ]]; then
        log_info "Creating directory: $dir"
        if [[ "$DRY_RUN" != "true" ]]; then
            mkdir -p "$dir" || {
                log_error "Failed to create directory: $dir"
                return 1
            }
        fi
    fi
    log_debug "Directory ready: $dir"
}

# Environment validation
validate_environment_variable() {
    local var_name="$1"
    local var_value="${!var_name}"
    local required="${2:-true}"
    
    if [[ -z "$var_value" ]]; then
        if [[ "$required" == "true" ]]; then
            log_error "Required environment variable not set: $var_name"
            return 1
        else
            log_warning "Optional environment variable not set: $var_name"
        fi
    else
        log_debug "Environment variable set: $var_name"
    fi
}

# File operations
backup_file() {
    local file="$1"
    local backup_suffix="${2:-$(date +%Y%m%d_%H%M%S)}"
    
    if [[ -f "$file" ]]; then
        local backup_file="${file}.backup.${backup_suffix}"
        log_info "Backing up file: $file -> $backup_file"
        if [[ "$DRY_RUN" != "true" ]]; then
            cp "$file" "$backup_file" || {
                log_error "Failed to backup file: $file"
                return 1
            }
        fi
    fi
}

restore_file() {
    local file="$1"
    local backup_suffix="${2:-$(date +%Y%m%d_%H%M%S)}"
    local backup_file="${file}.backup.${backup_suffix}"
    
    if [[ -f "$backup_file" ]]; then
        log_info "Restoring file: $backup_file -> $file"
        if [[ "$DRY_RUN" != "true" ]]; then
            cp "$backup_file" "$file" || {
                log_error "Failed to restore file: $file"
                return 1
            }
        fi
    else
        log_error "Backup file not found: $backup_file"
        return 1
    fi
}

# Template processing
process_template() {
    local template_file="$1"
    local output_file="$2"
    
    if [[ ! -f "$template_file" ]]; then
        log_error "Template file not found: $template_file"
        return 1
    fi
    
    log_info "Processing template: $template_file -> $output_file"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        envsubst < "$template_file" > "$output_file" || {
            log_error "Failed to process template: $template_file"
            return 1
        }
    fi
    
    log_success "Template processed successfully"
}

# Network utilities
wait_for_service() {
    local host="$1"
    local port="$2"
    local timeout="${3:-60}"
    local interval="${4:-5}"
    
    log_info "Waiting for service: $host:$port (timeout: ${timeout}s)"
    
    local elapsed=0
    while [[ $elapsed -lt $timeout ]]; do
        if nc -z "$host" "$port" 2>/dev/null; then
            log_success "Service is ready: $host:$port"
            return 0
        fi
        
        log_debug "Service not ready, waiting... (${elapsed}/${timeout}s)"
        sleep "$interval"
        elapsed=$((elapsed + interval))
    done
    
    log_error "Service not ready after ${timeout}s: $host:$port"
    return 1
}

check_url() {
    local url="$1"
    local expected_status="${2:-200}"
    local timeout="${3:-30}"
    
    log_info "Checking URL: $url"
    
    local status_code
    status_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time "$timeout" "$url" 2>/dev/null)
    
    if [[ "$status_code" == "$expected_status" ]]; then
        log_success "URL check passed: $url (status: $status_code)"
        return 0
    else
        log_error "URL check failed: $url (expected: $expected_status, got: $status_code)"
        return 1
    fi
}

# Docker utilities
docker_login() {
    local registry="$1"
    local username="$2"
    local password="$3"
    
    if [[ -n "$registry" && -n "$username" && -n "$password" ]]; then
        log_info "Logging into Docker registry: $registry"
        if [[ "$DRY_RUN" != "true" ]]; then
            echo "$password" | docker login "$registry" -u "$username" --password-stdin || {
                log_error "Failed to login to Docker registry: $registry"
                return 1
            }
        fi
        log_success "Docker login successful"
    else
        log_debug "Skipping Docker login (credentials not provided)"
    fi
}

docker_build_and_push() {
    local dockerfile="$1"
    local image_name="$2"
    local build_context="${3:-.}"
    
    log_info "Building Docker image: $image_name"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        docker build -f "$dockerfile" -t "$image_name" "$build_context" || {
            log_error "Failed to build Docker image: $image_name"
            return 1
        }
        
        docker push "$image_name" || {
            log_error "Failed to push Docker image: $image_name"
            return 1
        }
    fi
    
    log_success "Docker image built and pushed: $image_name"
}

# Kubernetes utilities
kubectl_apply() {
    local manifest="$1"
    local namespace="${2:-default}"
    
    log_info "Applying Kubernetes manifest: $manifest"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        kubectl apply -f "$manifest" -n "$namespace" || {
            log_error "Failed to apply Kubernetes manifest: $manifest"
            return 1
        }
    fi
    
    log_success "Kubernetes manifest applied: $manifest"
}

kubectl_wait_for_deployment() {
    local deployment="$1"
    local namespace="${2:-default}"
    local timeout="${3:-300}"
    
    log_info "Waiting for deployment: $deployment (namespace: $namespace)"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        kubectl wait --for=condition=available --timeout="${timeout}s" deployment/"$deployment" -n "$namespace" || {
            log_error "Deployment not ready: $deployment"
            return 1
        }
    fi
    
    log_success "Deployment ready: $deployment"
}

# Terraform utilities
terraform_init() {
    local terraform_dir="$1"
    
    log_info "Initializing Terraform: $terraform_dir"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        (cd "$terraform_dir" && terraform init) || {
            log_error "Terraform init failed"
            return 1
        }
    fi
    
    log_success "Terraform initialized"
}

terraform_plan() {
    local terraform_dir="$1"
    local var_file="$2"
    
    log_info "Planning Terraform changes: $terraform_dir"
    
    local plan_args=()
    if [[ -n "$var_file" ]]; then
        plan_args+=("-var-file=$var_file")
    fi
    
    if [[ "$DRY_RUN" != "true" ]]; then
        (cd "$terraform_dir" && terraform plan "${plan_args[@]}") || {
            log_error "Terraform plan failed"
            return 1
        }
    fi
    
    log_success "Terraform plan completed"
}

terraform_apply() {
    local terraform_dir="$1"
    local var_file="$2"
    
    log_info "Applying Terraform changes: $terraform_dir"
    
    local apply_args=("-auto-approve")
    if [[ -n "$var_file" ]]; then
        apply_args+=("-var-file=$var_file")
    fi
    
    if [[ "$DRY_RUN" != "true" ]]; then
        (cd "$terraform_dir" && terraform apply "${apply_args[@]}") || {
            log_error "Terraform apply failed"
            return 1
        }
    fi
    
    log_success "Terraform apply completed"
}

# Progress indicator
show_progress() {
    local message="$1"
    local duration="${2:-5}"
    
    echo -n "$message"
    for ((i=1; i<=duration; i++)); do
        echo -n "."
        sleep 1
    done
    echo " Done!"
}

# Cleanup functions
cleanup_temp_files() {
    local temp_dir="${1:-/tmp/reverse-tender-deploy}"
    
    if [[ -d "$temp_dir" ]]; then
        log_info "Cleaning up temporary files: $temp_dir"
        if [[ "$DRY_RUN" != "true" ]]; then
            rm -rf "$temp_dir"
        fi
    fi
}

# Error handling
handle_error() {
    local exit_code=$?
    local line_number=$1
    
    log_error "Script failed at line $line_number with exit code $exit_code"
    cleanup_temp_files
    exit $exit_code
}

# Set up error handling
set_error_handling() {
    set -e
    trap 'handle_error $LINENO' ERR
}

# Version checking
check_version() {
    local tool="$1"
    local required_version="$2"
    
    case "$tool" in
        docker)
            local current_version
            current_version=$(docker --version | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
            ;;
        kubectl)
            local current_version
            current_version=$(kubectl version --client -o json | jq -r '.clientVersion.gitVersion' | sed 's/v//')
            ;;
        terraform)
            local current_version
            current_version=$(terraform version -json | jq -r '.terraform_version')
            ;;
        *)
            log_warning "Version check not implemented for: $tool"
            return 0
            ;;
    esac
    
    if [[ -n "$current_version" ]]; then
        log_info "$tool version: $current_version"
        # Add version comparison logic here if needed
    fi
}

# Export functions for use in other scripts
export -f log_info log_success log_warning log_error log_debug log_step
export -f check_command check_required_commands check_file_exists check_directory_exists
export -f create_directory validate_environment_variable backup_file restore_file
export -f process_template wait_for_service check_url docker_login docker_build_and_push
export -f kubectl_apply kubectl_wait_for_deployment terraform_init terraform_plan terraform_apply
export -f show_progress cleanup_temp_files handle_error set_error_handling check_version
