# Azure Database Module for Reverse Tender Platform

# Random password for PostgreSQL admin
resource "random_password" "postgresql_admin" {
  length  = 16
  special = true
}

# Random password for Redis auth
resource "random_password" "redis_auth" {
  length  = 32
  special = false
}

# PostgreSQL Flexible Server
resource "azurerm_postgresql_flexible_server" "main" {
  name                   = "psql-${var.project_name}-${var.environment}"
  resource_group_name    = var.resource_group_name
  location              = var.location
  version               = var.postgresql_version
  delegated_subnet_id   = var.subnet_id
  private_dns_zone_id   = azurerm_private_dns_zone.postgresql.id
  
  administrator_login    = "psqladmin"
  administrator_password = random_password.postgresql_admin.result
  
  zone = "1"
  
  storage_mb   = var.postgresql_storage_mb
  sku_name     = var.postgresql_sku_name
  
  backup_retention_days        = 7
  geo_redundant_backup_enabled = var.environment == "prod" ? true : false
  
  high_availability {
    mode                      = var.environment == "prod" ? "ZoneRedundant" : "Disabled"
    standby_availability_zone = var.environment == "prod" ? "2" : null
  }
  
  maintenance_window {
    day_of_week  = 0
    start_hour   = 8
    start_minute = 0
  }
  
  tags = var.tags
  
  depends_on = [azurerm_private_dns_zone_virtual_network_link.postgresql]
}

# Private DNS Zone for PostgreSQL
resource "azurerm_private_dns_zone" "postgresql" {
  name                = "${var.project_name}-${var.environment}.postgres.database.azure.com"
  resource_group_name = var.resource_group_name
  
  tags = var.tags
}

# Link PostgreSQL DNS zone to VNet
resource "azurerm_private_dns_zone_virtual_network_link" "postgresql" {
  name                  = "postgresql-${var.project_name}-${var.environment}"
  private_dns_zone_name = azurerm_private_dns_zone.postgresql.name
  virtual_network_id    = var.virtual_network_id
  resource_group_name   = var.resource_group_name
  
  tags = var.tags
}

# PostgreSQL Configuration
resource "azurerm_postgresql_flexible_server_configuration" "extensions" {
  name      = "azure.extensions"
  server_id = azurerm_postgresql_flexible_server.main.id
  value     = "CITEXT,HSTORE,UUID-OSSP"
}

resource "azurerm_postgresql_flexible_server_configuration" "timezone" {
  name      = "timezone"
  server_id = azurerm_postgresql_flexible_server.main.id
  value     = "UTC"
}

resource "azurerm_postgresql_flexible_server_configuration" "log_statement" {
  name      = "log_statement"
  server_id = azurerm_postgresql_flexible_server.main.id
  value     = "none"
}

# Create databases for each microservice
resource "azurerm_postgresql_flexible_server_database" "microservices" {
  for_each = toset(var.databases)
  
  name      = each.value
  server_id = azurerm_postgresql_flexible_server.main.id
  collation = "en_US.utf8"
  charset   = "utf8"
}

# Redis Cache
resource "azurerm_redis_cache" "main" {
  name                = "redis-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name
  capacity            = var.redis_capacity
  family              = var.redis_sku_name == "Premium" ? "P" : "C"
  sku_name            = var.redis_sku_name
  enable_non_ssl_port = false
  minimum_tls_version = "1.2"
  
  # Redis configuration
  redis_configuration {
    enable_authentication           = true
    maxmemory_reserved             = var.redis_sku_name == "Premium" ? 50 : 30
    maxmemory_delta                = var.redis_sku_name == "Premium" ? 50 : 30
    maxmemory_policy               = "allkeys-lru"
    maxfragmentationmemory_reserved = var.redis_sku_name == "Premium" ? 50 : 30
    
    # Enable data persistence for Premium tier
    rdb_backup_enabled            = var.redis_sku_name == "Premium" ? true : false
    rdb_backup_frequency          = var.redis_sku_name == "Premium" ? 60 : null
    rdb_backup_max_snapshot_count = var.redis_sku_name == "Premium" ? 1 : null
    rdb_storage_connection_string = var.redis_sku_name == "Premium" ? azurerm_storage_account.redis_backup[0].primary_blob_connection_string : null
  }
  
  # Private endpoint configuration
  public_network_access_enabled = false
  
  tags = var.tags
}

# Storage account for Redis backup (Premium tier only)
resource "azurerm_storage_account" "redis_backup" {
  count                    = var.redis_sku_name == "Premium" ? 1 : 0
  name                     = "st${replace(var.project_name, "-", "")}redis${var.environment}"
  resource_group_name      = var.resource_group_name
  location                = var.location
  account_tier            = "Standard"
  account_replication_type = "LRS"
  
  tags = var.tags
}

# Private endpoint for Redis
resource "azurerm_private_endpoint" "redis" {
  name                = "pe-redis-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name
  subnet_id           = var.subnet_id
  
  private_service_connection {
    name                           = "psc-redis-${var.project_name}-${var.environment}"
    private_connection_resource_id = azurerm_redis_cache.main.id
    subresource_names             = ["redisCache"]
    is_manual_connection          = false
  }
  
  private_dns_zone_group {
    name                 = "default"
    private_dns_zone_ids = [azurerm_private_dns_zone.redis.id]
  }
  
  tags = var.tags
}

# Private DNS Zone for Redis
resource "azurerm_private_dns_zone" "redis" {
  name                = "privatelink.redis.cache.windows.net"
  resource_group_name = var.resource_group_name
  
  tags = var.tags
}

# Link Redis DNS zone to VNet
resource "azurerm_private_dns_zone_virtual_network_link" "redis" {
  name                  = "redis-${var.project_name}-${var.environment}"
  resource_group_name   = var.resource_group_name
  private_dns_zone_name = azurerm_private_dns_zone.redis.name
  virtual_network_id    = var.virtual_network_id
  
  tags = var.tags
}

# Key Vault secrets for database credentials
resource "azurerm_key_vault_secret" "postgresql_admin_password" {
  name         = "postgresql-admin-password"
  value        = random_password.postgresql_admin.result
  key_vault_id = var.key_vault_id
  
  tags = var.tags
}

resource "azurerm_key_vault_secret" "postgresql_connection_string" {
  name         = "postgresql-connection-string"
  value        = "postgresql://${azurerm_postgresql_flexible_server.main.administrator_login}:${random_password.postgresql_admin.result}@${azurerm_postgresql_flexible_server.main.fqdn}:5432"
  key_vault_id = var.key_vault_id
  
  tags = var.tags
}

resource "azurerm_key_vault_secret" "redis_connection_string" {
  name         = "redis-connection-string"
  value        = "${azurerm_redis_cache.main.hostname}:${azurerm_redis_cache.main.ssl_port}"
  key_vault_id = var.key_vault_id
  
  tags = var.tags
}

resource "azurerm_key_vault_secret" "redis_auth_key" {
  name         = "redis-auth-key"
  value        = azurerm_redis_cache.main.primary_access_key
  key_vault_id = var.key_vault_id
  
  tags = var.tags
}

# Database connection strings for each microservice
resource "azurerm_key_vault_secret" "microservice_db_connections" {
  for_each = toset(var.databases)
  
  name         = "${each.value}-db-connection"
  value        = "postgresql://${azurerm_postgresql_flexible_server.main.administrator_login}:${random_password.postgresql_admin.result}@${azurerm_postgresql_flexible_server.main.fqdn}:5432/${each.value}?sslmode=require"
  key_vault_id = var.key_vault_id
  
  tags = var.tags
}

# Monitoring and alerting
resource "azurerm_monitor_metric_alert" "postgresql_cpu" {
  name                = "postgresql-cpu-${var.project_name}-${var.environment}"
  resource_group_name = var.resource_group_name
  scopes              = [azurerm_postgresql_flexible_server.main.id]
  description         = "Action will be triggered when CPU usage is greater than 80%"
  
  criteria {
    metric_namespace = "Microsoft.DBforPostgreSQL/flexibleServers"
    metric_name      = "cpu_percent"
    aggregation      = "Average"
    operator         = "GreaterThan"
    threshold        = 80
  }
  
  action {
    action_group_id = var.action_group_id
  }
  
  tags = var.tags
}

resource "azurerm_monitor_metric_alert" "postgresql_memory" {
  name                = "postgresql-memory-${var.project_name}-${var.environment}"
  resource_group_name = var.resource_group_name
  scopes              = [azurerm_postgresql_flexible_server.main.id]
  description         = "Action will be triggered when memory usage is greater than 85%"
  
  criteria {
    metric_namespace = "Microsoft.DBforPostgreSQL/flexibleServers"
    metric_name      = "memory_percent"
    aggregation      = "Average"
    operator         = "GreaterThan"
    threshold        = 85
  }
  
  action {
    action_group_id = var.action_group_id
  }
  
  tags = var.tags
}

resource "azurerm_monitor_metric_alert" "redis_cpu" {
  name                = "redis-cpu-${var.project_name}-${var.environment}"
  resource_group_name = var.resource_group_name
  scopes              = [azurerm_redis_cache.main.id]
  description         = "Action will be triggered when Redis CPU usage is greater than 80%"
  
  criteria {
    metric_namespace = "Microsoft.Cache/Redis"
    metric_name      = "percentProcessorTime"
    aggregation      = "Average"
    operator         = "GreaterThan"
    threshold        = 80
  }
  
  action {
    action_group_id = var.action_group_id
  }
  
  tags = var.tags
}

resource "azurerm_monitor_metric_alert" "redis_memory" {
  name                = "redis-memory-${var.project_name}-${var.environment}"
  resource_group_name = var.resource_group_name
  scopes              = [azurerm_redis_cache.main.id]
  description         = "Action will be triggered when Redis memory usage is greater than 90%"
  
  criteria {
    metric_namespace = "Microsoft.Cache/Redis"
    metric_name      = "usedmemorypercentage"
    aggregation      = "Average"
    operator         = "GreaterThan"
    threshold        = 90
  }
  
  action {
    action_group_id = var.action_group_id
  }
  
  tags = var.tags
}
