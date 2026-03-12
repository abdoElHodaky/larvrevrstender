#!/usr/bin/env python3

"""
Phase 1: Constructor Property Promotion Modernization
Automatically modernize PHP constructors to use PHP 8.0+ constructor property promotion
"""

import os
import re
import glob

def modernize_constructor(file_path):
    """Modernize constructor property promotion in a PHP file"""
    
    with open(file_path, 'r') as f:
        content = f.read()
    
    # Pattern to match traditional constructor pattern
    pattern = r'(class\s+\w+[^{]*\{)\s*(?:\n\s*(?:private|protected|public)\s+[^;]+\$\w+;)*\s*\n\s*public\s+function\s+__construct\([^)]*\)\s*\{[^}]*\}'
    
    # More specific pattern for property + constructor combination
    prop_constructor_pattern = r'((?:(?:private|protected|public)\s+(?:readonly\s+)?[^;]+\$\w+;\s*\n\s*)*)\s*public\s+function\s+__construct\(([^)]*)\)\s*\{\s*((?:\$this->\w+\s*=\s*\$\w+;\s*)*)\s*\}'
    
    # Find all matches
    matches = re.finditer(prop_constructor_pattern, content, re.MULTILINE | re.DOTALL)
    
    changes_made = False
    
    for match in matches:
        properties = match.group(1).strip()
        constructor_params = match.group(2).strip()
        assignments = match.group(3).strip()
        
        # Parse properties
        prop_lines = [line.strip() for line in properties.split('\n') if line.strip()]
        property_info = {}
        
        for prop_line in prop_lines:
            if prop_line.endswith(';'):
                # Extract property info
                prop_match = re.match(r'(private|protected|public)\s+(readonly\s+)?([^$]+)\$(\w+);', prop_line)
                if prop_match:
                    visibility = prop_match.group(1)
                    readonly = prop_match.group(2) or ''
                    type_hint = prop_match.group(3).strip()
                    prop_name = prop_match.group(4)
                    property_info[prop_name] = {
                        'visibility': visibility,
                        'readonly': readonly.strip(),
                        'type': type_hint
                    }
        
        # Parse constructor parameters
        params = [p.strip() for p in constructor_params.split(',') if p.strip()]
        param_info = {}
        
        for param in params:
            param_match = re.match(r'([^$]+)\$(\w+)', param)
            if param_match:
                param_type = param_match.group(1).strip()
                param_name = param_match.group(2)
                param_info[param_name] = param_type
        
        # Check if we can apply constructor property promotion
        can_promote = True
        promoted_params = []
        
        for prop_name, prop_data in property_info.items():
            if prop_name in param_info:
                # Check if assignment exists
                assignment_pattern = f'\\$this->{prop_name}\\s*=\\s*\\${prop_name};'
                if re.search(assignment_pattern, assignments):
                    # Can promote this property
                    readonly_keyword = 'readonly ' if prop_data['readonly'] else ''
                    promoted_param = f"{prop_data['visibility']} {readonly_keyword}{prop_data['type']}${prop_name}"
                    promoted_params.append(promoted_param)
                else:
                    can_promote = False
                    break
            else:
                can_promote = False
                break
        
        if can_promote and promoted_params:
            # Create new constructor
            if len(promoted_params) == 1:
                new_constructor = f"public function __construct(\n        {promoted_params[0]}\n    ) {{\n    }}"
            else:
                param_str = ',\n        '.join(promoted_params)
                new_constructor = f"public function __construct(\n        {param_str}\n    ) {{\n    }}"
            
            # Replace the old pattern
            old_pattern = properties + '\n\n    ' + match.group(0).split('public function __construct')[1]
            new_pattern = new_constructor
            
            content = content.replace(match.group(0), new_constructor)
            # Remove the property declarations
            for prop_line in prop_lines:
                if prop_line.endswith(';'):
                    content = content.replace(prop_line + '\n', '')
                    content = content.replace('    ' + prop_line + '\n', '')
            
            changes_made = True
    
    if changes_made:
        with open(file_path, 'w') as f:
            f.write(content)
        return True
    
    return False

def process_service(service_name):
    """Process all PHP files in a service"""
    service_dir = f"services/{service_name}"
    
    if not os.path.exists(service_dir):
        print(f"❌ {service_dir} not found")
        return
    
    print(f"🔧 Processing {service_name}...")
    
    # Find all PHP controller files
    php_files = glob.glob(f"{service_dir}/app/Http/Controllers/*.php")
    
    changes_count = 0
    
    for php_file in php_files:
        if os.path.basename(php_file) != 'Controller.php':  # Skip base controller
            if modernize_constructor(php_file):
                print(f"  ✅ Modernized {os.path.basename(php_file)}")
                changes_count += 1
            else:
                print(f"  ⚪ No changes needed for {os.path.basename(php_file)}")
    
    print(f"  📊 {changes_count} files modernized in {service_name}")

def main():
    """Main function to process all services"""
    services = [
        "user-service",
        "analytics-service", 
        "auction-service",
        "bidding-service",
        "gateway-service",
        "notification-service",
        "order-service",
        "payment-service",
        "vin-ocr-service"
    ]
    
    print("🚀 Starting Constructor Property Promotion Modernization")
    print("=" * 60)
    
    total_changes = 0
    
    for service in services:
        process_service(service)
        print()
    
    print("🎉 Constructor Property Promotion Modernization Complete!")
    print("📋 Benefits:")
    print("   ✅ Reduced boilerplate code")
    print("   ✅ Improved readability")
    print("   ✅ Better type safety with readonly properties")
    print("   ✅ Modern PHP 8.3 syntax")

if __name__ == "__main__":
    main()

