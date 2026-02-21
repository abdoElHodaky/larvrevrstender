# Stage 1: Build Stage for Payment Service
FROM serversideup/php:8.3-frankenphp AS builder

ARG SERVICE_NAME=payment-service
ENV SERVICE_NAME=${SERVICE_NAME}

USER root

# Install build-only dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Configure GD extension with JPEG and FreeType support
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Install PHP extensions needed for payment processing
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    exif \
    xml \
    curl \
    mbstring

WORKDIR /var/www/html

# Copy application files
COPY --chown=www-data:www-data . ./

# Install Composer dependencies with payment-specific optimizations
RUN if [ -f "composer.json" ]; then \
        composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --classmap-authoritative; \
    fi

# Stage 2: Production Runtime for Payment Service
FROM serversideup/php:8.3-frankenphp AS runtime

ARG SERVICE_NAME=payment-service
ENV SERVICE_NAME=${SERVICE_NAME}
ENV AUTORUN_ENABLED=true

# Payment Service Specific Environment Variables
ENV PHP_OPCACHE_ENABLE=1
ENV PHP_OPCACHE_MEMORY_CONSUMPTION=512
ENV PHP_OPCACHE_MAX_ACCELERATED_FILES=20000
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0
ENV PHP_OPCACHE_REVALIDATE_FREQ=0
ENV PHP_OPCACHE_SAVE_COMMENTS=0
ENV PHP_OPCACHE_ENABLE_FILE_OVERRIDE=1

# FrankenPHP optimizations for payment processing
ENV FRANKENPHP_WORKER_MODE=true
ENV FRANKENPHP_NUM_THREADS=6
ENV FRANKENPHP_MAX_REQUESTS=500

# Laravel Octane optimizations for payment service
ENV OCTANE_WORKERS=6
ENV OCTANE_TASK_WORKERS=8
ENV OCTANE_MAX_REQUESTS=500
ENV OCTANE_MEMORY_LIMIT=1G

USER root

# Install runtime-only system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq5 \
    libxml2 \
    libcurl4 \
    curl \
    ca-certificates \
    libpq-dev \
    libicu-dev \
    libjpeg62-turbo \
    libfreetype6 \
    libpng16-16 \
    && rm -rf /var/lib/apt/lists/* && apt-get clean

# Re-install extensions for runtime
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    exif \
    xml \
    curl \
    mbstring \
    && docker-php-ext-enable opcache

# Install Redis extension
RUN (pecl install redis || echo "Redis already installed") && docker-php-ext-enable redis

WORKDIR /var/www/html

# Copy optimized application from builder
COPY --from=builder --chown=www-data:www-data /var/www/html ./

# Create payment-specific directories and set permissions
RUN mkdir -p \
    storage/framework/{cache,sessions,views} \
    storage/logs \
    storage/app/{public,private,temp} \
    storage/payment/{receipts,reports,exports} \
    bootstrap/cache \
    config/payment \
    && chown -R www-data:www-data storage bootstrap/cache config \
    && chmod -R 775 storage bootstrap/cache \
    && chmod -R 755 config

# Create payment service configuration directory
RUN mkdir -p /var/www/config/payment-service \
    && chown -R www-data:www-data /var/www/config \
    && chmod -R 755 /var/www/config

# Security hardening for payment service
RUN echo "expose_php = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "error_log = /var/www/storage/logs/php_errors.log" >> /usr/local/etc/php/conf.d/security.ini

# Payment service specific PHP configuration
RUN echo "memory_limit = 1G" >> /usr/local/etc/php/conf.d/payment.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/payment.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/payment.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/payment.ini \
    && echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/payment.ini \
    && echo "max_file_uploads = 20" >> /usr/local/etc/php/conf.d/payment.ini

# Switch to non-root user for security
USER www-data

# Payment service startup command with Octane FrankenPHP
CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8004", "--workers=6", "--task-workers=8", "--max-requests=500"]

EXPOSE 8004

# Health check for payment service
HEALTHCHECK --interval=30s --timeout=15s --start-period=90s --retries=5 \
    CMD curl -f http://localhost:8004/octane/health || exit 1

# Labels for payment service
LABEL maintainer="Reverse Tender Platform <dev@reversetender.com>"
LABEL service="payment-service"
LABEL version="1.0.0"
LABEL description="Enterprise Payment Processing Service with Multi-Gateway Support"
LABEL security.scan="enabled"
LABEL compliance.pci="required"
