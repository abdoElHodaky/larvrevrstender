#!/bin/bash

# Redis Setup Script for Linode Instance
# This script installs and configures Redis server

set -e

# Update system packages
apt-get update
apt-get upgrade -y

# Install Redis
apt-get install -y redis-server

# Configure Redis
cat > /etc/redis/redis.conf << EOF
# Redis configuration for Reverse Tender Platform

# Network
bind 0.0.0.0
port 6379
protected-mode yes

# Authentication
requirepass ${redis_password}

# Memory management
maxmemory 256mb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
save 300 10
save 60 10000

# Logging
loglevel notice
logfile /var/log/redis/redis-server.log

# Security
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command DEBUG ""
rename-command CONFIG "CONFIG_b835c3f8a5d2e7f1"

# Performance
tcp-keepalive 300
timeout 0
tcp-backlog 511

# Append only file
appendonly yes
appendfsync everysec
no-appendfsync-on-rewrite no
auto-aof-rewrite-percentage 100
auto-aof-rewrite-min-size 64mb

# Slow log
slowlog-log-slower-than 10000
slowlog-max-len 128

# Client output buffer limits
client-output-buffer-limit normal 0 0 0
client-output-buffer-limit replica 256mb 64mb 60
client-output-buffer-limit pubsub 32mb 8mb 60

# Memory usage
hash-max-ziplist-entries 512
hash-max-ziplist-value 64
list-max-ziplist-size -2
list-compress-depth 0
set-max-intset-entries 512
zset-max-ziplist-entries 128
zset-max-ziplist-value 64
hll-sparse-max-bytes 3000
stream-node-max-bytes 4096
stream-node-max-entries 100

# Active rehashing
activerehashing yes

# Client
hz 10

# AOF rewrite
aof-rewrite-incremental-fsync yes

# RDB checksum
rdbchecksum yes

# Stop writes on bgsave error
stop-writes-on-bgsave-error yes

# Compression
rdbcompression yes

# Supervised
supervised systemd
EOF

# Set proper permissions
chown redis:redis /etc/redis/redis.conf
chmod 640 /etc/redis/redis.conf

# Create log directory
mkdir -p /var/log/redis
chown redis:redis /var/log/redis

# Enable and start Redis service
systemctl enable redis-server
systemctl restart redis-server

# Configure firewall (if ufw is installed)
if command -v ufw &> /dev/null; then
    ufw allow 6379/tcp
fi

# Create Redis monitoring script
cat > /usr/local/bin/redis-health-check.sh << 'EOF'
#!/bin/bash

# Redis health check script
REDIS_CLI="/usr/bin/redis-cli"
REDIS_PASSWORD="${redis_password}"

# Check if Redis is running
if ! pgrep -x "redis-server" > /dev/null; then
    echo "Redis server is not running"
    exit 1
fi

# Check if Redis is responding
if ! $REDIS_CLI -a "$REDIS_PASSWORD" ping | grep -q "PONG"; then
    echo "Redis is not responding to ping"
    exit 1
fi

# Check memory usage
MEMORY_USAGE=$($REDIS_CLI -a "$REDIS_PASSWORD" info memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')
echo "Redis is healthy. Memory usage: $MEMORY_USAGE"
exit 0
EOF

chmod +x /usr/local/bin/redis-health-check.sh

# Create systemd service for health monitoring
cat > /etc/systemd/system/redis-health-monitor.service << EOF
[Unit]
Description=Redis Health Monitor
After=redis-server.service

[Service]
Type=oneshot
ExecStart=/usr/local/bin/redis-health-check.sh
User=redis
Group=redis

[Install]
WantedBy=multi-user.target
EOF

# Create timer for health monitoring
cat > /etc/systemd/system/redis-health-monitor.timer << EOF
[Unit]
Description=Run Redis Health Monitor every 5 minutes
Requires=redis-health-monitor.service

[Timer]
OnCalendar=*:0/5
Persistent=true

[Install]
WantedBy=timers.target
EOF

# Enable health monitoring
systemctl daemon-reload
systemctl enable redis-health-monitor.timer
systemctl start redis-health-monitor.timer

# Install Redis monitoring tools
apt-get install -y redis-tools

# Create backup script
cat > /usr/local/bin/redis-backup.sh << 'EOF'
#!/bin/bash

# Redis backup script
BACKUP_DIR="/var/backups/redis"
DATE=$(date +%Y%m%d_%H%M%S)
REDIS_CLI="/usr/bin/redis-cli"
REDIS_PASSWORD="${redis_password}"

# Create backup directory
mkdir -p $BACKUP_DIR

# Create backup
$REDIS_CLI -a "$REDIS_PASSWORD" --rdb $BACKUP_DIR/redis_backup_$DATE.rdb

# Keep only last 7 days of backups
find $BACKUP_DIR -name "redis_backup_*.rdb" -mtime +7 -delete

echo "Redis backup completed: $BACKUP_DIR/redis_backup_$DATE.rdb"
EOF

chmod +x /usr/local/bin/redis-backup.sh

# Create daily backup cron job
echo "0 2 * * * root /usr/local/bin/redis-backup.sh" >> /etc/crontab

# Final status check
echo "Redis installation and configuration completed successfully!"
echo "Redis status:"
systemctl status redis-server --no-pager
echo ""
echo "Redis info:"
redis-cli -a "${redis_password}" info server | head -10

# Log completion
echo "$(date): Redis setup completed successfully" >> /var/log/redis-setup.log
