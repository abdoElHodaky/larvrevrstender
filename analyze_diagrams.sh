#!/bin/bash

# Comprehensive Diagram Analysis Script
# Analyzes all Mermaid diagrams for syntax errors and rendering issues

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Counters
TOTAL_FILES=0
TOTAL_DIAGRAMS=0
BROKEN_DIAGRAMS=0
WARNINGS=0

# Results arrays
declare -a BROKEN_FILES=()
declare -a WARNING_FILES=()
declare -a GOOD_FILES=()

echo -e "${BLUE}🔍 Starting Comprehensive Diagram Analysis${NC}"
echo "=================================================="

# Function to analyze a single file
analyze_file() {
    local file="$1"
    local file_issues=0
    local file_warnings=0
    
    echo -e "\n${CYAN}📄 Analyzing: $file${NC}"
    
    # Check if file exists
    if [[ ! -f "$file" ]]; then
        echo -e "${RED}❌ File not found: $file${NC}"
        return 1
    fi
    
    # Count mermaid blocks
    local mermaid_count=$(grep -c '```mermaid' "$file" 2>/dev/null || echo "0")
    local closing_count=$(grep -c '^```$' "$file" 2>/dev/null || echo "0")
    
    echo -e "   📊 Mermaid blocks found: $mermaid_count"
    echo -e "   🔚 Closing blocks found: $closing_count"
    
    TOTAL_DIAGRAMS=$((TOTAL_DIAGRAMS + mermaid_count))
    
    # Check for unmatched blocks
    if [[ $mermaid_count -gt $closing_count ]]; then
        echo -e "${RED}❌ CRITICAL: Unmatched mermaid blocks (missing closing tags)${NC}"
        file_issues=$((file_issues + 1))
    elif [[ $mermaid_count -lt $closing_count ]]; then
        echo -e "${YELLOW}⚠️  WARNING: More closing tags than mermaid blocks${NC}"
        file_warnings=$((file_warnings + 1))
    fi
    
    # Extract and analyze each mermaid block
    local block_num=1
    local in_mermaid=false
    local current_block=""
    local line_num=0
    
    while IFS= read -r line; do
        line_num=$((line_num + 1))
        
        if [[ "$line" == '```mermaid' ]]; then
            in_mermaid=true
            current_block=""
            echo -e "   🔍 Block $block_num (line $line_num):"
        elif [[ "$line" == '```' ]] && [[ "$in_mermaid" == true ]]; then
            in_mermaid=false
            
            # Analyze the block content
            if [[ -z "$current_block" ]]; then
                echo -e "${RED}      ❌ Empty mermaid block${NC}"
                file_issues=$((file_issues + 1))
            else
                # Check for common diagram types
                if echo "$current_block" | grep -q "sequenceDiagram\|graph\|flowchart\|classDiagram\|stateDiagram\|erDiagram\|journey\|gantt\|pie\|gitgraph"; then
                    echo -e "${GREEN}      ✅ Valid diagram type detected${NC}"
                else
                    echo -e "${YELLOW}      ⚠️  Unknown or missing diagram type${NC}"
                    file_warnings=$((file_warnings + 1))
                fi
                
                # Check for syntax issues
                if echo "$current_block" | grep -q "%%{init:"; then
                    if echo "$current_block" | grep -q "}%%"; then
                        echo -e "${GREEN}      ✅ Theme configuration syntax OK${NC}"
                    else
                        echo -e "${RED}      ❌ Malformed theme configuration${NC}"
                        file_issues=$((file_issues + 1))
                    fi
                fi
                
                # Check for common syntax patterns
                if echo "$current_block" | grep -q "-->.*-->.*-->"; then
                    echo -e "${YELLOW}      ⚠️  Complex arrow chains detected (may cause rendering issues)${NC}"
                    file_warnings=$((file_warnings + 1))
                fi
                
                # Check for special characters that might break rendering
                if echo "$current_block" | grep -q "[{}]" && ! echo "$current_block" | grep -q "%%{init:"; then
                    echo -e "${YELLOW}      ⚠️  Unescaped special characters detected${NC}"
                    file_warnings=$((file_warnings + 1))
                fi
            fi
            
            block_num=$((block_num + 1))
        elif [[ "$in_mermaid" == true ]]; then
            current_block="$current_block$line"$'\n'
        fi
    done < "$file"
    
    # Check if we ended inside a mermaid block
    if [[ "$in_mermaid" == true ]]; then
        echo -e "${RED}❌ CRITICAL: File ends inside mermaid block (missing closing tag)${NC}"
        file_issues=$((file_issues + 1))
    fi
    
    # Categorize file
    if [[ $file_issues -gt 0 ]]; then
        BROKEN_FILES+=("$file")
        BROKEN_DIAGRAMS=$((BROKEN_DIAGRAMS + file_issues))
        echo -e "${RED}📋 Result: BROKEN ($file_issues issues)${NC}"
    elif [[ $file_warnings -gt 0 ]]; then
        WARNING_FILES+=("$file")
        WARNINGS=$((WARNINGS + file_warnings))
        echo -e "${YELLOW}📋 Result: WARNINGS ($file_warnings warnings)${NC}"
    else
        GOOD_FILES+=("$file")
        echo -e "${GREEN}📋 Result: GOOD${NC}"
    fi
    
    return 0
}

# Find all files with mermaid diagrams
echo -e "\n${BLUE}🔍 Discovering files with Mermaid diagrams...${NC}"
MERMAID_FILES=$(grep -l '```mermaid' **/*.md 2>/dev/null || true)

if [[ -z "$MERMAID_FILES" ]]; then
    echo -e "${RED}❌ No Mermaid diagrams found!${NC}"
    exit 1
fi

echo -e "${GREEN}📁 Found files with Mermaid diagrams:${NC}"
for file in $MERMAID_FILES; do
    echo "   📄 $file"
    TOTAL_FILES=$((TOTAL_FILES + 1))
done

# Analyze each file
echo -e "\n${BLUE}🔍 Starting detailed analysis...${NC}"
for file in $MERMAID_FILES; do
    analyze_file "$file"
done

# Generate summary report
echo -e "\n${PURPLE}📊 COMPREHENSIVE ANALYSIS SUMMARY${NC}"
echo "=================================================="
echo -e "${BLUE}📈 Statistics:${NC}"
echo "   📁 Total files analyzed: $TOTAL_FILES"
echo "   📊 Total diagrams found: $TOTAL_DIAGRAMS"
echo -e "   ${RED}❌ Broken diagrams: $BROKEN_DIAGRAMS${NC}"
echo -e "   ${YELLOW}⚠️  Warnings: $WARNINGS${NC}"

echo -e "\n${GREEN}✅ GOOD FILES (${#GOOD_FILES[@]}):${NC}"
for file in "${GOOD_FILES[@]}"; do
    echo "   ✅ $file"
done

if [[ ${#WARNING_FILES[@]} -gt 0 ]]; then
    echo -e "\n${YELLOW}⚠️  FILES WITH WARNINGS (${#WARNING_FILES[@]}):${NC}"
    for file in "${WARNING_FILES[@]}"; do
        echo "   ⚠️  $file"
    done
fi

if [[ ${#BROKEN_FILES[@]} -gt 0 ]]; then
    echo -e "\n${RED}❌ BROKEN FILES (${#BROKEN_FILES[@]}):${NC}"
    for file in "${BROKEN_FILES[@]}"; do
        echo "   ❌ $file"
    done
fi

# Overall health assessment
echo -e "\n${PURPLE}🏥 OVERALL HEALTH ASSESSMENT:${NC}"
if [[ $BROKEN_DIAGRAMS -eq 0 ]]; then
    if [[ $WARNINGS -eq 0 ]]; then
        echo -e "${GREEN}🎉 EXCELLENT: All diagrams are healthy!${NC}"
    else
        echo -e "${YELLOW}👍 GOOD: No broken diagrams, but some warnings to address${NC}"
    fi
else
    if [[ $BROKEN_DIAGRAMS -lt 5 ]]; then
        echo -e "${YELLOW}⚠️  FAIR: Few broken diagrams need attention${NC}"
    else
        echo -e "${RED}🚨 POOR: Multiple broken diagrams require immediate attention${NC}"
    fi
fi

# Exit with appropriate code
if [[ $BROKEN_DIAGRAMS -gt 0 ]]; then
    exit 1
else
    exit 0
fi
