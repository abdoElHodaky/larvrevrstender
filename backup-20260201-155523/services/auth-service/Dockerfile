FROM dunglas/frankenphp:php8.3

# Set working directory
WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libgd-dev \
    jpegoptim optipng pngquant gifsicle \
    libpq-dev \
    supervisor \
    cron \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        pdo_pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /app

# Set permissions
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app \
    && chmod -R 775 /app/storage \
    && chmod -R 775 /app/bootstrap/cache

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Configure PHP for production
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory-limit.ini \
    && echo "upload_max_filesize=100M" > /usr/local/etc/php/conf.d/upload.ini \
    && echo "post_max_size=100M" >> /usr/local/etc/php/conf.d/upload.ini \
    && echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/upload.ini \
    && echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Create startup script
RUN echo '#!/bin/bash\n\
# Ensure .env exists\n\
if [ ! -f .env ]; then\n\
    cp .env.example .env\n\
    php artisan key:generate\n\
fi\n\
\n\
# Cache configuration for better performance\n\
php artisan config:cache\n\
php artisan route:cache\n\
php artisan view:cache\n\
\n\
# Run migrations\n\
php artisan migrate --force\n\
\n\
# Start Octane with FrankenPHP\n\
exec php artisan octane:frankenphp --host=0.0.0.0 --port=8000 --workers=${OCTANE_WORKERS:-4} --max-requests=${OCTANE_MAX_REQUESTS:-500}' > /usr/local/bin/start-octane.sh

RUN chmod +x /usr/local/bin/start-octane.sh

# Expose port
EXPOSE 8000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8000/up || exit 1

# Start the application with Octane
CMD ["/usr/local/bin/start-octane.sh"]
