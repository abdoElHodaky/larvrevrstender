# Stage 1: Build Stage
FROM serversideup/php:8.3-frankenphp AS builder

ARG SERVICE_NAME=auction-service
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
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Configure GD extension with JPEG and FreeType support
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Install PHP extensions needed for building/dependencies
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

# Install Composer dependencies
RUN if [ -f "composer.json" ]; then \
        composer install --no-dev --optimize-autoloader --no-interaction --no-scripts; \
    fi

# Stage 2: Production Runtime
FROM serversideup/php:8.3-frankenphp AS runtime

ARG SERVICE_NAME=auction-service
ENV SERVICE_NAME=${SERVICE_NAME}
ENV AUTORUN_ENABLED=true

# Laravel/Octane Specific ENV
ENV PHP_OPCACHE_ENABLE=1
ENV PHP_OPCACHE_MEMORY_CONSUMPTION=256
ENV PHP_OPCACHE_MAX_ACCELERATED_FILES=20000
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

USER root

# Install runtime-only system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq5 \
    libxml2 \
    libcurl4 \
    && rm -rf /var/lib/apt/lists/* && apt-get clean

# Configure GD extension for runtime
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Re-install extensions (or copy from builder if compatible)
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

# Install Redis (skip if already installed)
RUN (pecl install redis || echo "Redis already installed") && docker-php-ext-enable redis

WORKDIR /var/www/html

# Copy optimized application from builder
COPY --from=builder --chown=www-data:www-data /var/www/html ./

# Permissions and Directory setup
RUN mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Switch to non-root user
USER www-data

# Use the serversideup custom entrypoint scripts if needed, 
# or use a simplified Octane command.
# We override the CMD to launch Octane via FrankenPHP.
CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8009"]

EXPOSE 8009
