#!/usr/bin/env python3

import os
import re

# Service to database mapping
service_dbs = {
    'auction-service': 'reverse_tender',
    'bidding-service': 'reverse_tender_bidding', 
    'gateway-service': 'reverse_tender',
    'notification-service': 'reverse_tender_notifications',
    'order-service': 'reverse_tender_orders',
    'vin-ocr-service': 'reverse_tender_vehicles'
}

def get_db_config_template(db_name):
    return f"""        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('NEON_DATABASE_URL'),
            'host' => env('NEON_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('NEON_DB_PORT', env('DB_PORT', '5432')),
            'database' => env('NEON_DB_DATABASE', env('DB_DATABASE', '{db_name}')),
            'username' => env('NEON_DB_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('NEON_DB_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'options' => extension_loaded('pdo_pgsql') ? array_filter([
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]) : [],
            // Failover configuration
            'failover_priority' => 1,
            'failover_name' => 'neon_postgresql',
        ],

        'pgsql_secondary' => [
            'driver' => 'pgsql',
            'url' => env('CLOUD_DATABASE_URL'),
            'host' => env('CLOUD_DB_HOST', '127.0.0.1'),
            'port' => env('CLOUD_DB_PORT', '5432'),
            'database' => env('CLOUD_DB_DATABASE', '{db_name}'),
            'username' => env('CLOUD_DB_USERNAME', 'root'),
            'password' => env('CLOUD_DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            'options' => extension_loaded('pdo_pgsql') ? array_filter([
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]) : [],
            // Failover configuration
            'failover_priority' => 2,
            'failover_name' => 'cloud_postgresql',
        ],

        'mongodb' => [
            'driver' => 'mongodb',
            'host' => env('MONGO_DB_HOST', '127.0.0.1'),
            'port' => env('MONGO_DB_PORT', 27017),
            'database' => env('MONGO_DB_DATABASE', '{db_name}'),
            'username' => env('MONGO_DB_USERNAME'),
            'password' => env('MONGO_DB_PASSWORD'),
            'options' => [
                'database' => env('MONGO_DB_AUTHENTICATION_DATABASE', 'admin'),
                'retryWrites' => true,
                'w' => 'majority',
                'connectTimeoutMS' => 30000,
                'serverSelectionTimeoutMS' => 30000,
            ],
            // Failover configuration
            'failover_priority' => 3,
            'failover_name' => 'mongodb_atlas',
        ],"""

for service, db_name in service_dbs.items():
    config_file = f"services/{service}/config/database.php"
    
    if os.path.exists(config_file):
        print(f"Updating {service} with database {db_name}...")
        
        with open(config_file, 'r') as f:
            content = f.read()
        
        # Find and replace the pgsql section
        pattern = r"        'pgsql' => \[.*?\],\s*\n"
        replacement = get_db_config_template(db_name) + "\n\n"
        
        # Use re.DOTALL to match across newlines
        new_content = re.sub(pattern, replacement, content, flags=re.DOTALL)
        
        with open(config_file, 'w') as f:
            f.write(new_content)
            
        print(f"✅ Updated {service}")
    else:
        print(f"❌ Config file not found: {config_file}")

print("Database configuration update complete!")

