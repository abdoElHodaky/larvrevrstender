# DigitalOcean Terraform Integration with Helm Multi-Cloud Deployment
# This file provides Terraform resources that integrate with the Helm chart deployment

# Data source for DOKS cluster credentials
data "digitalocean_kubernetes_cluster" "main" {
  name = digitalocean_kubernetes_cluster.main.name
}

# Kubernetes provider configuration for Helm deployment
provider "kubernetes" {
  host  = data.digitalocean_kubernetes_cluster.main.endpoint
  token = data.digitalocean_kubernetes_cluster.main.kube_config[0].token
  cluster_ca_certificate = base64decode(
    data.digitalocean_kubernetes_cluster.main.kube_config[0].cluster_ca_certificate
  )
}

# Helm provider configuration
provider "helm" {
  kubernetes {
    host  = data.digitalocean_kubernetes_cluster.main.endpoint
    token = data.digitalocean_kubernetes_cluster.main.kube_config[0].token
    cluster_ca_certificate = base64decode(
      data.digitalocean_kubernetes_cluster.main.kube_config[0].cluster_ca_certificate
    )
  }
}

# NGINX Ingress Controller installation for DigitalOcean
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
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/do-loadbalancer-protocol"
    value = "http"
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/do-loadbalancer-algorithm"
    value = "round_robin"
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/do-loadbalancer-size-slug"
    value = var.load_balancer_size
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/do-loadbalancer-hostname"
    value = var.domain_name
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/do-loadbalancer-redirect-http-to-https"
    value = "true"
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/do-loadbalancer-enable-proxy-protocol"
    value = "true"
  }

  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/do-loadbalancer-healthcheck-path"
    value = "/healthz"
  }

  depends_on = [digitalocean_kubernetes_cluster.main]
}

# Namespace for blue-green deployments
resource "kubernetes_namespace" "blue_green" {
  metadata {
    name = "blue-green"
    labels = {
      "app.kubernetes.io/managed-by" = "terraform"
      "environment" = local.environment
      "cloud-provider" = "digitalocean"
    }
  }

  depends_on = [digitalocean_kubernetes_cluster.main]
}

# Secret for DigitalOcean credentials used by Helm chart
resource "kubernetes_secret" "digitalocean_credentials" {
  metadata {
    name      = "digitalocean-credentials"
    namespace = kubernetes_namespace.blue_green.metadata[0].name
  }

  data = {
    DO_TOKEN        = var.digitalocean_token
    DO_SPACES_KEY   = var.spaces_access_key
    DO_SPACES_SECRET = var.spaces_secret_key
    DO_PROJECT_ID   = digitalocean_project.main.id
    DO_REGION       = var.region
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
    DB_HOST     = digitalocean_database_cluster.postgres.host
    DB_PORT     = digitalocean_database_cluster.postgres.port
    DB_DATABASE = digitalocean_database_cluster.postgres.database
    DB_USERNAME = digitalocean_database_cluster.postgres.user
    DB_PASSWORD = digitalocean_database_cluster.postgres.password
    REDIS_HOST  = digitalocean_database_cluster.redis.host
    REDIS_PORT  = digitalocean_database_cluster.redis.port
    REDIS_PASSWORD = digitalocean_database_cluster.redis.password
  }

  type = "Opaque"
}

# ConfigMap for DigitalOcean-specific configuration
resource "kubernetes_config_map" "digitalocean_config" {
  metadata {
    name      = "digitalocean-config"
    namespace = kubernetes_namespace.blue_green.metadata[0].name
  }

  data = {
    CLOUD_PROVIDER = "digitalocean"
    DO_REGION      = var.region
    DO_PROJECT_ID  = digitalocean_project.main.id
    INGRESS_CONTROLLER = "nginx"
    LOAD_BALANCER_TYPE = "digitalocean-load-balancer"
    CONTAINER_REGISTRY = digitalocean_container_registry.main.server_url
    
    # Spaces configuration
    SPACES_ENDPOINT = "${var.region}.digitaloceanspaces.com"
    SPACES_BUCKET   = digitalocean_spaces_bucket.assets.name
    SPACES_REGION   = var.region
    
    # Blue-Green deployment settings
    BLUE_GREEN_ENABLED = "true"
    BLUE_GREEN_STRATEGY = "digitalocean-load-balancer"
    HEALTH_CHECK_PATH = "/octane/health"
    
    # Performance settings
    OCTANE_WORKERS = "4"
    OCTANE_TASK_WORKERS = "6"
    OCTANE_MAX_REQUESTS = "500"
    OCTANE_MEMORY_LIMIT = "512M"
  }
}

# Container Registry Docker Config Secret
resource "kubernetes_secret" "registry_credentials" {
  metadata {
    name      = "regcred-digitalocean"
    namespace = kubernetes_namespace.blue_green.metadata[0].name
  }

  data = {
    ".dockerconfigjson" = jsonencode({
      auths = {
        "${digitalocean_container_registry.main.server_url}" = {
          username = var.digitalocean_token
          password = var.digitalocean_token
          auth     = base64encode("${var.digitalocean_token}:${var.digitalocean_token}")
        }
      }
    })
  }

  type = "kubernetes.io/dockerconfigjson"
}

# Helm release for the Reverse Tender application (Blue environment)
resource "helm_release" "reverse_tender_blue" {
  count     = var.enable_blue_green_deployment ? 1 : 0
  name      = "reverse-tender-blue"
  chart     = "../../helm/reverse-tender"
  namespace = kubernetes_namespace.blue_green.metadata[0].name
  
  values = [
    file("../../helm/reverse-tender/values-digitalocean.yaml")
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
    value = "digitalocean"
  }

  set {
    name  = "global.imageRegistry"
    value = digitalocean_container_registry.main.server_url
  }

  set {
    name  = "postgresql.external.host"
    value = digitalocean_database_cluster.postgres.host
  }

  set {
    name  = "redis.external.host"
    value = digitalocean_database_cluster.redis.host
  }

  set {
    name  = "ingress.annotations.service\\.beta\\.kubernetes\\.io/do-loadbalancer-hostname"
    value = "blue.${var.domain_name}"
  }

  set {
    name  = "storage.spaces.endpoint"
    value = "${var.region}.digitaloceanspaces.com"
  }

  set {
    name  = "storage.spaces.bucket"
    value = digitalocean_spaces_bucket.assets.name
  }

  depends_on = [
    digitalocean_kubernetes_cluster.main,
    digitalocean_database_cluster.postgres,
    digitalocean_database_cluster.redis,
    kubernetes_secret.digitalocean_credentials,
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
    file("../../helm/reverse-tender/values-digitalocean.yaml")
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
    value = "digitalocean"
  }

  set {
    name  = "global.imageRegistry"
    value = digitalocean_container_registry.main.server_url
  }

  set {
    name  = "postgresql.external.host"
    value = digitalocean_database_cluster.postgres.host
  }

  set {
    name  = "redis.external.host"
    value = digitalocean_database_cluster.redis.host
  }

  set {
    name  = "ingress.annotations.service\\.beta\\.kubernetes\\.io/do-loadbalancer-hostname"
    value = "green.${var.domain_name}"
  }

  set {
    name  = "storage.spaces.endpoint"
    value = "${var.region}.digitaloceanspaces.com"
  }

  set {
    name  = "storage.spaces.bucket"
    value = digitalocean_spaces_bucket.assets.name
  }

  # Initially deploy with 0 replicas (inactive)
  set {
    name  = "replicaCount"
    value = "0"
  }

  depends_on = [
    digitalocean_kubernetes_cluster.main,
    digitalocean_database_cluster.postgres,
    digitalocean_database_cluster.redis,
    kubernetes_secret.digitalocean_credentials,
    kubernetes_secret.database_credentials,
    helm_release.nginx_ingress
  ]
}

# DigitalOcean Spaces bucket for assets
resource "digitalocean_spaces_bucket" "assets" {
  name   = "${local.project_name}-assets-${local.environment}"
  region = var.region
  
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

# DigitalOcean Spaces bucket for backups
resource "digitalocean_spaces_bucket" "backups" {
  name   = "${local.project_name}-backups-${local.environment}"
  region = var.region
  
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

# DigitalOcean Container Registry
resource "digitalocean_container_registry" "main" {
  name                   = "${local.project_name}-registry"
  subscription_tier_slug = var.registry_tier
  region                 = var.region
}

# Output values for integration with CI/CD pipeline
output "digitalocean_integration" {
  description = "DigitalOcean integration details for Helm deployment"
  value = {
    cluster_name = digitalocean_kubernetes_cluster.main.name
    cluster_id   = digitalocean_kubernetes_cluster.main.id
    region       = var.region
    
    # Database connection details
    database_host = digitalocean_database_cluster.postgres.host
    database_port = digitalocean_database_cluster.postgres.port
    database_name = digitalocean_database_cluster.postgres.database
    
    # Redis connection details
    redis_host = digitalocean_database_cluster.redis.host
    redis_port = digitalocean_database_cluster.redis.port
    
    # Container registry
    container_registry = digitalocean_container_registry.main.server_url
    
    # Load Balancer IP (from NGINX ingress)
    load_balancer_ip = var.enable_blue_green_deployment ? helm_release.nginx_ingress.status[0].load_balancer[0].ingress[0].ip : null
    
    # Spaces configuration
    spaces_endpoint = "${var.region}.digitaloceanspaces.com"
    assets_bucket   = digitalocean_spaces_bucket.assets.name
    backups_bucket  = digitalocean_spaces_bucket.backups.name
    
    # Helm deployment status
    blue_deployment_status  = var.enable_blue_green_deployment ? helm_release.reverse_tender_blue[0].status : null
    green_deployment_status = var.enable_blue_green_deployment ? helm_release.reverse_tender_green[0].status : null
    
    # Kubernetes namespace
    blue_green_namespace = kubernetes_namespace.blue_green.metadata[0].name
    
    # Project ID
    project_id = digitalocean_project.main.id
  }
}
