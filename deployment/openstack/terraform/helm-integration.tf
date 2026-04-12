# OpenStack Terraform Integration with Helm Multi-Cloud Deployment
# This file provides Terraform resources that integrate with the Helm chart deployment

# Data source for Kubernetes cluster (assuming Magnum or external cluster)
data "external" "kubeconfig" {
  program = ["bash", "-c", "echo '{\"kubeconfig\": \"'$(base64 -w 0 ~/.kube/config)'\"}'"]
}

# Kubernetes provider configuration for Helm deployment
provider "kubernetes" {
  config_path = "~/.kube/config"
}

# Helm provider configuration
provider "helm" {
  kubernetes {
    config_path = "~/.kube/config"
  }
}

# NGINX Ingress Controller installation for OpenStack
resource "helm_release" "nginx_ingress" {
  name       = "ingress-nginx"
  repository = "https://kubernetes.github.io/ingress-nginx"
  chart      = "ingress-nginx"
  namespace  = "ingress-nginx"
  create_namespace = true
  version    = "4.8.3"

  set {
    name  = "controller.service.type"
    value = "LoadBalancer"
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/openstack-internal-load-balancer"
    value = "false"
  }

  set {
    name  = "controller.service.annotations.loadbalancer\\.openstack\\.org/class"
    value = "octavia"
  }

  set {
    name  = "controller.service.annotations.loadbalancer\\.openstack\\.org/proxy-protocol"
    value = "true"
  }

  set {
    name  = "controller.service.annotations.loadbalancer\\.openstack\\.org/timeout-client-data"
    value = "50000"
  }

  set {
    name  = "controller.service.annotations.loadbalancer\\.openstack\\.org/timeout-member-connect"
    value = "5000"
  }

  set {
    name  = "controller.service.annotations.loadbalancer\\.openstack\\.org/connection-limit"
    value = "2000"
  }

  set {
    name  = "controller.service.annotations.loadbalancer\\.openstack\\.org/keep-floatingip"
    value = "true"
  }

  set {
    name  = "controller.service.annotations.loadbalancer\\.openstack\\.org/floating-network-id"
    value = var.floating_network_id
  }

  set {
    name  = "controller.service.annotations.loadbalancer\\.openstack\\.org/health-check-delay"
    value = "10"
  }

  set {
    name  = "controller.service.annotations.loadbalancer\\.openstack\\.org/health-check-timeout"
    value = "5"
  }

  set {
    name  = "controller.service.annotations.loadbalancer\\.openstack\\.org/health-check-max-retries"
    value = "3"
  }
}

# Namespace for blue-green deployments
resource "kubernetes_namespace" "blue_green" {
  metadata {
    name = "blue-green"
    labels = {
      "app.kubernetes.io/managed-by" = "terraform"
      "environment" = var.environment
      "cloud-provider" = "openstack"
    }
  }
}

# Secret for OpenStack credentials used by Helm chart
resource "kubernetes_secret" "openstack_credentials" {
  metadata {
    name      = "openstack-credentials"
    namespace = kubernetes_namespace.blue_green.metadata[0].name
  }

  data = {
    OS_USERNAME            = var.os_username
    OS_PASSWORD            = var.os_password
    OS_AUTH_URL            = var.os_auth_url
    OS_TENANT_ID           = var.os_tenant_id
    OS_TENANT_NAME         = var.os_tenant_name
    OS_PROJECT_ID          = var.os_project_id
    OS_PROJECT_NAME        = var.os_project_name
    OS_USER_DOMAIN_NAME    = var.os_user_domain_name
    OS_PROJECT_DOMAIN_NAME = var.os_project_domain_name
    OS_REGION_NAME         = var.os_region_name
  }

  type = "Opaque"
}

# Secret for database credentials
resource "kubernetes_secret" "database_credentials" {
  metadata {
    name      = "database-credentials"
    namespace = kubernetes_namespace.blue_green.metadata[0].name
  }

  data = {
    DB_HOST     = var.database_host
    DB_PORT     = var.database_port
    DB_DATABASE = var.database_name
    DB_USERNAME = var.database_username
    DB_PASSWORD = var.database_password
    REDIS_HOST  = var.redis_host
    REDIS_PORT  = var.redis_port
    REDIS_PASSWORD = var.redis_password
  }

  type = "Opaque"
}

# ConfigMap for OpenStack-specific configuration
resource "kubernetes_config_map" "openstack_config" {
  metadata {
    name      = "openstack-config"
    namespace = kubernetes_namespace.blue_green.metadata[0].name
  }

  data = {
    CLOUD_PROVIDER         = "openstack"
    OS_REGION_NAME         = var.os_region_name
    OS_PROJECT_ID          = var.os_project_id
    OS_PROJECT_NAME        = var.os_project_name
    OS_USER_DOMAIN_NAME    = var.os_user_domain_name
    OS_PROJECT_DOMAIN_NAME = var.os_project_domain_name
    INGRESS_CONTROLLER     = "nginx"
    LOAD_BALANCER_TYPE     = "openstack-octavia"
    
    # Swift Object Storage configuration
    SWIFT_AUTH_URL    = var.os_auth_url
    SWIFT_USERNAME    = var.os_username
    SWIFT_TENANT_NAME = var.os_tenant_name
    SWIFT_CONTAINER   = openstack_objectstorage_container_v1.assets.name
    SWIFT_REGION      = var.os_region_name
    
    # Blue-Green deployment settings
    BLUE_GREEN_ENABLED  = "true"
    BLUE_GREEN_STRATEGY = "openstack-octavia"
    HEALTH_CHECK_PATH   = "/octane/health"
    
    # Performance settings
    OCTANE_WORKERS      = "4"
    OCTANE_TASK_WORKERS = "6"
    OCTANE_MAX_REQUESTS = "500"
    OCTANE_MEMORY_LIMIT = "512M"
    
    # Heat integration
    HEAT_STACK_NAME     = var.heat_stack_name
    HEAT_TEMPLATE_PATH  = "/deployment/openstack/heat/main.yaml"
  }
}

# Helm release for the Reverse Tender application (Blue environment)
resource "helm_release" "reverse_tender_blue" {
  count     = var.enable_blue_green_deployment ? 1 : 0
  name      = "reverse-tender-blue"
  chart     = "../../helm/reverse-tender"
  namespace = kubernetes_namespace.blue_green.metadata[0].name
  
  values = [
    file("../../helm/reverse-tender/values-openstack.yaml")
  ]

  set {
    name  = "blueGreen.enabled"
    value = "true"
  }

  set {
    name  = "blueGreen.activeEnvironment"
    value = "blue"
  }

  set {
    name  = "global.cloudProvider"
    value = "openstack"
  }

  set {
    name  = "postgresql.external.host"
    value = var.database_host
  }

  set {
    name  = "redis.external.host"
    value = var.redis_host
  }

  set {
    name  = "ingress.annotations.loadbalancer\\.openstack\\.org/floating-network-id"
    value = var.floating_network_id
  }

  set {
    name  = "storage.swift.authUrl"
    value = var.os_auth_url
  }

  set {
    name  = "storage.swift.username"
    value = var.os_username
  }

  set {
    name  = "storage.swift.tenantName"
    value = var.os_tenant_name
  }

  set {
    name  = "storage.swift.container"
    value = openstack_objectstorage_container_v1.assets.name
  }

  set {
    name  = "persistence.storageClass"
    value = "cinder-csi"
  }

  depends_on = [
    kubernetes_secret.openstack_credentials,
    kubernetes_secret.database_credentials,
    helm_release.nginx_ingress,
    openstack_objectstorage_container_v1.assets
  ]
}

# Helm release for the Reverse Tender application (Green environment)
resource "helm_release" "reverse_tender_green" {
  count     = var.enable_blue_green_deployment ? 1 : 0
  name      = "reverse-tender-green"
  chart     = "../../helm/reverse-tender"
  namespace = kubernetes_namespace.blue_green.metadata[0].name
  
  values = [
    file("../../helm/reverse-tender/values-openstack.yaml")
  ]

  set {
    name  = "blueGreen.enabled"
    value = "true"
  }

  set {
    name  = "blueGreen.activeEnvironment"
    value = "green"
  }

  set {
    name  = "global.cloudProvider"
    value = "openstack"
  }

  set {
    name  = "postgresql.external.host"
    value = var.database_host
  }

  set {
    name  = "redis.external.host"
    value = var.redis_host
  }

  set {
    name  = "ingress.annotations.loadbalancer\\.openstack\\.org/floating-network-id"
    value = var.floating_network_id
  }

  set {
    name  = "storage.swift.authUrl"
    value = var.os_auth_url
  }

  set {
    name  = "storage.swift.username"
    value = var.os_username
  }

  set {
    name  = "storage.swift.tenantName"
    value = var.os_tenant_name
  }

  set {
    name  = "storage.swift.container"
    value = openstack_objectstorage_container_v1.assets.name
  }

  set {
    name  = "persistence.storageClass"
    value = "cinder-csi"
  }

  # Initially deploy with 0 replicas (inactive)
  set {
    name  = "replicaCount"
    value = "0"
  }

  depends_on = [
    kubernetes_secret.openstack_credentials,
    kubernetes_secret.database_credentials,
    helm_release.nginx_ingress,
    openstack_objectstorage_container_v1.assets
  ]
}

# Swift Object Storage container for assets
resource "openstack_objectstorage_container_v1" "assets" {
  name   = "${var.project_name}-assets-${var.environment}"
  region = var.os_region_name

  metadata = {
    "X-Container-Read"  = ".r:*"
    "X-Container-Write" = "${var.os_tenant_name}:*"
  }

  versioning {
    type = "versions"
    location = "${var.project_name}-assets-versions-${var.environment}"
  }
}

# Swift Object Storage container for backups
resource "openstack_objectstorage_container_v1" "backups" {
  name   = "${var.project_name}-backups-${var.environment}"
  region = var.os_region_name

  metadata = {
    "X-Container-Read"  = "${var.os_tenant_name}:*"
    "X-Container-Write" = "${var.os_tenant_name}:*"
  }

  lifecycle_rule {
    enabled = true
    name    = "backup_lifecycle"
    
    expiration {
      days = var.backup_retention_days
    }
  }
}

# Cinder Block Storage volumes for persistent data
resource "openstack_blockstorage_volume_v3" "app_data" {
  count       = var.enable_persistent_storage ? 1 : 0
  name        = "${var.project_name}-app-data-${var.environment}"
  size        = var.persistent_storage_size
  region      = var.os_region_name
  volume_type = var.cinder_volume_type

  metadata = {
    environment   = var.environment
    project       = var.project_name
    managed_by    = "terraform"
  }
}

# Heat Stack for infrastructure orchestration
resource "openstack_orchestration_stack_v1" "main" {
  count           = var.enable_heat_stack ? 1 : 0
  name            = var.heat_stack_name
  template_opts   = {
    Bin = file("../heat/main.yaml")
  }
  
  parameters = {
    flavor           = var.heat_flavor
    image            = var.heat_image
    key_name         = var.heat_key_name
    network          = var.heat_network
    security_group   = var.heat_security_group
    environment      = var.environment
    project_name     = var.project_name
  }

  tags = [
    "environment:${var.environment}",
    "project:${var.project_name}",
    "managed-by:terraform"
  ]
}

# Output values for integration with CI/CD pipeline
output "openstack_integration" {
  description = "OpenStack integration details for Helm deployment"
  value = {
    region           = var.os_region_name
    project_id       = var.os_project_id
    project_name     = var.os_project_name
    
    # Database connection details
    database_host = var.database_host
    database_port = var.database_port
    database_name = var.database_name
    
    # Redis connection details
    redis_host = var.redis_host
    redis_port = var.redis_port
    
    # Load Balancer IP (from NGINX ingress)
    load_balancer_ip = var.enable_blue_green_deployment ? helm_release.nginx_ingress.status[0].load_balancer[0].ingress[0].ip : null
    
    # Swift Object Storage configuration
    swift_auth_url     = var.os_auth_url
    assets_container   = openstack_objectstorage_container_v1.assets.name
    backups_container  = openstack_objectstorage_container_v1.backups.name
    
    # Cinder Block Storage
    persistent_volume_id = var.enable_persistent_storage ? openstack_blockstorage_volume_v3.app_data[0].id : null
    
    # Helm deployment status
    blue_deployment_status  = var.enable_blue_green_deployment ? helm_release.reverse_tender_blue[0].status : null
    green_deployment_status = var.enable_blue_green_deployment ? helm_release.reverse_tender_green[0].status : null
    
    # Kubernetes namespace
    blue_green_namespace = kubernetes_namespace.blue_green.metadata[0].name
    
    # Heat Stack information
    heat_stack_id     = var.enable_heat_stack ? openstack_orchestration_stack_v1.main[0].id : null
    heat_stack_status = var.enable_heat_stack ? openstack_orchestration_stack_v1.main[0].status : null
    
    # Floating network
    floating_network_id = var.floating_network_id
  }
}
