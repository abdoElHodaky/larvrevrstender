# Azure Key Vault Module for Reverse Tender Platform

# Data source for current client configuration
data "azurerm_client_config" "current" {}

# Azure Key Vault
resource "azurerm_key_vault" "main" {
  name                = "kv-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name
  tenant_id           = data.azurerm_client_config.current.tenant_id
  sku_name            = var.sku_name

  # Enable soft delete and purge protection for production
  soft_delete_retention_days = var.soft_delete_retention_days
  purge_protection_enabled   = var.environment == "prod" ? true : false

  # Network access rules
  public_network_access_enabled = var.public_network_access_enabled
  network_acls {
    bypass                     = "AzureServices"
    default_action             = var.public_network_access_enabled ? "Allow" : "Deny"
    ip_rules                   = var.allowed_ip_ranges
    virtual_network_subnet_ids = var.allowed_subnet_ids
  }

  # Enable RBAC for access control
  enable_rbac_authorization = true

  tags = var.tags
}

# Private endpoint for Key Vault (if enabled)
resource "azurerm_private_endpoint" "key_vault" {
  count               = var.enable_private_endpoint ? 1 : 0
  name                = "pe-kv-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name
  subnet_id           = var.subnet_id

  private_service_connection {
    name                           = "psc-kv-${var.project_name}-${var.environment}"
    private_connection_resource_id = azurerm_key_vault.main.id
    subresource_names             = ["vault"]
    is_manual_connection          = false
  }

  private_dns_zone_group {
    name                 = "default"
    private_dns_zone_ids = [azurerm_private_dns_zone.key_vault[0].id]
  }

  tags = var.tags
}

# Private DNS Zone for Key Vault
resource "azurerm_private_dns_zone" "key_vault" {
  count               = var.enable_private_endpoint ? 1 : 0
  name                = "privatelink.vaultcore.azure.net"
  resource_group_name = var.resource_group_name
  
  tags = var.tags
}

# Link private DNS zone to VNet
resource "azurerm_private_dns_zone_virtual_network_link" "key_vault" {
  count                 = var.enable_private_endpoint ? 1 : 0
  name                  = "kv-${var.project_name}-${var.environment}"
  resource_group_name   = var.resource_group_name
  private_dns_zone_name = azurerm_private_dns_zone.key_vault[0].name
  virtual_network_id    = var.virtual_network_id
  
  tags = var.tags
}

# Role assignment for Key Vault Secrets Officer (for Terraform)
resource "azurerm_role_assignment" "key_vault_secrets_officer" {
  scope                = azurerm_key_vault.main.id
  role_definition_name = "Key Vault Secrets Officer"
  principal_id         = data.azurerm_client_config.current.object_id
}

# Role assignments for AKS managed identities
resource "azurerm_role_assignment" "key_vault_secrets_user" {
  count                = length(var.aks_principal_ids)
  scope                = azurerm_key_vault.main.id
  role_definition_name = "Key Vault Secrets User"
  principal_id         = var.aks_principal_ids[count.index]
  
  skip_service_principal_aad_check = true
}

# Common secrets for microservices
resource "azurerm_key_vault_secret" "app_key" {
  name         = "app-key"
  value        = var.app_key
  key_vault_id = azurerm_key_vault.main.id
  
  tags = var.tags
  
  depends_on = [azurerm_role_assignment.key_vault_secrets_officer]
}

resource "azurerm_key_vault_secret" "jwt_secret" {
  name         = "jwt-secret"
  value        = var.jwt_secret
  key_vault_id = azurerm_key_vault.main.id
  
  tags = var.tags
  
  depends_on = [azurerm_role_assignment.key_vault_secrets_officer]
}

resource "azurerm_key_vault_secret" "database_password" {
  name         = "database-password"
  value        = var.database_password
  key_vault_id = azurerm_key_vault.main.id
  
  tags = var.tags
  
  depends_on = [azurerm_role_assignment.key_vault_secrets_officer]
}

resource "azurerm_key_vault_secret" "redis_password" {
  name         = "redis-password"
  value        = var.redis_password
  key_vault_id = azurerm_key_vault.main.id
  
  tags = var.tags
  
  depends_on = [azurerm_role_assignment.key_vault_secrets_officer]
}

# Payment service secrets
resource "azurerm_key_vault_secret" "stripe_secret_key" {
  name         = "stripe-secret-key"
  value        = var.stripe_secret_key
  key_vault_id = azurerm_key_vault.main.id
  
  tags = var.tags
  
  depends_on = [azurerm_role_assignment.key_vault_secrets_officer]
}

resource "azurerm_key_vault_secret" "stripe_webhook_secret" {
  name         = "stripe-webhook-secret"
  value        = var.stripe_webhook_secret
  key_vault_id = azurerm_key_vault.main.id
  
  tags = var.tags
  
  depends_on = [azurerm_role_assignment.key_vault_secrets_officer]
}

# Notification service secrets
resource "azurerm_key_vault_secret" "mail_password" {
  name         = "mail-password"
  value        = var.mail_password
  key_vault_id = azurerm_key_vault.main.id
  
  tags = var.tags
  
  depends_on = [azurerm_role_assignment.key_vault_secrets_officer]
}

resource "azurerm_key_vault_secret" "twilio_auth_token" {
  name         = "twilio-auth-token"
  value        = var.twilio_auth_token
  key_vault_id = azurerm_key_vault.main.id
  
  tags = var.tags
  
  depends_on = [azurerm_role_assignment.key_vault_secrets_officer]
}

# Key for encryption at rest
resource "azurerm_key_vault_key" "encryption_key" {
  name         = "encryption-key"
  key_vault_id = azurerm_key_vault.main.id
  key_type     = "RSA"
  key_size     = 2048

  key_opts = [
    "decrypt",
    "encrypt",
    "sign",
    "unwrapKey",
    "verify",
    "wrapKey",
  ]

  tags = var.tags
  
  depends_on = [azurerm_role_assignment.key_vault_secrets_officer]
}

# Certificate for SSL/TLS (if custom certificates are used)
resource "azurerm_key_vault_certificate" "ssl_certificate" {
  count        = var.enable_ssl_certificate ? 1 : 0
  name         = "ssl-certificate"
  key_vault_id = azurerm_key_vault.main.id

  certificate_policy {
    issuer_parameters {
      name = "Self"
    }

    key_properties {
      exportable = true
      key_size   = 2048
      key_type   = "RSA"
      reuse_key  = true
    }

    lifetime_action {
      action {
        action_type = "AutoRenew"
      }

      trigger {
        days_before_expiry = 30
      }
    }

    secret_properties {
      content_type = "application/x-pkcs12"
    }

    x509_certificate_properties {
      extended_key_usage = ["1.3.6.1.5.5.7.3.1"]

      key_usage = [
        "cRLSign",
        "dataEncipherment",
        "digitalSignature",
        "keyAgreement",
        "keyCertSign",
        "keyEncipherment",
      ]

      subject_alternative_names {
        dns_names = var.ssl_dns_names
      }

      subject            = "CN=${var.ssl_subject}"
      validity_in_months = 12
    }
  }

  tags = var.tags
  
  depends_on = [azurerm_role_assignment.key_vault_secrets_officer]
}

# Diagnostic settings for Key Vault
resource "azurerm_monitor_diagnostic_setting" "key_vault" {
  name                       = "diag-kv-${var.project_name}-${var.environment}"
  target_resource_id         = azurerm_key_vault.main.id
  log_analytics_workspace_id = var.log_analytics_workspace_id

  enabled_log {
    category = "AuditEvent"
  }

  enabled_log {
    category = "AzurePolicyEvaluationDetails"
  }

  metric {
    category = "AllMetrics"
    enabled  = true
  }
}

# Alert for Key Vault access
resource "azurerm_monitor_metric_alert" "key_vault_requests" {
  name                = "kv-requests-${var.project_name}-${var.environment}"
  resource_group_name = var.resource_group_name
  scopes              = [azurerm_key_vault.main.id]
  description         = "Action will be triggered when Key Vault requests are unusually high"

  criteria {
    metric_namespace = "Microsoft.KeyVault/vaults"
    metric_name      = "ServiceApiHit"
    aggregation      = "Total"
    operator         = "GreaterThan"
    threshold        = var.requests_alert_threshold
  }

  action {
    action_group_id = var.action_group_id
  }

  tags = var.tags
}
