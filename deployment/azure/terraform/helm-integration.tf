# Azure Terraform Integration with Helm Multi-Cloud Deployment
# This file provides Terraform resources that integrate with the Helm chart deployment

# Data source for AKS cluster credentials
data "azurerm_kubernetes_cluster" "main" {
  count               = var.deployment_strategy == "aks" ? 1 : 0
  name                = module.aks[0].cluster_name
  resource_group_name = azurerm_resource_group.main.name
  depends_on          = [module.aks]
}

# Kubernetes provider configuration for Helm deployment
provider "kubernetes" {
  host                   = var.deployment_strategy == "aks" ? data.azurerm_kubernetes_cluster.main[0].kube_config.0.host : null
  client_certificate     = var.deployment_strategy == "aks" ? base64decode(data.azurerm_kubernetes_cluster.main[0].kube_config.0.client_certificate) : null
  client_key            = var.deployment_strategy == "aks" ? base64decode(data.azurerm_kubernetes_cluster.main[0].kube_config.0.client_key) : null
  cluster_ca_certificate = var.deployment_strategy == "aks" ? base64decode(data.azurerm_kubernetes_cluster.main[0].kube_config.0.cluster_ca_certificate) : null
}

# Helm provider configuration
provider "helm" {
  kubernetes {
    host                   = var.deployment_strategy == "aks" ? data.azurerm_kubernetes_cluster.main[0].kube_config.0.host : null
    client_certificate     = var.deployment_strategy == "aks" ? base64decode(data.azurerm_kubernetes_cluster.main[0].kube_config.0.client_certificate) : null
    client_key            = var.deployment_strategy == "aks" ? base64decode(data.azurerm_kubernetes_cluster.main[0].kube_config.0.client_key) : null
    cluster_ca_certificate = var.deployment_strategy == "aks" ? base64decode(data.azurerm_kubernetes_cluster.main[0].kube_config.0.cluster_ca_certificate) : null
  }
}

# Azure Application Gateway Ingress Controller (AGIC) installation
resource "helm_release" "agic" {
  count      = var.deployment_strategy == "aks" && var.enable_application_gateway ? 1 : 0
  name       = "ingress-azure"
  repository = "https://appgwingress.blob.core.windows.net/ingress-azure-helm-package/"
  chart      = "ingress-azure"
  namespace  = "default"
  version    = "1.7.5"

  set {
    name  = "appgw.name"
    value = module.application_gateway[0].name
  }

  set {
    name  = "appgw.resourceGroup"
    value = azurerm_resource_group.main.name
  }

  set {
    name  = "appgw.subscriptionId"
    value = data.azurerm_client_config.current.subscription_id
  }

  set {
    name  = "armAuth.type"
    value = "servicePrincipal"
  }

  set {
    name  = "armAuth.secretJSON"
    value = base64encode(jsonencode({
      clientId       = var.azure_client_id
      clientSecret   = var.azure_client_secret
      subscriptionId = data.azurerm_client_config.current.subscription_id
      tenantId       = data.azurerm_client_config.current.tenant_id
      activeDirectoryEndpointUrl = "https://login.microsoftonline.com"
      resourceManagerEndpointUrl = "https://management.azure.com/"
      activeDirectoryGraphResourceId = "https://graph.windows.net/"
      sqlManagementEndpointUrl = "https://management.core.windows.net:8443/"
      galleryEndpointUrl = "https://gallery.azure.com/"
      managementEndpointUrl = "https://management.core.windows.net/"
    }))
  }

  depends_on = [module.aks, module.application_gateway]
}

# Namespace for blue-green deployments
resource "kubernetes_namespace" "blue_green" {
  count = var.deployment_strategy == "aks" ? 1 : 0
  
  metadata {
    name = "blue-green"
    labels = {
      "app.kubernetes.io/managed-by" = "terraform"
      "environment" = local.environment
      "cloud-provider" = "azure"
    }
  }

  depends_on = [module.aks]
}

# Secret for Azure credentials used by Helm chart
resource "kubernetes_secret" "azure_credentials" {
  count = var.deployment_strategy == "aks" ? 1 : 0

  metadata {
    name      = "azure-credentials"
    namespace = kubernetes_namespace.blue_green[0].metadata[0].name
  }

  data = {
    AZURE_CLIENT_ID       = var.azure_client_id
    AZURE_CLIENT_SECRET   = var.azure_client_secret
    AZURE_TENANT_ID       = data.azurerm_client_config.current.tenant_id
    AZURE_SUBSCRIPTION_ID = data.azurerm_client_config.current.subscription_id
    AZURE_RESOURCE_GROUP  = azurerm_resource_group.main.name
    AZURE_REGION         = azurerm_resource_group.main.location
  }

  type = "Opaque"
}

# Secret for database credentials
resource "kubernetes_secret" "database_credentials" {
  count = var.deployment_strategy == "aks" ? 1 : 0

  metadata {
    name      = "database-credentials"
    namespace = kubernetes_namespace.blue_green[0].metadata[0].name
  }

  data = {
    DB_HOST     = module.database.server_fqdn
    DB_PORT     = "5432"
    DB_DATABASE = module.database.database_name
    DB_USERNAME = module.database.administrator_login
    DB_PASSWORD = module.database.administrator_password
    REDIS_HOST  = module.redis.hostname
    REDIS_PORT  = module.redis.ssl_port
    REDIS_PASSWORD = module.redis.primary_access_key
  }

  type = "Opaque"
}

# ConfigMap for Azure-specific configuration
resource "kubernetes_config_map" "azure_config" {
  count = var.deployment_strategy == "aks" ? 1 : 0

  metadata {
    name      = "azure-config"
    namespace = kubernetes_namespace.blue_green[0].metadata[0].name
  }

  data = {
    CLOUD_PROVIDER = "azure"
    AZURE_REGION   = azurerm_resource_group.main.location
    AZURE_RESOURCE_GROUP = azurerm_resource_group.main.name
    INGRESS_CONTROLLER = "azure-application-gateway"
    LOAD_BALANCER_TYPE = "azure-application-gateway"
    STORAGE_ACCOUNT_NAME = azurerm_storage_account.main.name
    CONTAINER_REGISTRY = module.container_registry.login_server
    
    # Application Gateway specific
    APPLICATION_GATEWAY_NAME = var.enable_application_gateway ? module.application_gateway[0].name : ""
    APPLICATION_GATEWAY_RESOURCE_GROUP = azurerm_resource_group.main.name
    
    # Blue-Green deployment settings
    BLUE_GREEN_ENABLED = "true"
    BLUE_GREEN_STRATEGY = "azure-application-gateway"
    HEALTH_CHECK_PATH = "/octane/health"
    
    # Performance settings
    OCTANE_WORKERS = "6"
    OCTANE_TASK_WORKERS = "8"
    OCTANE_MAX_REQUESTS = "1000"
    OCTANE_MEMORY_LIMIT = "1024M"
  }
}

# Helm release for the Reverse Tender application (Blue environment)
resource "helm_release" "reverse_tender_blue" {
  count      = var.deployment_strategy == "aks" && var.enable_blue_green_deployment ? 1 : 0
  name       = "reverse-tender-blue"
  chart      = "../../helm/reverse-tender"
  namespace  = kubernetes_namespace.blue_green[0].metadata[0].name
  
  values = [
    file("../../helm/reverse-tender/values-azure.yaml")
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
    value = "azure"
  }

  set {
    name  = "global.imageRegistry"
    value = module.container_registry.login_server
  }

  set {
    name  = "postgresql.external.host"
    value = module.database.server_fqdn
  }

  set {
    name  = "redis.external.host"
    value = module.redis.hostname
  }

  set {
    name  = "ingress.annotations.appgw\\.ingress\\.kubernetes\\.io/backend-hostname"
    value = "blue.${var.domain_name}"
  }

  depends_on = [
    module.aks,
    module.database,
    module.redis,
    kubernetes_secret.azure_credentials,
    kubernetes_secret.database_credentials,
    helm_release.agic
  ]
}

# Helm release for the Reverse Tender application (Green environment)
resource "helm_release" "reverse_tender_green" {
  count      = var.deployment_strategy == "aks" && var.enable_blue_green_deployment ? 1 : 0
  name       = "reverse-tender-green"
  chart      = "../../helm/reverse-tender"
  namespace  = kubernetes_namespace.blue_green[0].metadata[0].name
  
  values = [
    file("../../helm/reverse-tender/values-azure.yaml")
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
    value = "azure"
  }

  set {
    name  = "global.imageRegistry"
    value = module.container_registry.login_server
  }

  set {
    name  = "postgresql.external.host"
    value = module.database.server_fqdn
  }

  set {
    name  = "redis.external.host"
    value = module.redis.hostname
  }

  set {
    name  = "ingress.annotations.appgw\\.ingress\\.kubernetes\\.io/backend-hostname"
    value = "green.${var.domain_name}"
  }

  # Initially deploy with 0 replicas (inactive)
  set {
    name  = "replicaCount"
    value = "0"
  }

  depends_on = [
    module.aks,
    module.database,
    module.redis,
    kubernetes_secret.azure_credentials,
    kubernetes_secret.database_credentials,
    helm_release.agic
  ]
}

# Azure Monitor integration for Kubernetes
resource "azurerm_log_analytics_solution" "container_insights" {
  count                 = var.deployment_strategy == "aks" ? 1 : 0
  solution_name         = "ContainerInsights"
  location              = azurerm_resource_group.main.location
  resource_group_name   = azurerm_resource_group.main.name
  workspace_resource_id = module.log_analytics.workspace_id
  workspace_name        = module.log_analytics.workspace_name

  plan {
    publisher = "Microsoft"
    product   = "OMSGallery/ContainerInsights"
  }

  tags = local.common_tags
}

# Output values for integration with CI/CD pipeline
output "azure_integration" {
  description = "Azure integration details for Helm deployment"
  value = var.deployment_strategy == "aks" ? {
    cluster_name = module.aks[0].cluster_name
    resource_group = azurerm_resource_group.main.name
    subscription_id = data.azurerm_client_config.current.subscription_id
    tenant_id = data.azurerm_client_config.current.tenant_id
    
    # Database connection details
    database_host = module.database.server_fqdn
    database_name = module.database.database_name
    
    # Redis connection details
    redis_host = module.redis.hostname
    redis_port = module.redis.ssl_port
    
    # Container registry
    container_registry = module.container_registry.login_server
    
    # Application Gateway (if enabled)
    application_gateway_name = var.enable_application_gateway ? module.application_gateway[0].name : null
    application_gateway_ip = var.enable_application_gateway ? module.application_gateway[0].public_ip : null
    
    # Monitoring
    log_analytics_workspace_id = module.log_analytics.workspace_id
    
    # Helm deployment status
    blue_deployment_status = var.enable_blue_green_deployment ? helm_release.reverse_tender_blue[0].status : null
    green_deployment_status = var.enable_blue_green_deployment ? helm_release.reverse_tender_green[0].status : null
    
    # Kubernetes namespace
    blue_green_namespace = var.deployment_strategy == "aks" ? kubernetes_namespace.blue_green[0].metadata[0].name : null
  } : null
}
