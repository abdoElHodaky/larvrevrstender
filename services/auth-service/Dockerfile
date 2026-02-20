# Stage 1: Build Stage for Auth Service
FROM serversideup/php:8.3-frankenphp AS builder

ARG SERVICE_NAME=auth-service
ENV SERVICE_NAME=${SERVICE_NAME}

USER root

# Install build-only dependencies including auth-specific requirements
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
    libssl-dev \
    libonig-dev \
    libsodium-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Configure GD extension with JPEG and FreeType support
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Install PHP extensions needed for authentication processing
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
    sodium \
    sockets

# Install additional PHP extensions for auth security
RUN pecl install \
    redis \
    && docker-php-ext-enable redis

WORKDIR /var/www/html

# Copy application files
COPY --chown=www-data:www-data . ./

# Install Composer dependencies with auth-specific optimizations
RUN if [ -f "composer.json" ]; then \
        composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --classmap-authoritative; \
    fi

# Stage 2: Production Runtime for Auth Service
FROM serversideup/php:8.3-frankenphp AS runtime

ARG SERVICE_NAME=auth-service
ENV SERVICE_NAME=${SERVICE_NAME}
ENV AUTORUN_ENABLED=true

# Auth Service Specific Environment Variables
ENV PHP_OPCACHE_ENABLE=1
ENV PHP_OPCACHE_MEMORY_CONSUMPTION=256
ENV PHP_OPCACHE_MAX_ACCELERATED_FILES=20000
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0
ENV PHP_OPCACHE_REVALIDATE_FREQ=0
ENV PHP_OPCACHE_SAVE_COMMENTS=0
ENV PHP_OPCACHE_ENABLE_FILE_OVERRIDE=1

# FrankenPHP optimizations for auth processing
ENV FRANKENPHP_WORKER_MODE=true
ENV FRANKENPHP_NUM_THREADS=4
ENV FRANKENPHP_MAX_REQUESTS=500

# Laravel Octane optimizations for auth service
ENV OCTANE_WORKERS=4
ENV OCTANE_TASK_WORKERS=6
ENV OCTANE_MAX_REQUESTS=500
ENV OCTANE_MEMORY_LIMIT=512M

USER root

# Install runtime-only system dependencies for auth processing
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq5 \
    libicu72 \
    libxml2 \
    libcurl4 \
    libssl3 \
    libsodium23 \
    libpng16-16 \
    libjpeg-turbo8 \
    libfreetype6 \
    libzip4 \
    libonig5 \
    curl \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/* && apt-get clean

# Configure GD extension for runtime
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Re-install PHP extensions for auth processing
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
    sodium \
    sockets \
    && docker-php-ext-enable opcache

# Install Redis extension
RUN (pecl install redis || echo "Redis already installed") && docker-php-ext-enable redis

WORKDIR /var/www/html

# Copy optimized application from builder
COPY --from=builder --chown=www-data:www-data /var/www/html ./

# Create auth-specific directories and set permissions
RUN mkdir -p \
    storage/framework/{cache,sessions,views} \
    storage/logs \
    storage/app/{public,private,temp} \
    storage/auth/{tokens,sessions,logs} \
    bootstrap/cache \
    config/auth \
    && chown -R www-data:www-data storage bootstrap/cache config \
    && chmod -R 775 storage bootstrap/cache \
    && chmod -R 755 config

# Security hardening for auth service
RUN echo "expose_php = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "error_log = /var/www/storage/logs/php_errors.log" >> /usr/local/etc/php/conf.d/security.ini

# Auth service specific PHP configuration
RUN echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/auth.ini \
    && echo "max_execution_time = 120" >> /usr/local/etc/php/conf.d/auth.ini \
    && echo "max_input_time = 120" >> /usr/local/etc/php/conf.d/auth.ini \
    && echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/auth.ini \
    && echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/auth.ini \
    && echo "max_file_uploads = 10" >> /usr/local/etc/php/conf.d/auth.ini

# Switch to non-root user for security
USER www-data

# Auth service startup command with Octane FrankenPHP
CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8001", "--workers=4", "--task-workers=6", "--max-requests=500"]

EXPOSE 8001

# Health check for auth service
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=5 \
    CMD curl -f http://localhost:8001/octane/health || exit 1

# Labels for auth service
LABEL maintainer="Reverse Tender Platform <dev@reversetender.com>"
LABEL service="auth-service"
LABEL version="1.0.0"
LABEL description="Authentication and Authorization Service with Enhanced Security"
LABEL security.scan="enabled"
LABEL compliance.required="true"
