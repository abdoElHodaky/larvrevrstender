# DigitalOcean Terraform Variables for Multi-Cloud Integration

# Core Configuration
variable "digitalocean_token" {
  description = "DigitalOcean API token"
  type        = string
  sensitive   = true
}

variable "region" {
  description = "DigitalOcean region"
  type        = string
  default     = "nyc3"
}

variable "environment" {
  description = "Environment name (e.g., dev, staging, prod)"
  type        = string
  default     = "prod"
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

# Load Balancer Configuration
variable "load_balancer_size" {
  description = "Size of the DigitalOcean Load Balancer"
  type        = string
  default     = "lb-small"
  
  validation {
    condition     = contains(["lb-small", "lb-medium", "lb-large"], var.load_balancer_size)
    error_message = "Load balancer size must be lb-small, lb-medium, or lb-large."
  }
}

# Spaces Configuration
variable "spaces_access_key" {
  description = "DigitalOcean Spaces access key"
  type        = string
  sensitive   = true
}

variable "spaces_secret_key" {
  description = "DigitalOcean Spaces secret key"
  type        = string
  sensitive   = true
}

# Container Registry Configuration
variable "registry_tier" {
  description = "Container registry subscription tier"
  type        = string
  default     = "basic"
  
  validation {
    condition     = contains(["starter", "basic", "professional"], var.registry_tier)
    error_message = "Registry tier must be starter, basic, or professional."
  }
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
