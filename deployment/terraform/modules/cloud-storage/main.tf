# Cloud Storage Module for Multi-Cloud File Storage
# Supports both DigitalOcean Spaces and Linode Object Storage

terraform {
  required_providers {
    digitalocean = {
      source  = "digitalocean/digitalocean"
      version = "~> 2.0"
    }
    linode = {
      source  = "linode/linode"
      version = "~> 2.0"
    }
  }
}

# DigitalOcean Spaces Configuration
resource "digitalocean_spaces_bucket" "main" {
  count  = var.cloud_provider == "digitalocean" ? 1 : 0
  name   = var.bucket_name
  region = var.region

  # Enable versioning for file recovery
  versioning {
    enabled = var.versioning_enabled
  }

  # CORS configuration for web uploads
  cors_rule {
    allowed_headers = ["*"]
    allowed_methods = ["GET", "PUT", "POST", "DELETE", "HEAD"]
    allowed_origins = var.cors_allowed_origins
    expose_headers  = ["ETag"]
    max_age_seconds = 3000
  }

  # Lifecycle configuration for cost optimization
  dynamic "lifecycle_rule" {
    for_each = var.lifecycle_rules
    content {
      id      = lifecycle_rule.value.id
      enabled = lifecycle_rule.value.enabled

      dynamic "expiration" {
        for_each = lifecycle_rule.value.expiration != null ? [lifecycle_rule.value.expiration] : []
        content {
          days = expiration.value.days
        }
      }

      dynamic "noncurrent_version_expiration" {
        for_each = lifecycle_rule.value.noncurrent_version_expiration != null ? [lifecycle_rule.value.noncurrent_version_expiration] : []
        content {
          days = noncurrent_version_expiration.value.days
        }
      }
    }
  }

  tags = merge(var.tags, {
    Environment = var.environment
    Service     = "cloud-storage"
    Provider    = "digitalocean"
  })
}

# DigitalOcean CDN for faster content delivery
resource "digitalocean_cdn" "main" {
  count      = var.cloud_provider == "digitalocean" && var.cdn_enabled ? 1 : 0
  origin     = digitalocean_spaces_bucket.main[0].bucket_domain_name
  custom_domain = var.cdn_custom_domain

  tags = merge(var.tags, {
    Environment = var.environment
    Service     = "cdn"
    Provider    = "digitalocean"
  })
}

# Linode Object Storage Bucket
resource "linode_object_storage_bucket" "main" {
  count   = var.cloud_provider == "linode" ? 1 : 0
  label   = var.bucket_name
  cluster = var.region

  # Enable versioning
  versioning = var.versioning_enabled

  # CORS configuration
  cors_rule {
    allowed_headers = ["*"]
    allowed_methods = ["GET", "PUT", "POST", "DELETE", "HEAD"]
    allowed_origins = var.cors_allowed_origins
    expose_headers  = ["ETag"]
    max_age_seconds = 3000
  }

  # Lifecycle policy for cost optimization
  dynamic "lifecycle_rule" {
    for_each = var.lifecycle_rules
    content {
      id      = lifecycle_rule.value.id
      enabled = lifecycle_rule.value.enabled

      dynamic "expiration" {
        for_each = lifecycle_rule.value.expiration != null ? [lifecycle_rule.value.expiration] : []
        content {
          days = expiration.value.days
        }
      }

      dynamic "noncurrent_version_expiration" {
        for_each = lifecycle_rule.value.noncurrent_version_expiration != null ? [lifecycle_rule.value.noncurrent_version_expiration] : []
        content {
          days = noncurrent_version_expiration.value.days
        }
      }
    }
  }
}

# Access Keys for DigitalOcean Spaces
resource "digitalocean_spaces_bucket_object" "access_policy" {
  count   = var.cloud_provider == "digitalocean" ? 1 : 0
  region  = var.region
  bucket  = digitalocean_spaces_bucket.main[0].name
  key     = ".access-policy"
  content = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Sid       = "AllowServiceAccess"
        Effect    = "Allow"
        Principal = "*"
        Action = [
          "s3:GetObject",
          "s3:PutObject",
          "s3:DeleteObject"
        ]
        Resource = [
          "arn:aws:s3:::${var.bucket_name}/*"
        ]
        Condition = {
          StringEquals = {
            "s3:x-amz-acl" = "public-read"
          }
        }
      }
    ]
  })
  content_type = "application/json"
}

# Linode Object Storage Access Keys
resource "linode_object_storage_key" "main" {
  count = var.cloud_provider == "linode" ? 1 : 0
  label = "${var.bucket_name}-access-key"

  bucket_access {
    bucket_name = linode_object_storage_bucket.main[0].label
    cluster     = var.region
    permissions = "read_write"
  }
}

# Create service-specific folders/prefixes
locals {
  service_prefixes = [
    "auth-service/",
    "user-service/",
    "bidding-service/",
    "order-service/",
    "payment-service/",
    "notification-service/",
    "analytics-service/",
    "vin-ocr-service/",
    "shared/"
  ]
}

# DigitalOcean Spaces service folders
resource "digitalocean_spaces_bucket_object" "service_folders" {
  count        = var.cloud_provider == "digitalocean" ? length(local.service_prefixes) : 0
  region       = var.region
  bucket       = digitalocean_spaces_bucket.main[0].name
  key          = local.service_prefixes[count.index]
  content      = ""
  content_type = "application/x-directory"
  acl          = "private"
}

# Linode Object Storage service folders
resource "linode_object_storage_object" "service_folders" {
  count   = var.cloud_provider == "linode" ? length(local.service_prefixes) : 0
  bucket  = linode_object_storage_bucket.main[0].label
  cluster = var.region
  key     = local.service_prefixes[count.index]
  content = ""
}

# Backup bucket for critical files
resource "digitalocean_spaces_bucket" "backup" {
  count  = var.cloud_provider == "digitalocean" && var.backup_enabled ? 1 : 0
  name   = "${var.bucket_name}-backup"
  region = var.backup_region != "" ? var.backup_region : var.region

  versioning {
    enabled = true
  }

  # Backup lifecycle - keep for longer periods
  lifecycle_rule {
    id      = "backup-retention"
    enabled = true

    expiration {
      days = var.backup_retention_days
    }

    noncurrent_version_expiration {
      days = 30
    }
  }

  tags = merge(var.tags, {
    Environment = var.environment
    Service     = "backup-storage"
    Provider    = "digitalocean"
  })
}

resource "linode_object_storage_bucket" "backup" {
  count   = var.cloud_provider == "linode" && var.backup_enabled ? 1 : 0
  label   = "${var.bucket_name}-backup"
  cluster = var.backup_region != "" ? var.backup_region : var.region

  versioning = true

  lifecycle_rule {
    id      = "backup-retention"
    enabled = true

    expiration {
      days = var.backup_retention_days
    }

    noncurrent_version_expiration {
      days = 30
    }
  }
}

# Monitoring and alerting for storage usage
resource "digitalocean_monitor_alert" "storage_usage" {
  count       = var.cloud_provider == "digitalocean" && var.monitoring_enabled ? 1 : 0
  alerts {
    email = var.alert_email
  }
  window      = "5m"
  type        = "v1/insights/droplet/bandwidth_utilization"
  compare     = "GreaterThan"
  value       = 80
  enabled     = true
  entities    = []
  description = "Storage usage alert for ${var.bucket_name}"

  tags = ["storage", "monitoring", var.environment]
}
