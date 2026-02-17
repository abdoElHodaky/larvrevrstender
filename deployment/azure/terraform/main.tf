# Azure Terraform Configuration for Reverse Tender Platform
# This file orchestrates the deployment of microservices on Azure

terraform {
  required_version = ">= 1.6"
  required_providers {
    azurerm = {
      source  = "hashicorp/azurerm"
      version = "~> 3.80"
    }
    azuread = {
      source  = "hashicorp/azuread"
      version = "~> 2.45"
    }
    kubernetes = {
      source  = "hashicorp/kubernetes"
      version = "~> 2.24"
    }
    helm = {
      source  = "hashicorp/helm"
      version = "~> 2.12"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.6"
    }
  }
}

# Configure the Azure Provider
provider "azurerm" {
  features {
    resource_group {
      prevent_deletion_if_contains_resources = false
    }
    key_vault {
      purge_soft_delete_on_destroy    = true
      recover_soft_deleted_key_vaults = true
    }
  }
}

# Local variables for common configuration
locals {
  project_name = "reverse-tender"
  environment  = var.environment
  location     = var.location
  
  common_tags = {
    Project     = local.project_name
    Environment = local.environment
    ManagedBy   = "terraform"
    CreatedAt   = timestamp()
  }
  
  # Service configuration matching existing setup
  services = {
    api_gateway      = { port = 8000, replicas = var.api_gateway_replicas }
    auth_service     = { port = 8001, replicas = var.auth_service_replicas }
    bidding_service  = { port = 8002, replicas = var.bidding_service_replicas }
    user_service     = { port = 8003, replicas = var.user_service_replicas }
    order_service    = { port = 8004, replicas = var.order_service_replicas }
    notification_service = { port = 8005, replicas = var.notification_service_replicas }
    payment_service  = { port = 8006, replicas = var.payment_service_replicas }
    analytics_service = { port = 8007, replicas = var.analytics_service_replicas }
    vin_ocr_service  = { port = 8008, replicas = var.vin_ocr_service_replicas }
    auction_service  = { port = 8009, replicas = var.auction_service_replicas }
    gateway_service  = { port = 8010, replicas = var.gateway_service_replicas }
  }
}

# Resource Group
resource "azurerm_resource_group" "main" {
  name     = "rg-${local.project_name}-${local.environment}"
  location = local.location
  tags     = local.common_tags
}

# Networking Module
module "networking" {
  source = "./modules/networking"
  
  resource_group_name = azurerm_resource_group.main.name
  location           = azurerm_resource_group.main.location
  environment        = local.environment
  project_name       = local.project_name
  tags              = local.common_tags
  
  vnet_address_space     = var.vnet_address_space
  subnet_address_prefixes = var.subnet_address_prefixes
}

# Container Registry
module "container_registry" {
  source = "./modules/container_registry"
  
  resource_group_name = azurerm_resource_group.main.name
  location           = azurerm_resource_group.main.location
  environment        = local.environment
  project_name       = local.project_name
  tags              = local.common_tags
  
  sku = var.acr_sku
}

# Database Module
module "database" {
  source = "./modules/database"
  
  resource_group_name = azurerm_resource_group.main.name
  location           = azurerm_resource_group.main.location
  environment        = local.environment
  project_name       = local.project_name
  tags              = local.common_tags
  
  subnet_id                    = module.networking.database_subnet_id
  postgresql_sku_name         = var.postgresql_sku_name
  postgresql_storage_mb       = var.postgresql_storage_mb
  postgresql_version          = var.postgresql_version
  redis_sku_name              = var.redis_sku_name
  redis_capacity              = var.redis_capacity
  
  # Database names for each service
  databases = [
    "auth_service",
    "user_service", 
    "order_service",
    "payment_service",
    "auction_service",
    "bidding_service",
    "notification_service",
    "analytics_service",
    "vin_ocr_service"
  ]
}

# Key Vault for secrets management
module "key_vault" {
  source = "./modules/key_vault"
  
  resource_group_name = azurerm_resource_group.main.name
  location           = azurerm_resource_group.main.location
  environment        = local.environment
  project_name       = local.project_name
  tags              = local.common_tags
  
  subnet_id = module.networking.private_subnet_id
}

# Conditional AKS deployment
module "aks" {
  count  = var.deployment_strategy == "aks" ? 1 : 0
  source = "./modules/aks"
  
  resource_group_name = azurerm_resource_group.main.name
  location           = azurerm_resource_group.main.location
  environment        = local.environment
  project_name       = local.project_name
  tags              = local.common_tags
  
  subnet_id                = module.networking.aks_subnet_id
  node_count              = var.aks_node_count
  node_vm_size            = var.aks_node_vm_size
  kubernetes_version      = var.kubernetes_version
  container_registry_id   = module.container_registry.registry_id
  
  services = local.services
}

# Conditional Container Apps deployment
module "container_apps" {
  count  = var.deployment_strategy == "container_apps" ? 1 : 0
  source = "./modules/container_apps"
  
  resource_group_name = azurerm_resource_group.main.name
  location           = azurerm_resource_group.main.location
  environment        = local.environment
  project_name       = local.project_name
  tags              = local.common_tags
  
  subnet_id                = module.networking.container_apps_subnet_id
  container_registry_id    = module.container_registry.registry_id
  postgresql_connection_string = module.database.postgresql_connection_string
  redis_connection_string     = module.database.redis_connection_string
  
  services = local.services
}

# Monitoring Module
module "monitoring" {
  source = "./modules/monitoring"
  
  resource_group_name = azurerm_resource_group.main.name
  location           = azurerm_resource_group.main.location
  environment        = local.environment
  project_name       = local.project_name
  tags              = local.common_tags
  
  # Connect to AKS if deployed
  aks_cluster_id = var.deployment_strategy == "aks" ? module.aks[0].cluster_id : null
  
  # Connect to Container Apps if deployed
  container_app_environment_id = var.deployment_strategy == "container_apps" ? module.container_apps[0].environment_id : null
}

# Storage Account for logs and backups
resource "azurerm_storage_account" "main" {
  name                     = "st${replace(local.project_name, "-", "")}${local.environment}"
  resource_group_name      = azurerm_resource_group.main.name
  location                = azurerm_resource_group.main.location
  account_tier            = "Standard"
  account_replication_type = var.storage_replication_type
  
  blob_properties {
    versioning_enabled = true
    delete_retention_policy {
      days = 30
    }
  }
  
  tags = local.common_tags
}

# Application Gateway for load balancing (optional)
module "application_gateway" {
  count  = var.enable_application_gateway ? 1 : 0
  source = "./modules/application_gateway"
  
  resource_group_name = azurerm_resource_group.main.name
  location           = azurerm_resource_group.main.location
  environment        = local.environment
  project_name       = local.project_name
  tags              = local.common_tags
  
  subnet_id = module.networking.application_gateway_subnet_id
  services  = local.services
  
  # Backend configuration depends on deployment strategy
  backend_address_pool = var.deployment_strategy == "aks" ? 
    module.aks[0].ingress_ip : 
    module.container_apps[0].default_domain
}
