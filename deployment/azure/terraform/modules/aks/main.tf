# Azure Kubernetes Service (AKS) Module for Reverse Tender Platform

# Data source for current client configuration
data "azurerm_client_config" "current" {}

# Random password for AKS admin user
resource "random_password" "aks_admin_password" {
  length  = 16
  special = true
}

# AKS Cluster
resource "azurerm_kubernetes_cluster" "main" {
  name                = "aks-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name
  dns_prefix          = "${var.project_name}-${var.environment}"
  kubernetes_version  = var.kubernetes_version

  # Default node pool
  default_node_pool {
    name                = "default"
    node_count          = var.node_count
    vm_size            = var.node_vm_size
    vnet_subnet_id     = var.subnet_id
    enable_auto_scaling = true
    min_count          = 1
    max_count          = var.node_count * 2
    
    # Node pool configuration
    os_disk_size_gb = 30
    os_disk_type    = "Managed"
    
    # Enable system-assigned managed identity
    upgrade_settings {
      max_surge = "10%"
    }
    
    node_labels = {
      "nodepool-type" = "system"
      "environment"   = var.environment
      "nodepoolos"    = "linux"
    }
    
    tags = var.tags
  }

  # Identity configuration
  identity {
    type = "SystemAssigned"
  }

  # Network configuration
  network_profile {
    network_plugin    = "azure"
    network_policy    = "azure"
    dns_service_ip    = "10.2.0.10"
    service_cidr      = "10.2.0.0/24"
    load_balancer_sku = "standard"
  }

  # RBAC configuration
  role_based_access_control_enabled = true

  azure_active_directory_role_based_access_control {
    managed                = true
    admin_group_object_ids = []
    azure_rbac_enabled     = true
  }

  # Add-ons
  addon_profile {
    # Enable Azure Policy
    azure_policy {
      enabled = true
    }
    
    # Enable HTTP Application Routing (for development)
    http_application_routing {
      enabled = var.environment == "dev"
    }
    
    # Enable monitoring
    oms_agent {
      enabled                    = true
      log_analytics_workspace_id = azurerm_log_analytics_workspace.aks.id
    }
    
    # Enable Azure Key Vault Secrets Provider
    key_vault_secrets_provider {
      enabled = true
    }
  }

  # Auto-scaler profile
  auto_scaler_profile {
    balance_similar_node_groups      = false
    expander                        = "random"
    max_graceful_termination_sec    = "600"
    max_node_provisioning_time      = "15m"
    max_unready_nodes              = 3
    max_unready_percentage         = 45
    new_pod_scale_up_delay         = "10s"
    scale_down_delay_after_add     = "10m"
    scale_down_delay_after_delete  = "10s"
    scale_down_delay_after_failure = "3m"
    scan_interval                  = "10s"
    scale_down_threshold           = "0.5"
    scale_down_unneeded_time       = "10m"
    scale_down_unready_time        = "20m"
    scale_down_utilization_threshold = "0.5"
  }

  tags = var.tags
}

# Log Analytics Workspace for AKS monitoring
resource "azurerm_log_analytics_workspace" "aks" {
  name                = "log-aks-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name
  sku                = "PerGB2018"
  retention_in_days   = 30
  
  tags = var.tags
}

# Additional node pool for microservices workloads
resource "azurerm_kubernetes_cluster_node_pool" "microservices" {
  name                  = "microservices"
  kubernetes_cluster_id = azurerm_kubernetes_cluster.main.id
  vm_size              = var.node_vm_size
  node_count           = var.node_count
  vnet_subnet_id       = var.subnet_id
  
  enable_auto_scaling = true
  min_count          = 1
  max_count          = var.node_count * 3
  
  # Taints for microservices workloads
  node_taints = ["workload=microservices:NoSchedule"]
  
  node_labels = {
    "nodepool-type" = "microservices"
    "environment"   = var.environment
    "workload"      = "microservices"
  }
  
  tags = var.tags
}

# Role assignment for AKS to pull images from ACR
resource "azurerm_role_assignment" "aks_acr_pull" {
  principal_id                     = azurerm_kubernetes_cluster.main.kubelet_identity[0].object_id
  role_definition_name             = "AcrPull"
  scope                           = var.container_registry_id
  skip_service_principal_aad_check = true
}

# Kubernetes provider configuration
provider "kubernetes" {
  host                   = azurerm_kubernetes_cluster.main.kube_config.0.host
  client_certificate     = base64decode(azurerm_kubernetes_cluster.main.kube_config.0.client_certificate)
  client_key            = base64decode(azurerm_kubernetes_cluster.main.kube_config.0.client_key)
  cluster_ca_certificate = base64decode(azurerm_kubernetes_cluster.main.kube_config.0.cluster_ca_certificate)
}

# Helm provider configuration
provider "helm" {
  kubernetes {
    host                   = azurerm_kubernetes_cluster.main.kube_config.0.host
    client_certificate     = base64decode(azurerm_kubernetes_cluster.main.kube_config.0.client_certificate)
    client_key            = base64decode(azurerm_kubernetes_cluster.main.kube_config.0.client_key)
    cluster_ca_certificate = base64decode(azurerm_kubernetes_cluster.main.kube_config.0.cluster_ca_certificate)
  }
}

# Namespace for microservices
resource "kubernetes_namespace" "microservices" {
  metadata {
    name = "microservices"
    
    labels = {
      environment = var.environment
      project     = var.project_name
    }
  }
}

# NGINX Ingress Controller
resource "helm_release" "nginx_ingress" {
  name       = "nginx-ingress"
  repository = "https://kubernetes.github.io/ingress-nginx"
  chart      = "ingress-nginx"
  namespace  = "ingress-nginx"
  
  create_namespace = true
  
  set {
    name  = "controller.service.type"
    value = "LoadBalancer"
  }
  
  set {
    name  = "controller.service.annotations.service\\.beta\\.kubernetes\\.io/azure-load-balancer-health-probe-request-path"
    value = "/healthz"
  }
  
  set {
    name  = "controller.nodeSelector.nodepool-type"
    value = "system"
  }
  
  set {
    name  = "controller.tolerations[0].key"
    value = "CriticalAddonsOnly"
  }
  
  set {
    name  = "controller.tolerations[0].operator"
    value = "Exists"
  }
}

# Cert-Manager for SSL certificates
resource "helm_release" "cert_manager" {
  name       = "cert-manager"
  repository = "https://charts.jetstack.io"
  chart      = "cert-manager"
  namespace  = "cert-manager"
  version    = "v1.13.0"
  
  create_namespace = true
  
  set {
    name  = "installCRDs"
    value = "true"
  }
  
  set {
    name  = "nodeSelector.nodepool-type"
    value = "system"
  }
}

# Prometheus monitoring stack
resource "helm_release" "prometheus" {
  name       = "prometheus"
  repository = "https://prometheus-community.github.io/helm-charts"
  chart      = "kube-prometheus-stack"
  namespace  = "monitoring"
  
  create_namespace = true
  
  values = [
    yamlencode({
      prometheus = {
        prometheusSpec = {
          serviceMonitorSelectorNilUsesHelmValues = false
          podMonitorSelectorNilUsesHelmValues     = false
          retention = "30d"
          storageSpec = {
            volumeClaimTemplate = {
              spec = {
                storageClassName = "managed-csi"
                accessModes      = ["ReadWriteOnce"]
                resources = {
                  requests = {
                    storage = "50Gi"
                  }
                }
              }
            }
          }
        }
      }
      grafana = {
        adminPassword = "admin123"
        nodeSelector = {
          "nodepool-type" = "system"
        }
      }
      nodeExporter = {
        enabled = true
      }
      kubeStateMetrics = {
        enabled = true
      }
    })
  ]
}

# Create service accounts for microservices
resource "kubernetes_service_account" "microservices" {
  for_each = var.services
  
  metadata {
    name      = "${each.key}-sa"
    namespace = kubernetes_namespace.microservices.metadata[0].name
    
    annotations = {
      "azure.workload.identity/client-id" = azurerm_user_assigned_identity.microservices[each.key].client_id
    }
    
    labels = {
      "azure.workload.identity/use" = "true"
      service                       = each.key
      environment                   = var.environment
    }
  }
}

# User-assigned managed identities for workload identity
resource "azurerm_user_assigned_identity" "microservices" {
  for_each = var.services
  
  name                = "id-${each.key}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name
  
  tags = var.tags
}

# Federated identity credentials for workload identity
resource "azurerm_federated_identity_credential" "microservices" {
  for_each = var.services
  
  name                = "fic-${each.key}-${var.environment}"
  resource_group_name = var.resource_group_name
  audience            = ["api://AzureADTokenExchange"]
  issuer              = azurerm_kubernetes_cluster.main.oidc_issuer_url
  parent_id           = azurerm_user_assigned_identity.microservices[each.key].id
  subject             = "system:serviceaccount:${kubernetes_namespace.microservices.metadata[0].name}:${each.key}-sa"
}

# Network Security Group for AKS subnet
resource "azurerm_network_security_group" "aks" {
  name                = "nsg-aks-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name

  # Allow inbound HTTPS
  security_rule {
    name                       = "AllowHTTPS"
    priority                   = 1001
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "443"
    source_address_prefix      = "*"
    destination_address_prefix = "*"
  }

  # Allow inbound HTTP
  security_rule {
    name                       = "AllowHTTP"
    priority                   = 1002
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "80"
    source_address_prefix      = "*"
    destination_address_prefix = "*"
  }

  tags = var.tags
}

# Associate NSG with AKS subnet
resource "azurerm_subnet_network_security_group_association" "aks" {
  subnet_id                 = var.subnet_id
  network_security_group_id = azurerm_network_security_group.aks.id
}
