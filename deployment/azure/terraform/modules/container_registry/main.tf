# Azure Container Registry Module for Reverse Tender Platform

# Azure Container Registry
resource "azurerm_container_registry" "main" {
  name                = "acr${replace(var.project_name, "-", "")}${var.environment}"
  resource_group_name = var.resource_group_name
  location            = var.location
  sku                 = var.sku
  admin_enabled       = false

  # Enable system-assigned managed identity
  identity {
    type = "SystemAssigned"
  }

  # Network access rules
  public_network_access_enabled = var.public_network_access_enabled
  network_rule_bypass_option    = "AzureServices"

  # Retention policy for untagged manifests
  retention_policy {
    days    = var.retention_days
    enabled = true
  }

  # Trust policy for content trust
  trust_policy {
    enabled = var.environment == "prod" ? true : false
  }

  # Quarantine policy for vulnerability scanning
  quarantine_policy {
    enabled = var.enable_quarantine
  }

  tags = var.tags
}

# Private endpoint for ACR (if enabled)
resource "azurerm_private_endpoint" "acr" {
  count               = var.enable_private_endpoint ? 1 : 0
  name                = "pe-acr-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name
  subnet_id           = var.private_endpoint_subnet_id

  private_service_connection {
    name                           = "psc-acr-${var.project_name}-${var.environment}"
    private_connection_resource_id = azurerm_container_registry.main.id
    subresource_names             = ["registry"]
    is_manual_connection          = false
  }

  private_dns_zone_group {
    name                 = "default"
    private_dns_zone_ids = [azurerm_private_dns_zone.acr[0].id]
  }

  tags = var.tags
}

# Private DNS Zone for ACR
resource "azurerm_private_dns_zone" "acr" {
  count               = var.enable_private_endpoint ? 1 : 0
  name                = "privatelink.azurecr.io"
  resource_group_name = var.resource_group_name
  
  tags = var.tags
}

# Link private DNS zone to VNet
resource "azurerm_private_dns_zone_virtual_network_link" "acr" {
  count                 = var.enable_private_endpoint ? 1 : 0
  name                  = "acr-${var.project_name}-${var.environment}"
  resource_group_name   = var.resource_group_name
  private_dns_zone_name = azurerm_private_dns_zone.acr[0].name
  virtual_network_id    = var.virtual_network_id
  
  tags = var.tags
}

# Role assignments for AKS to pull images
resource "azurerm_role_assignment" "acr_pull" {
  count                = length(var.aks_principal_ids)
  principal_id         = var.aks_principal_ids[count.index]
  role_definition_name = "AcrPull"
  scope                = azurerm_container_registry.main.id
  
  skip_service_principal_aad_check = true
}

# Monitoring alerts
resource "azurerm_monitor_metric_alert" "acr_storage" {
  name                = "acr-storage-${var.project_name}-${var.environment}"
  resource_group_name = var.resource_group_name
  scopes              = [azurerm_container_registry.main.id]
  description         = "Action will be triggered when ACR storage usage is greater than 80%"

  criteria {
    metric_namespace = "Microsoft.ContainerRegistry/registries"
    metric_name      = "StorageUsed"
    aggregation      = "Average"
    operator         = "GreaterThan"
    threshold        = var.storage_alert_threshold
  }

  action {
    action_group_id = var.action_group_id
  }

  tags = var.tags
}
