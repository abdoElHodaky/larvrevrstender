# Azure Networking Module for Reverse Tender Platform

# Virtual Network
resource "azurerm_virtual_network" "main" {
  name                = "vnet-${var.project_name}-${var.environment}"
  address_space       = var.vnet_address_space
  location            = var.location
  resource_group_name = var.resource_group_name
  
  tags = var.tags
}

# AKS Subnet
resource "azurerm_subnet" "aks" {
  name                 = "snet-aks-${var.project_name}-${var.environment}"
  resource_group_name  = var.resource_group_name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = var.subnet_address_prefixes.aks_subnet
  
  # Delegate subnet to AKS
  delegation {
    name = "aks-delegation"
    service_delegation {
      name    = "Microsoft.ContainerService/managedClusters"
      actions = ["Microsoft.Network/virtualNetworks/subnets/join/action"]
    }
  }
}

# Container Apps Subnet
resource "azurerm_subnet" "container_apps" {
  name                 = "snet-ca-${var.project_name}-${var.environment}"
  resource_group_name  = var.resource_group_name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = var.subnet_address_prefixes.container_apps_subnet
  
  # Delegate subnet to Container Apps
  delegation {
    name = "container-apps-delegation"
    service_delegation {
      name    = "Microsoft.App/environments"
      actions = ["Microsoft.Network/virtualNetworks/subnets/join/action"]
    }
  }
}

# Database Subnet
resource "azurerm_subnet" "database" {
  name                 = "snet-db-${var.project_name}-${var.environment}"
  resource_group_name  = var.resource_group_name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = var.subnet_address_prefixes.database_subnet
  
  # Delegate subnet to PostgreSQL
  delegation {
    name = "postgresql-delegation"
    service_delegation {
      name    = "Microsoft.DBforPostgreSQL/flexibleServers"
      actions = ["Microsoft.Network/virtualNetworks/subnets/join/action"]
    }
  }
  
  service_endpoints = ["Microsoft.Storage"]
}

# Private Subnet for internal services
resource "azurerm_subnet" "private" {
  name                 = "snet-private-${var.project_name}-${var.environment}"
  resource_group_name  = var.resource_group_name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = var.subnet_address_prefixes.private_subnet
  
  service_endpoints = ["Microsoft.KeyVault", "Microsoft.Storage"]
}

# Application Gateway Subnet
resource "azurerm_subnet" "application_gateway" {
  name                 = "snet-agw-${var.project_name}-${var.environment}"
  resource_group_name  = var.resource_group_name
  virtual_network_name = azurerm_virtual_network.main.name
  address_prefixes     = var.subnet_address_prefixes.application_gateway_subnet
}

# Network Security Group for AKS
resource "azurerm_network_security_group" "aks" {
  name                = "nsg-aks-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name

  # Allow HTTP traffic
  security_rule {
    name                       = "AllowHTTP"
    priority                   = 1001
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "80"
    source_address_prefix      = "*"
    destination_address_prefix = "*"
  }

  # Allow HTTPS traffic
  security_rule {
    name                       = "AllowHTTPS"
    priority                   = 1002
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "443"
    source_address_prefix      = "*"
    destination_address_prefix = "*"
  }

  # Allow Kubernetes API server
  security_rule {
    name                       = "AllowKubernetesAPI"
    priority                   = 1003
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "6443"
    source_address_prefix      = "VirtualNetwork"
    destination_address_prefix = "*"
  }

  tags = var.tags
}

# Network Security Group for Database
resource "azurerm_network_security_group" "database" {
  name                = "nsg-db-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name

  # Allow PostgreSQL traffic from AKS and Container Apps subnets
  security_rule {
    name                       = "AllowPostgreSQL"
    priority                   = 1001
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "5432"
    source_address_prefixes    = concat(
      var.subnet_address_prefixes.aks_subnet,
      var.subnet_address_prefixes.container_apps_subnet
    )
    destination_address_prefix = "*"
  }

  # Allow Redis traffic from AKS and Container Apps subnets
  security_rule {
    name                       = "AllowRedis"
    priority                   = 1002
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "6379"
    source_address_prefixes    = concat(
      var.subnet_address_prefixes.aks_subnet,
      var.subnet_address_prefixes.container_apps_subnet
    )
    destination_address_prefix = "*"
  }

  tags = var.tags
}

# Network Security Group for Private Subnet
resource "azurerm_network_security_group" "private" {
  name                = "nsg-private-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name

  # Allow Key Vault access
  security_rule {
    name                       = "AllowKeyVault"
    priority                   = 1001
    direction                  = "Inbound"
    access                     = "Allow"
    protocol                   = "Tcp"
    source_port_range          = "*"
    destination_port_range     = "443"
    source_address_prefix      = "VirtualNetwork"
    destination_address_prefix = "*"
  }

  tags = var.tags
}

# Associate NSGs with subnets
resource "azurerm_subnet_network_security_group_association" "aks" {
  subnet_id                 = azurerm_subnet.aks.id
  network_security_group_id = azurerm_network_security_group.aks.id
}

resource "azurerm_subnet_network_security_group_association" "database" {
  subnet_id                 = azurerm_subnet.database.id
  network_security_group_id = azurerm_network_security_group.database.id
}

resource "azurerm_subnet_network_security_group_association" "private" {
  subnet_id                 = azurerm_subnet.private.id
  network_security_group_id = azurerm_network_security_group.private.id
}

# Route Table for custom routing
resource "azurerm_route_table" "main" {
  name                = "rt-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name

  # Route to internet via Azure Firewall (if needed)
  route {
    name           = "DefaultRoute"
    address_prefix = "0.0.0.0/0"
    next_hop_type  = "Internet"
  }

  tags = var.tags
}

# Associate route table with AKS subnet
resource "azurerm_subnet_route_table_association" "aks" {
  subnet_id      = azurerm_subnet.aks.id
  route_table_id = azurerm_route_table.main.id
}

# Public IP for Application Gateway
resource "azurerm_public_ip" "application_gateway" {
  name                = "pip-agw-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name
  allocation_method   = "Static"
  sku                = "Standard"
  
  tags = var.tags
}

# DNS Zone (optional)
resource "azurerm_dns_zone" "main" {
  count               = var.create_dns_zone ? 1 : 0
  name                = var.dns_zone_name
  resource_group_name = var.resource_group_name
  
  tags = var.tags
}

# Private DNS Zone for internal services
resource "azurerm_private_dns_zone" "internal" {
  name                = "${var.project_name}-${var.environment}.internal"
  resource_group_name = var.resource_group_name
  
  tags = var.tags
}

# Link private DNS zone to VNet
resource "azurerm_private_dns_zone_virtual_network_link" "internal" {
  name                  = "link-${var.project_name}-${var.environment}"
  resource_group_name   = var.resource_group_name
  private_dns_zone_name = azurerm_private_dns_zone.internal.name
  virtual_network_id    = azurerm_virtual_network.main.id
  registration_enabled  = true
  
  tags = var.tags
}

# NAT Gateway for outbound connectivity (optional)
resource "azurerm_public_ip" "nat_gateway" {
  count               = var.enable_nat_gateway ? 1 : 0
  name                = "pip-nat-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name
  allocation_method   = "Static"
  sku                = "Standard"
  
  tags = var.tags
}

resource "azurerm_nat_gateway" "main" {
  count               = var.enable_nat_gateway ? 1 : 0
  name                = "nat-${var.project_name}-${var.environment}"
  location            = var.location
  resource_group_name = var.resource_group_name
  sku_name           = "Standard"
  
  tags = var.tags
}

resource "azurerm_nat_gateway_public_ip_association" "main" {
  count                = var.enable_nat_gateway ? 1 : 0
  nat_gateway_id       = azurerm_nat_gateway.main[0].id
  public_ip_address_id = azurerm_public_ip.nat_gateway[0].id
}

resource "azurerm_subnet_nat_gateway_association" "aks" {
  count          = var.enable_nat_gateway ? 1 : 0
  subnet_id      = azurerm_subnet.aks.id
  nat_gateway_id = azurerm_nat_gateway.main[0].id
}
