#!/bin/bash

# MySQL Performance Benchmarking Script for Laravel Microservices
# This script performs comprehensive performance analysis of all MySQL databases

set -e

# Configuration
MYSQL_HOST="${DB_HOST:-mysql}"
MYSQL_PORT="${DB_PORT:-3306}"
MYSQL_USER="${DB_USERNAME:-root}"
MYSQL_PASSWORD="${DB_PASSWORD:-root_password}"
BENCHMARK_DIR="migration/benchmarks"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Create benchmark directory
mkdir -p "$BENCHMARK_DIR"

# Services and their databases
declare -A SERVICES=(
    ["gateway-service"]="gateway_service"
    ["auth-service"]="auth_service"
    ["user-service"]="user_service"
    ["analytics-service"]="analytics_service"
    ["order-service"]="order_service"
    ["payment-service"]="payment_service"
    ["bidding-service"]="bidding_service"
    ["auction-service"]="auction_service"
    ["notification-service"]="notification_service"
    ["vin-ocr-service"]="vin_ocr_service"
)

echo "🚀 Starting MySQL Performance Benchmarking - $TIMESTAMP"
echo "=================================================="

# Function to check if MySQL is accessible
check_mysql_connection() {
    echo "🔍 Checking MySQL connection..."
    if ! mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SELECT 1;" >/dev/null 2>&1; then
        echo "❌ Cannot connect to MySQL. Please ensure MySQL is running and credentials are correct."
        echo "   Host: $MYSQL_HOST:$MYSQL_PORT"
        echo "   User: $MYSQL_USER"
        exit 1
    fi
    echo "✅ MySQL connection successful"
}

# Function to get database size and table count
analyze_database_structure() {
    local service=$1
    local database=$2
    
    echo "📊 Analyzing database structure for $service..."
    
    mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
        SELECT 
            '$service' as service_name,
            '$database' as database_name,
            COUNT(*) as table_count,
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
            ROUND(SUM(data_length) / 1024 / 1024, 2) AS data_mb,
            ROUND(SUM(index_length) / 1024 / 1024, 2) AS index_mb
        FROM information_schema.tables 
        WHERE table_schema = '$database'
        GROUP BY table_schema;
    " >> "$BENCHMARK_DIR/database_sizes_$TIMESTAMP.csv"
    
    # Get table-level details
    mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
        SELECT 
            '$service' as service_name,
            table_name,
            table_rows,
            ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
            engine
        FROM information_schema.tables 
        WHERE table_schema = '$database'
        ORDER BY (data_length + index_length) DESC;
    " >> "$BENCHMARK_DIR/table_details_${service}_$TIMESTAMP.csv"
}

# Function to analyze query patterns using performance schema
analyze_query_patterns() {
    local service=$1
    local database=$2
    
    echo "🔍 Analyzing query patterns for $service..."
    
    # Enable performance schema if not already enabled
    mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
        UPDATE performance_schema.setup_consumers 
        SET enabled = 'YES' 
        WHERE name LIKE 'events_statements_%';
    " 2>/dev/null || echo "⚠️  Performance schema may not be fully available"
    
    # Get top queries by execution time
    mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
        SELECT 
            '$service' as service_name,
            SUBSTRING(digest_text, 1, 100) as query_sample,
            count_star as execution_count,
            ROUND(avg_timer_wait / 1000000000000, 6) as avg_execution_time_sec,
            ROUND(sum_timer_wait / 1000000000000, 6) as total_execution_time_sec,
            ROUND(sum_rows_examined / count_star, 2) as avg_rows_examined
        FROM performance_schema.events_statements_summary_by_digest 
        WHERE schema_name = '$database'
        ORDER BY sum_timer_wait DESC 
        LIMIT 20;
    " >> "$BENCHMARK_DIR/query_patterns_${service}_$TIMESTAMP.csv" 2>/dev/null || echo "⚠️  Could not analyze query patterns for $service"
}

# Function to run sysbench if available
run_sysbench() {
    local database=$1
    
    if command -v sysbench >/dev/null 2>&1; then
        echo "🏃 Running sysbench for $database..."
        
        # Prepare sysbench test
        sysbench oltp_read_write \
            --mysql-host="$MYSQL_HOST" \
            --mysql-port="$MYSQL_PORT" \
            --mysql-user="$MYSQL_USER" \
            --mysql-password="$MYSQL_PASSWORD" \
            --mysql-db="$database" \
            --tables=4 \
            --table-size=10000 \
            prepare > "$BENCHMARK_DIR/sysbench_prepare_${database}_$TIMESTAMP.log" 2>&1
        
        # Run benchmark
        sysbench oltp_read_write \
            --mysql-host="$MYSQL_HOST" \
            --mysql-port="$MYSQL_PORT" \
            --mysql-user="$MYSQL_USER" \
            --mysql-password="$MYSQL_PASSWORD" \
            --mysql-db="$database" \
            --tables=4 \
            --table-size=10000 \
            --threads=8 \
            --time=60 \
            --report-interval=10 \
            run > "$BENCHMARK_DIR/sysbench_results_${database}_$TIMESTAMP.log" 2>&1
        
        # Cleanup
        sysbench oltp_read_write \
            --mysql-host="$MYSQL_HOST" \
            --mysql-port="$MYSQL_PORT" \
            --mysql-user="$MYSQL_USER" \
            --mysql-password="$MYSQL_PASSWORD" \
            --mysql-db="$database" \
            --tables=4 \
            cleanup > "$BENCHMARK_DIR/sysbench_cleanup_${database}_$TIMESTAMP.log" 2>&1
            
        echo "✅ Sysbench completed for $database"
    else
        echo "⚠️  Sysbench not available, skipping performance benchmarking"
    fi
}

# Function to get MySQL configuration
get_mysql_config() {
    echo "⚙️  Capturing MySQL configuration..."
    
    mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
        SHOW VARIABLES;
    " > "$BENCHMARK_DIR/mysql_variables_$TIMESTAMP.txt"
    
    mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
        SHOW GLOBAL STATUS;
    " > "$BENCHMARK_DIR/mysql_status_$TIMESTAMP.txt"
}

# Function to analyze slow queries if slow query log is enabled
analyze_slow_queries() {
    echo "🐌 Checking for slow query analysis..."
    
    local slow_query_log=$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SHOW VARIABLES LIKE 'slow_query_log';" | grep slow_query_log | awk '{print $2}')
    
    if [ "$slow_query_log" = "ON" ]; then
        echo "✅ Slow query log is enabled"
        mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
            SELECT 
                start_time,
                user_host,
                query_time,
                lock_time,
                rows_sent,
                rows_examined,
                db,
                SUBSTRING(sql_text, 1, 200) as sql_sample
            FROM mysql.slow_log 
            ORDER BY start_time DESC 
            LIMIT 50;
        " > "$BENCHMARK_DIR/slow_queries_$TIMESTAMP.csv" 2>/dev/null || echo "⚠️  Could not access slow query log"
    else
        echo "⚠️  Slow query log is not enabled"
    fi
}

# Main execution
main() {
    check_mysql_connection
    get_mysql_config
    analyze_slow_queries
    
    # Initialize CSV headers
    echo "service_name,database_name,table_count,size_mb,data_mb,index_mb" > "$BENCHMARK_DIR/database_sizes_$TIMESTAMP.csv"
    
    # Analyze each service database
    for service in "${!SERVICES[@]}"; do
        database="${SERVICES[$service]}"
        
        echo ""
        echo "🔄 Processing $service (database: $database)"
        echo "----------------------------------------"
        
        # Check if database exists
        if mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "USE $database;" 2>/dev/null; then
            analyze_database_structure "$service" "$database"
            analyze_query_patterns "$service" "$database"
            
            # Run sysbench for a few key databases
            if [[ "$service" == "gateway-service" || "$service" == "auth-service" || "$service" == "analytics-service" ]]; then
                run_sysbench "$database"
            fi
        else
            echo "⚠️  Database $database does not exist, skipping $service"
        fi
    done
    
    echo ""
    echo "📈 Generating summary report..."
    
    # Generate summary
    cat > "$BENCHMARK_DIR/benchmark_summary_$TIMESTAMP.md" << EOF
# MySQL Benchmark Summary - $TIMESTAMP

## Database Overview
$(cat "$BENCHMARK_DIR/database_sizes_$TIMESTAMP.csv" | column -t -s,)

## Key Findings
- Total databases analyzed: $(wc -l < "$BENCHMARK_DIR/database_sizes_$TIMESTAMP.csv" | tr -d ' ')
- Benchmark timestamp: $TIMESTAMP
- MySQL Host: $MYSQL_HOST:$MYSQL_PORT

## Files Generated
- Database sizes: database_sizes_$TIMESTAMP.csv
- Table details: table_details_*_$TIMESTAMP.csv
- Query patterns: query_patterns_*_$TIMESTAMP.csv
- MySQL configuration: mysql_variables_$TIMESTAMP.txt
- MySQL status: mysql_status_$TIMESTAMP.txt
- Slow queries: slow_queries_$TIMESTAMP.csv
- Sysbench results: sysbench_results_*_$TIMESTAMP.log

## Next Steps
1. Review database sizes to prioritize migration order
2. Analyze query patterns to identify optimization opportunities
3. Use sysbench results as baseline for PostgreSQL comparison
4. Review slow queries for potential PostgreSQL optimizations

EOF
    
    echo "✅ Benchmarking completed successfully!"
    echo "📁 Results saved in: $BENCHMARK_DIR/"
    echo "📊 Summary report: $BENCHMARK_DIR/benchmark_summary_$TIMESTAMP.md"
}

# Run main function
main "$@"

