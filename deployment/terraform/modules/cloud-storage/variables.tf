# Cloud Storage Module Variables

variable "cloud_provider" {
  description = "Cloud provider to use (digitalocean or linode)"
  type        = string
  validation {
    condition     = contains(["digitalocean", "linode"], var.cloud_provider)
    error_message = "Cloud provider must be either 'digitalocean' or 'linode'."
  }
}

variable "bucket_name" {
  description = "Name of the storage bucket"
  type        = string
  validation {
    condition     = can(regex("^[a-z0-9][a-z0-9-]*[a-z0-9]$", var.bucket_name))
    error_message = "Bucket name must be lowercase, alphanumeric, and can contain hyphens."
  }
}

variable "region" {
  description = "Region for the storage bucket"
  type        = string
  default     = "us-east-1"
}

variable "environment" {
  description = "Environment name (development, staging, production)"
  type        = string
  default     = "development"
}

variable "versioning_enabled" {
  description = "Enable versioning for the bucket"
  type        = bool
  default     = true
}

variable "cors_allowed_origins" {
  description = "List of allowed origins for CORS"
  type        = list(string)
  default     = ["*"]
}

variable "lifecycle_rules" {
  description = "Lifecycle rules for cost optimization"
  type = list(object({
    id      = string
    enabled = bool
    expiration = optional(object({
      days = number
    }))
    noncurrent_version_expiration = optional(object({
      days = number
    }))
  }))
  default = [
    {
      id      = "temp-files-cleanup"
      enabled = true
      expiration = {
        days = 30
      }
      noncurrent_version_expiration = {
        days = 7
      }
    },
    {
      id      = "old-versions-cleanup"
      enabled = true
      noncurrent_version_expiration = {
        days = 90
      }
    }
  ]
}

variable "cdn_enabled" {
  description = "Enable CDN for DigitalOcean Spaces"
  type        = bool
  default     = false
}

variable "cdn_custom_domain" {
  description = "Custom domain for CDN"
  type        = string
  default     = ""
}

variable "backup_enabled" {
  description = "Enable backup bucket"
  type        = bool
  default     = false
}

variable "backup_region" {
  description = "Region for backup bucket (if different from main)"
  type        = string
  default     = ""
}

variable "backup_retention_days" {
  description = "Number of days to retain backups"
  type        = number
  default     = 90
}

variable "monitoring_enabled" {
  description = "Enable monitoring and alerting"
  type        = bool
  default     = false
}

variable "alert_email" {
  description = "Email address for storage alerts"
  type        = string
  default     = ""
}

variable "tags" {
  description = "Additional tags for resources"
  type        = map(string)
  default     = {}
}

# Service-specific storage configurations
variable "service_storage_configs" {
  description = "Storage configurations for each service"
  type = map(object({
    max_file_size_mb = number
    allowed_types    = list(string)
    public_access    = bool
    encryption       = bool
  }))
  default = {
    "auth-service" = {
      max_file_size_mb = 5
      allowed_types    = ["jpg", "jpeg", "png"]
      public_access    = false
      encryption       = true
    }
    "user-service" = {
      max_file_size_mb = 10
      allowed_types    = ["jpg", "jpeg", "png", "pdf"]
      public_access    = false
      encryption       = true
    }
    "bidding-service" = {
      max_file_size_mb = 20
      allowed_types    = ["jpg", "jpeg", "png", "pdf", "doc", "docx"]
      public_access    = true
      encryption       = false
    }
    "order-service" = {
      max_file_size_mb = 15
      allowed_types    = ["jpg", "jpeg", "png", "pdf"]
      public_access    = false
      encryption       = true
    }
    "payment-service" = {
      max_file_size_mb = 5
      allowed_types    = ["pdf"]
      public_access    = false
      encryption       = true
    }
    "notification-service" = {
      max_file_size_mb = 10
      allowed_types    = ["jpg", "jpeg", "png", "pdf"]
      public_access    = false
      encryption       = false
    }
    "analytics-service" = {
      max_file_size_mb = 50
      allowed_types    = ["csv", "xlsx", "pdf", "json"]
      public_access    = false
      encryption       = true
    }
    "vin-ocr-service" = {
      max_file_size_mb = 25
      allowed_types    = ["jpg", "jpeg", "png"]
      public_access    = false
      encryption       = true
    }
  }
}

# Cost optimization settings
variable "storage_class_transitions" {
  description = "Storage class transitions for cost optimization"
  type = list(object({
    days          = number
    storage_class = string
  }))
  default = [
    {
      days          = 30
      storage_class = "STANDARD_IA"
    },
    {
      days          = 90
      storage_class = "GLACIER"
    }
  ]
}

variable "multipart_upload_threshold" {
  description = "Threshold in MB for multipart uploads"
  type        = number
  default     = 100
}

variable "max_concurrent_uploads" {
  description = "Maximum concurrent uploads per service"
  type        = number
  default     = 10
}

# Security settings
variable "encryption_enabled" {
  description = "Enable server-side encryption"
  type        = bool
  default     = true
}

variable "encryption_key_id" {
  description = "KMS key ID for encryption (if using KMS)"
  type        = string
  default     = ""
}

variable "access_logging_enabled" {
  description = "Enable access logging"
  type        = bool
  default     = true
}

variable "access_log_bucket" {
  description = "Bucket for access logs"
  type        = string
  default     = ""
}

# Bandwidth and transfer settings
variable "bandwidth_limit_gb" {
  description = "Monthly bandwidth limit in GB"
  type        = number
  default     = 1000
}

variable "transfer_acceleration_enabled" {
  description = "Enable transfer acceleration (DigitalOcean only)"
  type        = bool
  default     = false
}

# Development and testing settings
variable "enable_local_testing" {
  description = "Enable local testing with MinIO"
  type        = bool
  default     = false
}

variable "minio_endpoint" {
  description = "MinIO endpoint for local testing"
  type        = string
  default     = "http://localhost:9000"
}

variable "minio_access_key" {
  description = "MinIO access key for local testing"
  type        = string
  default     = "minioadmin"
}

variable "minio_secret_key" {
  description = "MinIO secret key for local testing"
  type        = string
  default     = "minioadmin"
}
