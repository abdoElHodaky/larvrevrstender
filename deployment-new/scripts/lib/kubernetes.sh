#!/bin/bash

# Kubernetes deployment functions for Reverse Tender Platform
# This file contains Kubernetes-specific deployment logic

# Source common functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# Kubernetes deployment main function
kubernetes_deploy() {
    log_step "Starting Kubernetes deployment"
    
    # Check prerequisites
    kubernetes_check_prerequisites
    
    # Prepare Kubernetes environment
    kubernetes_prepare_environment
    
    # Configure kubectl
    kubernetes_configure_kubectl
    
    # Deploy applications
    kubernetes_deploy_applications
    
    # Verify deployment
    kubernetes_verify_deployment
    
    log_success "Kubernetes deployment completed successfully"
}

# Check Kubernetes prerequisites
kubernetes_check_prerequisites() {
    log_info "Checking Kubernetes prerequisites..."
    
    # Check required commands
    check_required_commands kubectl
    
    # Check kubectl version
    local kubectl_version
    kubectl_version=$(kubectl version --client -o json 2>/dev/null | jq -r '.clientVersion.gitVersion' | sed 's/v//' || kubectl version --client --short | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')
    log_info "kubectl version: $kubectl_version"
    
    # Check if kustomize is available
    if command -v kustomize &> /dev/null; then
        local kustomize_version
        kustomize_version=$(kustomize version --short | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')
        log_info "kustomize version: $kustomize_version"
    else
        log_warning "kustomize not found - using kubectl kustomize"
    fi
    
    # Check if helm is available (for monitoring stack)
    if command -v helm &> /dev/null; then
        local helm_version
        helm_version=$(helm version --short | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')
        log_info "helm version: $helm_version"
    else
        log_warning "helm not found - monitoring stack may not be available"
    fi
    
    log_success "Kubernetes prerequisites check passed"
}

# Prepare Kubernetes environment
kubernetes_prepare_environment() {
    log_info "Preparing Kubernetes environment..."
    
    local k8s_dir="${SCRIPT_DIR}/../../k8s"
    
    # Check Kubernetes configuration directory
    check_directory_exists "$k8s_dir"
    
    # Create secrets directory if needed
    create_directory "$k8s_dir/secrets"
    
    # Prepare environment-specific secrets
    kubernetes_prepare_secrets
    
    # Validate Kubernetes manifests
    kubernetes_validate_manifests
    
    log_success "Kubernetes environment prepared"
}

# Prepare Kubernetes secrets
kubernetes_prepare_secrets() {
    log_info "Preparing Kubernetes secrets..."
    
    local k8s_dir="${SCRIPT_DIR}/../../k8s"
    local secrets_dir="$k8s_dir/secrets"
    
    # Create secret files from environment variables
    if [[ "$DRY_RUN" != "true" ]]; then
        # JWT Secret
        if [[ -n "$JWT_SECRET" ]]; then
            echo -n "$JWT_SECRET" > "$secrets_dir/jwt-secret.txt"
        fi
        
        # App Key
        if [[ -n "$APP_KEY" ]]; then
            echo -n "$APP_KEY" > "$secrets_dir/app-key.txt"
        fi
        
        # Database Password
        if [[ -n "$DB_PASSWORD" ]]; then
            echo -n "$DB_PASSWORD" > "$secrets_dir/db-password.txt"
        fi
        
        # Redis Password
        if [[ -n "$REDIS_PASSWORD" ]]; then
            echo -n "$REDIS_PASSWORD" > "$secrets_dir/redis-password.txt"
        fi
        
        # Set proper permissions
        chmod 600 "$secrets_dir"/*.txt 2>/dev/null || true
    fi
    
    log_success "Kubernetes secrets prepared"
}

# Validate Kubernetes manifests
kubernetes_validate_manifests() {
    log_info "Validating Kubernetes manifests..."
    
    local k8s_dir="${SCRIPT_DIR}/../../k8s"
    local base_dir="$k8s_dir/base"
    local overlay_dir="$k8s_dir/overlays/${ENVIRONMENT}"
    
    # Validate base configuration
    if [[ -d "$base_dir" ]]; then
        if kubectl kustomize "$base_dir" > /dev/null; then
            log_debug "Base Kubernetes configuration is valid"
        else
            log_error "Base Kubernetes configuration validation failed"
            return 1
        fi
    fi
    
    # Validate environment overlay
    if [[ -d "$overlay_dir" ]]; then
        if kubectl kustomize "$overlay_dir" > /dev/null; then
            log_debug "Environment overlay is valid: $ENVIRONMENT"
        else
            log_error "Environment overlay validation failed: $ENVIRONMENT"
            return 1
        fi
    fi
    
    log_success "Kubernetes manifests validation completed"
}

# Configure kubectl
kubernetes_configure_kubectl() {
    log_info "Configuring kubectl..."
    
    # Get kubeconfig from Terraform outputs if available
    local terraform_dir="${SCRIPT_DIR}/../../terraform"
    
    if [[ -f "$terraform_dir/terraform.tfstate" ]]; then
        log_debug "Getting Kubernetes configuration from Terraform"
        
        # Extract kubeconfig from Terraform outputs
        local kubeconfig_data
        kubeconfig_data=$(cd "$terraform_dir" && terraform output -raw kubeconfig 2>/dev/null || echo "")
        
        if [[ -n "$kubeconfig_data" ]]; then
            # Save kubeconfig to temporary file
            local kubeconfig_file="/tmp/kubeconfig-${ENVIRONMENT}"
            echo "$kubeconfig_data" > "$kubeconfig_file"
            export KUBECONFIG="$kubeconfig_file"
            
            log_debug "Kubeconfig configured from Terraform"
        else
            log_warning "Could not get kubeconfig from Terraform outputs"
        fi
    fi
    
    # Test kubectl connectivity
    if kubectl cluster-info &> /dev/null; then
        log_success "kubectl is configured and cluster is accessible"
        
        # Show cluster info
        local cluster_info
        cluster_info=$(kubectl cluster-info | head -1)
        log_info "Connected to: $cluster_info"
    else
        log_error "kubectl is not configured or cluster is not accessible"
        return 1
    fi
}

# Deploy applications to Kubernetes
kubernetes_deploy_applications() {
    log_info "Deploying applications to Kubernetes..."
    
    local k8s_dir="${SCRIPT_DIR}/../../k8s"
    local overlay_dir="$k8s_dir/overlays/${ENVIRONMENT}"
    local base_dir="$k8s_dir/base"
    
    # Determine which configuration to use
    local deploy_dir
    if [[ -d "$overlay_dir" ]]; then
        deploy_dir="$overlay_dir"
        log_info "Using environment overlay: $ENVIRONMENT"
    else
        deploy_dir="$base_dir"
        log_warning "Environment overlay not found, using base configuration"
    fi
    
    # Deploy namespace first
    kubernetes_deploy_namespace
    
    # Deploy secrets
    kubernetes_deploy_secrets
    
    # Deploy applications
    kubernetes_deploy_manifests "$deploy_dir"
    
    # Wait for deployments to be ready
    kubernetes_wait_for_deployments
    
    # Deploy monitoring if enabled
    if [[ "$MONITORING_ENABLED" == "true" ]]; then
        kubernetes_deploy_monitoring
    fi
    
    log_success "Applications deployed to Kubernetes"
}

# Deploy namespace
kubernetes_deploy_namespace() {
    log_info "Creating namespace..."
    
    local namespace="${KUBERNETES_NAMESPACE:-reverse-tender}"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        kubectl create namespace "$namespace" --dry-run=client -o yaml | kubectl apply -f - || {
            log_debug "Namespace already exists or creation failed"
        }
    fi
    
    log_success "Namespace ready: $namespace"
}

# Deploy secrets
kubernetes_deploy_secrets() {
    log_info "Deploying secrets..."
    
    local namespace="${KUBERNETES_NAMESPACE:-reverse-tender}"
    local secrets_dir="${SCRIPT_DIR}/../../k8s/secrets"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        # Create generic secret from files
        if [[ -d "$secrets_dir" && $(ls -A "$secrets_dir" 2>/dev/null) ]]; then
            kubectl create secret generic app-secrets \
                --from-file="$secrets_dir" \
                --namespace="$namespace" \
                --dry-run=client -o yaml | kubectl apply -f - || {
                log_warning "Failed to create secrets"
            }
        else
            log_warning "No secret files found in $secrets_dir"
        fi
    fi
    
    log_success "Secrets deployed"
}

# Deploy Kubernetes manifests
kubernetes_deploy_manifests() {
    local deploy_dir="$1"
    local namespace="${KUBERNETES_NAMESPACE:-reverse-tender}"
    
    log_info "Deploying manifests from: $deploy_dir"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        # Apply manifests using kustomize
        kubectl apply -k "$deploy_dir" --namespace="$namespace" || {
            log_error "Failed to deploy manifests"
            return 1
        }
    else
        log_info "DRY RUN: Would deploy manifests from $deploy_dir"
        
        # Show what would be deployed
        kubectl kustomize "$deploy_dir" | head -20
        echo "... (truncated)"
    fi
    
    log_success "Manifests deployed"
}

# Wait for deployments to be ready
kubernetes_wait_for_deployments() {
    log_info "Waiting for deployments to be ready..."
    
    local namespace="${KUBERNETES_NAMESPACE:-reverse-tender}"
    local timeout="${DEPLOYMENT_TIMEOUT:-600}"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        # Get all deployments in namespace
        local deployments
        deployments=$(kubectl get deployments -n "$namespace" -o name 2>/dev/null || echo "")
        
        if [[ -n "$deployments" ]]; then
            # Wait for each deployment
            while IFS= read -r deployment; do
                local deployment_name
                deployment_name=$(echo "$deployment" | sed 's|deployment.apps/||')
                
                log_info "Waiting for deployment: $deployment_name"
                
                if kubectl wait --for=condition=available --timeout="${timeout}s" "$deployment" -n "$namespace"; then
                    log_success "Deployment ready: $deployment_name"
                else
                    log_error "Deployment failed or timed out: $deployment_name"
                    
                    # Show deployment status for debugging
                    kubectl describe "$deployment" -n "$namespace"
                    return 1
                fi
            done <<< "$deployments"
        else
            log_warning "No deployments found in namespace: $namespace"
        fi
    else
        log_info "DRY RUN: Would wait for deployments to be ready"
    fi
    
    log_success "All deployments are ready"
}

# Deploy monitoring stack
kubernetes_deploy_monitoring() {
    log_info "Deploying monitoring stack..."
    
    local namespace="${KUBERNETES_NAMESPACE:-reverse-tender}"
    
    if command -v helm &> /dev/null; then
        kubernetes_deploy_monitoring_helm
    else
        kubernetes_deploy_monitoring_manifests
    fi
    
    log_success "Monitoring stack deployed"
}

# Deploy monitoring using Helm
kubernetes_deploy_monitoring_helm() {
    log_info "Deploying monitoring with Helm..."
    
    local namespace="${KUBERNETES_NAMESPACE:-reverse-tender}"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        # Add Prometheus Helm repository
        helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
        helm repo update
        
        # Install Prometheus
        helm upgrade --install prometheus prometheus-community/kube-prometheus-stack \
            --namespace "$namespace" \
            --create-namespace \
            --set prometheus.prometheusSpec.retention=30d \
            --set grafana.adminPassword="${GRAFANA_PASSWORD:-admin}" \
            --wait || {
            log_warning "Failed to deploy monitoring with Helm"
        }
    else
        log_info "DRY RUN: Would deploy monitoring with Helm"
    fi
}

# Deploy monitoring using manifests
kubernetes_deploy_monitoring_manifests() {
    log_info "Deploying monitoring with manifests..."
    
    local k8s_dir="${SCRIPT_DIR}/../../k8s"
    local monitoring_dir="$k8s_dir/monitoring"
    
    if [[ -d "$monitoring_dir" ]]; then
        if [[ "$DRY_RUN" != "true" ]]; then
            kubectl apply -k "$monitoring_dir" || {
                log_warning "Failed to deploy monitoring manifests"
            }
        else
            log_info "DRY RUN: Would deploy monitoring manifests"
        fi
    else
        log_warning "Monitoring manifests not found: $monitoring_dir"
    fi
}

# Verify Kubernetes deployment
kubernetes_verify_deployment() {
    log_info "Verifying Kubernetes deployment..."
    
    local namespace="${KUBERNETES_NAMESPACE:-reverse-tender}"
    
    if [[ "$DRY_RUN" != "true" ]]; then
        # Check pod status
        kubernetes_check_pod_status "$namespace"
        
        # Check service status
        kubernetes_check_service_status "$namespace"
        
        # Check ingress status
        kubernetes_check_ingress_status "$namespace"
        
        # Run health checks
        kubernetes_run_health_checks "$namespace"
        
        # Show deployment summary
        kubernetes_show_deployment_summary "$namespace"
    else
        log_info "DRY RUN: Would verify Kubernetes deployment"
    fi
    
    log_success "Kubernetes deployment verification completed"
}

# Check pod status
kubernetes_check_pod_status() {
    local namespace="$1"
    
    log_info "Checking pod status..."
    
    # Get pod status
    local pod_status
    pod_status=$(kubectl get pods -n "$namespace" --no-headers 2>/dev/null || echo "")
    
    if [[ -n "$pod_status" ]]; then
        log_info "Pod status in namespace $namespace:"
        kubectl get pods -n "$namespace"
        
        # Check for failed pods
        local failed_pods
        failed_pods=$(kubectl get pods -n "$namespace" --field-selector=status.phase=Failed --no-headers 2>/dev/null | wc -l)
        
        if [[ $failed_pods -gt 0 ]]; then
            log_warning "Found $failed_pods failed pods"
            kubectl get pods -n "$namespace" --field-selector=status.phase=Failed
        fi
    else
        log_warning "No pods found in namespace: $namespace"
    fi
}

# Check service status
kubernetes_check_service_status() {
    local namespace="$1"
    
    log_info "Checking service status..."
    
    local services
    services=$(kubectl get services -n "$namespace" --no-headers 2>/dev/null || echo "")
    
    if [[ -n "$services" ]]; then
        log_info "Services in namespace $namespace:"
        kubectl get services -n "$namespace"
    else
        log_warning "No services found in namespace: $namespace"
    fi
}

# Check ingress status
kubernetes_check_ingress_status() {
    local namespace="$1"
    
    log_info "Checking ingress status..."
    
    local ingresses
    ingresses=$(kubectl get ingress -n "$namespace" --no-headers 2>/dev/null || echo "")
    
    if [[ -n "$ingresses" ]]; then
        log_info "Ingresses in namespace $namespace:"
        kubectl get ingress -n "$namespace"
    else
        log_debug "No ingresses found in namespace: $namespace"
    fi
}

# Run health checks
kubernetes_run_health_checks() {
    local namespace="$1"
    
    log_info "Running health checks..."
    
    # Get service endpoints
    local services
    services=$(kubectl get services -n "$namespace" -o jsonpath='{.items[*].metadata.name}' 2>/dev/null || echo "")
    
    if [[ -n "$services" ]]; then
        for service in $services; do
            # Skip load balancer service
            if [[ "$service" == *"-lb" ]]; then
                continue
            fi
            
            log_debug "Checking health for service: $service"
            
            # Port forward and check health endpoint
            local port
            port=$(kubectl get service "$service" -n "$namespace" -o jsonpath='{.spec.ports[0].port}' 2>/dev/null || echo "")
            
            if [[ -n "$port" ]]; then
                # Use kubectl port-forward to test connectivity
                timeout 10 kubectl port-forward "service/$service" "$port:$port" -n "$namespace" &>/dev/null &
                local pf_pid=$!
                
                sleep 2
                
                if curl -f "http://localhost:$port/health" &>/dev/null; then
                    log_success "Health check passed: $service"
                else
                    log_warning "Health check failed: $service"
                fi
                
                kill $pf_pid 2>/dev/null || true
            fi
        done
    fi
}

# Show deployment summary
kubernetes_show_deployment_summary() {
    local namespace="$1"
    
    log_info "Kubernetes Deployment Summary:"
    
    echo "Namespace: $namespace"
    echo ""
    
    echo "Deployments:"
    kubectl get deployments -n "$namespace" 2>/dev/null || echo "  No deployments found"
    echo ""
    
    echo "Services:"
    kubectl get services -n "$namespace" 2>/dev/null || echo "  No services found"
    echo ""
    
    echo "Pods:"
    kubectl get pods -n "$namespace" 2>/dev/null || echo "  No pods found"
    echo ""
    
    # Show external access information
    local lb_ip
    lb_ip=$(kubectl get service -n "$namespace" -l tier=loadbalancer -o jsonpath='{.items[0].status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "")
    
    if [[ -n "$lb_ip" ]]; then
        echo "External Access:"
        echo "  Load Balancer IP: $lb_ip"
        echo "  Application URL: http://$lb_ip"
        echo ""
    fi
}

# Scale deployment
kubernetes_scale_deployment() {
    local deployment="$1"
    local replicas="$2"
    local namespace="${KUBERNETES_NAMESPACE:-reverse-tender}"
    
    log_info "Scaling deployment $deployment to $replicas replicas..."
    
    if [[ "$DRY_RUN" != "true" ]]; then
        kubectl scale deployment "$deployment" --replicas="$replicas" -n "$namespace" || {
            log_error "Failed to scale deployment: $deployment"
            return 1
        }
        
        # Wait for scaling to complete
        kubectl wait --for=condition=available --timeout=300s deployment/"$deployment" -n "$namespace" || {
            log_warning "Scaling may not have completed within timeout"
        }
    else
        log_info "DRY RUN: Would scale $deployment to $replicas replicas"
    fi
    
    log_success "Deployment scaled: $deployment"
}

# Export functions
export -f kubernetes_deploy kubernetes_check_prerequisites kubernetes_prepare_environment
export -f kubernetes_configure_kubectl kubernetes_deploy_applications kubernetes_verify_deployment
export -f kubernetes_scale_deployment

