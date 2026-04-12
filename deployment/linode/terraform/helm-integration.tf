# Linode Terraform Integration with Helm Multi-Cloud Deployment
# This file provides Terraform resources that integrate with the Helm chart deployment

# Data source for LKE cluster credentials
data "linode_lke_cluster" "main" {
  id = linode_lke_cluster.main.id
}

# Kubernetes provider configuration for Helm deployment
provider "kubernetes" {
  host                   = data.linode_lke_cluster.main.api_endpoints[0]
  token                  = data.linode_lke_cluster.main.kubeconfig
  cluster_ca_certificate = base64decode(data.linode_lke_cluster.main.kubeconfig)
}

# Helm provider configuration
provider "helm" {
  kubernetes {
    host                   = data.linode_lke_cluster.main.api_endpoints[0]
    token                  = data.linode_lke_cluster.main.kubeconfig
    cluster_ca_certificate = base64decode(data.linode_lke_cluster.main.kubeconfig)
  }
}

# NGINX Ingress Controller installation for Linode
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
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/linode-loadbalancer-protocol"
    value = "http"
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/linode-loadbalancer-check-type"
    value = "http"
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/linode-loadbalancer-check-path"
    value = "/healthz"
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/linode-loadbalancer-algorithm"
    value = "roundrobin"
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/linode-loadbalancer-session-stickiness"
    value = "table"
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/linode-loadbalancer-session-persistence"
    value = "300"
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/linode-loadbalancer-proxy-protocol"
    value = "v2"
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/linode-loadbalancer-throttle"
    value = "20"
  }

  depends_on = [linode_lke_cluster.main]
}

# Namespace for blue-green deployments
resource "kubernetes_namespace" "blue_green" {
  metadata {
    name = "blue-green"
    labels = {
      "app.kubernetes.io/managed-by" = "terraform"
      "environment" = var.environment
      "cloud-provider" = "linode"
    }
  }

  depends_on = [linode_lke_cluster.main]
}

# Secret for Linode credentials used by Helm chart
resource "kubernetes_secret" "linode_credentials" {
  metadata {
    name      = "linode-credentials"
    namespace = kubernetes_namespace.blue_green.metadata[0].name
  }

  data = {
    LINODE_TOKEN     = var.linode_token
    LINODE_ROOT_PASS = var.linode_root_password
    LINODE_REGION    = var.region
    LINODE_OBJECT_STORAGE_KEY    = var.object_storage_access_key
    LINODE_OBJECT_STORAGE_SECRET = var.object_storage_secret_key
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
    DB_HOST     = linode_database_mysql.main.host
    DB_PORT     = "3306"
    DB_DATABASE = linode_database_mysql.main.database
    DB_USERNAME = linode_database_mysql.main.username
    DB_PASSWORD = linode_database_mysql.main.root_password
    REDIS_HOST  = var.redis_host
    REDIS_PORT  = var.redis_port
    REDIS_PASSWORD = var.redis_password
  }

  type = "Opaque"
}

# ConfigMap for Linode-specific configuration
resource "kubernetes_config_map" "linode_config" {
  metadata {
    name      = "linode-config"
    namespace = kubernetes_namespace.blue_green.metadata[0].name
  }

  data = {
    CLOUD_PROVIDER = "linode"
    LINODE_REGION  = var.region
    LINODE_TOKEN   = var.linode_token
    INGRESS_CONTROLLER = "nginx"
    LOAD_BALANCER_TYPE = "linode-nodebalancer"
    
    # Object Storage configuration
    OBJECT_STORAGE_ENDPOINT = "${var.region}.linodeobjects.com"
    OBJECT_STORAGE_BUCKET   = linode_object_storage_bucket.assets.label
    OBJECT_STORAGE_REGION   = var.region
    
    # Blue-Green deployment settings
    BLUE_GREEN_ENABLED = "true"
    BLUE_GREEN_STRATEGY = "linode-nodebalancer"
    HEALTH_CHECK_PATH = "/octane/health"
    
    # Performance settings
    OCTANE_WORKERS = "4"
    OCTANE_TASK_WORKERS = "6"
    OCTANE_MAX_REQUESTS = "500"
    OCTANE_MEMORY_LIMIT = "512M"
  }
}

# Helm release for the Reverse Tender application (Blue environment)
resource "helm_release" "reverse_tender_blue" {
  count     = var.enable_blue_green_deployment ? 1 : 0
  name      = "reverse-tender-blue"
  chart     = "../../helm/reverse-tender"
  namespace = kubernetes_namespace.blue_green.metadata[0].name
  
  values = [
    file("../../helm/reverse-tender/values-linode.yaml")
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
    value = "linode"
  }

  set {
    name  = "postgresql.external.host"
    value = linode_database_mysql.main.host
  }

  set {
    name  = "redis.external.host"
    value = var.redis_host
  }

  set {
    name  = "ingress.annotations.service\\.beta\\.kubernetes\\.io/linode-loadbalancer-hostname-only-ingress"
    value = "true"
  }

  set {
    name  = "storage.objectStorage.endpoint"
    value = "${var.region}.linodeobjects.com"
  }

  set {
    name  = "storage.objectStorage.bucket"
    value = linode_object_storage_bucket.assets.label
  }

  set {
    name  = "persistence.storageClass"
    value = "linode-block-storage-retain"
  }

  depends_on = [
    linode_lke_cluster.main,
    linode_database_mysql.main,
    kubernetes_secret.linode_credentials,
    kubernetes_secret.database_credentials,
    helm_release.nginx_ingress
  ]
}

# Helm release for the Reverse Tender application (Green environment)
resource "helm_release" "reverse_tender_green" {
  count     = var.enable_blue_green_deployment ? 1 : 0
  name      = "reverse-tender-green"
  chart     = "../../helm/reverse-tender"
  namespace = kubernetes_namespace.blue_green.metadata[0].name
  
  values = [
    file("../../helm/reverse-tender/values-linode.yaml")
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
    value = "linode"
  }

  set {
    name  = "postgresql.external.host"
    value = linode_database_mysql.main.host
  }

  set {
    name  = "redis.external.host"
    value = var.redis_host
  }

  set {
    name  = "ingress.annotations.service\\.beta\\.kubernetes\\.io/linode-loadbalancer-hostname-only-ingress"
    value = "true"
  }

  set {
    name  = "storage.objectStorage.endpoint"
    value = "${var.region}.linodeobjects.com"
  }

  set {
    name  = "storage.objectStorage.bucket"
    value = linode_object_storage_bucket.assets.label
  }

  set {
    name  = "persistence.storageClass"
    value = "linode-block-storage-retain"
  }

  # Initially deploy with 0 replicas (inactive)
  set {
    name  = "replicaCount"
    value = "0"
  }

  depends_on = [
    linode_lke_cluster.main,
    linode_database_mysql.main,
    kubernetes_secret.linode_credentials,
    kubernetes_secret.database_credentials,
    helm_release.nginx_ingress
  ]
}

# Linode Object Storage bucket for assets
resource "linode_object_storage_bucket" "assets" {
  cluster = var.region
  label   = "${var.project_name}-assets-${var.environment}"
  
  cors_rule {
    allowed_headers = ["*"]
    allowed_methods = ["GET", "PUT", "POST", "DELETE", "HEAD"]
    allowed_origins = ["*"]
    max_age_seconds = 3000
  }

  versioning {
    enabled = true
  }
}

# Linode Object Storage bucket for backups
resource "linode_object_storage_bucket" "backups" {
  cluster = var.region
  label   = "${var.project_name}-backups-${var.environment}"
  
  lifecycle_rule {
    id      = "backup_lifecycle"
    enabled = true
    
    expiration {
      days = var.backup_retention_days
    }
    
    noncurrent_version_expiration {
      days = 7
    }
  }
}

# Linode Block Storage volumes for persistent data
resource "linode_volume" "app_data" {
  count  = var.enable_persistent_storage ? 1 : 0
  label  = "${var.project_name}-app-data-${var.environment}"
  size   = var.persistent_storage_size
  region = var.region
  
  tags = [
    "environment:${var.environment}",
    "project:${var.project_name}",
    "managed-by:terraform"
  ]
}

# Backup schedule for database
resource "linode_database_backup" "main" {
  count       = var.enable_database_backups ? 1 : 0
  database_id = linode_database_mysql.main.id
  label       = "${var.project_name}-db-backup-${var.environment}"
  
  day_of_week   = "sunday"
  hour_of_day   = 2
  week_of_month = null
}

# Output values for integration with CI/CD pipeline
output "linode_integration" {
  description = "Linode integration details for Helm deployment"
  value = {
    cluster_name = linode_lke_cluster.main.label
    cluster_id   = linode_lke_cluster.main.id
    region       = var.region
    
    # Database connection details
    database_host = linode_database_mysql.main.host
    database_port = "3306"
    database_name = linode_database_mysql.main.database
    
    # Redis connection details (external)
    redis_host = var.redis_host
    redis_port = var.redis_port
    
    # Load Balancer IP (from NGINX ingress)
    load_balancer_ip = var.enable_blue_green_deployment ? helm_release.nginx_ingress.status[0].load_balancer[0].ingress[0].ip : null
    
    # Object Storage configuration
    object_storage_endpoint = "${var.region}.linodeobjects.com"
    assets_bucket          = linode_object_storage_bucket.assets.label
    backups_bucket         = linode_object_storage_bucket.backups.label
    
    # Block Storage
    persistent_volume_id = var.enable_persistent_storage ? linode_volume.app_data[0].id : null
    
    # Helm deployment status
    blue_deployment_status  = var.enable_blue_green_deployment ? helm_release.reverse_tender_blue[0].status : null
    green_deployment_status = var.enable_blue_green_deployment ? helm_release.reverse_tender_green[0].status : null
    
    # Kubernetes namespace
    blue_green_namespace = kubernetes_namespace.blue_green.metadata[0].name
    
    # Backup configuration
    database_backup_enabled = var.enable_database_backups
    backup_schedule = var.enable_database_backups ? "Sunday 2:00 AM" : null
  }
}
