# Use the official ServerSideUp FrankenPHP image
FROM serversideup/php:8.3-frankenphp

# 1. Build Arguments & Environment
ARG SERVICE_NAME=shared-service
ENV SERVICE_NAME=${SERVICE_NAME}
ENV AUTORUN_ENABLED=true

# 2. Install System Dependencies
# ServerSideUp images are Debian-based, not Alpine
USER root
RUN apt-get update && apt-get install -y \
    mariadb-client \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

# 3. Install PHP Extensions
# ServerSideUp images come with most extensions pre-installed
# Check what's already available and add only what's needed
RUN docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mysqli \
    pcntl \
    sockets \
    && docker-php-ext-enable opcache

# 4. Set Working Directory
WORKDIR /var/www/html

# 5. Copy Application Code
# We copy with ownership to the web user (standard is 9999 for serversideup)
COPY --chown=www-data:www-data services/${SERVICE_NAME} ./

# 6. Install Composer Dependencies
# The image includes composer by default
RUN if [ -f "composer.json" ]; then \
        composer install --no-dev --optimize-autoloader --no-scripts; \
    fi

# 7. Octane / FrankenPHP Specifics
# ServerSideUp images use "Autodiscovery" for Laravel. 
# It will detect Octane and start FrankenPHP automatically.
# We just need to ensure the entrypoint knows we want Octane.
ENV PHP_OPCACHE_ENABLE=1

# 8. Healthcheck
# ServerSideUp images have built-in healthcheck endpoint
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD healthcheck

# Expose the default FrankenPHP ports (8080 HTTP, 8443 HTTPS)
EXPOSE 8080 8443 6010 6011

# The image uses its own entrypoint to manage the process lifecycle
# No manual CMD [supervisord] needed!

