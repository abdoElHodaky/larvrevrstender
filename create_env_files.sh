#!/bin/bash

echo "🔧 Creating .env files from .env.example templates..."

# Find all .env.example files and create corresponding .env files
find services -name ".env.example" -type f | while read example_file; do
    env_file="${example_file%.example}"
    service_name=$(echo "$example_file" | cut -d'/' -f2)
    
    if [ ! -f "$env_file" ]; then
        cp "$example_file" "$env_file"
        echo "✅ Created $env_file for $service_name"
    else
        echo "⚠️  $env_file already exists for $service_name"
    fi
done

echo "🎯 Environment files creation completed!"
echo ""
echo "⚠️  IMPORTANT: You need to configure the following in each .env file:"
echo "   - Database credentials"
echo "   - RPC authentication tokens"
echo "   - Service endpoint URLs"
echo "   - Cache/Redis configuration"
