#!/usr/bin/env python3

import json
import os
import glob

def fix_composer_json(file_path):
    """Fix composer.json by removing phpstan/phpstan-laravel and cleaning up formatting"""
    try:
        with open(file_path, 'r') as f:
            data = json.load(f)
        
        # Remove the problematic package if it exists
        if 'require-dev' in data and 'phpstan/phpstan-laravel' in data['require-dev']:
            del data['require-dev']['phpstan/phpstan-laravel']
            print(f"✅ Removed phpstan/phpstan-laravel from {file_path}")
        
        # Write back with proper formatting
        with open(file_path, 'w') as f:
            json.dump(data, f, indent=4, sort_keys=False)
        
        return True
    except Exception as e:
        print(f"❌ Error fixing {file_path}: {e}")
        return False

def main():
    print("🔧 Fixing composer.json files...")
    
    # Find all composer.json files in services directories
    composer_files = glob.glob("services/*/composer.json")
    
    fixed_count = 0
    for file_path in composer_files:
        if fix_composer_json(file_path):
            fixed_count += 1
    
    print(f"\n🎉 Fixed {fixed_count} out of {len(composer_files)} composer.json files")

if __name__ == "__main__":
    main()

