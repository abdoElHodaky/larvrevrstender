# DEPRECATED: This Dockerfile is designed for monolithic Laravel apps
# TODO: Update for microservices architecture
# 
# Current Issue: This Dockerfile expects root-level composer.json/composer.lock
# but this project uses microservices with individual composer files per service.
# 
# Future Solution: Create separate Dockerfiles for each service or a multi-stage
# build that handles multiple services.

FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    default-mysql-client \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# NOTE: The following lines are commented out because they expect
# root-level composer files that don't exist in microservices architecture
# COPY composer.json composer.lock ./
# RUN composer install --no-dev --optimize-autoloader

# Copy application code
COPY . .

# TODO: Add logic to install dependencies for each microservice
# Example approach:
# RUN for service in services/*/; do \
#       if [ -f "$service/composer.json" ]; then \
#         cd "$service" && composer install --no-dev --optimize-autoloader && cd /app; \
#       fi; \
#     done

# Set permissions (commented out as storage/bootstrap may not exist at root)
# RUN chmod -R 755 storage bootstrap/cache

# Default command for integration tests (currently disabled)
CMD ["echo", "Integration tests disabled - Docker setup needs microservices update"]
