# Azure Terraform Variables for Reverse Tender Platform

# General Configuration
variable "environment" {
  description = "Environment name (dev, staging, prod)"
  type        = string
  default     = "dev"
  
  validation {
    condition     = contains(["dev", "staging", "prod"], var.environment)
    error_message = "Environment must be one of: dev, staging, prod."
  }
}

variable "location" {
  description = "Azure region for resources"
  type        = string
  default     = "East US"
}

# Deployment Strategy
variable "deployment_strategy" {
  description = "Deployment strategy: aks or container_apps"
  type        = string
  default     = "aks"
  
  validation {
    condition     = contains(["aks", "container_apps"], var.deployment_strategy)
    error_message = "Deployment strategy must be either 'aks' or 'container_apps'."
  }
}

# Networking Configuration
variable "vnet_address_space" {
  description = "Address space for the virtual network"
  type        = list(string)
  default     = ["10.0.0.0/16"]
}

variable "subnet_address_prefixes" {
  description = "Address prefixes for subnets"
  type = object({
    aks_subnet                = list(string)
    container_apps_subnet     = list(string)
    database_subnet          = list(string)
    private_subnet           = list(string)
    application_gateway_subnet = list(string)
  })
  default = {
    aks_subnet                = ["10.0.1.0/24"]
    container_apps_subnet     = ["10.0.2.0/24"]
    database_subnet          = ["10.0.3.0/24"]
    private_subnet           = ["10.0.4.0/24"]
    application_gateway_subnet = ["10.0.5.0/24"]
  }
}

# Container Registry Configuration
variable "acr_sku" {
  description = "SKU for Azure Container Registry"
  type        = string
  default     = "Standard"
  
  validation {
    condition     = contains(["Basic", "Standard", "Premium"], var.acr_sku)
    error_message = "ACR SKU must be one of: Basic, Standard, Premium."
  }
}

# AKS Configuration
variable "aks_node_count" {
  description = "Number of nodes in the AKS cluster"
  type        = number
  default     = 3
  
  validation {
    condition     = var.aks_node_count >= 1 && var.aks_node_count <= 100
    error_message = "AKS node count must be between 1 and 100."
  }
}

variable "aks_node_vm_size" {
  description = "VM size for AKS nodes"
  type        = string
  default     = "Standard_D2s_v3"
}

variable "kubernetes_version" {
  description = "Kubernetes version for AKS"
  type        = string
  default     = "1.28"
}

# Database Configuration
variable "postgresql_sku_name" {
  description = "SKU name for PostgreSQL server"
  type        = string
  default     = "GP_Standard_D2s_v3"
}

variable "postgresql_storage_mb" {
  description = "Storage size in MB for PostgreSQL server"
  type        = number
  default     = 32768
  
  validation {
    condition     = var.postgresql_storage_mb >= 32768
    error_message = "PostgreSQL storage must be at least 32GB (32768 MB)."
  }
}

variable "postgresql_version" {
  description = "PostgreSQL version"
  type        = string
  default     = "14"
  
  validation {
    condition     = contains(["11", "12", "13", "14", "15"], var.postgresql_version)
    error_message = "PostgreSQL version must be one of: 11, 12, 13, 14, 15."
  }
}

variable "redis_sku_name" {
  description = "SKU name for Redis cache"
  type        = string
  default     = "Standard"
  
  validation {
    condition     = contains(["Basic", "Standard", "Premium"], var.redis_sku_name)
    error_message = "Redis SKU must be one of: Basic, Standard, Premium."
  }
}

variable "redis_capacity" {
  description = "Capacity for Redis cache"
  type        = number
  default     = 1
  
  validation {
    condition     = var.redis_capacity >= 0 && var.redis_capacity <= 6
    error_message = "Redis capacity must be between 0 and 6."
  }
}

# Storage Configuration
variable "storage_replication_type" {
  description = "Replication type for storage account"
  type        = string
  default     = "LRS"
  
  validation {
    condition     = contains(["LRS", "GRS", "RAGRS", "ZRS", "GZRS", "RAGZRS"], var.storage_replication_type)
    error_message = "Storage replication type must be one of: LRS, GRS, RAGRS, ZRS, GZRS, RAGZRS."
  }
}

# Application Gateway Configuration
variable "enable_application_gateway" {
  description = "Enable Application Gateway for load balancing"
  type        = bool
  default     = true
}

# Service Replica Configuration
variable "api_gateway_replicas" {
  description = "Number of replicas for API Gateway service"
  type        = number
  default     = 3
}

variable "auth_service_replicas" {
  description = "Number of replicas for Auth service"
  type        = number
  default     = 2
}

variable "bidding_service_replicas" {
  description = "Number of replicas for Bidding service"
  type        = number
  default     = 3
}

variable "user_service_replicas" {
  description = "Number of replicas for User service"
  type        = number
  default     = 2
}

variable "order_service_replicas" {
  description = "Number of replicas for Order service"
  type        = number
  default     = 2
}

variable "notification_service_replicas" {
  description = "Number of replicas for Notification service"
  type        = number
  default     = 2
}

variable "payment_service_replicas" {
  description = "Number of replicas for Payment service"
  type        = number
  default     = 3
}

variable "analytics_service_replicas" {
  description = "Number of replicas for Analytics service"
  type        = number
  default     = 2
}

variable "vin_ocr_service_replicas" {
  description = "Number of replicas for VIN OCR service"
  type        = number
  default     = 2
}

variable "auction_service_replicas" {
  description = "Number of replicas for Auction service"
  type        = number
  default     = 2
}

variable "gateway_service_replicas" {
  description = "Number of replicas for Gateway service"
  type        = number
  default     = 2
}

# Cost Optimization
variable "enable_auto_scaling" {
  description = "Enable auto-scaling for services"
  type        = bool
  default     = true
}

variable "min_replicas" {
  description = "Minimum number of replicas for auto-scaling"
  type        = number
  default     = 1
}

variable "max_replicas" {
  description = "Maximum number of replicas for auto-scaling"
  type        = number
  default     = 10
}

# Monitoring Configuration
variable "enable_application_insights" {
  description = "Enable Application Insights monitoring"
  type        = bool
  default     = true
}

variable "enable_log_analytics" {
  description = "Enable Log Analytics workspace"
  type        = bool
  default     = true
}

# Security Configuration
variable "enable_private_endpoints" {
  description = "Enable private endpoints for services"
  type        = bool
  default     = true
}

variable "allowed_ip_ranges" {
  description = "IP ranges allowed to access services"
  type        = list(string)
  default     = []
}

# Backup Configuration
variable "backup_retention_days" {
  description = "Number of days to retain backups"
  type        = number
  default     = 30
  
  validation {
    condition     = var.backup_retention_days >= 7 && var.backup_retention_days <= 365
    error_message = "Backup retention days must be between 7 and 365."
  }
}

# Environment-specific overrides
variable "environment_config" {
  description = "Environment-specific configuration overrides"
  type = object({
    node_count           = optional(number)
    node_vm_size        = optional(string)
    postgresql_sku_name = optional(string)
    redis_sku_name      = optional(string)
    enable_monitoring   = optional(bool)
  })
  default = {}
}

# Tags
variable "additional_tags" {
  description = "Additional tags to apply to resources"
  type        = map(string)
  default     = {}
}

# Multi-cloud integration variables
variable "enable_blue_green_deployment" {
  description = "Enable blue-green deployment with Helm"
  type        = bool
  default     = true
}

variable "domain_name" {
  description = "Domain name for the application"
  type        = string
  default     = "reversetender.com"
}

variable "azure_client_id" {
  description = "Azure Service Principal Client ID"
  type        = string
  sensitive   = true
}

variable "azure_client_secret" {
  description = "Azure Service Principal Client Secret"
  type        = string
  sensitive   = true
}
