# Cloud Storage Module Outputs

# Bucket Information
output "bucket_name" {
  description = "Name of the created storage bucket"
  value       = var.cloud_provider == "digitalocean" ? (length(digitalocean_spaces_bucket.main) > 0 ? digitalocean_spaces_bucket.main[0].name : "") : (length(linode_object_storage_bucket.main) > 0 ? linode_object_storage_bucket.main[0].label : "")
}

output "bucket_domain_name" {
  description = "Domain name of the storage bucket"
  value       = var.cloud_provider == "digitalocean" ? (length(digitalocean_spaces_bucket.main) > 0 ? digitalocean_spaces_bucket.main[0].bucket_domain_name : "") : (length(linode_object_storage_bucket.main) > 0 ? "${linode_object_storage_bucket.main[0].label}.${var.region}.linodeobjects.com" : "")
}

output "bucket_endpoint" {
  description = "S3-compatible endpoint for the storage bucket"
  value = var.cloud_provider == "digitalocean" ? (
    length(digitalocean_spaces_bucket.main) > 0 ? "https://${var.region}.digitaloceanspaces.com" : ""
  ) : (
    length(linode_object_storage_bucket.main) > 0 ? "https://${var.region}.linodeobjects.com" : ""
  )
}

output "bucket_region" {
  description = "Region of the storage bucket"
  value       = var.region
}

# Access Credentials
output "access_key_id" {
  description = "Access key ID for the storage bucket"
  value       = var.cloud_provider == "linode" ? (length(linode_object_storage_key.main) > 0 ? linode_object_storage_key.main[0].access_key : "") : ""
  sensitive   = true
}

output "secret_access_key" {
  description = "Secret access key for the storage bucket"
  value       = var.cloud_provider == "linode" ? (length(linode_object_storage_key.main) > 0 ? linode_object_storage_key.main[0].secret_key : "") : ""
  sensitive   = true
}

# CDN Information
output "cdn_endpoint" {
  description = "CDN endpoint URL (DigitalOcean only)"
  value       = var.cloud_provider == "digitalocean" && var.cdn_enabled ? (length(digitalocean_cdn.main) > 0 ? digitalocean_cdn.main[0].endpoint : "") : ""
}

output "cdn_custom_domain" {
  description = "Custom CDN domain (if configured)"
  value       = var.cdn_custom_domain
}

# Backup Bucket Information
output "backup_bucket_name" {
  description = "Name of the backup storage bucket"
  value = var.backup_enabled ? (
    var.cloud_provider == "digitalocean" ? (
      length(digitalocean_spaces_bucket.backup) > 0 ? digitalocean_spaces_bucket.backup[0].name : ""
    ) : (
      length(linode_object_storage_bucket.backup) > 0 ? linode_object_storage_bucket.backup[0].label : ""
    )
  ) : ""
}

# Service Configuration Outputs
output "service_prefixes" {
  description = "List of service prefixes in the bucket"
  value       = local.service_prefixes
}

output "service_storage_configs" {
  description = "Storage configurations for each service"
  value       = var.service_storage_configs
}

# Environment Configuration for Services
output "laravel_filesystem_config" {
  description = "Laravel filesystem configuration for services"
  value = {
    default = "s3"
    disks = {
      s3 = {
        driver                  = "s3"
        key                    = var.cloud_provider == "linode" ? (length(linode_object_storage_key.main) > 0 ? linode_object_storage_key.main[0].access_key : "") : ""
        secret                 = var.cloud_provider == "linode" ? (length(linode_object_storage_key.main) > 0 ? linode_object_storage_key.main[0].secret_key : "") : ""
        region                 = var.region
        bucket                 = var.cloud_provider == "digitalocean" ? (length(digitalocean_spaces_bucket.main) > 0 ? digitalocean_spaces_bucket.main[0].name : "") : (length(linode_object_storage_bucket.main) > 0 ? linode_object_storage_bucket.main[0].label : "")
        endpoint               = var.cloud_provider == "digitalocean" ? "https://${var.region}.digitaloceanspaces.com" : "https://${var.region}.linodeobjects.com"
        use_path_style_endpoint = true
        throw                  = false
      }
      public = {
        driver     = "s3"
        key        = var.cloud_provider == "linode" ? (length(linode_object_storage_key.main) > 0 ? linode_object_storage_key.main[0].access_key : "") : ""
        secret     = var.cloud_provider == "linode" ? (length(linode_object_storage_key.main) > 0 ? linode_object_storage_key.main[0].secret_key : "") : ""
        region     = var.region
        bucket     = var.cloud_provider == "digitalocean" ? (length(digitalocean_spaces_bucket.main) > 0 ? digitalocean_spaces_bucket.main[0].name : "") : (length(linode_object_storage_bucket.main) > 0 ? linode_object_storage_bucket.main[0].label : "")
        endpoint   = var.cloud_provider == "digitalocean" ? "https://${var.region}.digitaloceanspaces.com" : "https://${var.region}.linodeobjects.com"
        use_path_style_endpoint = true
        visibility = "public"
        throw      = false
      }
    }
  }
  sensitive = true
}

# Environment Variables for Services
output "storage_environment_variables" {
  description = "Environment variables for service configuration"
  value = {
    FILESYSTEM_DISK                = "s3"
    AWS_ACCESS_KEY_ID             = var.cloud_provider == "linode" ? (length(linode_object_storage_key.main) > 0 ? linode_object_storage_key.main[0].access_key : "") : ""
    AWS_SECRET_ACCESS_KEY         = var.cloud_provider == "linode" ? (length(linode_object_storage_key.main) > 0 ? linode_object_storage_key.main[0].secret_key : "") : ""
    AWS_DEFAULT_REGION            = var.region
    AWS_BUCKET                    = var.cloud_provider == "digitalocean" ? (length(digitalocean_spaces_bucket.main) > 0 ? digitalocean_spaces_bucket.main[0].name : "") : (length(linode_object_storage_bucket.main) > 0 ? linode_object_storage_bucket.main[0].label : "")
    AWS_ENDPOINT                  = var.cloud_provider == "digitalocean" ? "https://${var.region}.digitaloceanspaces.com" : "https://${var.region}.linodeobjects.com"
    AWS_USE_PATH_STYLE_ENDPOINT   = "true"
    
    # Additional S3 configuration
    S3_BUCKET                     = var.cloud_provider == "digitalocean" ? (length(digitalocean_spaces_bucket.main) > 0 ? digitalocean_spaces_bucket.main[0].name : "") : (length(linode_object_storage_bucket.main) > 0 ? linode_object_storage_bucket.main[0].label : "")
    S3_ACCESS_KEY                 = var.cloud_provider == "linode" ? (length(linode_object_storage_key.main) > 0 ? linode_object_storage_key.main[0].access_key : "") : ""
    S3_SECRET_KEY                 = var.cloud_provider == "linode" ? (length(linode_object_storage_key.main) > 0 ? linode_object_storage_key.main[0].secret_key : "") : ""
    S3_REGION                     = var.region
    S3_ENDPOINT                   = var.cloud_provider == "digitalocean" ? "https://${var.region}.digitaloceanspaces.com" : "https://${var.region}.linodeobjects.com"
    
    # CDN configuration
    CDN_ENDPOINT                  = var.cloud_provider == "digitalocean" && var.cdn_enabled ? (length(digitalocean_cdn.main) > 0 ? digitalocean_cdn.main[0].endpoint : "") : ""
    CDN_ENABLED                   = var.cdn_enabled ? "true" : "false"
    
    # Storage provider information
    STORAGE_PROVIDER              = var.cloud_provider
    STORAGE_REGION                = var.region
    STORAGE_VERSIONING_ENABLED    = var.versioning_enabled ? "true" : "false"
  }
  sensitive = true
}

# Cost and Usage Information
output "storage_cost_optimization" {
  description = "Storage cost optimization settings"
  value = {
    lifecycle_rules_enabled = length(var.lifecycle_rules) > 0
    versioning_enabled     = var.versioning_enabled
    backup_enabled         = var.backup_enabled
    cdn_enabled           = var.cdn_enabled
    monitoring_enabled    = var.monitoring_enabled
  }
}

# Security Configuration
output "security_configuration" {
  description = "Security configuration for the storage"
  value = {
    encryption_enabled     = var.encryption_enabled
    access_logging_enabled = var.access_logging_enabled
    cors_configured       = length(var.cors_allowed_origins) > 0
    versioning_enabled    = var.versioning_enabled
  }
}

# Service-specific URLs
output "service_base_urls" {
  description = "Base URLs for each service's storage"
  value = {
    for service in keys(var.service_storage_configs) : service => {
      private_url = var.cloud_provider == "digitalocean" ? (
        length(digitalocean_spaces_bucket.main) > 0 ? "https://${digitalocean_spaces_bucket.main[0].bucket_domain_name}/${service}/" : ""
      ) : (
        length(linode_object_storage_bucket.main) > 0 ? "https://${linode_object_storage_bucket.main[0].label}.${var.region}.linodeobjects.com/${service}/" : ""
      )
      public_url = var.cdn_enabled && var.cloud_provider == "digitalocean" ? (
        length(digitalocean_cdn.main) > 0 ? "https://${digitalocean_cdn.main[0].endpoint}/${service}/" : ""
      ) : (
        var.cloud_provider == "digitalocean" ? (
          length(digitalocean_spaces_bucket.main) > 0 ? "https://${digitalocean_spaces_bucket.main[0].bucket_domain_name}/${service}/" : ""
        ) : (
          length(linode_object_storage_bucket.main) > 0 ? "https://${linode_object_storage_bucket.main[0].label}.${var.region}.linodeobjects.com/${service}/" : ""
        )
      )
    }
  }
}

# Kubernetes Secret Configuration
output "kubernetes_secret_data" {
  description = "Data for Kubernetes secret containing storage credentials"
  value = {
    AWS_ACCESS_KEY_ID           = base64encode(var.cloud_provider == "linode" ? (length(linode_object_storage_key.main) > 0 ? linode_object_storage_key.main[0].access_key : "") : "")
    AWS_SECRET_ACCESS_KEY       = base64encode(var.cloud_provider == "linode" ? (length(linode_object_storage_key.main) > 0 ? linode_object_storage_key.main[0].secret_key : "") : "")
    AWS_DEFAULT_REGION          = base64encode(var.region)
    AWS_BUCKET                  = base64encode(var.cloud_provider == "digitalocean" ? (length(digitalocean_spaces_bucket.main) > 0 ? digitalocean_spaces_bucket.main[0].name : "") : (length(linode_object_storage_bucket.main) > 0 ? linode_object_storage_bucket.main[0].label : ""))
    AWS_ENDPOINT                = base64encode(var.cloud_provider == "digitalocean" ? "https://${var.region}.digitaloceanspaces.com" : "https://${var.region}.linodeobjects.com")
    AWS_USE_PATH_STYLE_ENDPOINT = base64encode("true")
  }
  sensitive = true
}
