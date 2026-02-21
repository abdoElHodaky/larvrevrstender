# Multi-stage Dockerfile for Shared Service Infrastructure
# Optimized for cross-service communication and procedure execution

# Build stage - Install dependencies and optimize
FROM serversideup/php:8.3-frankenphp AS builder

USER root

# Install build dependencies for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    zip \
    intl \
    xml \
    curl \
    mbstring

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better layer caching
COPY composer.json composer.lock* ./

# Install PHP dependencies with optimizations
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --optimize-autoloader \
    --classmap-authoritative \
    && composer clear-cache

# Copy application source code
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --classmap-authoritative

# Production stage - Minimal runtime image
FROM serversideup/php:8.3-frankenphp AS production

USER root

# Install runtime dependencies for PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq5 \
    libxml2 \
    libcurl4 \
    curl \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/* && apt-get clean

# Install required PHP extensions for runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    zip \
    intl \
    xml \
    curl \
    mbstring \
    gd \
    && docker-php-ext-enable opcache

# Set labels for container metadata
LABEL maintainer="Reverse Tender Team <dev@reversetender.com>"
LABEL description="Cross-service infrastructure for Laravel microservices"
LABEL version="1.0.0"

# Set working directory
WORKDIR /var/www/html

# Copy optimized application from builder stage
COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configure PHP for shared service operations
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_REVALIDATE_FREQ=0 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=10000 \
    PHP_OPCACHE_MEMORY_CONSUMPTION=192 \
    PHP_OPCACHE_MAX_WASTED_PERCENTAGE=10 \
    PHP_REALPATH_CACHE_SIZE=4096K \
    PHP_REALPATH_CACHE_TTL=600

# Configure FrankenPHP for shared service
ENV SERVER_NAME=":8010" \
    DOCUMENT_ROOT="/var/www/html/public"

# Expose port for shared service
EXPOSE 8010

# Health check for shared service
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8010/health || exit 1

# Switch to non-root user
USER www-data

# Start FrankenPHP server
CMD ["frankenphp", "run", "--config", "Caddyfile"]
